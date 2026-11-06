// services/api.js
import axios from 'axios';

const API_BASE_URL = process.env.REACT_APP_API_URL || 'http://localhost:5678/api';

// Create axios instance with default config
const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 30000,
});

// Request interceptor - Add auth token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('access_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor - Handle token refresh
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    
    // Only attempt refresh on 401 and if not already retrying
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      
      try {
        const refreshToken = localStorage.getItem('refresh_token');
        if (!refreshToken) {
          throw new Error('No refresh token');
        }

        const response = await axios.post(`${API_BASE_URL}/auth/refresh-token`, {
          refresh_token: refreshToken,
        });

        const { access_token, refresh_token } = response.data.data;
        
        localStorage.setItem('access_token', access_token);
        localStorage.setItem('refresh_token', refresh_token);
        
        // Retry original request with new token
        originalRequest.headers.Authorization = `Bearer ${access_token}`;
        return api(originalRequest);
      } catch (refreshError) {
        // Refresh failed - clear tokens and redirect to login
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
        localStorage.removeItem('user');
        
        // Redirect to login if not already there
        if (!window.location.pathname.includes('/login')) {
          window.location.href = '/login';
        }
        return Promise.reject(refreshError);
      }
    }
    
    return Promise.reject(error);
  }
);

// ==================== AUTHENTICATION SERVICES ====================

export const AuthService = {
  login: async (credentials) => {
    const response = await api.post('/auth/login', credentials);
    const { access_token, refresh_token, user } = response.data.data;
    
    localStorage.setItem('access_token', access_token);
    localStorage.setItem('refresh_token', refresh_token);
    localStorage.setItem('user', JSON.stringify(user));
    
    return response.data;
  },

  logout: async () => {
    try {
      await api.post('/auth/logout');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      localStorage.removeItem('access_token');
      localStorage.removeItem('refresh_token');
      localStorage.removeItem('user');
    }
  },

  getCurrentUser: async () => {
    const response = await api.get('/auth/me');
    return response.data.data;
  },

  refreshToken: async () => {
    const refreshToken = localStorage.getItem('refresh_token');
    const response = await api.post('/auth/refresh-token', { refresh_token: refreshToken });
    const { access_token, refresh_token } = response.data.data;
    
    localStorage.setItem('access_token', access_token);
    localStorage.setItem('refresh_token', refresh_token);
    
    return response.data;
  },

  forgotPassword: async (email) => {
    const response = await api.post('/auth/forgot-password', { email });
    return response.data;
  },

  resetPassword: async (token, password) => {
    const response = await api.post('/auth/reset-password', { token, password });
    return response.data;
  },

  changePassword: async (currentPassword, newPassword) => {
    const response = await api.post('/auth/change-password', { currentPassword, newPassword });
    return response.data;
  },

  updateProfile: async (profileData) => {
    const response = await api.put('/auth/me', profileData);
    const updatedUser = response.data.data;
    localStorage.setItem('user', JSON.stringify(updatedUser));
    return response.data;
  },
};

// ==================== USER MANAGEMENT SERVICES ====================

export const UserService = {
  getUsers: async (params = {}) => {
    const response = await api.get('/admin/users', { params });
    return response.data.data;
  },

  getUserById: async (id) => {
    const response = await api.get(`/admin/users/${id}`);
    return response.data.data;
  },

  createUser: async (userData) => {
    const response = await api.post('/admin/users', userData);
    return response.data.data;
  },

  updateUser: async (id, userData) => {
    const response = await api.put(`/admin/users/${id}`, userData);
    return response.data.data;
  },

  deleteUser: async (id) => {
    const response = await api.delete(`/admin/users/${id}`);
    return response.data.data;
  },

  assignRole: async (userId, roleId) => {
    const response = await api.post(`/admin/users/${userId}/roles`, { role_id: roleId });
    return response.data.data;
  },

  removeRole: async (userId, roleId) => {
    const response = await api.delete(`/admin/users/${userId}/roles/${roleId}`);
    return response.data.data;
  },

  getUserActivity: async (userId, params = {}) => {
    const response = await api.get(`/admin/users/${userId}/activity`, { params });
    return response.data.data;
  },

  toggleUserStatus: async (userId) => {
    const response = await api.patch(`/admin/users/${userId}/toggle-status`);
    return response.data.data;
  },
};

