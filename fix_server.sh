#!/bin/bash

echo "=========================================="
echo "🔍 CHECKING SERVER FILES"
echo "=========================================="
echo ""

# Set the correct path
cd /home/pakmfguk/whatsapp.nexofydigital.com || exit 1

echo "📂 Current directory: $(pwd)"
echo ""

echo "1️⃣ Checking current git commit..."
git log -1 --oneline
echo ""

echo "2️⃣ Checking base.html.twig (should have 'assets/css/style.css' NOT full URL)..."
echo "Current content:"
grep -n "stylesheet" templates/base.html.twig
echo ""

echo "3️⃣ Checking what files git thinks are modified..."
git status --short
echo ""

echo "=========================================="
echo "🔧 FIXING FILES NOW"
echo "=========================================="
echo ""

echo "4️⃣ Fetching latest from GitHub..."
git fetch origin

echo ""
echo "5️⃣ Forcing reset to match GitHub (this WILL overwrite local changes)..."
git reset --hard origin/main

echo ""
echo "6️⃣ Verifying fix was applied..."
echo "base.html.twig now has:"
grep -n "stylesheet" templates/base.html.twig
echo ""

echo "7️⃣ Current git commit after fix:"
git log -1 --oneline
echo ""

echo "=========================================="
echo "✅ VERIFICATION"
echo "=========================================="
echo ""

# Check if the fix is correct
if grep -q 'href="assets/css/style.css' templates/base.html.twig; then
    echo "✅ SUCCESS! base.html.twig now uses relative path 'assets/css/style.css'"
    echo ""
    echo "🎯 Next step: Hard refresh your browser (Cmd+Shift+R)"
    echo "   The CRM Dashboard will now load CSS properly!"
else
    echo "❌ FAILED! Still showing full URL path"
    echo ""
    echo "Showing full base.html.twig content:"
    cat templates/base.html.twig
fi

echo ""
echo "=========================================="
echo "Done!"
echo "=========================================="
