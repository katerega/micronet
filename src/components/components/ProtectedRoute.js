// components/ProtectedRoute.js
import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';

export const ProtectedRoute = ({ allowedRoles, allowedDepartments }) => {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100vh' }}>
        <div>Loading...</div>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  // Check role-based access
  if (allowedRoles && allowedRoles.length > 0) {
    const userRole = user.role?.code?.toLowerCase();
    const hasAllowedRole = allowedRoles.some(role => 
      userRole === role.toLowerCase() || 
      user.role?.name?.toLowerCase() === role.toLowerCase()
    );
    
    if (!hasAllowedRole) {
      return <Navigate to="/unauthorized" replace />;
    }
  }

  // Check department-based access
  if (allowedDepartments && allowedDepartments.length > 0) {
    const userDepartment = user.department?.code?.toLowerCase();
    const hasAllowedDepartment = allowedDepartments.some(dept => 
      userDepartment === dept.toLowerCase() ||
      user.department?.name?.toLowerCase() === dept.toLowerCase()
    );
    
    if (!hasAllowedDepartment && !user.role?.can_manage_roles) {
      return <Navigate to="/unauthorized" replace />;
    }
  }

  return <Outlet />;
};
