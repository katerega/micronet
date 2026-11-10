from rest_framework import viewsets, status, generics, permissions, serializers
from rest_framework.decorators import action, api_view, permission_classes
from rest_framework.response import Response
from rest_framework.permissions import IsAuthenticated, AllowAny
from django.contrib.auth.models import User
from django.contrib.auth import authenticate
from django.shortcuts import get_object_or_404
from django.utils import timezone
from rest_framework_simplejwt.tokens import RefreshToken
from drf_yasg.utils import swagger_auto_schema
from drf_yasg import openapi
import uuid
import json
import logging
from decimal import Decimal
from datetime import datetime

from .models import Company, ActivationPayment, Invoice, AuditLog
from .serializers import (
    UserSerializer, CompanySerializer, RegisterSerializer,
    PaymentInitiateSerializer, PaymentVerifySerializer,
    TallyConnectionSerializer, InvoiceUploadSerializer,
    InvoiceSerializer, AuditLogSerializer
)
from .utils.tally_connector import TallyConnector
from .utils.n8n_webhook import N8nWebhook
from .utils.payment_processor import PaymentProcessor
from .permissions import IsCompanyOwner, IsAdminUser

logger = logging.getLogger(__name__)

class RegisterView(generics.CreateAPIView):
    serializer_class = RegisterSerializer
    permission_classes = [AllowAny]
    
    def create(self, request, *args, **kwargs):
        serializer = self.get_serializer(data=request.data)
        serializer.is_valid(raise_exception=True)
        user = serializer.save()
        
        refresh = RefreshToken.for_user(user)
        
        return Response({
            'user': UserSerializer(user).data,
            'refresh': str(refresh),
            'access': str(refresh.access_token),
            'message': 'Registration successful. Please activate your account to start using the system.'
        }, status=status.HTTP_201_CREATED)

class LoginView(generics.GenericAPIView):
    permission_classes = [AllowAny]
    
    def post(self, request):
        username = request.data.get('username')
        password = request.data.get('password')
        
        if not username or not password:
            return Response({
                'error': 'Please provide username and password'
            }, status=status.HTTP_400_BAD_REQUEST)
        
        user = authenticate(username=username, password=password)
        
        if not user:
            return Response({
                'error': 'Invalid credentials'
            }, status=status.HTTP_401_UNAUTHORIZED)
        
        refresh = RefreshToken.for_user(user)
        
        # Get company
        try:
            company = Company.objects.get(user=user)
            company_data = CompanySerializer(company).data
        except Company.DoesNotExist:
            company_data = None
        
        return Response({
            'user': UserSerializer(user).data,
            'company': company_data,
            'refresh': str(refresh),
            'access': str(refresh.access_token),
        })

class CompanyViewSet(viewsets.ModelViewSet):
    serializer_class = CompanySerializer
    permission_classes = [IsAuthenticated, IsCompanyOwner]
    
    def get_queryset(self):
        user = self.request.user
        if user.is_staff:
            return Company.objects.all()
        return Company.objects.filter(user=user)
    
    def get_object(self):
        queryset = self.get_queryset()
        obj = get_object_or_404(queryset, id=self.kwargs['pk'])
        self.check_object_permissions(self.request, obj)
        return obj
    
    @action(detail=True, methods=['post'])
    def test_tally_connection(self, request, pk=None):
        company = self.get_object()
        serializer = TallyConnectionSerializer(data=request.data)
        serializer.is_valid(raise_exception=True)
        
        host = serializer.validated_data.get('host', 'localhost')
        port = serializer.validated_data.get('port', 9000)
        company_name = serializer.validated_data.get('company_name')
        
        connector = TallyConnector(host, port)
        result = connector.test_connection(company_name)
        
        if result.get('success'):
            company.tally_host = host
            company.tally_port = port
            if company_name:
                company.tally_company_name = company_name
            company.tally_connected = True
            company.tally_last_check = timezone.now()
            company.save()
            
            # Log the action
            AuditLog.objects.create(
                company=company,
                user=request.user,
                action='TEST_TALLY_CONNECTION',
                details={'host': host, 'port': port, 'result': result}
            )
        
        return Response(result)
    
    @action(detail=True, methods=['post'])
    def test_n8n_connection(self, request, pk=None):
        company = self.get_object()
        webhook_url = request.data.get('webhook_url')
        
        if not webhook_url:
            return Response({
                'error': 'webhook_url is required'
            }, status=status.HTTP_400_BAD_REQUEST)
        
        n8n = N8nWebhook(webhook_url)
        result = n8n.test_connection()
        
        if result.get('success'):
            company.n8n_webhook_url = webhook_url
            company.n8n_connected = True
            company.n8n_last_check = timezone.now()
            company.save()
            
            AuditLog.objects.create(
                company=company,
                user=request.user,
                action='TEST_N8N_CONNECTION',
                details={'webhook_url': webhook_url, 'result': result}
            )
        
        return Response(result)
    
    @action(detail=True, methods=['post'])
    def lookup_po(self, request, pk=None):
        company = self.get_object()
        po_number = request.data.get('po_number')
        
        if not po_number:
            return Response({
                'error': 'po_number is required'
            }, status=status.HTTP_400_BAD_REQUEST)
        
        if not company.tally_connected:
            return Response({
                'error': 'Tally is not connected'
            }, status=status.HTTP_400_BAD_REQUEST)
        
        connector = TallyConnector(company.tally_host, company.tally_port)
        result = connector.lookup_po(po_number, company.tally_company_name)
        
        AuditLog.objects.create(
            company=company,
            user=request.user,
            action='LOOKUP_PO',
            details={'po_number': po_number, 'result': result}
        )
        
        return Response(result)

