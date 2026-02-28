#!/bin/bash
set -e

echo "Deploying application..."

# Pull the latest changes from GitHub
git reset --hard
git pull origin master

# Build and start the containers in detached mode
# (Will recreate the 'app' container with the new code)
docker compose -f docker-compose.yml up -d --build app

# Run composer install, config cache, and migrations inside the running app container
docker compose -f docker-compose.yml exec -T app composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.yml exec -T app php artisan config:cache
docker compose -f docker-compose.yml exec -T app php artisan route:cache
docker compose -f docker-compose.yml exec -T app php artisan view:cache
docker compose -f docker-compose.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.yml exec -T app php artisan storage:link

echo "Deployment finished!"
