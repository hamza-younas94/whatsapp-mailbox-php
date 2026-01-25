<?php
// Simple page to link a device via WhatsApp Web bridge (non-intrusive)
require 'bootstrap.php';

// Use existing auth when available; otherwise allow ?user_id= for unauthenticated use (e.g., public QR page)
$userId = null;
if (function_exists('auth_required')) {
    $user = auth_required();
    $userId = $user->id;
} else {
    $userId = intval($_GET['user_id'] ?? 0);
    if (!$userId) {
        die('user_id required');
    }
}
// On cPanel Node.js app, set WHATSAPP_WEB_SERVICE_URL to the app's URL path (e.g., /whatsappweb)
// Fallbacks:
// - If running locally: http://localhost:4000
// - If deployed under same domain path: /whatsappweb
$serviceUrl = getenv('WHATSAPP_WEB_SERVICE_URL') ?: '/whatsappweb';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Link WhatsApp Device</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: Inter, system-ui, -apple-system, sans-serif; background:#f7fafc; }
    .container { max-width: 720px; margin: 40px auto; background:#fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); padding: 24px; }
    .row { display:flex; gap:24px; align-items:flex-start; }
    .qr { width: 280px; height: 280px; border: 1px solid #e5e7eb; border-radius: 12px; display:flex; align-items:center; justify-content:center; background:#fafafa; }
    img { max-width:100%; border-radius: 8px; }
    .status { padding: 8px 12px; border-radius: 8px; background:#eef2ff; color:#1e40af; display:inline-block; }
    .btn { padding:10px 14px; border:none; border-radius:8px; background:#10b981; color:#fff; cursor:pointer; }
    .btn.secondary { background:#374151; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Link WhatsApp Device (QR)</h2>
    <p>This QR login uses the optional WhatsApp Web bridge and does not affect your existing mailbox/Cloud API number.</p>

    <div class="row">
      <div>
        <div class="qr" id="qrBox">QR not generated</div>
        <div style="margin-top:12px;">
          <button class="btn" onclick="startSession()">Generate QR</button>
          <button class="btn secondary" onclick="checkStatus()">Check Status</button>
        </div>
      </div>
      <div style="flex:1;">
        <div class="status" id="statusBox">Status: unknown</div>
        <p style="margin-top:12px;">Steps:
          <ol>
            <li>Click Generate QR</li>
            <li>Open WhatsApp → Linked devices → Scan QR</li>
            <li>On success, status becomes ready/authenticated</li>
          </ol>
        </p>
      </div>
    </div>
  </div>

<script>
const serviceUrl = '<?= htmlspecialchars($serviceUrl, ENT_QUOTES) ?>';
const userId = <?= json_encode($userId) ?>;

async function startSession() {
  const res = await fetch(serviceUrl + '/session/start', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ userId })
  });
  const data = await res.json();
  document.getElementById('statusBox').textContent = 'Status: ' + (data.status || 'unknown');
  if (data.qr) {
    document.getElementById('qrBox').innerHTML = '<img src="' + data.qr + '" alt="QR">';
  }
}

async function checkStatus() {
  const res = await fetch(serviceUrl + '/session/' + userId + '/status');
  const data = await res.json();
  document.getElementById('statusBox').textContent = 'Status: ' + (data.status || 'unknown');
  if (data.status === 'ready' || data.status === 'authenticated') {
    document.getElementById('qrBox').textContent = 'Device linked';
  }
}
</script>
</body>
</html>
