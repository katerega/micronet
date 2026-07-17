import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

const Dashboard = () => {
  const navigate = useNavigate();
  const [user, setUser] = useState({});

  useEffect(() => {
    const userData = JSON.parse(localStorage.getItem('user') || '{}');
    setUser(userData);
  }, []);

  const handleLogout = () => {
    localStorage.removeItem('user');
    localStorage.removeItem('access_token');
    navigate('/login');
  };

  return (
    <div style={{ minHeight: '100vh', background: '#f0f2f5', padding: '20px' }}>
      {/* Header */}
      <div style={{ background: 'white', padding: '20px 30px', borderRadius: '10px', marginBottom: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <div>
          <h1 style={{ margin: 0, color: '#1976d2' }}>Micronet Procurement</h1>
          <p style={{ margin: '5px 0 0', color: '#666' }}>Welcome, {user.name || user.email || 'User'}!</p>
        </div>
        <button onClick={handleLogout} style={{ padding: '10px 20px', background: '#dc004e', color: 'white', border: 'none', borderRadius: '5px', cursor: 'pointer' }}>
          Logout
        </button>
      </div>

      {/* Stats */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '20px', marginBottom: '20px' }}>
        <div style={{ background: 'white', padding: '20px', borderRadius: '10px', textAlign: 'center' }}>
          <h3 style={{ color: '#666' }}>Total Items</h3>
          <p style={{ fontSize: '30px', fontWeight: 'bold', color: '#1976d2' }}>1,254</p>
        </div>
        <div style={{ background: 'white', padding: '20px', borderRadius: '10px', textAlign: 'center' }}>
          <h3 style={{ color: '#666' }}>Low Stock</h3>
          <p style={{ fontSize: '30px', fontWeight: 'bold', color: '#ed6c02' }}>24</p>
        </div>
        <div style={{ background: 'white', padding: '20px', borderRadius: '10px', textAlign: 'center' }}>
          <h3 style={{ color: '#666' }}>Open Procurement</h3>
          <p style={{ fontSize: '30px', fontWeight: 'bold', color: '#2e7d32' }}>16</p>
        </div>
        <div style={{ background: 'white', padding: '20px', borderRadius: '10px', textAlign: 'center' }}>
          <h3 style={{ color: '#666' }}>Pending Quotes</h3>
          <p style={{ fontSize: '30px', fontWeight: 'bold', color: '#9c27b0' }}>12</p>
        </div>
      </div>

      {/* Quick Actions */}
      <div style={{ background: 'white', padding: '20px', borderRadius: '10px', marginBottom: '20px' }}>
        <h2>Quick Actions</h2>
        <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
          <button style={{ padding: '10px 20px', background: '#1976d2', color: 'white', border: 'none', borderRadius: '5px', cursor: 'pointer' }}>New Request</button>
          <button style={{ padding: '10px 20px', background: '#2e7d32', color: 'white', border: 'none', borderRadius: '5px', cursor: 'pointer' }}>View Inventory</button>
          <button style={{ padding: '10px 20px', background: '#ed6c02', color: 'white', border: 'none', borderRadius: '5px', cursor: 'pointer' }}>Request Quote</button>
          <button style={{ padding: '10px 20px', background: '#9c27b0', color: 'white', border: 'none', borderRadius: '5px', cursor: 'pointer' }}>View Reports</button>
        </div>
      </div>

      {/* Recent Requests */}
      <div style={{ background: 'white', padding: '20px', borderRadius: '10px' }}>
        <h2>Recent Procurement Requests</h2>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr style={{ background: '#f5f5f5' }}>
              <th style={{ padding: '10px', textAlign: 'left' }}>ID</th>
              <th style={{ padding: '10px', textAlign: 'left' }}>Item</th>
              <th style={{ padding: '10px', textAlign: 'left' }}>Quantity</th>
              <th style={{ padding: '10px', textAlign: 'left' }}>Status</th>
              <th style={{ padding: '10px', textAlign: 'left' }}>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>PR-1001</td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>Router Board</td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>50</td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}><span style={{ background: '#fff3e0', padding: '3px 10px', borderRadius: '12px', fontSize: '12px' }}>Pending</span></td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>2024-01-15</td></tr>
            <tr><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>PR-1002</td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>Fiber Module</td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>30</td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}><span style={{ background: '#e3f2fd', padding: '3px 10px', borderRadius: '12px', fontSize: '12px' }}>Sourcing</span></td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>2024-01-14</td></tr>
            <tr><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>PR-1003</td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>UPS Battery</td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>10</td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}><span style={{ background: '#e8f5e9', padding: '3px 10px', borderRadius: '12px', fontSize: '12px' }}>Approved</span></td><td style={{ padding: '10px', borderTop: '1px solid #eee' }}>2024-01-13</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  );
};

export default Dashboard;
