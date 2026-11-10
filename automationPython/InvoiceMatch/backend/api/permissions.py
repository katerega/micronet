from rest_framework import permissions
from .models import Company

class IsCompanyOwner(permissions.BasePermission):
    """
    Custom permission to only allow owners of a company to access it
    """
    
    def has_object_permission(self, request, view, obj):
        # Allow staff users to access everything
        if request.user.is_staff:
            return True
        
        # Check if the object is a Company
        if hasattr(obj, 'user'):
            return obj.user == request.user
        
        # Check if it's a model with a company relation
        if hasattr(obj, 'company'):
            return obj.company.user == request.user
        
        # Check if it's a view with a company lookup
        if hasattr(view, 'get_company'):
            try:
                company = view.get_company(request)
                if hasattr(obj, 'company'):
                    return obj.company == company
                return True
            except:
                pass
        
        return False
    
    def has_permission(self, request, view):
        # For list views, check if user is authenticated
        return request.user and request.user.is_authenticated

class IsAdminUser(permissions.BasePermission):
    """
    Allows access only to admin users
    """
    
    def has_permission(self, request, view):
        return request.user and request.user.is_staff