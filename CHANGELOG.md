# Changelog

## 0.5.0 — 2026-10-01
### Šablona
- Kompaktní lišta: **hamburger menu** – když se témata do lišty nevejdou (menší monitor, tablet, mobil), schovají se za tlačítko ☰ a rozbalí se jako přehledný seznam s ikonami a počtem článků (zavře Esc / klik mimo)
- Kompaktní lišta: celé logo (monogram + „ElvisEK“), na mobilu jen monogram
- Oprava: při malém počtu vybraných témat se dlaždice neroztahují přes celou šířku – drží běžnou velikost (mřížka min. 8 / 4 / 4 sloupce) a řadí se zleva

## 0.4.9 — 2026-10-01
### Šablona
- Přizpůsobit → Úvodní stránka → **Témata v hlavičce**: výběr rubrik zaškrtnutím a pořadí přetažením (dlaždice i lišta); bez výběru automaticky podle počtu článků
- Odkazy Starší / Novější pod článkem jsou ve výchozím stavu skryté – lze zapnout v Přizpůsobit → Články
- „Mohlo by vás zajímat“: menší nadpis (20 px) a titulky (15,5 px), kompaktnější karty
- Patička na jeden řádek: text o webu vlevo, vpravo přepínač vzhledu, Široká stránka a odkazy (menší ovládací prvky)
- Široká stránka = dosavadní šířka 1480 px se 4 sloupci (výchozí); vypnutím užší stránka 1180 px se 3 sloupci (pamatuje si prohlížeč)
- Bloky kódu ve dvou šířkách: krátké ukázky (nejdelší řádek do 64 znaků) úzké 800 px s tlačítkem Kopírovat u kódu, delší přes celý sloupec

## 0.4.8 — 2026-10-01
### Šablona
- Přepínač vzhledu přesunut z hlavičky do patičky: **Systém / Světlý / Tmavý** (Systém = podle nastavení zařízení)
- Patička: přepínač **Široká stránka** – obsah přes celou šířku okna (od 1100 px, pamatuje si ho prohlížeč, bez probliknutí)

## 0.4.7 — 2026-10-01
### Šablona
- Formulář komentářů přes celou šířku sloupce (bez omezení 920 px)
- Text článku už není omezený na 920 px – jde přes celý sloupec stejně jako obrázky (`--ek-measure: none`)

### ElvisEK Core
- Údržba webu → **Bloky kódu**: převod „Předformátovaného textu“ a holých `<pre>` na blok Kód s odhadnutým jazykem (bash, PowerShell, Python, JSON, YAML, SQL, …) → zvýraznění i čísla řádků; náhled, záloha původního obsahu, vrácení; bloky s formátováním (tučné, odkazy) se nemění

## 0.4.6 — 2026-10-01
### Šablona
- Kompaktní formulář komentářů: menší box (šířka textu, menší nadpis), sbalený na jedno pole „Napište komentář…“ – jméno, e-mail a tlačítko se ukážou po kliknutí do pole nebo na „Odpovědět“ (bez JS celý formulář)
- Boční panel se nezobrazí, když by byl prázdný (vypnuté Novinky a článek bez obsahu/TOC) – článek pak zabere celou šířku místo prázdného sloupce
- Pás s obrázkem na titulce má stejnou šířku jako obsah (max. 1480 px) se zaoblenými rohy – na širokých monitorech už nejde přes celou obrazovku

## 0.4.5 — 2026-10-01
### Šablona
- Bezpečnost: JSON-LD se kóduje s `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` – text z článku (např. „</script>“) už nemůže ukončit blok skriptu (uložené XSS, audit 2026-10-01)

## 0.4.4 — 2026-10-01
### Šablona
- Ikona pro iOS podle nové varianty ikony webu (čitelnější „EK“ v domku)

## 0.4.3 — 2026-10-01
### Šablona
- Ikona pro rubriku AI nástroje (`ai-tools`) – „jiskra“
- Nová ikona pro iOS (domek s monogramem EK a kurzorem) – `apple-touch-icon.png` 180 × 180; v 0.4.2 se kvůli pořadí commitu dostala ještě stará

## 0.4.2 — 2026-09-30
### Šablona
- Oprava: dlouhý nezalomitelný řetězec v komentáři (výpis ze sériové linky) roztáhl stránku na 8 000 px – komentáře se teď zalamují kdekoli, sloupce mřížky mají `min-width: 0`

