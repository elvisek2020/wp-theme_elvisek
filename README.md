# elvisek.cz — šablona a vývoj

| Složka | Obsah |
|---|---|
| `theme/elvisek/` | šablona ElvisEK (viz její README) |
| `mu-plugins/elvisek-core.php` | funkce nezávislé na šabloně — na produkci do `wp-content/mu-plugins/` |
| `mu-plugins/elvisek-dev-tools.php` | **jen lokální vývoj, nenasazovat** |
| `dev/` | lokální WordPress v Dockeru (`dev/README.md`) |
| `docs/` | plán a wishlist |

## Vydání nové verze šablony
1. Zvýšit `Version:` v `theme/elvisek/style.css` (např. `0.2.0`).
2. `git commit -am "…" && git tag v0.2.0 && git push --follow-tags`
3. GitHub Action sestaví `elvisek.zip` a vytvoří Release.
4. Ve WordPressu: Nástěnka → Aktualizace → aktualizovat šablonu ElvisEK.

První instalace na web: stáhnout `elvisek.zip` z Releases → Vzhled → Motivy → Přidat → Nahrát.
