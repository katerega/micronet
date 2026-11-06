// hooks/useAuth.js
import { useState, useEffect, useContext, createContext } from 'react';
import { api } from '../services/api';
import { jwtDecode } from 'jwt-decode';

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const initAuth = async () => {
      const token = localStorage.getItem('access_token');
      if (token) {
        try {
          const decoded = jwtDecode(token);
          if (decoded.exp * 1000 > Date.now()) {
            const userData = await getCurrentUser();
            setUser(userData);
          } else {
            // Token expired, try to refresh
            await refreshToken();
          }
        } catch (err) {
          console.error('Auth initialization error:', err);
          logout();
        }
      }
      setLoading(false);
    };

    initAuth();
  }, []);

  const login = async (credentials) => {
    try {
      setError(null);
      const response = await api.post('/auth/login', credentials);
      const { access_token, refresh_token, user } = response.data.data;

      // Store tokens
      localStorage.setItem('access_token', access_token);
      localStorage.setItem('refresh_token', refresh_token);

      // Set user
      setUser(user);

      return { success: true, user };
    } catch (err) {
      const message = err.response?.data?.message || 'Login failed';
      setError(message);
      throw new Error(message);
    }
  };

  const logout = () => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    setUser(null);
    window.location.href = '/login';
  };

  const refreshToken = async () => {
    try {
      const refreshToken = localStorage.getItem('refresh_token');
      if (!refreshToken) {
        throw new Error('No refresh token');
      }

      const response = await api.post('/auth/refresh-token', { refresh_token: refreshToken });
      const { access_token, refresh_token: newRefreshToken } = response.data.data;

      localStorage.setItem('access_token', access_token);
      localStorage.setItem('refresh_token', newRefreshToken);

      const userData = await getCurrentUser();
      setUser(userData);
      return true;
    } catch (err) {
      logout();
      return false;
    }
  };

  const getCurrentUser = async () => {
    try {
      const response = await api.get('/auth/me');
      return response.data.data;
    } catch (err) {
      throw new Error('Failed to get user data');
    }
  };

  const hasPermission = (permission) => {
    if (!user || !user.permissions) return false;
    return user.permissions.includes(permission);
  };

  const hasRole = (roleCode) => {
    if (!user || !user.role) return false;
    return user.role.code === roleCode;
  };

  const value = {
    user,
    loading,
    error,
    login,
    logout,
    refreshToken,
    hasPermission,
    hasRole,
    isAuthenticated: !!user,
    isAdmin: user?.role?.can_manage_users || user?.role?.can_manage_roles || false,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};