## 0.4.1 — 2026-09-30
- Vydání druhé části změn 0.4.0 (bezpečnostní hlavičky, sitemap bez uživatelů, `llms.txt`, alt texty, `twitter:*`, jedna hlavička na titulce, tisk v `main.css`, odkaz Soukromí, ikona pro iOS) – tag v0.4.0 ukazoval na dřívější commit

## 0.4.0 — 2026-09-30
### ElvisEK Core
- **Nástroje → Údržba webu** (dříve Údržba/Přechod ElvisEK), nový modul `inc/maintenance.php`; stará adresa přesměruje
- Odebráno vše k přechodu z Graphene: migrace shortcodů, vypínání pluginů, osiřelé tabulky a volby (jednorázově se uklidí i jejich zbytky)
- **WebP pro starší obrázky**: ke starým PNG/JPG vytvoří `soubor.png.webp` (dávkově), web je posílá místo originálu v obsahu, náhledech i lightboxu; bez zásahu do databáze, jde vypnout nebo smazat; při smazání přílohy se smažou i kopie
- **Databáze**: velikost tabulek, autoload (celkem + top 10), optimalizace tabulek
- **Média a odkazy** (jen přehled): největší soubory, nepoužité obrázky, rozbité interní odkazy a obrázky v obsahu
- Úklid obsahu: revize, koncepty a koš, transienty (s potvrzením)
- Bezpečnostní hlavičky: HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- Sitemapa bez uživatelů (prozrazovala login), `lastmod` u článků i v indexu; autorské archivy přesměrují na titulku
- `/llms.txt` – stručný přehled webu pro AI (témata, nejnovější články)
### Šablona
- Alt text náhledů = název článku (karty, hlavní článek, detail); obrázek v nadpisu zůstává dekorativní
- `twitter:title` a `twitter:description`
- Na titulce jen jedna `<header>` a jedna navigace (plovoucí lišta je `div`)
- Tiskové styly sloučeny do `main.css` (o jeden požadavek méně)
- Odkaz „Soukromí“ v patičce (stránka z Nastavení → Soukromí)
- Ikona pro iOS (`apple-touch-icon`) jako PNG z šablony

## 0.3.8 — 2026-09-30
### Šablona
- Ikona pro rubriku Home Assistant (`homeassistant`)
- Mobil, světlý režim: pod nadpisem se světlou variantou obrázku světlý závoj zdola (motto se už nepere s obrázkem)
- Nový `screenshot.png` (aktuální hlavička s dlaždicemi a obrázkem)
- SEO: `rel=canonical` i pro titulku, rubriky, štítky, autory a stránkování
### Nástroje
- `tools/img.py` přeskočí už zpracované a nedostupné soubory (iCloud)

## 0.3.7 — 2026-09-30
### Šablona
- Obrázek rubriky: nové pole u rubriky (Příspěvky → Rubriky → Upravit) s výběrem z médií a sloupec s náhledem v seznamu rubrik
- Přizpůsobit → Články → „Obrázek rubriky“: vždy (místo náhledu článku) / jen u článků bez náhledu / nepoužívat; platí pro karty, detail článku i Open Graph, data článků se nemění
### Nástroje
- `tools/wp.py` – klient REST API (koncepty, média, výpis článků a stránek), `tools/img.py` – příprava obrázků (16 : 9, 1600 × 900, WebP)

## 0.3.6 — 2026-09-30
### Šablona
- Přizpůsobit → Úvodní stránka: volitelná „Světlá varianta obrázku“ pro nadpis – ve světlém režimu se použije ona, text je tmavý a čitelnost drží světlý přechod zleva; v tmavém režimu zůstává tmavý obrázek s bílým textem
- Stahuje se jen varianta pro aktuální režim (`<picture>` + přepnutí při ručním přepínači)

## 0.3.5 — 2026-09-30
### Šablona
- Článek a stránky mají stejnou šířku jako titulka (1480 px): sloupec článku se roztáhne, boční panel 340 px
- Běžný text drží čitelnou délku řádku (max. 920 px, proměnná `--ek-measure`); kód, obrázky, tabulky a úvodní obrázek jdou přes celou šířku sloupce

