#!/bin/bash
# Tidak pakai set -e agar script tidak berhenti total saat seeder gagal

echo "🚀 Deploying application..."

# 1. Pull the latest changes from GitHub
echo "📥 Pulling latest code..."
git reset --hard
git pull origin master || { echo "❌ Git pull failed!"; exit 1; }

# 2. Build dan start SEMUA container
echo "🔨 Building and starting containers..."
docker compose up -d --build || { echo "❌ Docker build failed!"; exit 1; }

# 3. Tunggu sampai container app benar-benar sehat dan siap
echo "⏳ Waiting for app container to be ready (30s)..."
sleep 30

# 4. Verifikasi container berjalan
echo "🔍 Checking container status..."
docker compose ps

# 5. Run database migrations
echo "🗄️ Running database migrations..."
docker compose exec -T app php artisan migrate --force || echo "⚠️  Migration failed (check manually)"

# 6. Cache config, route, dan view
echo "⚡ Caching configuration..."
docker compose exec -T app php artisan config:cache || echo "⚠️  Config cache failed (non-critical)"
docker compose exec -T app php artisan route:cache || echo "⚠️  Route cache failed (non-critical)"
docker compose exec -T app php artisan view:cache || echo "⚠️  View cache failed (non-critical)"

# 6. Seeders (non-critical, jangan hentikan deploy jika gagal)
echo "🌱 Seeding research data..."
docker compose exec -T app php artisan db:seed --class=LocalWisdomSeeder --force 2>&1 || echo "⚠️  LocalWisdomSeeder failed (non-critical)"
docker compose exec -T app php artisan db:seed --class=DailyTaskSeeder --force 2>&1 || echo "⚠️  DailyTaskSeeder failed (non-critical)"

# 7. Storage link
docker compose exec -T app php artisan storage:link 2>/dev/null || true

echo ""
echo "✅ Deployment finished!"
echo "📊 Final container status:"
docker compose ps