// src/App.js
import React, { useState } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import './App.css';

// Import Auth Components
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import { AuthProvider, useAuth } from './hooks/useAuth';
import { ProtectedRoute } from './components/ProtectedRoute';

// Simple Layout Component
const Layout = ({ children }) => {
  const { user, logout } = useAuth();
  const [activeModule, setActiveModule] = useState('DASHBOARD');

  const handleLogout = async () => {
    await logout();
    window.location.href = '/login';
  };

  return (
    <div className="app">
      <header className="app-header">
        <div className="logo">
          <span className="logo-icon">⚡</span>
          <span className="logo-text">Micronet Procurement</span>
        </div>
        <div className="header-stats">
          <div className="user-info">
            <span className="user-name">{user?.name || user?.email || 'User'}</span>
            <span className="user-role">{user?.role || 'User'}</span>
            <button onClick={handleLogout} className="logout-btn">Logout</button>
          </div>
        </div>
      </header>

      <nav className="module-nav">
        <button 
          className={`module-btn ${activeModule === 'DASHBOARD' ? 'active' : ''}`}
          onClick={() => setActiveModule('DASHBOARD')}
        >
          <span className="module-icon">📊</span>
          <span className="module-name">Dashboard</span>
        </button>
        <button className="module-btn">
          <span className="module-icon">🛒</span>
          <span className="module-name">Procurement</span>
        </button>
        <button className="module-btn">
          <span className="module-icon">📈</span>
          <span className="module-name">Sales</span>
        </button>
        <button className="module-btn">
          <span className="module-icon">💰</span>
          <span className="module-name">Accounts</span>
        </button>
      </nav>

      <div className="main-layout">
        <main className="content-area">
          {children}
        </main>
      </div>

      <footer className="app-footer">
        <div className="footer-left">
          <span>© 2026 Micronet Procurement Automation System</span>
        </div>
        <div className="footer-right">
          <span>Version 2.0.0</span>
        </div>
      </footer>
    </div>
  );
};

// Main App Component
const AppContent = () => {
  const { loading } = useAuth();

  if (loading) {
    return (
      <div className="loading-container">
        <div className="loading-spinner"></div>
        <p>Loading application...</p>
      </div>
    );
  }

  return (
    <Routes>
      {/* Public Routes */}
      <Route path="/login" element={<Login />} />
      
      {/* Protected Routes with Layout */}
      <Route path="/" element={<Navigate to="/login" replace />} />
      <Route 
        path="/dashboard" 
        element={
          <ProtectedRoute>
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        } 
      />
      
      {/* Catch all - redirect to login */}
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
};

function App() {
  return (
    <Router future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <AuthProvider>
        <AppContent />
      </AuthProvider>
    </Router>
  );
}

export default App;