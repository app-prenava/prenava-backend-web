#!/bin/bash
set -e

echo "🚀 Deploying application..."

# 1. Pull the latest changes from GitHub
echo "📥 Pulling latest code..."
git reset --hard
git pull origin master

# 2. Build dan start SEMUA container (app, mysql, redis, phpmyadmin)
#    --build hanya rebuild image yang berubah, tanpa menghentikan mysql/redis
echo "🔨 Building and starting containers..."
docker compose up -d --build

# 3. Tunggu sampai container app benar-benar sehat dan siap
#    (start-service.sh di dalam container sudah menangani:
#     - menunggu MySQL ready
#     - menjalankan migrate
#     - menjalankan FrankenPHP)
echo "⏳ Waiting for app container to be ready..."
sleep 15

# 4. Verifikasi container berjalan
if ! docker compose ps --format json | grep -q '"nuri-backend-app"'; then
  echo "❌ App container failed to start! Checking logs..."
  docker compose logs --tail=50 app
  exit 1
fi

# 5. Cache config, route, dan view (ringan, aman dijalankan setelah app siap)
echo "⚡ Caching configuration..."
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

# 6. Seeders for research data (Tugas Akhir)
echo "🌱 Seeding research data..."
docker compose exec -T app php artisan db:seed --class=LocalWisdomSeeder --force
docker compose exec -T app php artisan db:seed --class=DailyTaskSeeder --force

# 7. Pastikan storage link aktif
docker compose exec -T app php artisan storage:link 2>/dev/null || true

echo "✅ Deployment finished successfully!"