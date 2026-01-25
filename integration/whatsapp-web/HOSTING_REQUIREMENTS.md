# WhatsApp Web Bridge - Hosting Requirements

## ⚠️ Important: Shared Hosting Limitation

The WhatsApp Web bridge **CANNOT run on shared hosting** (cPanel, Plesk, etc.) because it requires:

1. **System libraries** for Chrome/Chromium:
   - `libatk-bridge-2.0.so.0`
   - `libgtk-3.so.0`
   - `libasound.so.2`
   - `libgbm.so.1`
   - And many others...

2. **Full system access** to install these libraries (requires root/sudo)

## ✅ Supported Hosting Options

### Option 1: VPS/Dedicated Server (Recommended)
- DigitalOcean Droplet ($6/month)
- Linode
- Vultr
- AWS EC2 (t2.micro free tier)
- Any Ubuntu/Debian server with root access

**Setup:**
```bash
# Install system dependencies (Ubuntu/Debian)
sudo apt-get update
sudo apt-get install -y \
  chromium-browser \
  libatk-bridge2.0-0 \
  libgtk-3-0 \
  libasound2 \
  libgbm1

# Then install and run the bridge
cd integration/whatsapp-web
npm install
npm start
```

### Option 2: Cloud Platform Services
- **Railway.app** (Free tier available) - Easiest deployment
- **Render.com** (Free tier available)
- **Fly.io**
- **Heroku** (Paid)

These platforms handle system dependencies automatically.

### Option 3: Cloud Browser Service
Use Browserless.io or similar service:
```javascript
// Update server.cjs puppeteer config:
puppeteer: {
  browserWSEndpoint: 'wss://chrome.browserless.io?token=YOUR_TOKEN'
}
```

## 🎯 Recommendation for Your Setup

**Keep using Cloud API on shared hosting** - it's already working perfectly!

The WhatsApp Web bridge is optional and only needed if you want to:
- Link a personal WhatsApp account
- Use WhatsApp without Business API verification

For most production use cases, **Cloud API is the better choice** because:
- ✅ More reliable
- ✅ Better for business use
- ✅ Official Meta support
- ✅ Works on any hosting
- ✅ No browser/Chromium dependencies

## Alternative: Deploy Bridge Separately

If you really need the Web bridge:

1. Deploy the Node bridge to Railway/Render (free)
2. Set the service URL in your PHP app:
   ```
   WHATSAPP_WEB_SERVICE_URL=https://your-bridge.railway.app
   ```
3. Keep your PHP app on shared hosting

This gives you the best of both worlds!
