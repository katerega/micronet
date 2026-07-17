// src/components/ProtectedRoute.jsx
import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { CircularProgress, Box } from '@mui/material';

export const ProtectedRoute = ({ children, allowedRoles, allowedDepartments }) => {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <Box 
        display="flex" 
        justifyContent="center" 
        alignItems="center" 
        minHeight="100vh"
      >
        <CircularProgress />
      </Box>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

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

  return children;
};