// ==================== ROLE MANAGEMENT SERVICES ====================

export const RoleService = {
  getRoles: async () => {
    const response = await api.get('/admin/roles');
    return response.data.data;
  },

  getRoleById: async (id) => {
    const response = await api.get(`/admin/roles/${id}`);
    return response.data.data;
  },

  createRole: async (roleData) => {
    const response = await api.post('/admin/roles', roleData);
    return response.data.data;
  },

  updateRole: async (id, roleData) => {
    const response = await api.put(`/admin/roles/${id}`, roleData);
    return response.data.data;
  },

  deleteRole: async (id) => {
    const response = await api.delete(`/admin/roles/${id}`);
    return response.data.data;
  },

  getRolePermissions: async (roleId) => {
    const response = await api.get(`/admin/roles/${roleId}/permissions`);
    return response.data.data;
  },

  updateRolePermissions: async (roleId, permissions) => {
    const response = await api.put(`/admin/roles/${roleId}/permissions`, { permissions });
    return response.data.data;
  },
};

// ==================== DEPARTMENT SERVICES ====================

export const DepartmentService = {
  getDepartments: async () => {
    const response = await api.get('/admin/departments');
    return response.data.data;
  },

  getDepartmentById: async (id) => {
    const response = await api.get(`/admin/departments/${id}`);
    return response.data.data;
  },

  createDepartment: async (departmentData) => {
    const response = await api.post('/admin/departments', departmentData);
    return response.data.data;
  },

  updateDepartment: async (id, departmentData) => {
    const response = await api.put(`/admin/departments/${id}`, departmentData);
    return response.data.data;
  },

  deleteDepartment: async (id) => {
    const response = await api.delete(`/admin/departments/${id}`);
    return response.data.data;
  },

  getDepartmentStats: async () => {
    const response = await api.get('/admin/departments/stats');
    return response.data.data;
  },
};

// ==================== ADMIN SERVICES ====================

export const AdminService = {
  getDashboardMetrics: async () => {
    const response = await api.get('/admin/metrics');
    return response.data.data;
  },

  getAuditLogs: async (params = {}) => {
    const response = await api.get('/admin/audit-logs', { params });
    return response.data.data;
  },

  getSystemSettings: async () => {
    const response = await api.get('/admin/settings');
    return response.data.data;
  },

  updateSystemSettings: async (settings) => {
    const response = await api.put('/admin/settings', settings);
    return response.data.data;
  },

  getSystemHealth: async () => {
    const response = await api.get('/admin/health');
    return response.data.data;
  },

  clearCache: async () => {
    const response = await api.post('/admin/cache/clear');
    return response.data.data;
  },

  getSystemMetrics: async () => {
    const response = await api.get('/admin/metrics/system');
    return response.data.data;
  },
};

// ==================== PROCUREMENT SERVICES ====================

