import React, { useState, useEffect } from 'react';
import { subscribeToSessionStatus } from '@/api/socket';
import { sessionAPI } from '@/api/queries';
import '@/styles/session-status.css';

type SessionState = 'CONNECTED' | 'CONNECTING' | 'DISCONNECTED' | 'QR_READY' | 'UNKNOWN';

interface QRData {
  qr: string;
  sessionId: string;
}

interface SessionStatusProps {
  onQRRequired?: (qrData: QRData) => void;
}

const SessionStatus: React.FC<SessionStatusProps> = ({ onQRRequired }) => {
  const [status, setStatus] = useState<SessionState>('UNKNOWN');
  const [showQR, setShowQR] = useState(false);
  const [qrData, setQRData] = useState<QRData | null>(null);
  const [message, setMessage] = useState('Initializing...');
  const [isInitializing, setIsInitializing] = useState(false);

  // Auto-initialize session on component mount
  useEffect(() => {
    const initializeSession = async () => {
      if (isInitializing) return;
      
      try {
        setIsInitializing(true);
        setMessage('Starting WhatsApp session...');
        console.log('Auto-initializing WhatsApp session...');
        
        const result = await sessionAPI.initializeSession();
        console.log('Session initialization result:', result);
        
        // Session initialization will emit socket events
        // Let the socket subscription handle the status updates
      } catch (error: any) {
        console.error('Failed to initialize session:', error);
        // If initialization fails, let user manually reconnect
        setStatus('DISCONNECTED');
        setMessage('Failed to initialize session. Click reconnect to try again.');
      } finally {
        setIsInitializing(false);
      }
    };

    // Check if auth token exists before trying to initialize
    const token = localStorage.getItem('authToken');
    if (token) {
      initializeSession();
    } else {
      setMessage('Please log in first');
      setStatus('DISCONNECTED');
    }
  }, [isInitializing]);

  // Subscribe to session status updates via socket
  useEffect(() => {
    const unsubscribe = subscribeToSessionStatus((data) => {
      const newStatus = (data.status || 'UNKNOWN') as SessionState;
      setStatus(newStatus);

      switch (newStatus) {
        case 'CONNECTED':
          setMessage('Connected to WhatsApp');
          setShowQR(false);
          break;
        case 'CONNECTING':
          setMessage('Connecting to WhatsApp...');
          setShowQR(false);
          break;
        case 'QR_READY':
          setMessage('Scan QR code to connect');
          setQRData(data);
          setShowQR(true);
          onQRRequired?.(data);
          break;
        case 'DISCONNECTED':
          setMessage('Disconnected from WhatsApp');
          setShowQR(false);
          break;
        default:
          setMessage('Loading...');
          setShowQR(false);
      }
    });

    return unsubscribe;
  }, [onQRRequired]);

  const handleReconnect = async () => {
    try {
      setIsInitializing(true);
      setMessage('Reconnecting to WhatsApp...');
      const result = await sessionAPI.initializeSession();
      console.log('Reconnection result:', result);
    } catch (error) {
      console.error('Failed to reconnect:', error);
      setMessage('Failed to reconnect. Reloading page...');
      setTimeout(() => window.location.reload(), 2000);
    } finally {
      setIsInitializing(false);
    }
  };

  const statusClass = status.toLowerCase();

  return (
    <div className={`session-status status-${statusClass}`}>
      <div className="status-content">
        <div className="status-indicator">
          <div className="status-dot"></div>
          <span className="status-text">{message}</span>
        </div>

        {status === 'DISCONNECTED' && (
          <button className="reconnect-btn" onClick={handleReconnect} disabled={isInitializing}>
            {isInitializing ? 'Reconnecting...' : 'Reconnect'}
          </button>
        )}
      </div>

      {showQR && qrData && (
        <div className="qr-modal-overlay" onClick={() => setShowQR(false)}>
          <div className="qr-modal" onClick={(e) => e.stopPropagation()}>
            <h3>Scan QR Code</h3>
            <p>Open WhatsApp on your phone and scan this QR code to connect your account.</p>
            <div className="qr-container">
              <img src={`data:image/png;base64,${qrData.qr}`} alt="QR Code" className="qr-image" />
            </div>
            <p className="qr-helper-text">Make sure your phone is connected to the internet</p>
            <button className="close-btn" onClick={() => setShowQR(false)}>
              Close
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default SessionStatus;
