// App.js
import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import CssBaseline from '@mui/material/CssBaseline';
import { LocalizationProvider } from '@mui/x-date-pickers';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFns';
import { QueryClient, QueryClientProvider } from 'react-query';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import Inventory from './pages/Inventory';
import ProcurementRequests from './pages/ProcurementRequests';
import Suppliers from './pages/Suppliers';
import QuoteComparison from './pages/QuoteComparison';
import WhatsAppCenter from './pages/WhatsAppCenter';
import VoiceAssistant from './pages/VoiceAssistant';
import Analytics from './pages/Analytics';
import { NotificationProvider } from './context/NotificationContext';

const theme = createTheme({
  palette: {
    primary: {
      main: '#1976d2',
    },
    secondary: {
      main: '#dc004e',
    },
    background: {
      default: '#f5f5f5',
    },
  },
  typography: {
    fontFamily: '"Inter", "Roboto", "Helvetica", "Arial", sans-serif',
  },
});

const queryClient = new QueryClient();

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <LocalizationProvider dateAdapter={AdapterDateFns}>
          <CssBaseline />
          <NotificationProvider>
            <Router>
              <Layout>
                <Routes>
                  <Route path="/" element={<Navigate to="/dashboard" />} />
                  <Route path="/dashboard" element={<Dashboard />} />
                  <Route path="/inventory" element={<Inventory />} />
                  <Route path="/procurement-requests" element={<ProcurementRequests />} />
                  <Route path="/suppliers" element={<Suppliers />} />
                  <Route path="/quote-comparison/:requestId?" element={<QuoteComparison />} />
                  <Route path="/whatsapp" element={<WhatsAppCenter />} />
                  <Route path="/voice-assistant" element={<VoiceAssistant />} />
                  <Route path="/analytics" element={<Analytics />} />
                </Routes>
              </Layout>
            </Router>
          </NotificationProvider>
        </LocalizationProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}

export default App;