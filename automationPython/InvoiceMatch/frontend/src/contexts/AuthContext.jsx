import React, { createContext, useContext, useState } from 'react';
import api from '../services/api';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(() => {
    try {
      const stored = localStorage.getItem('user');
      return stored ? JSON.parse(stored) : null;
    } catch {
      return null;
    }
  });

  const [company, setCompany] = useState(() => {
    try {
      const stored = localStorage.getItem('company');
      return stored ? JSON.parse(stored) : null;
    } catch {
      return null;
    }
  });

  const [loading, setLoading] = useState(false);

  const login = async (username, password) => {
    setLoading(true);

    try {
      const response = await api.post('/auth/login/', {
        username,
        password,
      });

      const data = response.data;

      if (!data.access || !data.refresh) {
        throw new Error('Login response did not contain authentication tokens.');
      }

      localStorage.setItem('access_token', data.access);
      localStorage.setItem('refresh_token', data.refresh);

      if (data.user) {
        localStorage.setItem('user', JSON.stringify(data.user));
        setUser(data.user);
      }

      if (data.company) {
        localStorage.setItem('company', JSON.stringify(data.company));
        setCompany(data.company);
      }

      return data;
    } finally {
      setLoading(false);
    }
  };

  const updateCompany = (newCompany) => {
    setCompany(newCompany);
    localStorage.setItem('company', JSON.stringify(newCompany));
  };

  const logout = () => {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');
    localStorage.removeItem('company');

    setUser(null);
    setCompany(null);

    window.location.href = '/login';
  };

  const isAuthenticated = !!localStorage.getItem('access_token');

  return (
    <AuthContext.Provider
      value={{
        user,
        company,
        loading,
        login,
        logout,
        updateCompany,
        isAuthenticated,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider');
  }

  return context;
};

export default AuthContext;
