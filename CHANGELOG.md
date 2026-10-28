# Changelog

## v0.16.0 — 2026-10-28

Převzetí novějších kanonických modulů ze skillu `wp-custom-theme`. Pro návštěvníka se nic nemění, funkce, které web dosud řešil vlastním kódem, teď dělají kanonické moduly.

### ElvisEK Core
- **Kanonické moduly hardening 1.1.0, security-headers 1.1.0 a updater 1.1.0**: CSP, COOP, `security.txt`, batch API jen pro přihlášené, blok `readme.html` v `.htaccess` a kompatibilita s celou řadou WordPressu 7.1.x už nejsou projektový kód webu
- Nové volby v **Nastavení → ElvisEK Core → Zabezpečení**: Content-Security-Policy, Cross-Origin-Opener-Policy, kontakt pro `security.txt`, batch API jen pro přihlášené, ochrana souborů jádra v `.htaccess`. Při první návštěvě administrace se CSP zapne a vyplní se kontakt, takže web funguje stejně jako ve verzi 0.15.1

### Šablona
- **Kanonické moduly term-image 1.1.0 a lightbox 1.1.0**: „Vlastní náhled místo obrázku rubriky“ je v boxu Nastavení článku z kanonu. Hodnoty u článků se převedou samy (`ekt_site_own_thumb` → `ekt_own_thumb`)
- Nová volba **Přizpůsobit → Články → Zvětšovat i obrázky bez odkazu**, při převodu se zapne (jako dosud)

## v0.15.1 — 2026-10-24

### ElvisEK Core
- Aktualizace pluginu už nehlásí **„Kompatibilita s WordPressem 7.1.3: Nebylo otestováno“**: „Tested up to: 7.1“ platí pro celou řadu 7.1.x (jako na wordpress.org). Projeví se u aktualizací nabízených po instalaci této verze

## v0.15.0 — 2026-10-24

### ElvisEK Core
- **Content-Security-Policy** na webu: skripty jen z webu a Google Tag Manageru (GA až po souhlasu), vložená videa jen z YouTube a Vimea, zakázané pluginy (`object`), cizí `<base>` a odesílání formulářů jinam, web nejde vložit do cizí stránky. Inline skripty šablony zůstávají povolené (nonce kvůli cache hostingu nejde). Administrace beze změny
- **`/.well-known/security.txt`** (RFC 9116): kontakt pro hlášení zranitelností, platnost se posouvá sama; `/security.txt` přesměruje
- Hlavička **Cross-Origin-Opener-Policy: same-origin**
- **REST batch API** (`/wp-json/batch/v1`) jen pro přihlášené, nepřihlášený dostane 401 (útočná plocha díry wp2shell; editor batch dál používá)
- **`readme.html`, `license.txt` a `wp-config-sample.php`** (prozrazují verzi WordPressu) blokované pravidlem v `.htaccess`. Plugin si zapíše vlastní blok „ElvisEK Core“ při první návštěvě administrace a při deaktivaci ho odebere, ostatní pravidla zůstávají

### Šablona
- **Logo pro BIMI** (`assets/icons/bimi.svg`, SVG Tiny PS podle ikony webu): logo vedle odesílatele v podporovaných poštovních klientech. Adresu uvádí DNS záznam `default._bimi.elvisek.cz`
- **Lišta s tématy nebliká** při vypnuté široké stránce na velkém monitoru: témata jsou v liště bez ikon jako na menším okně (dřív se s ikonami nevešla a lišta přepínala mezi tématy a menu), položky se nesmršťují a přepnutí na menu má rezervu proti přepínání dokola
- **Komentáře** se sbalí jen u diskuse s víc než 5 komentáři; do 5 komentářů jsou vidět rovnou (hranice jde změnit filtrem `ek_comments_collapse_over`)

## v0.14.0 — 2026-10-22