class PaymentViewSet(viewsets.ViewSet):
    permission_classes = [IsAuthenticated, IsCompanyOwner]
    
    def get_company(self, request):
        return get_object_or_404(Company, user=request.user)
    
    @swagger_auto_schema(request_body=PaymentInitiateSerializer)
    def initiate(self, request):
        company = self.get_company(request)
        serializer = PaymentInitiateSerializer(data=request.data)
        serializer.is_valid(raise_exception=True)
        
        payment_method = serializer.validated_data['payment_method']
        phone_number = serializer.validated_data.get('phone_number')
        
        # Generate reference
        reference = f"MAC-{uuid.uuid4().hex[:8].upper()}"
        
        # Create payment record
        payment = ActivationPayment.objects.create(
            company=company,
            amount=Decimal('500000'),
            payment_method=payment_method,
            reference=reference,
            status='pending'
        )
        
        # Process payment
        processor = PaymentProcessor()
        result = processor.initiate_payment(payment, phone_number)
        
        if result.get('success'):
            payment.status = 'processing'
            payment.provider_reference = result.get('provider_reference')
            payment.save()
            
            AuditLog.objects.create(
                company=company,
                user=request.user,
                action='INITIATE_PAYMENT',
                details={'reference': reference, 'method': payment_method}
            )
            
            return Response({
                'reference': reference,
                'status': payment.status,
                'payment_url': result.get('payment_url'),
                'message': 'Payment initiated. Complete the payment and the system will activate automatically.'
            })
        else:
            payment.status = 'failed'
            payment.provider_data = result
            payment.save()
            return Response({
                'error': result.get('error', 'Payment initiation failed')
            }, status=status.HTTP_400_BAD_REQUEST)
    
    def status(self, request, reference):
        company = self.get_company(request)
        payment = get_object_or_404(ActivationPayment, reference=reference, company=company)
        
        return Response({
            'reference': payment.reference,
            'status': payment.status,
            'amount': str(payment.amount),
            'payment_method': payment.payment_method,
            'created_at': payment.created_at,
            'completed_at': payment.completed_at,
            'is_completed': payment.status == 'completed'
        })
    
    def history(self, request):
        company = self.get_company(request)
        payments = ActivationPayment.objects.filter(company=company).order_by('-created_at')
        
        return Response([{
            'reference': p.reference,
            'status': p.status,
            'amount': str(p.amount),
            'payment_method': p.payment_method,
            'created_at': p.created_at,
            'completed_at': p.completed_at
        } for p in payments])

