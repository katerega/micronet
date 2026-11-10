import api from './api';

export const login = async (username, password) => {
  const response = await api.post('/auth/login/', {
    username,
    password,
  });

  const data = response.data;

  if (data.access) {
    localStorage.setItem('access_token', data.access);
  }

  if (data.refresh) {
    localStorage.setItem('refresh_token', data.refresh);
  }

  if (data.user) {
    localStorage.setItem('user', JSON.stringify(data.user));
  }

  if (data.company) {
    localStorage.setItem('company', JSON.stringify(data.company));
  }

  return data;
};

export const logout = () => {
  localStorage.removeItem('access_token');
  localStorage.removeItem('refresh_token');
  localStorage.removeItem('user');
  localStorage.removeItem('company');

  window.location.href = '/login';
};

export const getStoredUser = () => {
  const user = localStorage.getItem('user');

  return user ? JSON.parse(user) : null;
};

export const getStoredCompany = () => {
  const company = localStorage.getItem('company');

  return company ? JSON.parse(company) : null;
};

export const isAuthenticated = () => {
  return !!localStorage.getItem('access_token');
};