### ElvisEK Core
- **Upozornění na odpověď v komentáři**: ve formuláři komentáře zaškrtávátko „Pošlete mi e-mail, až někdo odpoví“. Když je odpověď schválená, odejde autorovi původního komentáře e-mail s úryvkem a odkazem. Vlastní odpověď ani pingback e-mail nepošle a každá odpověď ho pošle nejvýš jednou. Odběr jde zrušit jedním odkazem v e-mailu. Vypíná se v Nastavení → ElvisEK Core → Komentáře
- **Počítadlo zobrazení bez cookies**: článek a stránka se započítají jednou za relaci karty, i když je web v cache hostingu. Nepočítají se přihlášení, roboti ani automaty a nic o návštěvníkovi se neukládá, takže to nepotřebuje souhlas s cookies. Výsledky jsou ve widgetu Zdraví webu (dnes / 7 / 30 dní, nejčtenější za 30 dní) a ve sloupci Zobrazení v přehledu článků a stránek (30 dní / celkem)
- Údržba webu: pozůstatky pluginů podle novější verze kanonu
- Odinstalace smaže i vlastní tabulky a metadata komentářů pluginu

### Šablona
- **Česká typografie**: pevné mezery za jednopísmennými předložkami a spojkami („v Praze“, „z Gmailu“), mezi číslem a jednotkou („500 kWh“, „10 %“) a v číslech. Netýká se kódu ani uloženého obsahu, upravuje se jen výstup na webu
- Zaškrtávátko odběru odpovědí ve formuláři komentáře ve stylu webu
- SEO: oprava slepených popisů (kanon seo 1.0.1)

## v0.13.0 — 2026-10-21

### Šablona
- **Převod na kanonické moduly** ze skillu `wp-custom-theme`: funkce šablony (SEO, GA4 s cookie lištou, lightbox, blok Kód, obsah článku, Ve zkratce, Aktualizováno, Markdown pro AI, GitHub repozitář, PWA, světlý/tmavý režim, rychlé hledání, Načíst další, barva a obrázek rubriky, sdílení, Nahoru, antispam) jsou teď moduly stejné na všech spravovaných webech (`inc/ekt/`, `assets/ekt/`). Vzhled webu se nemění. Nastavení se převede samo při první návštěvě administrace: Přizpůsobit (GA, motto, témata, obrázky hlavičky, patička, sítě), Nastavení článku (Ve zkratce, Aktualizováno, obsah článku, vlastní náhled, skrytý úvodní obrázek), barvy a obrázky rubrik, volby v prohlížeči (režim, šířka, souhlas s cookies)
- **SEO**: datové archivy (`/2026/10/`) mají `noindex, follow`, feedy hlavičku `X-Robots-Tag: noindex, follow`, Markdown verze článku (`.md`) jen canonical na článek (bez `noindex`)
- Nová sekce **Přizpůsobit → Sociální sítě**; projektová nastavení jsou v sekcích Úvodní stránka a Články jako dosud
- Kopírovat pro AI, sdílení, kód, lightbox a hledání beze změny pro návštěvníka; obsah článků se nepřepisuje (starý blok GitHub, styl Výstup a název souboru u kódu fungují dál)

Změny chování:
- Meta článku přes REST mají nové názvy: `ekt_summary`, `ekt_updated`, `ekt_toc`, `ekt_site_own_thumb`, `ekt_site_hide_hero` (dřív `ek_…`)
- Rychlé hledání má adresu `/wp-json/ekt/v1/search` (dřív `ek/v1`)

### ElvisEK Core
- Obrázky rubrik pro Údržbu (nepoužité obrázky) teď dodává šablona; projektový filtr v pluginu odstraněn

### Vydání
- Workflow vydání podle šablony skillu `wp-custom-theme`: `actions/checkout@v7`, runner `ubuntu-latest`, PHP pevně 8.5 jako na produkci (`shivammathur/setup-php`), text vydání z tohoto CHANGELOGu
- Nadpisy verzí v CHANGELOGu s `v` (podle nich workflow hledá text vydání)
- `.gitignore` podle šablony skillu

