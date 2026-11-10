import React, { useState, useEffect } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { Link } from 'react-router-dom';
import { 
  HomeIcon, 
  UploadIcon, 
  DocumentIcon, 
  CogIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ClockIcon
} from '@heroicons/react/outline';
import api from '../../services/api';
import toast from 'react-hot-toast';

const Dashboard = () => {
  const { user, company } = useAuth();
  const [stats, setStats] = useState({
    invoices: { total: 0, matched: 0, discrepancies: 0, processing: 0 },
    health: { tally: 'Not Connected', n8n: 'Not Connected', activation: 'Inactive' }
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      const response = await api.get('/dashboard/summary/');
      setStats(response.data);
    } catch (error) {
      toast.error('Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  };

  const statsCards = [
    {
      title: 'Total Invoices',
      value: stats.invoices.total,
      icon: DocumentIcon,
      color: 'blue'
    },
    {
      title: 'Matched',
      value: stats.invoices.matched,
      icon: CheckCircleIcon,
      color: 'green'
    },
    {
      title: 'Discrepancies',
      value: stats.invoices.discrepancies,
      icon: ExclamationCircleIcon,
      color: 'red'
    },
    {
      title: 'Processing',
      value: stats.invoices.processing,
      icon: ClockIcon,
      color: 'yellow'
    }
  ];

  const healthStatus = [
    {
      label: 'Tally Connection',
      status: stats.health.tally,
      connected: stats.health.tally === 'Connected',
      path: '/settings'
    },
    {
      label: 'n8n Webhook',
      status: stats.health.n8n,
      connected: stats.health.n8n === 'Connected',
      path: '/settings'
    },
    {
      label: 'Account Status',
      status: stats.health.activation,
      connected: stats.health.activation === 'Active',
      path: '/activate'
    }
  ];

  const quickActions = [
    {
      title: 'Upload Invoice',
      description: 'Upload a supplier invoice for processing',
      icon: UploadIcon,
      path: '/upload',
      color: 'indigo'
    },
    {
      title: 'View History',
      description: 'View your invoice processing history',
      icon: DocumentIcon,
      path: '/history',
      color: 'green'
    },
    {
      title: 'Settings',
      description: 'Configure Tally and n8n connections',
      icon: CogIcon,
      path: '/settings',
      color: 'gray'
    }
  ];

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Navigation */}
      <nav className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between h-16">
            <div className="flex items-center">
              <HomeIcon className="h-6 w-6 text-indigo-600" />
              <span className="ml-2 text-xl font-bold text-gray-900">
                MICRONET AP Automation
              </span>
            </div>
            <div className="flex items-center space-x-4">
              <span className="text-sm text-gray-600">
                {user?.first_name} {user?.last_name}
              </span>
              <button
                onClick={() => useAuth().logout()}
                className="text-sm text-gray-600 hover:text-gray-900"
              >
                Logout
              </button>
            </div>
          </div>
        </div>
      </nav>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Welcome */}
        <div className="bg-white rounded-lg shadow-sm p-6 mb-8">
          <h1 className="text-2xl font-bold text-gray-900">
            Welcome, {user?.first_name || 'User'}!
          </h1>
          <p className="text-gray-600 mt-1">
            {company?.name} - {company?.is_active ? 'Active Account' : 'Account Pending Activation'}
          </p>
        </div>

        {/* Stats */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {statsCards.map((stat, idx) => {
            const Icon = stat.icon;
            const colorClasses = {
              blue: 'bg-blue-100 text-blue-600',
              green: 'bg-green-100 text-green-600',
              red: 'bg-red-100 text-red-600',
              yellow: 'bg-yellow-100 text-yellow-600'
            };
            return (
              <div key={idx} className="bg-white rounded-lg shadow-sm p-6">
                <div className="flex items-center">
                  <div className={`p-3 rounded-full ${colorClasses[stat.color]}`}>
                    <Icon className="h-6 w-6" />
                  </div>
                  <div className="ml-4">
                    <p className="text-sm text-gray-600">{stat.title}</p>
                    <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Quick Actions */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          {quickActions.map((action, idx) => {
            const Icon = action.icon;
            const colorClasses = {
              indigo: 'hover:bg-indigo-50 border-indigo-200',
              green: 'hover:bg-green-50 border-green-200',
              gray: 'hover:bg-gray-50 border-gray-200'
            };
            return (
              <Link
                key={idx}
                to={action.path}
                className={`bg-white rounded-lg shadow-sm p-6 border-2 transition-colors ${colorClasses[action.color]}`}
              >
                <div className="flex items-center">
                  <Icon className="h-8 w-8 text-gray-600" />
                  <div className="ml-4">
                    <h3 className="font-semibold text-gray-900">{action.title}</h3>
                    <p className="text-sm text-gray-600">{action.description}</p>
                  </div>
                </div>
              </Link>
            );
          })}
        </div>

        {/* System Health */}
        <div className="bg-white rounded-lg shadow-sm p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">System Health</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {healthStatus.map((item, idx) => (
              <div key={idx} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <span className="text-gray-700">{item.label}</span>
                <Link
                  to={item.path}
                  className={`px-3 py-1 rounded-full text-sm font-medium ${
                    item.connected
                      ? 'bg-green-100 text-green-800'
                      : 'bg-red-100 text-red-800'
                  }`}
                >
                  {item.status}
                </Link>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
