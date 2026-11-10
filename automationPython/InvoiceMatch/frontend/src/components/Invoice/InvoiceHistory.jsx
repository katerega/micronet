import React, { useState, useEffect } from 'react'
import { useAuth } from '../../contexts/AuthContext'
import api from '../../services/api'
import toast from 'react-hot-toast'

const InvoiceHistory = () => {
  const [invoices, setInvoices] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetchInvoices()
  }, [])

  const fetchInvoices = async () => {
    try {
      const response = await api.get('/invoices/')
      setInvoices(response.data)
    } catch (error) {
      toast.error('Failed to load invoices')
    } finally {
      setLoading(false)
    }
  }

  const getStatusBadge = (status) => {
    const classes = {
      uploaded: 'bg-yellow-100 text-yellow-800',
      processing: 'bg-blue-100 text-blue-800',
      matched: 'bg-green-100 text-green-800',
      discrepancy: 'bg-red-100 text-red-800',
      error: 'bg-red-100 text-red-800'
    }
    const labels = {
      uploaded: 'Uploaded',
      processing: 'Processing',
      matched: '✓ Matched',
      discrepancy: '⚠ Discrepancy',
      error: 'Error'
    }
    return (
      <span className={\px-2 py-1 rounded-full text-xs font-medium \\}>
        {labels[status] || status}
      </span>
    )
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>
    )
  }

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      <div className="card">
        <h1 className="text-2xl font-bold text-gray-900 mb-6">Invoice History</h1>

        {invoices.length === 0 ? (
          <div className="text-center py-12">
            <div className="text-6xl mb-4">📋</div>
            <p className="text-gray-600">No invoices uploaded yet</p>
            <a href="/upload" className="text-indigo-600 hover:text-indigo-800 mt-2 inline-block">
              Upload your first invoice
            </a>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {invoices.map((invoice) => (
                  <tr key={invoice.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-sm text-gray-900">{invoice.filename}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{invoice.po_number || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">{invoice.supplier_name || '-'}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">
                      {invoice.total_amount ? \UGX \\ : '-'}
                    </td>
                    <td className="px-4 py-3">{getStatusBadge(invoice.status)}</td>
                    <td className="px-4 py-3 text-sm text-gray-600">
                      {new Date(invoice.created_at).toLocaleDateString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}

export default InvoiceHistory
