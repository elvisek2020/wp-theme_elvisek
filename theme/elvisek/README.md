# Šablona ElvisEK

Vlastní šablona pro www.elvisek.cz. Hybrid: PHP šablony + `theme.json`. Bez jQuery, bez build kroku, bez externích služeb (fonty i zvýrazňovač kódu jsou lokálně).

## Struktura
| Cesta | Obsah |
|---|---|
| `functions.php` | seznam modulů v `inc/` (každý jde vypnout) |
| `inc/setup.php` | podpora šablony, menu, velikosti obrázků, úklid `<head>` |
| `inc/customizer.php` | Vzhled → Přizpůsobit (úvodní stránka, témata, články, patička, SEO) |
| `inc/category-image.php` | obrázek rubriky (Příspěvky → Rubriky) jako náhled článků |
| `inc/updated.php` | štítek „Aktualizováno“ u návodů (box v editoru) |
| `inc/updater.php` | aktualizace šablony z GitHub Releases |
| `inc/assets.php` | CSS/JS; Prism jen u článků s kódem |
| `inc/content.php` | externí odkazy, kotvy nadpisů, obsah článku |
| `inc/seo.php` | meta description, Open Graph, JSON-LD |
| `inc/comments-antispam.php` | honeypot + časová kontrola |
| `inc/analytics-consent.php` | GA4 + cookie lišta — jen s vyplněným ID |
| `home.php` | titulka: nadpis + motto, hlavní článek, témata, karty |
| `single.php` | článek + obsah článku + novinky |
| `assets/css/main.css` | barvy (světlý/tmavý) jako `--ek-*` proměnné nahoře v souboru |
| `assets/js/theme.js` | vzhled a šířka v patičce, lišta a hamburger menu, hledání, kopírování kódu, „načíst další“, komentáře, tlačítko Nahoru |
| `assets/js/lightbox.js` | zvětšení obrázků |
| `assets/js/prism.js` | PrismJS 1.30 (bash, php, powershell, python, yaml, json, sql, docker, nginx…) |
| `assets/fonts/` | Nunito Sans, Space Grotesk, JetBrains Mono (OFL) |

## Kód v článcích
Blok **Kód** → v pravém panelu **Jazyk kódu** vybrat jazyk (nastaví třídu `language-…`). Čísla řádků jdou vypnout v Přizpůsobit → ElvisEK.

## Související
- `../../plugins/elvisek-core/` — plugin ElvisEK Core: funkce nezávislé na šabloně (přihlášení, bezpečnost, WebP, SEO, údržba webu)
- `../../mu-plugins/elvisek-dev-tools.php` — **jen lokálně**, nenasazovat
