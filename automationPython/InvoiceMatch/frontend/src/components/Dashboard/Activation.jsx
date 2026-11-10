import React, { useEffect, useState } from 'react';
import toast from 'react-hot-toast';
import api from '../../services/api';
import { useAuth } from '../../contexts/AuthContext';

const Activation = () => {
  const { company } = useAuth();

  const [paymentMethod, setPaymentMethod] = useState('mobile_money');
  const [phoneNumber, setPhoneNumber] = useState('');
  const [loading, setLoading] = useState(false);
  const [payment, setPayment] = useState(null);

  const initiatePayment = async () => {
    setLoading(true);

    try {
      const payload = {
        payment_method: paymentMethod,
      };

      if (phoneNumber.trim()) {
        payload.phone_number = phoneNumber.trim();
      }

      const response = await api.post('/payments/initiate/', payload);

      setPayment(response.data);

      toast.success(
        response.data.message || 'Payment initiated successfully.'
      );

      if (response.data.payment_url) {
        window.open(response.data.payment_url, '_blank');
      }
    } catch (error) {
      console.error('Payment initiation error:', error);

      toast.error(
        error.response?.data?.error ||
        'Unable to initiate payment.'
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!payment?.reference) {
      return;
    }

    const interval = setInterval(async () => {
      try {
        const response = await api.get(
          \/payments/status/\/\
        );

        setPayment((previous) => ({
          ...previous,
          ...response.data,
        }));

        if (response.data.is_completed) {
          clearInterval(interval);
          toast.success(
            'Payment completed. Your account is now active.'
          );
        }
      } catch (error) {
        console.error('Payment status error:', error);
      }
    }, 5000);

    return () => clearInterval(interval);
  }, [payment?.reference]);

  if (company?.is_active) {
    return (
      <div className="settings-card">
        <h2>Account Activated</h2>
        <p>
          Micronet Computers Ltd. is already activated.
        </p>
      </div>
    );
  }

  return (
    <div className="settings-card">
      <div className="settings-header">
        <h2>Activate MICRONET AP Automation</h2>
        <p>
          Activate your account for UGX 500,000.
        </p>
      </div>

      <div className="form-group">
        <label htmlFor="payment-method">
          Payment Method
        </label>

        <select
          id="payment-method"
          value={paymentMethod}
          onChange={(e) => setPaymentMethod(e.target.value)}
        >
          <option value="mobile_money">
            Mobile Money
          </option>
          <option value="card">
            Card
          </option>
        </select>
      </div>

      {paymentMethod === 'mobile_money' && (
        <div className="form-group">
          <label htmlFor="phone-number">
            Mobile Money Number
          </label>

          <input
            id="phone-number"
            type="tel"
            value={phoneNumber}
            onChange={(e) => setPhoneNumber(e.target.value)}
            placeholder="2567XXXXXXXX"
          />
        </div>
      )}

      <button
        type="button"
        className="primary-button"
        onClick={initiatePayment}
        disabled={loading}
      >
        {loading
          ? 'Starting payment...'
          : 'Pay UGX 500,000 & Activate'}
      </button>

      {payment?.reference && (
        <div className="connection-status">
          <strong>Payment Reference:</strong>{' '}
          {payment.reference}

          <br />

          <strong>Status:</strong>{' '}
          {payment.status || 'pending'}
        </div>
      )}
    </div>
  );
};

export default Activation;
