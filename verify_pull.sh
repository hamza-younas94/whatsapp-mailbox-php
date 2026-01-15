#!/bin/bash

echo "=== Verifying Git Pull and Clearing Cache ==="
echo ""

echo "1. Checking git status..."
git status

echo ""
echo "2. Checking last commit..."
git log -1 --oneline

echo ""
echo "3. Searching for new log statements in WhatsAppService.php..."
grep -n "Found .* active quick replies" app/Services/WhatsAppService.php

echo ""
echo "4. Clearing PHP opcache..."
# Method 1: Clear opcache cache files if using file-based cache
if [ -d "/tmp/opcache" ]; then
    rm -rf /tmp/opcache/*
    echo "✅ Cleared /tmp/opcache"
fi

# Method 2: Touch PHP files to invalidate opcache
touch app/Services/WhatsAppService.php
echo "✅ Touched WhatsAppService.php to invalidate cache"

# Method 3: Clear Laravel cache if exists
if [ -f "clear_cache.php" ]; then
    php clear_cache.php
    echo "✅ Ran clear_cache.php"
fi

echo ""
echo "5. Verifying the actual line that should be in the file..."
sed -n '842,843p' app/Services/WhatsAppService.php

echo ""
echo "✅ Done! Try sending a test message now."