## 0.3.4 — 2026-09-30
### ElvisEK Core
- Aktualizace hlásí kompatibilitu s WordPressem (hlavička „Tested up to“, teď 7.1) – pryč „Nebylo otestováno“
- Okno „Zobrazit podrobnosti o verzi“ ukazuje poznámky k release z GitHubu místo chyby z wordpress.org

## 0.3.3 — 2026-09-30
### Šablona
- Články se načítají po dávkách 12 (plné řádky při 1, 2, 3 i 4 sloupcích) – titulka, rubriky i hledání
- Automatické načítání při dorolování (3×), pak tlačítko „Načíst další články“, aby šlo dojet k patičce
- Odebráno nastavení „Počet článků pod hlavním článkem“ (nahrazeno pevnou dávkou 12)
### ElvisEK Core
- Nástroje → „Přechod ElvisEK“ přejmenováno na „Údržba ElvisEK“
- Sekce Migrace obsahu a Pluginy nahrazené šablonou se zobrazí jen tehdy, když mají co dělat
- Nové tlačítko „Vše v pořádku – smazat zálohy“ (zálohy původního obsahu po migraci)

## 0.3.2 — 2026-09-30
### ElvisEK Core
- Přechod ElvisEK → Úklid: nová část „Nastavení po starých pluginech a šablonách“ – náhled zbytků ve `wp_options` (velikost, autoload) se zaškrtávátky a smazáním; ManageWP (`mwp_`, `mmb_`, `worker_`) a nastavení ElvisEK se nikdy nenabízí
### Šablona
- Přizpůsobit → Úvodní stránka: „Zobrazit nadpis webu s mottem“ (vypnuto = jen skrytý H1 pro čtečky a SEO)
- Přizpůsobit → Úvodní stránka: „Počet článků pod hlavním článkem“ (3–48, výchozí 6); stejný počet i pro „Načíst další“ a další strany, nezávisle na Nastavení → Čtení

## 0.3.1 — 2026-09-30
### Šablona
- Dlaždice témat se přizpůsobí počtu: vyvážené řádky (10 → 1 řádek, 12 → 2×6; tablet i mobil zvlášť), max. počet v Přizpůsobit snížen na 12
- Hledání v hlavičce: pole vyjede vedle lupy (max. 320 px) místo panelu přes celou šířku; lupa s textem hledá, prázdná zavře, Esc zavře
- Kompaktní lišta: při více než 8 tématech bez ikon a těsněji; maska na okraji jen když lišta opravdu přetéká
### ElvisEK Core
- Beze změny funkcí (verze srovnaná se šablonou kvůli společnému release)
### Dokumentace
- README: release s anotovaným tagem (`git tag -a`)

## 0.3.0 — 2026-09-30
### Šablona
- Témata místo menu: na titulce dlaždice témat, při rolování plynulý přechod do kompaktní lišty; ostatní stránky mají lištu rovnou
- Nové logo: monogram EK s kurzorem + „ElvisEK“
- Štítky všech rubrik u článku (na kartách, u hlavního článku i v detailu)
- Čísla řádků u kódu (Prism line-numbers), vypnutelné v Přizpůsobit → Články
- Editor: panel „Jazyk kódu“ u bloku Kód
- Přizpůsobit rozdělené do sekcí Úvodní stránka / Články / Patička / SEO a analytika
- Nízká patička: text o webu, Kontakt, RSS
- Odkaz „Nastavení cookies“ jen pro nepřihlášené (přihlášeným se GA nenačítá)
- `screenshot.png` pro přehled motivů
- Upozornění v administraci, když chybí plugin ElvisEK Core

### ElvisEK Core (nově běžný plugin místo mu-pluginu)
- Aktualizace z GitHub Releases (`elvisek-core.zip`), bez automatických aktualizací
- Nástroje → Přechod ElvisEK: náhledy pro šablonu, cache hlavičky v `.htaccess`, úklid tabulek/revizí/koše/transientů
- Nové zmenšeniny obrázků ve WebP
- XML-RPC úplně vypnuté

### Přechod z mu-pluginu
1. Pluginy → Přidat → Nahrát `elvisek-core.zip` → Aktivovat
2. Přes FTP smazat `wp-content/mu-plugins/elvisek-core.php`

## 0.1.0 — 2026-09-30
- První verze šablony ElvisEK (návrh D · Magazín), GitHub Releases + updater
