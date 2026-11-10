from rest_framework import serializers
from django.contrib.auth.models import User
from django.contrib.auth.password_validation import validate_password
from .models import Company, ActivationPayment, Invoice, AuditLog

class UserSerializer(serializers.ModelSerializer):
    class Meta:
        model = User
        fields = ('id', 'username', 'email', 'first_name', 'last_name', 'date_joined')
        read_only_fields = ('id', 'date_joined')

class CompanySerializer(serializers.ModelSerializer):
    user = UserSerializer(read_only=True)
    user_id = serializers.UUIDField(write_only=True)
    
    class Meta:
        model = Company
        fields = '__all__'
        read_only_fields = ('id', 'created_at', 'updated_at', 'user')

class RegisterSerializer(serializers.ModelSerializer):
    password = serializers.CharField(write_only=True, required=True, validators=[validate_password])
    password2 = serializers.CharField(write_only=True, required=True)
    company_name = serializers.CharField(required=True)
    phone = serializers.CharField(required=True)
    
    class Meta:
        model = User
        fields = ('username', 'email', 'password', 'password2', 'first_name', 'last_name', 'company_name', 'phone')
        
    def validate(self, attrs):
        if attrs['password'] != attrs['password2']:
            raise serializers.ValidationError({"password": "Password fields didn't match."})
        return attrs
    
    def create(self, validated_data):
        company_name = validated_data.pop('company_name')
        phone = validated_data.pop('phone')
        password = validated_data.pop('password')
        validated_data.pop('password2')
        
        user = User.objects.create_user(**validated_data)
        company = Company.objects.create(
            user=user,
            name=company_name,
            phone=phone
        )
        
        return user

class PaymentInitiateSerializer(serializers.Serializer):
    payment_method = serializers.ChoiceField(choices=ActivationPayment.PAYMENT_METHODS)
    phone_number = serializers.CharField(required=False, allow_blank=True)
    
    def validate_phone_number(self, value):
        if value and not value.startswith('256'):
            return f"256{value.lstrip('0')}"
        return value

class PaymentVerifySerializer(serializers.Serializer):
    reference = serializers.CharField()
    provider_reference = serializers.CharField(required=False)

class TallyConnectionSerializer(serializers.Serializer):
    host = serializers.CharField(default='localhost')
    port = serializers.IntegerField(default=9000)
    company_name = serializers.CharField(required=False)

class InvoiceUploadSerializer(serializers.Serializer):
    file = serializers.FileField()

class InvoiceSerializer(serializers.ModelSerializer):
    class Meta:
        model = Invoice
        fields = '__all__'
        read_only_fields = ('id', 'company', 'uploaded_at', 'processed_at')

class AuditLogSerializer(serializers.ModelSerializer):
    class Meta:
        model = AuditLog
        fields = '__all__'
        read_only_fields = ('id', 'created_at')