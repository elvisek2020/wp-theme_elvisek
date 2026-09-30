# elvisek.cz — nová WordPress šablona (plán)

Stav k 2026-09-30. Záloha: `backup-2026-09-30/` (soubory 789 MB + DB dump).

## Cíl
Vlastní, rychlá a jednoduše upravitelná šablona pro www.elvisek.cz. Nahradí Graphene Plus 2.9.1 a většinu pluginů. Nový vzhled se světlým i tmavým režimem.

## Rozhodnutí
| Téma | Volba |
|---|---|
| Typ šablony | Hybrid: PHP šablony + `theme.json` + blokové patterny |
| Frontend | Bez jQuery, bez Bootstrapu, bez build kroku (čisté CSS + malé ES moduly) |
| Vzhled | Varianta **D · Magazín** (Space Grotesk + Nunito Sans, modrý akcent, light/dark) — návrh v Claude artifactu „elvisek.cz – návrh šablony“ |
| Analytika | GA4 + vlastní lehká cookie lišta (Consent Mode v2) |
| Komentáře | Zůstávají, antispam honeypot + časová kontrola (bez reCAPTCHA) |
| ManageWP | Zůstává (plugin `worker`) |
| Shortcody v obsahu | Převést v DB na nativní bloky (`core/details`, `core/code`) |
| Ne-vizuální funkce | Malý mu-plugin `elvisek-core` (přežije změnu šablony) |
| Psaní přes AI | Claude přes MCP: oficiální WordPress MCP Adapter + vlastní abilities v `elvisek-core`, AI zakládá jen koncepty |

Proč hybrid: PHP šablony se v Claudovi upravují nejsnáz a dávají nejčistší a nejrychlejší HTML. `theme.json` řídí barvy, písma a mezery pro web i editor na jednom místě. Sekce (hero, karty, CTA…) budou blokové patterny, takže je v editoru jen naklikáš.

## Architektura
```
wp-content/
├── themes/elvisek/
│   ├── style.css, theme.json, functions.php
│   ├── header.php, footer.php, sidebar.php
│   ├── index.php, single.php, page.php, archive.php, search.php, 404.php, comments.php
│   ├── inc/          # moduly, každý jde vypnout jedním řádkem
│   │   ├── setup.php, assets.php, seo.php, reading-time.php
│   │   ├── code-highlight.php, lightbox.php, external-links.php
│   │   ├── analytics-consent.php, comments-antispam.php
│   ├── patterns/     # blokové sekce (hero, karty, CTA, …)
│   ├── assets/css/   # main.css, print.css, editor.css
│   └── assets/js/    # theme.js (menu, dark mode), lightbox.js, consent.js
└── mu-plugins/elvisek-core.php
```

## Náhrada pluginů
| Plugin | Náhrada |
|---|---|
| gutenberg | nic (core WP 7 stačí) |
| shortcodes-ultimate | migrace `[su_spoiler]` (17×) → blok Details |
| syntaxhighlighter | migrace `[php]` (22×) + 4 bloky → `core/code` + Prism jen na stránkách s kódem |
| wp-jquery-lightbox | `inc/lightbox.php` + ~2 kB vanilla JS |
| open-external-links | `inc/external-links.php` |
| google-analytics-for-wordpress + GA v Graphene | `inc/analytics-consent.php` (jediné vložení GA4) |
| cookie-law-info | `consent.js` (~3 kB) |
| advanced-nocaptcha-recaptcha | honeypot v komentářích, omezení pokusů o login v `elvisek-core` |
| simple-login-log, wp-last-login | `elvisek-core` (log přihlášení + poslední login) |
| display-mysql-version, server-ip-memory-usage | `elvisek-core` (info do patičky adminu) |
| wp-extra-file-types | `elvisek-core` (filtr `upload_mimes`) |
| stops-core-theme-and-plugin-updates | core auto-updates |
| wpbenchmark | pryč |
| worker (ManageWP) | **zůstává** |
| Neaktivní (Yoast, Cerber, AI Engine, 3× záloha, phpMyAdmin ext., Admin Menu Editor) | smazat vč. tabulek |

Výsledek: 16 aktivních pluginů → 1 (worker) + mu-plugin.

## Z Graphene přebíráme (verze 1)
Hlavička s obrázkem, názvem a sloganem · lepivé menu s hledáním · obsah + sidebar „Novinky“ · datum jako odznak · doba čtení · perex + „Pokračovat ve čtení“ · navigace předchozí/další · tisková verze · patička s copyrightem · meta description.

## Fáze
| # | Fáze | Výstup |
|---|---|---|
| 0 | Příprava ✅ | git repo, lokální Docker (WP + MariaDB) z dnešní zálohy, ověřená obnova DB |
| 1 | Design ✅ | vybrána varianta D; zbývá: obrázky (pozadí hlavičky, náhledy), obsah (motto, texty) |
| 2 | Kostra šablony ✅ | layout, hlavička/menu, výpis, detail článku, stránka, archivy, hledání, 404, patička |
| 3 | Funkce místo pluginů ✅ | kód, lightbox, externí odkazy, doba čtení, tisk, GA4 + lišta, antispam, SEO (description, OG, JSON-LD) |
| 4 | `elvisek-core` ✅ | login limit + log, info v adminu, MIME typy, hardening (xmlrpc, verze) |
| 4b | Claude ↔ WordPress (MCP) | MCP Adapter, uživatel `claude` (role Autor) + Application Password, abilities: koncept článku v blocích, upload obrázku, kategorie/štítky, čtení článků |
| 5 | Migrace obsahu ✅ (lokálně) | skript: shortcody → bloky, kontrola 94 článků |
| 6 | Výkon | obrázky (WebP, správné velikosti, lazy), cache hlavičky v `.htaccess`, page cache (WEDOS Global vs. vlastní) |
| 7 | Úklid DB a souborů | viz níže |
| 8 | Nasazení 🟡 (šablona 0.1.0 + elvisek-core 0.2.0 na produkci, migrace hotová, pluginy vypnuty; zbývá GA4 ID, PHP 8.3, úklid) | staging → produkce, měření před/po (Lighthouse), rollback plán |

## Úklid DB a souborů
- Tabulky po nepoužívaných pluginech: `cerber_*`, `wp_cerber_*`, `wp_itsec_*`, `wp_yoast_*`, `wp_mwai_*`, `wp_tm_*`, `wp_eum_logs` (15 MB), `wp_monsterinsights_cache`, `wp_wp_phpmyadmin_extension__errors_log`, `wp_xsg_sitemap_meta`
- 297 revizí, auto-drafty, osiřelá postmeta, expirované transienty
- Options po odebraných pluginech a po Graphene (až po přepnutí šablony)
- Soubory: 33 MB starých záloh ve `wp-content`, nepoužívané šablony (Flavor, twentytwenty*), smazané pluginy
- Vždy až po čerstvé záloze

## Měřítka úspěchu
| Metrika | Dnes | Cíl |
|---|---|---|
| Požadavky (homepage) | 46 | < 15 |
| JS | 468 kB / 24 souborů | < 30 kB |
| Obrázky nad ohybem | ~900 kB | < 200 kB |
| Lighthouse Performance (mobil) | měřit ve fázi 0 | ≥ 95 |
| **Po nasazení (30. 9.)** | titulka: 1 skript, 2 CSS, 0 externích, TTFB ~0,31 s | |
| TTFB | 0,7–1,1 s | < 0,3 s (s page cache) |

## Bezpečnost po dokončení
Změnit hesla k FTP i DB · smazat reCAPTCHA klíče v Google konzoli · zvážit SFTP/FTPS.
