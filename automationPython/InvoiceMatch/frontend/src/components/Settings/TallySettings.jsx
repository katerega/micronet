import React, { useState } from 'react';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { useAuth } from '../../contexts/AuthContext';

const TallySettings = () => {
  const { company, updateCompany } = useAuth();

  const [settings, setSettings] = useState({
    host: company?.tally_host || 'host.docker.internal',
    port: company?.tally_port || '9000',
    company_name: company?.tally_company_name || '',
  });

  const [loading, setLoading] = useState(false);

  const handleChange = (e) => {
    setSettings({
      ...settings,
      [e.target.name]: e.target.value,
    });
  };

  const testConnection = async () => {
    if (!settings.company_name.trim()) {
      toast.error('Enter the Tally company name.');
      return;
    }

    setLoading(true);

    try {
      const response = await api.post(
        '/companies/test-tally/',
        {
          host: settings.host.trim(),
          port: Number(settings.port),
          company_name: settings.company_name.trim(),
        }
      );

      if (response.data.success) {
        toast.success('Tally connection successful!');

        updateCompany({
          ...company,
          tally_connected: true,
          tally_host: settings.host,
          tally_port: settings.port,
          tally_company_name: settings.company_name,
        });
      } else {
        toast.error(
          response.data.error || 'Tally connection failed.'
        );
      }
    } catch (error) {
      console.error('Tally connection error:', error);

      toast.error(
        error.response?.data?.error ||
        'Could not connect to Tally.'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="settings-card">
      <div className="settings-header">
        <h2>Tally Integration</h2>
        <p>
          Connect MICRONET AP Automation to your Tally server.
        </p>
      </div>

      <div className="form-group">
        <label htmlFor="tally-host">
          Tally Host
        </label>

        <input
          id="tally-host"
          type="text"
          name="host"
          value={settings.host}
          onChange={handleChange}
          placeholder="host.docker.internal"
        />
      </div>

      <div className="form-group">
        <label htmlFor="tally-port">
          Tally Port
        </label>

        <input
          id="tally-port"
          type="number"
          name="port"
          value={settings.port}
          onChange={handleChange}
          placeholder="9000"
        />
      </div>

      <div className="form-group">
        <label htmlFor="tally-company">
          Tally Company Name
        </label>

        <input
          id="tally-company"
          type="text"
          name="company_name"
          value={settings.company_name}
          onChange={handleChange}
          placeholder="MICRONET"
        />
      </div>

      <div className="connection-status">
        <strong>Status:</strong>{' '}
        {company?.tally_connected ? (
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
        {loading ? 'Testing connection...' : 'Test Tally Connection'}
      </button>
    </div>
  );
};

export default TallySettings;
