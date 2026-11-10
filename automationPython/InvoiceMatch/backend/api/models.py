from django.db import models
from django.contrib.auth.models import User
from django.utils import timezone
import uuid

class Company(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    user = models.OneToOneField(User, on_delete=models.CASCADE, related_name='company')
    name = models.CharField(max_length=255)
    registration_number = models.CharField(max_length=100, blank=True, null=True)
    tax_id = models.CharField(max_length=100, blank=True, null=True)
    phone = models.CharField(max_length=20)
    address = models.TextField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)
    
    # Tally connection settings
    tally_host = models.CharField(max_length=255, default='localhost')
    tally_port = models.IntegerField(default=9000)
    tally_company_name = models.CharField(max_length=255, blank=True, null=True)
    tally_connected = models.BooleanField(default=False)
    tally_last_check = models.DateTimeField(null=True, blank=True)
    
    # n8n settings
    n8n_webhook_url = models.CharField(max_length=500, blank=True, null=True)
    n8n_connected = models.BooleanField(default=False)
    n8n_last_check = models.DateTimeField(null=True, blank=True)
    
    # Activation
    is_active = models.BooleanField(default=False)
    activation_date = models.DateTimeField(null=True, blank=True)
    activation_reference = models.CharField(max_length=100, blank=True, null=True)
    
    def __str__(self):
        return self.name
    
    @property
    def has_tally_connection(self):
        return self.tally_connected and self.tally_last_check and \
               (timezone.now() - self.tally_last_check).seconds < 300

class ActivationPayment(models.Model):
    PAYMENT_STATUS = (
        ('pending', 'Pending'),
        ('processing', 'Processing'),
        ('completed', 'Completed'),
        ('failed', 'Failed'),
        ('refunded', 'Refunded'),
    )
    
    PAYMENT_METHODS = (
        ('mtn_mobile', 'MTN Mobile Money'),
        ('airtel_money', 'Airtel Money'),
        ('card', 'Card'),
        ('test', 'Test Payment'),
    )
    
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    company = models.ForeignKey(Company, on_delete=models.CASCADE, related_name='payments')
    amount = models.DecimalField(max_digits=12, decimal_places=0)
    payment_method = models.CharField(max_length=20, choices=PAYMENT_METHODS)
    reference = models.CharField(max_length=100, unique=True)
    status = models.CharField(max_length=20, choices=PAYMENT_STATUS, default='pending')
    provider_reference = models.CharField(max_length=100, blank=True, null=True)
    provider_data = models.JSONField(default=dict, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    completed_at = models.DateTimeField(null=True, blank=True)
    
    def __str__(self):
        return f"{self.company.name} - {self.reference}"

class Invoice(models.Model):
    INVOICE_STATUS = (
        ('uploaded', 'Uploaded'),
        ('processing', 'Processing'),
        ('matched', 'Matched'),
        ('discrepancy', 'Discrepancy'),
        ('error', 'Error'),
    )
    
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    company = models.ForeignKey(Company, on_delete=models.CASCADE, related_name='invoices')
    file = models.FileField(upload_to='invoices/%Y/%m/%d/')
    filename = models.CharField(max_length=255)
    file_size = models.IntegerField()
    
    # Extracted data
    po_number = models.CharField(max_length=100, blank=True, null=True)
    supplier_name = models.CharField(max_length=255, blank=True, null=True)
    invoice_number = models.CharField(max_length=100, blank=True, null=True)
    invoice_date = models.DateField(null=True, blank=True)
    total_amount = models.DecimalField(max_digits=12, decimal_places=2, null=True, blank=True)
    
    # Processing results
    status = models.CharField(max_length=20, choices=INVOICE_STATUS, default='uploaded')
    match_status = models.CharField(max_length=20, null=True, blank=True)
    discrepancies = models.JSONField(default=list, blank=True)
    tally_response = models.JSONField(default=dict, blank=True)
    n8n_response = models.JSONField(default=dict, blank=True)
    
    # Timestamps
    uploaded_at = models.DateTimeField(auto_now_add=True)
    processed_at = models.DateTimeField(null=True, blank=True)
    
    def __str__(self):
        return f"{self.company.name} - {self.filename}"

class AuditLog(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    company = models.ForeignKey(Company, on_delete=models.CASCADE, related_name='audit_logs')
    user = models.ForeignKey(User, on_delete=models.SET_NULL, null=True)
    action = models.CharField(max_length=100)
    details = models.JSONField(default=dict)
    ip_address = models.GenericIPAddressField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)
    
    def __str__(self):
        return f"{self.company.name} - {self.action} - {self.created_at}"