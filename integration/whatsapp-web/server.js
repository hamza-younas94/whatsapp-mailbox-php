// WhatsApp Web Bridge (QR login) - Non-intrusive optional channel
// Runs independently; exposes REST endpoints; posts incoming events to PHP webhook
// Requires: Node 18+, npm packages: whatsapp-web.js, express, qrcode

const express = require('express');
const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');

// On cPanel/Passenger, PORT is injected. Locally defaults to 4000.
const PORT = process.env.PORT || 4000;
// Point to your PHP app webhook. On shared hosting, same domain path works.
const WEBHOOK_URL = process.env.WEBHOOK_URL || (process.env.BASE_URL ? `${process.env.BASE_URL}/webhook_web.php` : 'http://localhost/webhook_web.php');

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

app.listen(PORT, () => {
  console.log(`WhatsApp Web bridge listening on :${PORT}`);
});
