#!/bin/bash
# start.sh — Starts the Nitro Worker and the Web Server together.

echo "--- Starting Nitro Worker in background ---"
php worker.php &

echo "--- Starting Web Server ---"
# Check if we should use php built-in server or just let the environment handle it.
# Most Railway PHP apps use the php -S command from Procfile.
php -S 0.0.0.0:$PORT