class InvoiceViewSet(viewsets.ModelViewSet):
    serializer_class = InvoiceSerializer
    permission_classes = [IsAuthenticated, IsCompanyOwner]
    
    def get_queryset(self):
        return Invoice.objects.filter(company__user=self.request.user).order_by('-uploaded_at')
    
    def perform_create(self, serializer):
        company = get_object_or_404(Company, user=self.request.user)
        if not company.is_active:
            raise serializers.ValidationError('Account is not activated')
        
        file = serializer.validated_data['file']
        serializer.save(
            company=company,
            filename=file.name,
            file_size=file.size,
            status='uploaded'
        )
    
    @swagger_auto_schema(request_body=InvoiceUploadSerializer)
    def upload(self, request):
        serializer = InvoiceUploadSerializer(data=request.data)
        serializer.is_valid(raise_exception=True)
        
        company = get_object_or_404(Company, user=request.user)
        
        if not company.is_active:
            return Response({
                'error': 'Account is not activated. Please complete payment to activate.'
            }, status=status.HTTP_403_FORBIDDEN)
        
        file = serializer.validated_data['file']
        
        invoice = Invoice.objects.create(
            company=company,
            file=file,
            filename=file.name,
            file_size=file.size,
            status='uploaded'
        )
        
        # Start processing asynchronously (we'll simulate for now)
        invoice.status = 'processing'
        invoice.save()
        
        # Process the invoice
        try:
            result = self.process_invoice(invoice)
            return Response({
                'invoice': InvoiceSerializer(invoice).data,
                'processing_result': result
            }, status=status.HTTP_202_ACCEPTED)
        except Exception as e:
            invoice.status = 'error'
            invoice.save()
            logger.error(f"Invoice processing error: {str(e)}")
            return Response({
                'error': str(e),
                'invoice': InvoiceSerializer(invoice).data
            }, status=status.HTTP_500_INTERNAL_SERVER_ERROR)
    
    def process_invoice(self, invoice):
        company = invoice.company
        
        # Step 1: Send to n8n for extraction
        n8n = N8nWebhook(company.n8n_webhook_url)
        extraction_result = n8n.process_invoice(invoice.file.path)
        
        if extraction_result.get('success'):
            # Update invoice with extracted data
            extracted_data = extraction_result.get('data', {})
            invoice.po_number = extracted_data.get('po_number')
            invoice.supplier_name = extracted_data.get('supplier_name')
            invoice.invoice_number = extracted_data.get('invoice_number')
            invoice.invoice_date = extracted_data.get('invoice_date')
            invoice.total_amount = extracted_data.get('total_amount')
            invoice.n8n_response = extraction_result
            invoice.save()
            
            # Step 2: Look up PO in Tally
            if invoice.po_number and company.tally_connected:
                connector = TallyConnector(company.tally_host, company.tally_port)
                po_result = connector.lookup_po(invoice.po_number, company.tally_company_name)
                invoice.tally_response = po_result
                
                # Step 3: Reconcile
                if po_result.get('success'):
                    po_data = po_result.get('data', {})
                    discrepancies = self.reconcile_invoice(invoice, po_data)
                    
                    if discrepancies:
                        invoice.status = 'discrepancy'
                        invoice.match_status = 'mismatch'
                        invoice.discrepancies = discrepancies
                    else:
                        invoice.status = 'matched'
                        invoice.match_status = 'match'
                    
                    invoice.save()
                else:
                    invoice.status = 'discrepancy'
                    invoice.match_status = 'po_not_found'
                    invoice.discrepancies = [{
                        'field': 'po_number',
                        'message': 'PO not found in Tally',
                        'expected': invoice.po_number,
                        'actual': None
                    }]
                    invoice.save()
            else:
                # If Tally not connected, we can't reconcile
                invoice.status = 'uploaded'
                invoice.save()
        else:
            invoice.status = 'error'
            invoice.n8n_response = extraction_result
            invoice.save()
        
        return extraction_result
    
    def reconcile_invoice(self, invoice, po_data):
        discrepancies = []
        
        # Compare invoice totals
        if invoice.total_amount and po_data.get('total_amount'):
            if abs(float(invoice.total_amount) - float(po_data['total_amount'])) > 1:
                discrepancies.append({
                    'field': 'total_amount',
                    'message': 'Amount mismatch',
                    'expected': str(po_data['total_amount']),
                    'actual': str(invoice.total_amount)
                })
        
        # Compare supplier
        if invoice.supplier_name and po_data.get('supplier_name'):
            if invoice.supplier_name.lower() != po_data['supplier_name'].lower():
                discrepancies.append({
                    'field': 'supplier_name',
                    'message': 'Supplier mismatch',
                    'expected': po_data['supplier_name'],
                    'actual': invoice.supplier_name
                })
        
        return discrepancies
    
    @action(detail=True, methods=['get'])
    def reconcile(self, request, pk=None):
        invoice = self.get_object()
        
        if not invoice.po_number:
            return Response({
                'error': 'No PO number found in invoice'
            }, status=status.HTTP_400_BAD_REQUEST)
        
        company = invoice.company
        if not company.tally_connected:
            return Response({
                'error': 'Tally is not connected'
            }, status=status.HTTP_400_BAD_REQUEST)
        
        connector = TallyConnector(company.tally_host, company.tally_port)
        po_result = connector.lookup_po(invoice.po_number, company.tally_company_name)
        
        if po_result.get('success'):
            po_data = po_result.get('data', {})
            discrepancies = self.reconcile_invoice(invoice, po_data)
            
            return Response({
                'po_data': po_data,
                'discrepancies': discrepancies,
                'status': 'match' if not discrepancies else 'mismatch'
            })
        else:
            return Response({
                'error': 'PO not found in Tally',
                'po_result': po_result
            }, status=status.HTTP_404_NOT_FOUND)

