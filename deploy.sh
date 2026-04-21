#!/bin/bash
set -e

echo "Deploying application..."

# Pull the latest changes from GitHub
git reset --hard
git pull origin master

# Build and start the containers in detached mode
docker compose -f docker-compose.yml up -d --build app

# Run composer install, config cache, and migrations inside the running app container
docker compose -f docker-compose.yml exec -T app composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.yml exec -T app php artisan config:cache
docker compose -f docker-compose.yml exec -T app php artisan route:cache
docker compose -f docker-compose.yml exec -T app php artisan view:cache
docker compose -f docker-compose.yml exec -T app php artisan migrate --force

# Seeders for research data (Tugas Akhir)
echo "Seeding research data..."
docker compose -f docker-compose.yml exec -T app php artisan db:seed --class=LocalWisdomSeeder --force
docker compose -f docker-compose.yml exec -T app php artisan db:seed --class=DailyTaskSeeder --force

docker compose -f docker-compose.yml exec -T app php artisan storage:link

echo "Deployment finished!"