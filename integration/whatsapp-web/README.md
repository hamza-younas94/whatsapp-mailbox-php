# WhatsApp Web Bridge (QR Login)

This optional microservice lets you link a regular WhatsApp device via QR and automate sending/receiving without touching your existing Mailbox (Cloud API).

- Runs separately (Node.js) on `http://localhost:4000` by default
- Exposes REST endpoints for session start/QR/status and sending
- Posts incoming messages to `webhook_web.php` in this PHP app
- No changes to existing Cloud API flows unless you explicitly choose to use this channel

## Quick Start

```sh
cd integration/whatsapp-web
npm install
# optional locally: node server.js
# on cPanel: use Application Manager → Create Node.js app (Passenger)
```

## Endpoints

- POST `/session/start` { userId } → `{ status, qr }`
- GET  `/session/:userId/qr` → `{ status, qr }`
- GET  `/session/:userId/status` → `{ status }`
- POST `/message/send` { userId, to, text } → send text

## How it stays non-intrusive

- Separate process and port
- New webhook `webhook_web.php` (does not affect Meta webhook)
- You choose which contacts/messages to send via Cloud API or this Web bridge

## Linking your phone

1. Open `link_device.php` in the PHP app
2. Click "Generate QR" → scan with WhatsApp → Done
3. Status updates show connected/disconnected

## Switching send path
## Namecheap cPanel (Passenger) Deployment

1. Compress and upload `integration/whatsapp-web/` to your home directory (or deploy via Git).
2. In cPanel → Application Manager → Create Node.js App:
	- Application root: `integration/whatsapp-web`
	- Application URL: `/whatsappweb` (or any path you prefer)
	- Startup file: `server.js`
	- Node.js version: 18+
3. Click "Create" then "Run NPM Install" in the app panel (or SSH: `npm install`).
4. Environment variables (App Manager → Edit):
	- `WEBHOOK_URL=https://yourdomain.com/webhook_web.php`
	- Optionally `BASE_URL=https://yourdomain.com`
5. Restart the app.
6. In your PHP app, set `WHATSAPP_WEB_SERVICE_URL=/whatsappweb` (default already).
7. Open `link_device.php`, click Generate QR, and scan in WhatsApp → Linked devices.

Notes:
- Passenger handles the port; your app is served under the URL path you select.
- LocalAuth stores session under `.wwebjs_auth` in the app root. Ensure write permissions.
- No changes to your Cloud API webhook; the bridge posts to `webhook_web.php` only.

By default, your mailbox continues to use Cloud API. For automation use-cases, call the new endpoint `api.php/web-send` to send via the bridge for specific messages/flows.
