import React from 'react'

const ReconciliationResult = ({ invoice, poData, discrepancies, onClose }) => {
  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-bold text-gray-900">Reconciliation Result</h2>
          <button onClick={onClose} className="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg">
            <div>
              <p className="text-sm text-gray-500">Invoice Number</p>
              <p className="font-medium">{invoice?.invoice_number || '-'}</p>
            </div>
            <div>
              <p className="text-sm text-gray-500">PO Number</p>
              <p className="font-medium">{invoice?.po_number || '-'}</p>
            </div>
            <div>
              <p className="text-sm text-gray-500">Supplier</p>
              <p className="font-medium">{invoice?.supplier_name || '-'}</p>
            </div>
            <div>
              <p className="text-sm text-gray-500">Total Amount</p>
              <p className="font-medium">{invoice?.total_amount ? \UGX \\ : '-'}</p>
            </div>
          </div>

          {discrepancies && discrepancies.length > 0 ? (
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
              <h3 className="text-red-800 font-semibold mb-2">⚠ Discrepancies Found</h3>
              {discrepancies.map((d, i) => (
                <div key={i} className="text-sm text-red-700 mb-2">
                  <span className="font-medium">{d.field}:</span> {d.message}
                  <br />
                  <span className="text-xs text-red-600">Expected: {d.expected || 'N/A'} | Actual: {d.actual || 'N/A'}</span>
                </div>
              ))}
            </div>
          ) : (
            <div className="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
              <span className="text-2xl block mb-2">✅</span>
              <h3 className="text-green-800 font-semibold">All Matched!</h3>
              <p className="text-sm text-green-700">Invoice matches PO perfectly</p>
            </div>
          )}

          {poData && (
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h4 className="text-blue-800 font-semibold mb-2">PO Details</h4>
              <div className="grid grid-cols-2 gap-2 text-sm">
                <div>
                  <span className="text-gray-500">Supplier:</span>
                  <span className="ml-2">{poData.supplier_name || '-'}</span>
                </div>
                <div>
                  <span className="text-gray-500">Total:</span>
                  <span className="ml-2">{poData.total_amount || '-'}</span>
                </div>
              </div>
            </div>
          )}
        </div>

        <button onClick={onClose} className="mt-6 w-full btn-primary">Close</button>
      </div>
    </div>
  )
}

export default ReconciliationResult
