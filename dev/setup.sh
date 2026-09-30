#!/usr/bin/env bash
# Jednorázové spuštění lokálního webu z dnešní zálohy.
set -euo pipefail
cd "$(dirname "$0")"

echo "▶ Startuju DB a WordPress…"
docker compose up -d

echo "▶ Čekám na import databáze…"
until docker compose exec -T db mariadb -uwp -pwp elvisek -e "SELECT 1 FROM wp_options LIMIT 1" >/dev/null 2>&1; do sleep 3; done

WP="docker compose run --rm cli wp"
echo "▶ Přepisuju URL na localhost…"
$WP search-replace 'https://www.elvisek.cz' 'http://localhost:8080' --all-tables --skip-columns=guid --report-changed-only
$WP search-replace 'http://www.elvisek.cz' 'http://localhost:8080' --all-tables --skip-columns=guid --report-changed-only

echo "▶ Vypínám pluginy, které lokálně nechceme (ManageWP)…"
$WP plugin deactivate worker || true
$WP cache flush || true

echo
echo "✅ Hotovo: http://localhost:8080  (admin: http://localhost:8080/wp-admin, stejné přihlášení jako na produkci)"
