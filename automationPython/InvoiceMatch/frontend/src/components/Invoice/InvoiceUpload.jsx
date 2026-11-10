import React, { useState } from 'react'
import { useAuth } from '../../contexts/AuthContext'
import api from '../../services/api'
import toast from 'react-hot-toast'

const InvoiceUpload = () => {
  const { company } = useAuth()
  const [file, setFile] = useState(null)
  const [uploading, setUploading] = useState(false)
  const [progress, setProgress] = useState(0)

  const handleFileChange = (e) => {
    const selected = e.target.files[0]
    if (selected && selected.type === 'application/pdf') {
      setFile(selected)
    } else {
      toast.error('Please select a PDF file')
      e.target.value = ''
    }
  }

  const handleUpload = async (e) => {
    e.preventDefault()
    if (!file) {
      toast.error('Please select a file')
      return
    }

    if (!company?.is_active) {
      toast.error('Account is not activated')
      return
    }

    setUploading(true)
    setProgress(0)

    const formData = new FormData()
    formData.append('file', file)

    try {
      const response = await api.post('/invoices/upload/', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (event) => {
          const percent = Math.round((event.loaded * 100) / event.total)
          setProgress(percent)
        }
      })

      toast.success('Invoice uploaded successfully!')
      setFile(null)
      setProgress(0)
      document.getElementById('file-input').value = ''
    } catch (error) {
      toast.error(error.response?.data?.error || 'Upload failed')
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="max-w-3xl mx-auto px-4 py-8">
      <div className="card">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Upload Invoice</h1>
        <p className="text-gray-600 mb-6">Upload a supplier invoice PDF for processing</p>

        <form onSubmit={handleUpload} className="space-y-6">
          <div
            className={\order-2 border-dashed rounded-lg p-12 text-center transition-colors \\}
          >
            <input
              id="file-input"
              type="file"
              accept=".pdf"
              onChange={handleFileChange}
              className="hidden"
            />
            <label htmlFor="file-input" className="cursor-pointer block">
              <div className="text-4xl mb-4">📄</div>
              <p className="text-lg font-medium text-gray-700">
                {file ? file.name : 'Drop your invoice PDF here'}
              </p>
              <p className="text-sm text-gray-500 mt-2">
                {file ? \\ KB\ : 'or click to browse'}
              </p>
              <p className="text-xs text-gray-400 mt-4">Maximum file size: 10MB</p>
            </label>
          </div>

          {uploading && (
            <div className="space-y-2">
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div className="bg-indigo-600 h-2 rounded-full transition-all" style={{ width: \\%\ }}></div>
              </div>
              <p className="text-sm text-gray-600 text-center">{progress}% uploaded</p>
            </div>
          )}

          <button
            type="submit"
            disabled={!file || uploading}
            className="w-full btn-primary"
          >
            {uploading ? 'Uploading...' : 'Upload Invoice'}
          </button>
        </form>
      </div>
    </div>
  )
}

export default InvoiceUpload
