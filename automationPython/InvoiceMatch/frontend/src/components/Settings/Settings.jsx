import React from 'react';
import N8nSettings from './N8nSettings';
import TallySettings from './TallySettings';

const Settings = () => {
  return (
    <div className="min-h-screen bg-gray-50">
      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">
            Settings
          </h1>
          <p className="mt-2 text-gray-600">
            Configure your Tally and n8n integrations.
          </p>
        </div>

        <div className="space-y-6">
          <section className="bg-white rounded-lg shadow-sm border p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">
              Tally Integration
            </h2>
            <TallySettings />
          </section>

          <section className="bg-white rounded-lg shadow-sm border p-6">
            <h2 className="text-xl font-semibold text-gray-900 mb-4">
              n8n Integration
            </h2>
            <N8nSettings />
          </section>
        </div>
      </div>
    </div>
  );
};

export default Settings;