export const ProcurementService = {
  getRequests: async (params = {}) => {
    const response = await api.get('/procurement/requests', { params });
    return response.data.data;
  },

  getRequestById: async (id) => {
    const response = await api.get(`/procurement/requests/${id}`);
    return response.data.data;
  },

  createRequest: async (data) => {
    const response = await api.post('/procurement/requests', data);
    return response.data.data;
  },

  updateRequest: async (id, data) => {
    const response = await api.put(`/procurement/requests/${id}`, data);
    return response.data.data;
  },

  deleteRequest: async (id) => {
    const response = await api.delete(`/procurement/requests/${id}`);
    return response.data.data;
  },

  approveRequest: async (id) => {
    const response = await api.post(`/procurement/requests/${id}/approve`);
    return response.data.data;
  },

  rejectRequest: async (id, reason) => {
    const response = await api.post(`/procurement/requests/${id}/reject`, { reason });
    return response.data.data;
  },

  assignRequest: async (id, assigneeId) => {
    const response = await api.post(`/procurement/requests/${id}/assign`, { assignee_id: assigneeId });
    return response.data.data;
  },

  getRequestStatus: async (id) => {
    const response = await api.get(`/procurement/requests/${id}/status`);
    return response.data.data;
  },

  getRequestHistory: async (id) => {
    const response = await api.get(`/procurement/requests/${id}/history`);
    return response.data.data;
  },

  createFromVoice: async (transcript) => {
    const response = await api.post('/procurement/voice', { transcript });
    return response.data.data;
  },

  extractItems: async (transcript) => {
    const response = await api.post('/procurement/extract-items', { transcript });
    return response.data.data;
  },
};

// ==================== INVENTORY SERVICES ====================

export const InventoryService = {
  getInventory: async (params = {}) => {
    const response = await api.get('/inventory', { params });
    return response.data.data;
  },

  getItemById: async (id) => {
    const response = await api.get(`/inventory/${id}`);
    return response.data.data;
  },

  updateStock: async (data) => {
    const response = await api.post('/inventory/update', data);
    return response.data.data;
  },

  getLowStock: async () => {
    const response = await api.get('/inventory/low-stock');
    return response.data.data;
  },

  getReorderLevels: async () => {
    const response = await api.get('/inventory/reorder-levels');
    return response.data.data;
  },

  updateReorderLevel: async (itemId, reorderData) => {
    const response = await api.put(`/inventory/${itemId}/reorder-level`, reorderData);
    return response.data.data;
  },

  getStockHistory: async (itemId, params = {}) => {
    const response = await api.get(`/inventory/${itemId}/history`, { params });
    return response.data.data;
  },

  addStock: async (data) => {
    const response = await api.post('/inventory/add-stock', data);
    return response.data.data;
  },

  removeStock: async (data) => {
    const response = await api.post('/inventory/remove-stock', data);
    return response.data.data;
  },

  getInventoryReport: async (params = {}) => {
    const response = await api.get('/inventory/report', { params });
    return response.data.data;
  },
};

// ==================== QUOTE SERVICES ====================

export const QuoteService = {
  getQuotes: async (params = {}) => {
    const response = await api.get('/quotes', { params });
    return response.data.data;
  },

  getQuoteById: async (id) => {
    const response = await api.get(`/quotes/${id}`);
    return response.data.data;
  },

  requestQuote: async (data) => {
    const response = await api.post('/quotes/request', data);
    return response.data.data;
  },

  getQuotesForRequest: async (requestId) => {
    const response = await api.get(`/quotes/request/${requestId}`);
    return response.data.data;
  },

  compareQuotes: async (quoteIds) => {
    const response = await api.post('/quotes/compare', { quote_ids: quoteIds });
    return response.data.data;
  },

  approveQuote: async (id) => {
    const response = await api.post(`/quotes/${id}/approve`);
    return response.data.data;
  },

  rejectQuote: async (id, reason) => {
    const response = await api.post(`/quotes/${id}/reject`, { reason });
    return response.data.data;
  },

  getBestQuotes: async (itemIds) => {
    const response = await api.post('/quotes/best', { item_ids: itemIds });
    return response.data.data;
  },

  generateQuotation: async (quoteIds) => {
    const response = await api.post('/quotes/generate', { quote_ids: quoteIds });
    return response.data.data;
  },
};

// ==================== SUPPLIER SERVICES ====================

