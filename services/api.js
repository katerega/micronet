// services/api.js
import axios from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:5678';

export const api = {
  // Dashboard
  getDashboardData: async () => {
    const response = await axios.get(`${API_BASE_URL}/api/dashboard`);
    return response.data;
  },

  // Inventory
  getInventory: async () => {
    const response = await axios.get(`${API_BASE_URL}/api/inventory`);
    return response.data;
  },

  updateStock: async (data) => {
    const response = await axios.post(`${API_BASE_URL}/api/inventory/update`, data);
    return response.data;
  },

  // Procurement
  getProcurementRequests: async () => {
    const response = await axios.get(`${API_BASE_URL}/api/procurement-requests`);
    return response.data;
  },

  createProcurementRequest: async (data) => {
    const response = await axios.post(`${API_BASE_URL}/api/procurement-requests`, data);
    return response.data;
  },

  // Quotes
  getQuotesForRequest: async (requestId) => {
    const response = await axios.get(`${API_BASE_URL}/api/quotes/${requestId}`);
    return response.data;
  },

  approveQuotes: async (data) => {
    const response = await axios.post(`${API_BASE_URL}/api/quotes/approve`, data);
    return response.data;
  },

  // Suppliers
  getSuppliers: async () => {
    const response = await axios.get(`${API_BASE_URL}/api/suppliers`);
    return response.data;
  },

  // WhatsApp
  getWhatsAppMessages: async () => {
    const response = await axios.get(`${API_BASE_URL}/api/whatsapp/messages`);
    return response.data;
  },

  sendWhatsAppMessage: async (data) => {
    const response = await axios.post(`${API_BASE_URL}/api/whatsapp/send`, data);
    return response.data;
  },

  // Voice Assistant
  transcribeAudio: async (formData) => {
    const response = await axios.post(`${API_BASE_URL}/api/transcribe`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data;
  },

  extractProcurementItems: async (transcript) => {
    const response = await axios.post(`${API_BASE_URL}/api/extract-items`, { transcript });
    return response.data;
  },

  createProcurementFromVoice: async (data) => {
    const response = await axios.post(`${API_BASE_URL}/api/procurement/voice`, data);
    return response.data;
  },

  // Analytics
  getAnalytics: async () => {
    const response = await axios.get(`${API_BASE_URL}/api/analytics`);
    return response.data;
  },
};