## v0.12.0 — 2026-10-21
### ElvisEK Core
- **Převod na kanonické moduly** ze skillu `wp-custom-theme`: plugin je teď složený z modulů, které jsou stejné na všech spravovaných webech (`inc/*.php`, prefix `ekc_`). Projektové úpravy webu jsou v `inc/site-elvisek.php`. Data se převedou sama při první návštěvě administrace: log přihlášení, poslední přihlášení, čas údržby, úvodní text `/llms.txt`
- **Nová stránka Nastavení → ElvisEK Core**: funkce jdou vypnout nebo upravit bez zásahu do kódu (počet pokusů a délka blokace, typy souborů, `/llms.txt`, automatické aktualizace…)
- Údržba webu: nové sekce **Pozůstatky pluginů** a **Karanténa** (nepoužité obrázky a tabulky se nejdřív přesunou stranou), log přihlášení s tlačítkem **Zrušit blokace**
- Staré adresy stránek (Nástroje → Údržba webu, Log přihlášení) se přesměrují na nové

Změny chování oproti 0.11.x (výchozí hodnoty společné pro všechny weby):
- Opakovaná blokace přihlášení z jedné IP se prodlužuje (eskalace); aktivní blokace ze staré verze po aktualizaci zaniknou
- Vypnutý editor souborů v administraci, skrytá verze WordPressu
- Hlavička `Permissions-Policy` navíc blokuje `payment` a `usb` (místo `interest-cohort` je `browsing-topics`)
- Rizikové typy souborů (`sh`, `py`, `dmg`, `bin`, `gbl`, `mobileconfig`) smí nahrát jen administrátor, ne editor
- `/llms.txt`: správné tvary („4 články“) a bez HTML entit v popisech

### Šablona
- Beze změny, jen verze (šablona a plugin mají vždy stejné číslo)

## v0.11.7 — 2026-10-19
### Šablona
- **Skrýt úvodní obrázek v článku**: nová volba v boxu **Nastavení článku** → Náhledový obrázek. Detail článku začne rovnou textem, karty na webu a náhled při sdílení zůstanou (hodí se pro portfolio, kde je obrázek v textu)

## v0.11.6 — 2026-10-19
### Šablona
- **Vlastní náhled u článku**: v boxu **Nastavení článku** volba „Použít vlastní náhled místo obrázku rubriky“. Článek s nastaveným náhledovým obrázkem ho pak ukazuje v kartách, v detailu i v Open Graph, i když je v Přizpůsobit → Články obrázek rubriky nastavený na „Vždy“. Hodí se pro portfolio a podobné články

### Vydání
- Runner pevně `ubuntu-24.04` místo `ubuntu-latest` (to se od 19. 10. 2026 přepíná na Ubuntu 26)

## v0.11.5 — 2026-10-05
*(obsahuje i 0.11.4 – její vydání se nepodařilo kvůli tagu na špatném commitu)*
### Šablona
- Dlaždice témat (hlavička titulky, sekce Témata): barva rubriky v okraji a ikoně, plocha dlaždice zůstává neutrální; lišta a menu beze změny

### Vydání
- GitHub Action na Node 24: `actions/checkout@v5`, `softprops/action-gh-release@v3` (konec varování o Node 20)

## v0.11.3 — 2026-10-05
### Šablona
- Patička na mobilu: odkazy se zalomí do více řádků, stránka už nejde posouvat do strany (přetékala o 13 px)
- Obrázek v hlavičce titulky přebírá alternativní text z knihovny médií (prázdný = dekorativní obrázek)

## v0.11.2 — 2026-10-05
### Šablona
- Ve zkratce: text v obrácených apostrofech jako `<16>` se už při uložení neztratí (dřív ho mazala sanitizace jako HTML značku)

