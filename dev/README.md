# Lokální vývoj

Požadavek: Docker (Docker Desktop / OrbStack).

```bash
cd dev
./setup.sh              # první spuštění (import DB + přepis URL)
docker compose up -d    # další spuštění
docker compose down     # zastavení (data zůstanou)
docker compose down -v  # smazání DB → příští setup.sh naimportuje znovu
docker compose run --rm cli wp <příkaz>   # WP-CLI
```

- Web: http://localhost:8080
- Šablona se vyvíjí v `../theme/elvisek` (připojeno do kontejneru, změny jsou vidět hned).
- mu-pluginy v `../mu-plugins`.
- `wp/` je kopie webrootu ze zálohy 2026-09-30, `db/` je upravený dump (datumy převedené z hex).
- Debug log: `wp/wp-content/debug.log`.
