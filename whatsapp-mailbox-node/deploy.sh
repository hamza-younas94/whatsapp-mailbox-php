#!/bin/bash

# Safe deployment script that preserves WhatsApp session data

set -e

echo "Pulling latest code..."
git pull

echo "Building new image..."
docker compose build

echo "Stopping containers (but keeping volumes)..."
docker compose stop

echo "Starting containers..."
docker compose up -d

echo "Waiting for app to start..."
sleep 5

echo "Checking health..."
docker compose ps

echo ""
echo "✅ Deployed! WhatsApp session should be preserved."
echo "If WhatsApp is still connected, you can use it immediately."
echo "Otherwise, go to QR Connect page to reconnect."
