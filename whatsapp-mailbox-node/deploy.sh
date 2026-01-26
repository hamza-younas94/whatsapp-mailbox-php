#!/bin/bash

# Safe deployment script that preserves WhatsApp session data

set -e

echo "Pulling latest code..."
git pull

echo "Building new image (code changes only)..."
docker compose build --no-cache app

echo "Updating containers without recreating them..."
docker compose up -d --no-recreate

echo "Waiting for app to start..."
sleep 3

echo "Checking health..."
docker compose ps

echo ""
echo "✅ Deployed! WhatsApp session preserved."
echo "If WhatsApp was connected before, it should still be connected."