@api_view(['POST'])
@permission_classes([AllowAny])
def payment_webhook(request):
    """
    Webhook endpoint for payment providers
    """
    try:
        # Verify webhook signature
        signature = request.headers.get('X-Webhook-Signature')
        # In production, verify signature here
        
        data = request.data
        reference = data.get('reference')
        status = data.get('status')
        provider_reference = data.get('provider_reference')
        
        if not reference or not status:
            return Response({
                'error': 'Missing required fields'
            }, status=status.HTTP_400_BAD_REQUEST)
        
        try:
            payment = ActivationPayment.objects.get(reference=reference)
        except ActivationPayment.DoesNotExist:
            return Response({
                'error': 'Payment not found'
            }, status=status.HTTP_404_NOT_FOUND)
        
        if status == 'completed':
            # Activate the company
            payment.status = 'completed'
            payment.completed_at = timezone.now()
            payment.provider_reference = provider_reference
            payment.save()
            
            company = payment.company
            company.is_active = True
            company.activation_date = timezone.now()
            company.activation_reference = reference
            company.save()
            
            AuditLog.objects.create(
                company=company,
                user=None,
                action='ACTIVATION_COMPLETED',
                details={'reference': reference}
            )
            
            logger.info(f"Company {company.name} activated successfully")
            
        elif status == 'failed':
            payment.status = 'failed'
            payment.provider_data = data
            payment.save()
            
            logger.warning(f"Payment {reference} failed")
        
        elif status == 'refunded':
            payment.status = 'refunded'
            payment.save()
            logger.info(f"Payment {reference} refunded")
        
        return Response({'status': 'ok'}, status=status.HTTP_200_OK)
        
    except Exception as e:
        logger.error(f"Webhook error: {str(e)}")
        return Response({
            'error': str(e)
        }, status=status.HTTP_400_BAD_REQUEST)

class DashboardViewSet(viewsets.ViewSet):
    permission_classes = [IsAuthenticated, IsCompanyOwner]
    
    def get_company(self, request):
        return get_object_or_404(Company, user=request.user)
    
    def summary(self, request):
        company = self.get_company(request)
        
        # Invoice statistics
        invoices = Invoice.objects.filter(company=company)
        total_invoices = invoices.count()
        matched = invoices.filter(status='matched').count()
        discrepancies = invoices.filter(status='discrepancy').count()
        processing = invoices.filter(status='processing').count()
        
        # Payment history
        payments = ActivationPayment.objects.filter(company=company)
        total_paid = payments.filter(status='completed').count()
        
        return Response({
            'company': {
                'name': company.name,
                'is_active': company.is_active,
                'tally_connected': company.tally_connected,
                'n8n_connected': company.n8n_connected,
                'activation_date': company.activation_date,
            },
            'invoices': {
                'total': total_invoices,
                'matched': matched,
                'discrepancies': discrepancies,
                'processing': processing,
            },
            'payments': {
                'total': total_paid,
                'total_amount': sum([p.amount for p in payments.filter(status='completed')])
            },
            'system_health': {
                'tally': 'Connected' if company.tally_connected else 'Not Connected',
                'n8n': 'Connected' if company.n8n_connected else 'Not Connected',
                'activation': 'Active' if company.is_active else 'Inactive',
            }
        })
