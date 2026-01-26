# Deploy WhatsApp Web Bridge to Railway (Free)

Railway.app provides free hosting with all required system dependencies for Puppeteer/Chrome.

## 🚀 Quick Deploy (5 minutes)

### Step 1: Create Railway Account
1. Go to https://railway.app
2. Sign up with GitHub (free)

### Step 2: Deploy This Service
1. Click **"New Project"**
2. Select **"Deploy from GitHub repo"**
3. Connect your GitHub account
4. Select this repository: `hamza-younas94/whatsapp-mailbox-php`
5. Railway will detect the Node.js app

### Step 3: Configure Root Directory
Since the bridge is in a subdirectory:
1. In Railway project settings, click **"Settings"**
2. Under **"Build"**, set:
   - **Root Directory**: `integration/whatsapp-web`
   - **Build Command**: `npm ci`
   - **Start Command**: `node server.cjs`

### Step 4: Set Environment Variable
1. Go to **"Variables"** tab
2. Add variable:
   - **Name**: `WEBHOOK_URL`
   - **Value**: `https://messagehub.nexofydigital.com/webhook_web.php`
3. Click **"Deploy"**

### Step 5: Get Your Service URL
1. Railway will generate a URL like: `your-app.railway.app`
2. Copy this URL

### Step 6: Update Your PHP App
On your shared hosting, update `.env`:
```env
WHATSAPP_WEB_SERVICE_URL=https://your-app.railway.app
```

That's it! The QR linking will now work.

## 🧪 Test the Deployment

Visit: `https://your-app.railway.app/health`

Should return:
```json
{"ok": true, "node": "v24.6.0"}
```

## 💰 Cost
- **Free tier**: 500 hours/month (enough for 24/7 uptime)
- **No credit card required**

## 🔄 Auto-Deploy
Every time you push to GitHub, Railway automatically redeploys!

## 📊 Monitor Your Service
- **Logs**: Railway dashboard → Deployments → Logs
- **Metrics**: CPU, memory, network usage
- **Uptime**: Railway keeps it running 24/7

## Alternative: Render.com

If you prefer Render:
1. Go to https://render.com
2. New → Web Service
3. Connect GitHub repo
4. Root Directory: `integration/whatsapp-web`
5. Build Command: `npm ci`
6. Start Command: `node server.cjs`
7. Environment: Add `WEBHOOK_URL`

Free tier includes 750 hours/month.
