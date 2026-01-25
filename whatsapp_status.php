<?php
// Diagnostic: Check server status and environment
// Visit: https://yourdomain.com/whatsapp_status.php

require 'bootstrap.php';

$user = auth_required();

$diagnostics = [
    'Node Service URL' => getenv('WHATSAPP_WEB_SERVICE_URL') ?: '/whatsappweb',
    'cPanel App Status' => 'Check cPanel Application Manager logs',
    'Server Requirements' => [
        'Node 18+' => 'Required',
        'npm install ran' => 'Check via SSH: ls integration/whatsapp-web/node_modules',
        'server.cjs exists' => 'Check: ls integration/whatsapp-web/server.cjs'
    ],
    'Troubleshooting' => [
        'Step 1' => 'SSH into cPanel and check app logs: tail -f /path/to/logs',
        'Step 2' => 'Verify: npm list in integration/whatsapp-web/ shows all deps installed',
        'Step 3' => 'Run manually: cd integration/whatsapp-web && node server.cjs',
        'Step 4' => 'Check WEBHOOK_URL environment variable in cPanel App Manager'
    ]
];

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>WhatsApp Web Bridge Diagnostic</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .section { margin: 20px 0; padding: 12px; background: #252526; border-left: 3px solid #0e639c; }
        h2 { color: #4ec9b0; }
        code { background: #1e1e1e; padding: 4px 8px; border-radius: 4px; color: #ce9178; }
    </style>
</head>
<body>
    <div class="container">
        <h1>WhatsApp Web Bridge - Diagnostic</h1>
        
        <?php foreach ($diagnostics as $key => $value): ?>
            <div class="section">
                <h2><?= $key ?></h2>
                <?php if (is_array($value)): ?>
                    <ul>
                        <?php foreach ($value as $k => $v): ?>
                            <li><strong><?= $k ?></strong>: <?= $v ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p><?= $value ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <div class="section">
            <h2>Next Steps</h2>
            <p>1. Check cPanel Application Manager → WhatsApp Web Bridge → View Logs</p>
            <p>2. Ensure <code>WEBHOOK_URL</code> is set to <code>https://yourdomain.com/webhook_web.php</code></p>
            <p>3. If still seeing "It works!", the app may not be initializing. Check npm dependencies.</p>
            <p>4. Test locally: <code>cd integration/whatsapp-web && npm install && node server.cjs</code></p>
        </div>
    </div>
</body>
</html>
