# elvisek.cz — šablona a vývoj

| Složka | Obsah |
|---|---|
| `theme/elvisek/` | šablona ElvisEK (viz její README) |
| `plugins/elvisek-core/` | plugin ElvisEK Core — funkce nezávislé na šabloně, nástroje pro přechod a údržbu |
| `mu-plugins/elvisek-dev-tools.php` | **jen lokální vývoj, nenasazovat** |
| `dev/` | lokální WordPress v Dockeru (`dev/README.md`) |
| `docs/` | plán a wishlist |

## Vydání nové verze šablony
Šablona i plugin mají **společnou verzi**.

1. Zvýšit `Version:` v `theme/elvisek/style.css` **i** v `plugins/elvisek-core/elvisek-core.php` (např. `0.4.0`).
2. `git add -A && git commit -m "…" && git tag -a v0.4.0 -m "0.4.0" && git push --follow-tags`
   (tag musí být **anotovaný** `-a`, jinak ho `--follow-tags` nepošle; případně `git push origin v0.4.0`)
3. GitHub Action zkontroluje verze a PHP, sestaví `elvisek.zip` + `elvisek-core.zip` a vytvoří Release.
4. Ve WordPressu: Nástěnka → Aktualizace → *Zkontrolovat znovu* → aktualizovat šablonu i plugin.

První instalace: stáhnout ZIPy z Releases → Vzhled → Motivy → Přidat → Nahrát (`elvisek.zip`), Pluginy → Přidat → Nahrát (`elvisek-core.zip`).
