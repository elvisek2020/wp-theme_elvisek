# Šablona ElvisEK

Vlastní šablona pro www.elvisek.cz. Hybrid: PHP šablony + `theme.json`. Bez jQuery, bez build kroku, bez externích služeb (fonty i zvýrazňovač kódu jsou lokálně).

Logika je z kanonických modulů skillu `wp-custom-theme` (`inc/ekt/`, `assets/ekt/`, prefix `ekt_`, stejné na všech webech, nemění se tady), vzhled je vlastní.

## Struktura
| Cesta | Obsah |
|---|---|
| `functions.php` | identita šablony (`EKT_*`, `EKT_LEGACY = ['ek']`) a načtení modulů |
| `inc/ekt/*.php` | kanonické moduly: SEO, GA4 s cookie lištou, externí odkazy, lightbox, videa, sociální sítě, aktualizace z GitHubu, blok Kód, Klávesa, obsah článku, Ve zkratce, Aktualizováno, Markdown pro AI, GitHub repozitář, PWA, světlý/tmavý režim, rychlé hledání, Načíst další, barva a obrázek rubriky, Nahoru, sdílení, antispam komentářů |
| `assets/ekt/` | JS a funkční CSS kanonických modulů (PrismJS 1.30 v `assets/ekt/js/prism.js`) |
| `inc/setup.php` | logo, menu, velikosti obrázků, perex, ikona pro iOS |
| `inc/assets.php` | `main.css`, `theme.js`, preload písem |
| `inc/template-tags.php` | logo, témata v hlavičce, karty, štítky rubrik, drobečky, související články, doba čtení |
| `inc/site-elvisek.php` | nastavení webu nad kanonem: výchozí hodnoty, Přizpůsobit → Úvodní stránka, boční panel, vlastní náhled a skrytý úvodní obrázek, jednotná šířka obrázků, ikony rubrik, texty cookie lišty |
| `home.php` | titulka: nadpis + motto, hlavní článek, karty |
| `single.php` | článek + obsah článku + novinky |
| `assets/css/main.css` | barvy (světlý/tmavý) jako `--ek-*` proměnné nahoře, most na `--ekt-*`, vzhled všech částí včetně výstupů kanonu (`.ekt-…`) |
| `assets/js/theme.js` | lišta a hamburger menu, přechod dlaždice → lišta, rozbalení hledání, komentáře, tisk, obsah článku nad textem |
| `assets/fonts/` | Nunito Sans, Space Grotesk, JetBrains Mono (OFL) |

## Kód v článcích
Blok **Kód** → v pravém panelu **Kód** vybrat jazyk (třída `language-…`) a případně název souboru. Styl **Výstup** pro výstup z terminálu. Čísla řádků jdou vypnout v Přizpůsobit → ElvisEK → Články.

## Související
- `../../plugins/elvisek-core/` — plugin ElvisEK Core: funkce nezávislé na šabloně (přihlášení, bezpečnost, WebP, SEO, údržba webu)
- `../../mu-plugins/elvisek-dev-tools.php` — **jen lokálně**, nenasazovat
