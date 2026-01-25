// WhatsApp Web Bridge (QR login) - Non-intrusive optional channel
// Runs independently; exposes REST endpoints; posts incoming events to PHP webhook
// Requires: Node 18+, npm packages: whatsapp-web.js, express, qrcode
// CommonJS (CJS) - compatible with cPanel/Passenger

const express = require('express');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');

// On cPanel/Passenger, PORT is injected by Passenger. Locally defaults to 4000.
const PORT = process.env.PORT || 4000;
// WEBHOOK_URL must point to your PHP app's webhook_web.php
// On cPanel: set via Application Manager → Environment Variables
const WEBHOOK_URL = process.env.WEBHOOK_URL || 'http://localhost/webhook_web.php';

// In-memory session registry by userId (for demo). For production, persist sessions.
const sessions = new Map();

function ensureSession(userId) {
  if (!userId) throw new Error('userId required');
  if (sessions.has(userId)) return sessions.get(userId);

  const client = new Client({
    authStrategy: new LocalAuth({ clientId: String(userId) }),
    puppeteer: {
      headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
    },
  });

  const state = {
    client,
    status: 'initializing',
    qrDataUrl: null,
    connectedNumber: null,
  };

  client.on('qr', async (qr) => {
    state.qrDataUrl = await qrcode.toDataURL(qr);
    state.status = 'qr';
    console.log(`[${userId}] QR generated`);
  });

  client.on('ready', async () => {
    state.status = 'ready';
    try {
      const info = await client.getState();
      console.log(`[${userId}] Client state:`, info);
    } catch (e) {}
  });

  client.on('authenticated', () => {
    state.status = 'authenticated';
    console.log(`[${userId}] Authenticated`);
  });

  client.on('disconnected', (reason) => {
    state.status = 'disconnected';
    console.log(`[${userId}] Disconnected:`, reason);
  });

  // Incoming messages -> post to PHP webhook
  client.on('message', async (msg) => {
    try {
      const payload = {
        user_id: userId,
        phone_number: msg.from,
        message: {
          message_id: msg.id._serialized,
          message_type: msg.type || 'chat',
          direction: 'incoming',
          message_body: msg.body || '',
          timestamp: new Date().toISOString(),
        },
      };
      // Use global fetch (Node 18+) to avoid ESM import issues on cPanel
      await fetch(WEBHOOK_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
    } catch (e) {
      console.error(`[${userId}] webhook post failed`, e.message);
    }
  });

  client.initialize();
  sessions.set(userId, state);
  return state;
}

const app = express();
app.use(express.json());

// Simple health/root endpoint so GET / works under Passenger
app.get('/', (req, res) => {
  res.json({
    status: 'ok',
    message: 'WhatsApp Web Bridge running',
    routes: [
      { method: 'POST', path: '/session/start', body: '{ userId }' },
      { method: 'GET', path: '/session/:userId/qr' },
      { method: 'GET', path: '/session/:userId/status' },
      { method: 'POST', path: '/message/send', body: '{ userId, to, text }' }
    ]
  });
});

app.get('/health', (req, res) => res.json({ ok: true }));

// Start or get session, return QR if needed
app.post('/session/start', async (req, res) => {
  try {
    const { userId } = req.body;
    const session = ensureSession(userId);
    res.json({ status: session.status, qr: session.qrDataUrl });
  } catch (e) {
    res.status(400).json({ error: e.message });
  }
});

// Get QR for current session
app.get('/session/:userId/qr', (req, res) => {
  const { userId } = req.params;
  const session = sessions.get(userId);
  if (!session) return res.status(404).json({ error: 'session not found' });
  res.json({ status: session.status, qr: session.qrDataUrl });
});

// Session status
app.get('/session/:userId/status', (req, res) => {
  const { userId } = req.params;
  const session = sessions.get(userId);
  if (!session) return res.status(404).json({ error: 'session not found' });
  res.json({ status: session.status });
});

// Send text message
app.post('/message/send', async (req, res) => {
  try {
    const { userId, to, text } = req.body;
    const session = sessions.get(userId) || ensureSession(userId);
    await session.client.sendMessage(to, text);
    res.json({ success: true });
  } catch (e) {
    res.status(500).json({ error: e.message });
  }
});

// Use Passenger's listening mechanism if available, otherwise listen directly
const server = app.listen(PORT, '0.0.0.0', () => {
  console.log(`[WhatsApp Web Bridge] Listening on port ${PORT}`);
});

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('[WhatsApp Web Bridge] SIGTERM received, shutting down gracefully...');
  server.close(() => {
    console.log('Server closed');
    process.exit(0);
  });
});