## v0.11.1 — 2026-10-05
### Šablona
- Barva rubriky jen u štítků rubrik a proužku na kartách článků; dlaždice témat, lišta a menu zase v původní neutrální podobě
- Stránka 404: velké „404“ je odkaz na úvod; bez pole hledání a tlačítka Zpět (hledání je v hlavičce)

## v0.11.0 — 2026-10-05
### Šablona
- **Blok Kód → styl „Výstup“** pro výstup z terminálu: světlejší přerušovaný rámeček, štítek „Výstup“, bez tlačítka Kopírovat a čísel řádků. V Markdownu řádek „Výstup:“
- **Dlouhý kód se sbalí**: blok nad 30 řádků ukáže prvních 15 a tlačítko „Zobrazit celé (N řádků)“; Kopírovat, tisk i Markdown mají vždy celý kód
- **Klávesy**: v liště formátování nové tlačítko **Klávesa** (`<kbd>`), na webu vypadá jako klávesa, v Markdownu jako `kód`
- **Ve zkratce**: nové pole v boxu **Nastavení článku** (jeden bod na řádek, max. 8) → box nad textem článku, i v Markdown verzi a „Kopírovat pro AI“
- **Stránka 404**: hláška ve stylu terminálu, hledání, 6 nejnovějších článků a témata
- **Barva rubriky**: jemný podtón u štítků rubrik a tenký proužek nahoře na kartě článku (víc rubrik = víc úseků). Barva se nastavuje u rubriky (Příspěvky → Rubriky), bez nastavení výchozí z palety
- **Instalovatelný web (PWA)**: manifest s ikonami, service worker ukládá přečtené články a soubory šablony – offline jdou otevřít, jinak stránka „Jsi offline“ se seznamem uložených článků. Administrace, přihlášení, REST, hledání a náhledy se neukládají; nová verze šablony starou mezipaměť smaže
- **Blok GitHub repozitář**: karta s popisem, hvězdičkami, posledním vydáním, jazykem a licencí. Data stahuje server (cache 12 h, při výpadku poslední úspěšná), návštěvník se GitHubu nedotkne

### ElvisEK Core
- **Zdraví webu** na Nástěnce: verze (a dostupné aktualizace) šablony, pluginu, WordPressu, PHP; velikost databáze, autoload, uploads; koncepty, komentáře ke schválení, články bez náhledu; čas poslední údržby. Tlačítko do Údržby webu

## v0.10.0 — 2026-10-04
### Šablona
- **Název souboru nad blokem kódu**: v editoru v bloku Kód (panel **Kód**) nové pole „Název souboru“, např. `docker-compose.yml` nebo `/etc/fstab`. Na webu se zobrazí jako štítek (záložka s ikonou souboru) nad kódem, v editoru taky. V Markdown verzi článku a v „Kopírovat pro AI“ je před blokem řádek „Soubor `…`:“
- Panel v editoru „Jazyk kódu“ přejmenován na „Kód“

## v0.9.2 — 2026-10-03
### Šablona
- Obrázek v článku jde zarovnat **na střed** (v editoru blok Obrázek → Zarovnat na střed); výchozí zůstává 800 px zarovnaných vlevo s textem, popisek se u vycentrovaného obrázku také vycentruje

## v0.9.1 — 2026-10-02
### Šablona
- Video v článku (blok Video): přes celý sloupec se zaoblenými rohy; v editoru styl **Úzké (800 px)** pro video stejně široké jako obrázky
- **Obsah článku** jde vypnout/zapnout: globálně v Přizpůsobit → Články („Obsah článku (z nadpisů)“ – platí pro boční panel i pro obsah nad textem na užších obrazovkách) a u každého článku zvlášť v editoru v boxu **Nastavení článku** (Podle šablony / Zobrazit / Skrýt)
- Box v editoru „Aktualizace návodu“ přejmenován na „Nastavení článku“

### Nástroje
- `tools/wp.py draft --slug` – adresa článku rovnou při vytvoření konceptu

