#!/bin/bash
# Runtime bootstrap for the SendLock container.
#
# Runs on every pod start, after the real environment (ConfigMap + Secret) is
# injected — which is why config caching lives here and not in the image build.
set -euo pipefail

cd /opt/app-root/src

echo "==> Caching configuration for this environment"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optional: run migrations on boot instead of via the migrate Job. Off by
# default — with >1 replica the Job (openshift/migrate-job.yaml) is safer.
if [[ "${RUN_MIGRATIONS_ON_STARTUP:-false}" == "true" ]]; then
    echo "==> Running database migrations"
    php artisan migrate --force
fi

echo "==> Starting Apache (s2i run)"
exec /usr/libexec/s2i/run