export const SupplierService = {
  getSuppliers: async (params = {}) => {
    const response = await api.get('/suppliers', { params });
    return response.data.data;
  },

  getSupplierById: async (id) => {
    const response = await api.get(`/suppliers/${id}`);
    return response.data.data;
  },

  createSupplier: async (data) => {
    const response = await api.post('/suppliers', data);
    return response.data.data;
  },

  updateSupplier: async (id, data) => {
    const response = await api.put(`/suppliers/${id}`, data);
    return response.data.data;
  },

  deleteSupplier: async (id) => {
    const response = await api.delete(`/suppliers/${id}`);
    return response.data.data;
  },

  getLocalSuppliers: async () => {
    const response = await api.get('/suppliers/local');
    return response.data.data;
  },

  getInternationalSuppliers: async () => {
    const response = await api.get('/suppliers/international');
    return response.data.data;
  },

  getSupplierProducts: async (supplierId) => {
    const response = await api.get(`/suppliers/${supplierId}/products`);
    return response.data.data;
  },

  addSupplierProduct: async (supplierId, productData) => {
    const response = await api.post(`/suppliers/${supplierId}/products`, productData);
    return response.data.data;
  },

  updateSupplierProduct: async (supplierId, productId, productData) => {
    const response = await api.put(`/suppliers/${supplierId}/products/${productId}`, productData);
    return response.data.data;
  },

  removeSupplierProduct: async (supplierId, productId) => {
    const response = await api.delete(`/suppliers/${supplierId}/products/${productId}`);
    return response.data.data;
  },

  getSupplierPerformance: async (supplierId) => {
    const response = await api.get(`/suppliers/${supplierId}/performance`);
    return response.data.data;
  },
};

// ==================== SALES SERVICES ====================

export const SalesService = {
  getSalesOrders: async (params = {}) => {
    const response = await api.get('/sales/orders', { params });
    return response.data.data;
  },

  getSalesOrderById: async (id) => {
    const response = await api.get(`/sales/orders/${id}`);
    return response.data.data;
  },

  createSalesOrder: async (data) => {
    const response = await api.post('/sales/orders', data);
    return response.data.data;
  },

  updateSalesOrder: async (id, data) => {
    const response = await api.put(`/sales/orders/${id}`, data);
    return response.data.data;
  },

  checkStockAvailability: async (items) => {
    const response = await api.post('/sales/check-stock', { items });
    return response.data.data;
  },

  getSalesAnalytics: async (params = {}) => {
    const response = await api.get('/sales/analytics', { params });
    return response.data.data;
  },

  getCustomers: async (params = {}) => {
    const response = await api.get('/sales/customers', { params });
    return response.data.data;
  },

  createCustomer: async (data) => {
    const response = await api.post('/sales/customers', data);
    return response.data.data;
  },

  updateCustomer: async (id, data) => {
    const response = await api.put(`/sales/customers/${id}`, data);
    return response.data.data;
  },
};

// ==================== FINANCE SERVICES ====================

export const FinanceService = {
  getBudget: async (params = {}) => {
    const response = await api.get('/finance/budget', { params });
    return response.data.data;
  },

  updateBudget: async (data) => {
    const response = await api.put('/finance/budget', data);
    return response.data.data;
  },

  getExpenses: async (params = {}) => {
    const response = await api.get('/finance/expenses', { params });
    return response.data.data;
  },

  createExpense: async (data) => {
    const response = await api.post('/finance/expenses', data);
    return response.data.data;
  },

  getFinanceReport: async (params = {}) => {
    const response = await api.get('/finance/report', { params });
    return response.data.data;
  },

  getProcurementSpending: async (params = {}) => {
    const response = await api.get('/finance/procurement-spending', { params });
    return response.data.data;
  },
};

// ==================== WHATSAPP SERVICES ====================

export const WhatsAppService = {
  getMessages: async (params = {}) => {
    const response = await api.get('/whatsapp/messages', { params });
    return response.data.data;
  },

  sendMessage: async (data) => {
    const response = await api.post('/whatsapp/send', data);
    return response.data.data;
  },

  getConversations: async () => {
    const response = await api.get('/whatsapp/conversations');
    return response.data.data;
  },

  sendOrderConfirmation: async (orderId) => {
    const response = await api.post(`/whatsapp/send-confirmation/${orderId}`);
    return response.data.data;
  },
};