## v0.9.0 — 2026-10-01
### Šablona
- **Kopírovat pro AI** v hlavičce článku (vedle Tisk): zkopíruje článek jako Markdown (nadpis, zdroj, obsah, bloky kódu s jazykem, obrázky, tabulky) – stačí vložit do libovolné AI. Šipka vedle nabídne **Otevřít v Claude**, **Otevřít v ChatGPT** (s předvyplněným dotazem a odkazem na článek) a Zobrazit jako Markdown
- Každý článek a stránka má **Markdown verzi** na adrese `…/nazev-clanku.md` (`text/markdown`, noindex, kanonický odkaz na článek) a v hlavičce `<link rel="alternate" type="text/markdown">`
- Sdílení článku v boxu (stejný styl jako komentáře) mezi „Mohlo by vás zajímat“ a komentáři
- Patička ve třech sloupcích: text o webu vlevo, vzhled a šířka stránky uprostřed, odkazy vpravo

## v0.8.0 — 2026-10-01
### Šablona
- **Rychlé hledání během psaní**: po 2 znacích se pod polem hledání rozbalí až 6 článků (název se zvýrazněným slovem, rubrika, datum) a odkaz „Všechny výsledky“. Šipky ↑↓ + Enter, Esc zavře. Data z vlastního REST endpointu `ek/v1/search` (bez externích služeb)
- **Sdílení článku** pod článkem: Kopírovat odkaz (na mobilu systémové sdílení), LinkedIn, Facebook – obyčejné odkazy bez skriptů a sledování. Vypínatelné v Přizpůsobit → Články
- **Profily v patičce**: ikony e-mail (chráněný proti robotům), GitHub, LinkedIn – adresy v Přizpůsobit → Patička

## v0.7.1 — 2026-10-01
### Šablona
- Nadpis „ElvisEK“ v hlavičce titulky je odkaz na úvodní stránku (při najetí se obarví)

## v0.7.0 — 2026-10-01
### ElvisEK Core
- **Nahrávání obrázků rovnou do WebP**: PNG/JPG nahrané přes Média (i přes API) se uloží jako `.webp` – originál se neukládá, fotky z mobilu se podle EXIF otočí. GIF a SVG beze změny
- Údržba webu → **Sjednotit obrázky na WebP** (natrvalo): starší PNG/JPG převede na `.webp` ve všech velikostech, přepíše odkazy v článcích a stránkách a původní soubory smaže; staré adresy obrázků (odkazy zvenku, Google Obrázky) se přesměrují 301 na WebP. Běží po dávkách (~40 s), sekce zmizí, až nic nezbývá
- Odebrány nepotřebné sekce Údržby: **Výkon** (náhledy, cache hlavičky – už nastavené v .htaccess zůstávají), **Bloky kódu** a **WebP kopie** (soubor.png.webp + přepisování adres při výpisu stránky)
- Jednorázový úklid: smazány zálohy z „Bloků kódu“ (po sjednocení editoru by vrátily starý obsah) a volba doručování WebP kopií

## v0.6.3 — 2026-10-01
### ElvisEK Core
- Údržba webu → Média a odkazy → **Nepoužité obrázky: hromadné mazání** – zaškrtávátka u náhledů, Vybrat vše / Zrušit výběr, počítadlo a tlačítko Smazat vybrané (s potvrzením). U každého obrázku velikost na disku, nahoře součet. Server před smazáním znovu ověří, že obrázek opravdu nikde není použitý; maže se i se všemi zmenšeninami a WebP kopiemi

