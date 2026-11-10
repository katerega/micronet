import React, { useState } from 'react';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { useAuth } from '../../contexts/AuthContext';

const N8nSettings = () => {
  const { company, updateCompany } = useAuth();

  const [webhookUrl, setWebhookUrl] = useState(
    company?.n8n_webhook_url || ''
  );

  const [loading, setLoading] = useState(false);

  const testConnection = async () => {
    if (!webhookUrl.trim()) {
      toast.error('Enter your n8n webhook URL first.');
      return;
    }

    setLoading(true);

    try {
      const response = await api.post(
        '/companies/test-n8n/',
        {
          webhook_url: webhookUrl.trim(),
        }
      );

      if (response.data.success) {
        toast.success('n8n connection successful!');

        updateCompany({
          ...company,
          n8n_connected: true,
          n8n_webhook_url: webhookUrl.trim(),
        });
      } else {
        toast.error(
          response.data.error || 'n8n connection failed.'
        );
      }
    } catch (error) {
      console.error('n8n connection error:', error);

      toast.error(
        error.response?.data?.error ||
        'Could not connect to n8n.'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="settings-card">
      <div className="settings-header">
        <h2>n8n Integration</h2>
        <p>
          Connect MICRONET AP Automation to your n8n workflows.
        </p>
      </div>

      <div className="form-group">
        <label htmlFor="n8n-webhook">
          n8n Webhook URL
        </label>

        <input
          id="n8n-webhook"
          type="url"
          value={webhookUrl}
          onChange={(e) => setWebhookUrl(e.target.value)}
          placeholder="https://your-domain.com/webhook/..."
        />
      </div>

      <div className="connection-status">
        <strong>Status:</strong>{' '}
        {company?.n8n_connected ? (
          <span>Connected</span>
        ) : (
          <span>Not connected</span>
        )}
      </div>

      <button
        type="button"
        className="primary-button"
        onClick={testConnection}
        disabled={loading}
      >
        {loading ? 'Testing connection...' : 'Test n8n Connection'}
      </button>
    </div>
  );
};

export default N8nSettings;
