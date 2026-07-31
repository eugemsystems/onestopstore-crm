#!/bin/sh
# Runs at container START (not build time). The Dockerfile's own chown/chmod
# on storage/ and bootstrap/cache only takes effect on the image's baked-in
# filesystem — in both local and production compose, ./onestopstore-crm is
# bind-mounted straight over /var/www/html, which replaces that with the
# HOST's actual file ownership (whatever UID owns the repo on the host).
# Without this, www-data can't write logs/cache/sessions and Laravel dies
# with an unrecoverable, unlogged error.
set -e

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

exec "$@"