// ==================== ANALYTICS SERVICES ====================

export const AnalyticsService = {
  getDashboardAnalytics: async (params = {}) => {
    const response = await api.get('/analytics/dashboard', { params });
    return response.data.data;
  },

  getProcurementAnalytics: async (params = {}) => {
    const response = await api.get('/analytics/procurement', { params });
    return response.data.data;
  },

  getInventoryAnalytics: async (params = {}) => {
    const response = await api.get('/analytics/inventory', { params });
    return response.data.data;
  },

  getSalesAnalytics: async (params = {}) => {
    const response = await api.get('/analytics/sales', { params });
    return response.data.data;
  },

  getSupplierAnalytics: async (params = {}) => {
    const response = await api.get('/analytics/suppliers', { params });
    return response.data.data;
  },

  exportReport: async (reportType, params = {}) => {
    const response = await api.post(`/analytics/export/${reportType}`, params, {
      responseType: 'blob',
    });
    return response.data;
  },
};

// ==================== VOICE ASSISTANT SERVICES ====================

export const VoiceService = {
  transcribeAudio: async (formData) => {
    const response = await api.post('/voice/transcribe', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return response.data.data;
  },

  processVoiceCommand: async (audioData) => {
    const response = await api.post('/voice/process', audioData);
    return response.data.data;
  },

  getVoiceHistory: async (params = {}) => {
    const response = await api.get('/voice/history', { params });
    return response.data.data;
  },
};

// ==================== NOTIFICATION SERVICES ====================

export const NotificationService = {
  getNotifications: async (params = {}) => {
    const response = await api.get('/notifications', { params });
    return response.data.data;
  },

  markAsRead: async (id) => {
    const response = await api.post(`/notifications/${id}/read`);
    return response.data.data;
  },

  markAllAsRead: async () => {
    const response = await api.post('/notifications/read-all');
    return response.data.data;
  },

  getUnreadCount: async () => {
    const response = await api.get('/notifications/unread-count');
    return response.data.data;
  },

  deleteNotification: async (id) => {
    const response = await api.delete(`/notifications/${id}`);
    return response.data.data;
  },

  sendNotification: async (data) => {
    const response = await api.post('/notifications/send', data);
    return response.data.data;
  },
};

// ==================== REPORT SERVICES ====================

export const ReportService = {
  generateReport: async (reportType, params = {}) => {
    const response = await api.post(`/reports/generate/${reportType}`, params);
    return response.data.data;
  },

  getReports: async (params = {}) => {
    const response = await api.get('/reports', { params });
    return response.data.data;
  },

  getReportById: async (id) => {
    const response = await api.get(`/reports/${id}`);
    return response.data.data;
  },

  downloadReport: async (id, format = 'pdf') => {
    const response = await api.get(`/reports/${id}/download`, {
      params: { format },
      responseType: 'blob',
    });
    return response.data;
  },

  deleteReport: async (id) => {
    const response = await api.delete(`/reports/${id}`);
    return response.data.data;
  },

  scheduleReport: async (data) => {
    const response = await api.post('/reports/schedule', data);
    return response.data.data;
  },

  getScheduledReports: async () => {
    const response = await api.get('/reports/scheduled');
    return response.data.data;
  },

  cancelScheduledReport: async (id) => {
    const response = await api.delete(`/reports/schedule/${id}`);
    return response.data.data;
  },
};

// ==================== EXPORT ALL SERVICES ====================

export default {
  AuthService,
  UserService,
  RoleService,
  DepartmentService,
  AdminService,
  ProcurementService,
  InventoryService,
  QuoteService,
  SupplierService,
  SalesService,
  FinanceService,
  WhatsAppService,
  AnalyticsService,
  VoiceService,
  NotificationService,
  ReportService,
};