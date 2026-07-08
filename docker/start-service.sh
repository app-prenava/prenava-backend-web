#!/bin/bash

# Wait for MySQL to be ready (max 60 seconds)
MAX_RETRIES=20
RETRY_COUNT=0

echo "Checking database connection..."
until php artisan db:show 2>/dev/null; do
  RETRY_COUNT=$((RETRY_COUNT + 1))
  if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
    echo "⚠️  WARNING: Database not reachable after ${MAX_RETRIES} attempts. Starting server anyway..."
    break
  fi
  echo "Waiting for database connection... (attempt ${RETRY_COUNT}/${MAX_RETRIES})"
  sleep 3
done

# Run migrations only if DB is actually reachable
if php artisan db:show 2>/dev/null; then
  echo "Database is ready! Running migrations..."
  php artisan migrate --force || echo "⚠️  Migration failed, continuing..."
else
  echo "⚠️  Skipping migrations (database not available)"
fi

echo "Starting FrankenPHP..."

# Start FrankenPHP (this MUST always run)
exec frankenphp run --config /etc/caddy/Caddyfile