## v0.6.2 — 2026-10-01
### Šablona
- Oprava: úvodní obrázek článku zase vede přes celou šířku sloupce (od 0.6.1 se kvůli omezení výšky na 440 px zužoval)
- **Komentáře ve sbaleném boxu** ve stejném stylu jako „Napsat komentář“: nadpis „Komentáře (N)“ s tlačítkem Zobrazit / Skrýt, uvnitř kompaktní seznam (menší písmo, oddělovače místo karet, odpovědi odsazené s linkou). Odkaz na konkrétní komentář (#comment-…) box sám rozbalí
- Odpověď na komentář: formulář se v seznamu zobrazí v rámečku a po zrušení se vrátí dolů (dřív po sobě nechával prázdný box)

## v0.6.1 — 2026-10-01
### Šablona
- Úvodní obrázek článku má nejvýš 440 px na výšku (na širokých monitorech už nezabírá celou obrazovku, ořízne se na střed)
- **Obrázky v článcích jednotně**: šířka 800 px, zarovnané vlevo s textem (dřív každý jinak velký a na středu); vysoké screenshoty (mobil) max. 640 px na výšku; malé ikonky (< 160 px) a galerie beze změny
- Obrázky se načítají v dostatečném rozlišení pro 800 px (ne rozmazaný náhled 300 px)
- Kliknutím se **každý obrázek otevře v prohlížeči obrázků** (lightbox) – i ty, které neměly odkaz na plnou velikost; šipkami mezi obrázky, Esc zavře

## v0.6.0 — 2026-10-01
### Šablona
- **Štítek „Aktualizováno“** u zrevidovaných návodů: v editoru článku je box **Aktualizace návodu** (datum + tlačítko Dnes); zelený štítek s datem v detailu článku a malý štítek na kartách. Prázdné datum = bez štítku
- Zrušeno automatické „(aktualizováno …)“ podle data změny ve WordPressu – svítilo u skoro všech článků kvůli hromadným úpravám
- **Tlačítko Nahoru** vpravo dole: objeví se po odrolování zhruba jedné obrazovky, plynule vyroluje nahoru (bez animace při „omezit pohyb“), v tisku skryté

## v0.5.1 — 2026-10-01
### Šablona
- Kompaktní lišta na mobilu: celé logo (monogram + „ElvisEK“) jako na desktopu – místo pro něj uvolnilo hamburger menu
- Tmavý vzhled o kousek světlejší (pozadí, plochy, linky a štítky), méně „černá díra“

## v0.5.0 — 2026-10-01
### Šablona
- Kompaktní lišta: **hamburger menu** – když se témata do lišty nevejdou (menší monitor, tablet, mobil), schovají se za tlačítko ☰ a rozbalí se jako přehledný seznam s ikonami a počtem článků (zavře Esc / klik mimo)
- Kompaktní lišta: celé logo (monogram + „ElvisEK“), na mobilu jen monogram
- Oprava: při malém počtu vybraných témat se dlaždice neroztahují přes celou šířku – drží běžnou velikost (mřížka min. 8 / 4 / 4 sloupce) a řadí se zleva

## v0.4.9 — 2026-10-01
### Šablona
- Přizpůsobit → Úvodní stránka → **Témata v hlavičce**: výběr rubrik zaškrtnutím a pořadí přetažením (dlaždice i lišta); bez výběru automaticky podle počtu článků
- Odkazy Starší / Novější pod článkem jsou ve výchozím stavu skryté – lze zapnout v Přizpůsobit → Články
- „Mohlo by vás zajímat“: menší nadpis (20 px) a titulky (15,5 px), kompaktnější karty
- Patička na jeden řádek: text o webu vlevo, vpravo přepínač vzhledu, Široká stránka a odkazy (menší ovládací prvky)
- Široká stránka = dosavadní šířka 1480 px se 4 sloupci (výchozí); vypnutím užší stránka 1180 px se 3 sloupci (pamatuje si prohlížeč)
- Bloky kódu ve dvou šířkách: krátké ukázky (nejdelší řádek do 64 znaků) úzké 800 px s tlačítkem Kopírovat u kódu, delší přes celý sloupec

## v0.4.8 — 2026-10-01
### Šablona
- Přepínač vzhledu přesunut z hlavičky do patičky: **Systém / Světlý / Tmavý** (Systém = podle nastavení zařízení)
- Patička: přepínač **Široká stránka** – obsah přes celou šířku okna (od 1100 px, pamatuje si ho prohlížeč, bez probliknutí)

## v0.4.7 — 2026-10-01
### Šablona
- Formulář komentářů přes celou šířku sloupce (bez omezení 920 px)
- Text článku už není omezený na 920 px – jde přes celý sloupec stejně jako obrázky (`--ek-measure: none`)

### ElvisEK Core
- Údržba webu → **Bloky kódu**: převod „Předformátovaného textu“ a holých `<pre>` na blok Kód s odhadnutým jazykem (bash, PowerShell, Python, JSON, YAML, SQL, …) → zvýraznění i čísla řádků; náhled, záloha původního obsahu, vrácení; bloky s formátováním (tučné, odkazy) se nemění

## v0.4.6 — 2026-10-01
### Šablona
- Kompaktní formulář komentářů: menší box (šířka textu, menší nadpis), sbalený na jedno pole „Napište komentář…“ – jméno, e-mail a tlačítko se ukážou po kliknutí do pole nebo na „Odpovědět“ (bez JS celý formulář)
- Boční panel se nezobrazí, když by byl prázdný (vypnuté Novinky a článek bez obsahu/TOC) – článek pak zabere celou šířku místo prázdného sloupce
- Pás s obrázkem na titulce má stejnou šířku jako obsah (max. 1480 px) se zaoblenými rohy – na širokých monitorech už nejde přes celou obrazovku

## v0.4.5 — 2026-10-01
### Šablona
- Bezpečnost: JSON-LD se kóduje s `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` – text z článku (např. „</script>“) už nemůže ukončit blok skriptu (uložené XSS, audit 2026-10-01)

## v0.4.4 — 2026-10-01
### Šablona
- Ikona pro iOS podle nové varianty ikony webu (čitelnější „EK“ v domku)

## v0.4.3 — 2026-10-01
### Šablona
- Ikona pro rubriku AI nástroje (`ai-tools`) – „jiskra“
- Nová ikona pro iOS (domek s monogramem EK a kurzorem) – `apple-touch-icon.png` 180 × 180; v 0.4.2 se kvůli pořadí commitu dostala ještě stará

## v0.4.2 — 2026-09-30
### Šablona
- Oprava: dlouhý nezalomitelný řetězec v komentáři (výpis ze sériové linky) roztáhl stránku na 8 000 px – komentáře se teď zalamují kdekoli, sloupce mřížky mají `min-width: 0`

## v0.4.1 — 2026-09-30
- Vydání druhé části změn 0.4.0 (bezpečnostní hlavičky, sitemap bez uživatelů, `llms.txt`, alt texty, `twitter:*`, jedna hlavička na titulce, tisk v `main.css`, odkaz Soukromí, ikona pro iOS) – tag v0.4.0 ukazoval na dřívější commit

## v0.4.0 — 2026-09-30
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

## v0.3.8 — 2026-09-30
### Šablona
- Ikona pro rubriku Home Assistant (`homeassistant`)
- Mobil, světlý režim: pod nadpisem se světlou variantou obrázku světlý závoj zdola (motto se už nepere s obrázkem)
- Nový `screenshot.png` (aktuální hlavička s dlaždicemi a obrázkem)
- SEO: `rel=canonical` i pro titulku, rubriky, štítky, autory a stránkování
### Nástroje
- `tools/img.py` přeskočí už zpracované a nedostupné soubory (iCloud)

## v0.3.7 — 2026-09-30
### Šablona
- Obrázek rubriky: nové pole u rubriky (Příspěvky → Rubriky → Upravit) s výběrem z médií a sloupec s náhledem v seznamu rubrik
- Přizpůsobit → Články → „Obrázek rubriky“: vždy (místo náhledu článku) / jen u článků bez náhledu / nepoužívat; platí pro karty, detail článku i Open Graph, data článků se nemění
### Nástroje
- `tools/wp.py` – klient REST API (koncepty, média, výpis článků a stránek), `tools/img.py` – příprava obrázků (16 : 9, 1600 × 900, WebP)

## v0.3.6 — 2026-09-30
### Šablona
- Přizpůsobit → Úvodní stránka: volitelná „Světlá varianta obrázku“ pro nadpis – ve světlém režimu se použije ona, text je tmavý a čitelnost drží světlý přechod zleva; v tmavém režimu zůstává tmavý obrázek s bílým textem
- Stahuje se jen varianta pro aktuální režim (`<picture>` + přepnutí při ručním přepínači)

## v0.3.5 — 2026-09-30
### Šablona
- Článek a stránky mají stejnou šířku jako titulka (1480 px): sloupec článku se roztáhne, boční panel 340 px
- Běžný text drží čitelnou délku řádku (max. 920 px, proměnná `--ek-measure`); kód, obrázky, tabulky a úvodní obrázek jdou přes celou šířku sloupce

## v0.3.4 — 2026-09-30
### ElvisEK Core
- Aktualizace hlásí kompatibilitu s WordPressem (hlavička „Tested up to“, teď 7.1) – pryč „Nebylo otestováno“
- Okno „Zobrazit podrobnosti o verzi“ ukazuje poznámky k release z GitHubu místo chyby z wordpress.org

## v0.3.3 — 2026-09-30
### Šablona
- Články se načítají po dávkách 12 (plné řádky při 1, 2, 3 i 4 sloupcích) – titulka, rubriky i hledání
- Automatické načítání při dorolování (3×), pak tlačítko „Načíst další články“, aby šlo dojet k patičce
- Odebráno nastavení „Počet článků pod hlavním článkem“ (nahrazeno pevnou dávkou 12)
### ElvisEK Core
- Nástroje → „Přechod ElvisEK“ přejmenováno na „Údržba ElvisEK“
- Sekce Migrace obsahu a Pluginy nahrazené šablonou se zobrazí jen tehdy, když mají co dělat
- Nové tlačítko „Vše v pořádku – smazat zálohy“ (zálohy původního obsahu po migraci)

## v0.3.2 — 2026-09-30
### ElvisEK Core
- Přechod ElvisEK → Úklid: nová část „Nastavení po starých pluginech a šablonách“ – náhled zbytků ve `wp_options` (velikost, autoload) se zaškrtávátky a smazáním; ManageWP (`mwp_`, `mmb_`, `worker_`) a nastavení ElvisEK se nikdy nenabízí
### Šablona
- Přizpůsobit → Úvodní stránka: „Zobrazit nadpis webu s mottem“ (vypnuto = jen skrytý H1 pro čtečky a SEO)
- Přizpůsobit → Úvodní stránka: „Počet článků pod hlavním článkem“ (3–48, výchozí 6); stejný počet i pro „Načíst další“ a další strany, nezávisle na Nastavení → Čtení

## v0.3.1 — 2026-09-30
### Šablona
- Dlaždice témat se přizpůsobí počtu: vyvážené řádky (10 → 1 řádek, 12 → 2×6; tablet i mobil zvlášť), max. počet v Přizpůsobit snížen na 12
- Hledání v hlavičce: pole vyjede vedle lupy (max. 320 px) místo panelu přes celou šířku; lupa s textem hledá, prázdná zavře, Esc zavře
- Kompaktní lišta: při více než 8 tématech bez ikon a těsněji; maska na okraji jen když lišta opravdu přetéká
### ElvisEK Core
- Beze změny funkcí (verze srovnaná se šablonou kvůli společnému release)
### Dokumentace
- README: release s anotovaným tagem (`git tag -a`)

## v0.3.0 — 2026-09-30
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

## v0.1.0 — 2026-09-30
- První verze šablony ElvisEK (návrh D · Magazín), GitHub Releases + updater
