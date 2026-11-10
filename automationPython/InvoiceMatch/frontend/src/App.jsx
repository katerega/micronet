import React from 'react';

function App() {
  return (
    <div style={{
      minHeight: '100vh',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      background: '#f3f4f6',
      fontFamily: 'Arial'
    }}>
      <div style={{
        width: '400px',
        padding: '32px',
        background: '#fff',
        borderRadius: '12px',
        boxShadow: '0 4px 20px rgba(0,0,0,0.08)'
      }}>
        <h1>MICRONET</h1>
        <p>AP Automation Platform</p>

        <input
          placeholder="Username"
          style={{
            width: '100%',
            padding: '12px',
            marginBottom: '12px',
            boxSizing: 'border-box'
          }}
        />

        <input
          type="password"
          placeholder="Password"
          style={{
            width: '100%',
            padding: '12px',
            marginBottom: '16px',
            boxSizing: 'border-box'
          }}
        />

        <button style={{
          width: '100%',
          padding: '12px',
          background: '#111827',
          color: '#fff',
          border: 0,
          borderRadius: '6px'
        }}>
          Sign In
        </button>
      </div>
    </div>
  );
}

export default App;
