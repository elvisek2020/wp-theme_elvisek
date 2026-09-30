# Šablona ElvisEK

Vlastní šablona pro www.elvisek.cz. Hybrid: PHP šablony + `theme.json`. Bez jQuery, bez build kroku, bez externích služeb (fonty i zvýrazňovač kódu jsou lokálně).

## Struktura
| Cesta | Obsah |
|---|---|
| `functions.php` | seznam modulů v `inc/` (každý jde vypnout) |
| `inc/setup.php` | podpora šablony, menu, velikosti obrázků, úklid `<head>` |
| `inc/customizer.php` | Vzhled → Přizpůsobit → ElvisEK (motto, obrázek hlavičky, patička, GA4 ID) |
| `inc/assets.php` | CSS/JS; Prism jen u článků s kódem |
| `inc/content.php` | externí odkazy, kotvy nadpisů, obsah článku |
| `inc/seo.php` | meta description, Open Graph, JSON-LD |
| `inc/comments-antispam.php` | honeypot + časová kontrola |
| `inc/analytics-consent.php` | GA4 + cookie lišta — jen s vyplněným ID |
| `home.php` | titulka: nadpis + motto, hlavní článek, témata, karty |
| `single.php` | článek + obsah článku + novinky |
| `assets/css/main.css` | barvy (světlý/tmavý) jako `--ek-*` proměnné nahoře v souboru |
| `assets/js/theme.js` | přepínač režimu, menu, hledání, kopírování kódu, „načíst další“ |
| `assets/js/lightbox.js` | zvětšení obrázků |
| `assets/js/prism.js` | PrismJS 1.30 (bash, php, powershell, python, yaml, json, sql, docker, nginx…) |
| `assets/fonts/` | Nunito Sans, Space Grotesk, JetBrains Mono (OFL) |

## Kód v článcích
Blok **Kód** + do „Další CSS třídy“ napsat `language-bash` (nebo `language-php`, `language-powershell`, `language-python` …).

## Související
- `../../mu-plugins/elvisek-core.php` — funkce nezávislé na šabloně (login, log, MIME, aktualizace, hardening)
- `../../mu-plugins/elvisek-dev-tools.php` — **jen lokálně**, nenasazovat
