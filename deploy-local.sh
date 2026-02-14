#!/bin/bash
#
# Local Build & Upload Script
# Run this on your Mac BEFORE uploading to server
#
# Usage: bash deploy-local.sh
#

set -e

echo "🔨 Building for production..."

# ─── Step 1: Install Node dependencies & build ────────────
echo "📦 Installing Node dependencies..."
npm install --legacy-peer-deps

echo "🏗️  Building Vite assets..."
npm run build

echo ""
echo "✅ Build complete! Assets are in public/build/"
echo ""
echo "📤 Now upload the ENTIRE project to your server:"
echo ""
echo "   Option A — Git (recommended):"
echo "   ssh user@yourserver"
echo "   cd /home/youruser/htdocs/portal.yourdomain.com"
echo "   git pull origin main"
echo "   bash deploy.sh"
echo ""
echo "   Option B — rsync:"
echo "   rsync -avz --exclude='node_modules' --exclude='.git' \\"
echo "     . user@yourserver:/home/youruser/htdocs/portal.yourdomain.com/"
echo "   ssh user@yourserver 'cd /home/youruser/htdocs/portal.yourdomain.com && bash deploy.sh'"
echo ""
