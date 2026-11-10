import React from 'react'

function App() {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <div className="text-center">
        <h1 className="text-4xl font-bold text-indigo-600 mb-4">MICRONET</h1>
        <p className="text-xl text-gray-600 mb-8">AP Automation System</p>
        <div className="space-x-4">
          <a href="/api/" className="text-indigo-600 hover:text-indigo-800">API</a>
          <a href="/admin/" className="text-indigo-600 hover:text-indigo-800">Admin</a>
        </div>
        <div className="mt-8 p-4 bg-green-100 text-green-800 rounded-lg">
          ✅ Frontend is running!
        </div>
      </div>
    </div>
  )
}

export default App
