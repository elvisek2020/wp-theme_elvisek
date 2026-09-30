# Changelog

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
