from django.urls import path, include
from rest_framework.routers import DefaultRouter
from rest_framework_simplejwt.views import TokenRefreshView
from .views import (
    RegisterView, LoginView, CompanyViewSet, PaymentViewSet,
    InvoiceViewSet, DashboardViewSet, payment_webhook
)

router = DefaultRouter()
router.register(r'companies', CompanyViewSet, basename='company')
router.register(r'invoices', InvoiceViewSet, basename='invoice')
router.register(r'dashboard', DashboardViewSet, basename='dashboard')

urlpatterns = [
    # Auth
    path('auth/register/', RegisterView.as_view(), name='register'),
    path('auth/login/', LoginView.as_view(), name='login'),
    path('auth/refresh/', TokenRefreshView.as_view(), name='token_refresh'),
    
    # Payments
    path('payments/initiate/', PaymentViewSet.as_view({'post': 'initiate'}), name='payment_initiate'),
    path('payments/status/<str:reference>/', PaymentViewSet.as_view({'get': 'status'}), name='payment_status'),
    path('payments/history/', PaymentViewSet.as_view({'get': 'history'}), name='payment_history'),
    path('payments/webhook/', payment_webhook, name='payment_webhook'),
    
    # Invoice specific
    path('invoices/upload/', InvoiceViewSet.as_view({'post': 'upload'}), name='invoice_upload'),
    path('invoices/<uuid:pk>/reconcile/', InvoiceViewSet.as_view({'get': 'reconcile'}), name='invoice_reconcile'),
    
    # Dashboard
    path('dashboard/summary/', DashboardViewSet.as_view({'get': 'summary'}), name='dashboard_summary'),
    
    # Include router URLs
    path('', include(router.urls)),
]