# Změny kanonických modulů z tohoto webu

www.elvisek.cz je **referenční web** skillu `wp-custom-theme`. Nové a upravené kanonické moduly
vznikají a testují se tady, skill je pak přebírá do kanonu (`scripts/modules.py add` / ruční
převzetí). Po převzetí se tady položka označí jako převzatá a web se srovná přes
`modules.py status` / `sync`.

Stav proti kanonu: `python3 -I scripts/modules.py status <repo>/plugins/elvisek-core`
(spouští se ze složky skillu).

## Čeká na převzetí do skillu

Zatím nic. Na příští verzi modulu `two-factor`: komentář v hlavičce uvádí jako příklad klienta
aplikačních hesel „wp.py“ (nástroj tohoto webu) — nahradit obecným popisem.

## Převzato do skillu

### v0.17.0 + v0.18.0 — převzato 2026-11-02

Agent pro skilly převzal do kanonu všech 10 modulů z tagu `v0.18.0` beze změny (bajtově shodné):
common 1.0.3, settings 1.1.0, health 1.1.0, login-guard 1.2.0, login-log 1.2.0, two-factor 1.1.0
(nový, volitelný), hardening 1.1.1, security-headers 1.1.1, llms-txt 1.0.1, sitemap 1.0.1.
Dokumentace skillu doplněná (modules-api.md, plugin.md, SKILL.md, dev-and-testing.md).
`modules.py status` na `plugins/elvisek-core` hlásí vše aktuální. V repu claude-skills commit
`10998c9`, skill je nahraný do claude.ai.

#### v0.18.0 — přehlednější nastavení, whitelist, stránkovaný log

| Modul | Verze | Změna |
|---|---|---|
| `settings` | 1.0.0 → 1.1.0 | stránka nastavení po záložkách (sekce), podskupiny a podřízené volby |
| `common` | 1.0.2 → 1.0.3 | `ekc_settings_register()` přijímá `group` a `parent`; `ekc_maintenance_section()` přijímá `hidden` |
| `login-guard` | 1.1.0 → 1.2.0 | whitelist `login_whitelist`, přehled blokací přesunutý z Údržby do logu přihlášení |
| `login-log` | 1.1.0 → 1.2.0 | stránkování (filtr `ekc_login_log_per_page`, výchozí 50) |
| `two-factor` | 1.0.0 → 1.1.0 | klíč k ručnímu zadání bez mezer; `group`/`parent` u nastavení |
| `hardening` | 1.1.0 → 1.1.1 | `group` u nastavení |
| `security-headers` | 1.1.0 → 1.1.1 | `group`/`parent` u nastavení |
| `llms-txt` | 1.0.0 → 1.0.1 | `group`/`parent` u nastavení |
| `sitemap` | 1.0.0 → 1.0.1 | `group` u nastavení |

##### settings 1.1.0 a common 1.0.3

- Nová (volitelná) pole v `ekc_settings_register()`: `group` (podnadpis uvnitř sekce, pořadí podle
  první registrace) a `parent` (klíč bool přepínače; řádek je odsazený se šipkou ↳ a ztlumený, když
  je přepínač vypnutý — jen vzhled, hodnota se dál ukládá).
- Stránka: záložky `nav-tab-wrapper` (sekce = karta `#ekc-sec-<slug>`, slug z názvu sekce bez
  diakritiky), tlačítko Uložit v každé kartě; vybraná záložka se drží v kotvě a přes
  `_wp_http_referer` přežije uložení. Bez JS se zobrazí všechny karty pod sebou.
- `ekc_maintenance_section( …, array( 'hidden' => true ) )` — sekce se v Údržbě nezobrazí, jen
  obsluhuje úlohy tlačítek z jiné stránky.
- Skupiny na tomto webu: Přihlášení (Omezení pokusů / Log přihlášení / Dvoufázové ověření),
  Zabezpečení (WordPress / Hlášení zranitelností (security.txt) / HTTP hlavičky),
  SEO (/llms.txt / Sitemapa). Hlavní přepínače skupin mají kratší popisky (Omezovat pokusy,
  Zapisovat přihlášení, Povolit uživatelům), aby se neopakoval nadpis skupiny.

##### login-guard 1.2.0

- `login_whitelist` (list, výchozí prázdný, `sanitize` → `ekc_login_whitelist_sanitize()`): adresy
  a rozsahy CIDR (IPv4 i IPv6), neplatné položky se zahodí. Z adres na whitelistu se pokusy
  nepočítají, `ekc_login_locked_until()` pro ně vrací 0 (ani blokace sítě je nezastaví), hláška
  o zbývajících pokusech se nezobrazuje. Nápověda ukazuje aktuální adresu správce.
- Nové funkce: `ekc_ip_in_range( $ip, $range )`, `ekc_login_whitelisted( $ip = '' )`.
- Sekce `login` v Údržbě je `hidden`; pravidlo, whitelist, tabulka blokací a tlačítka Zrušit /
  Zrušit všechny jsou v kartě „Blokace“ nad logem přihlášení (`ekc_login_log_before`).

##### login-log 1.2.0

- Stránkování po 50 (`paged`, filtr `ekc_login_log_per_page`, min. 10), počet „1–50 z 190“
  nahoře i dole.

#### v0.17.0 — dvoufázové ověření, blokace sítí, delší log přihlášení

Motivace: log přihlášení ukázal pomalý distribuovaný útok na dvě jména (ElvisEK, Claude Laura) —
pokus každých 9–14 minut, pokaždé z jiné IP ve stejné /24. Blokace po 5 pokusech z jedné IP
v 15 minutách nezabrala ani jednou za den, log se 200 událostmi držel jen 4 dny.

| Modul | Verze | Změna |
|---|---|---|
| `two-factor` | **nový 1.0.0** | TOTP (RFC 6238) se záložními kódy, QR kód v SVG bez knihoven |
| `login-guard` | 1.0.0 → 1.1.0 | blokace podle sítě /24 (IPv6 /64), nastavitelné okno pro počítání pokusů |
| `login-log` | 1.0.0 → 1.1.0 | nastavitelná délka a stáří logu, nová událost `twofactor` |
| `health` | 1.0.0 → 1.1.0 | čte celý log (`ekc_login_log_max()`), počítá i `twofactor`, řádek „Chybný kód 2FA za 24 h“ |
| `common` | 1.0.1 → 1.0.2 | `ekc_login_log_row()` propustí událost `twofactor` |

##### two-factor 1.0.0 (nový)

- **Nastavení** (sekce Přihlášení): `two_factor` (bool, výchozí zapnuto: uživatelé si ověření můžou
  zapnout v profilu), `two_factor_required` (bool, výchozí vypnuto: správce bez 2FA pustí
  administrace jen do profilu).
- **Profil**: QR kód + klíč k ručnímu zadání, potvrzení prvním kódem; 10 záložních kódů
  (`abcd-efgh`, zobrazí se jednou); nové kódy a vypnutí jen s platným kódem. Správce může jinému
  uživateli ověření vypnout bez kódu (ztracený telefon).
- **Přihlášení**: filtr `authenticate` (priorita 100, po login-guard 99). Po správném hesle
  na wp-login.php se nenastaví cookie, uloží se rozpracované přihlášení (token, platnost 10 min,
  zapamatovat, redirect_to, interim-login) a vykreslí se formulář na kód
  (`wp-login.php?action=ekc_2fa`, hook `login_form_ekc_2fa`). Po kódu `wp_set_auth_cookie()`
  a `do_action( 'wp_login' )`, takže login-log a login-guard fungují beze změny.
- **Bezpečnost**: tolerance ±1 krok (30 s), kód nejde použít dvakrát (`ekc_2fa_last`), 5 chybných
  kódů na jedno přihlášení, chybný kód = `wp_login_failed` s chybou `ekc_2fa_invalid` (počítá
  login-guard, login-log zapíše `twofactor`). Zablokovaná IP se ke kódu nedostane.
- **Ostatní cesty**: aplikační hesla projdou (`application_password_did_authenticate`), jiné
  ověření heslem mimo wp-login.php (XML-RPC, `wp_signon` z pluginů) účet s 2FA odmítne
  (`ekc_2fa_required`).
- **Další**: sloupec 2FA v přehledu uživatelů, řádek „Správci bez 2FA“ v Zdraví webu
  (`ekc_health_rows`).
- **User meta** (mažou se s pluginem, prefix `ekc_`): `ekc_2fa_secret`, `ekc_2fa_setup`,
  `ekc_2fa_backup`, `ekc_2fa_fresh`, `ekc_2fa_last`, `ekc_2fa_since`, `ekc_2fa_pending`.
- **Obnova přístupu**: jiný správce v profilu uživatele, nebo
  `wp user meta delete <uživatel> ekc_2fa_secret`, nebo vypnout `two_factor`.
- **QR**: bajtový režim, úroveň M, verze 1–15, výběr masky podle penalizace (pravidla 1, 2, 4).
  Ověřeno dekodérem jsQR pro verze 1, 7, 9 a 11 včetně UTF-8.

##### login-guard 1.1.0

- `login_window` (int, minuty, výchozí 15, 5–1440) — okno pro počítání chybných pokusů.
- `login_subnet` (bool, výchozí vypnuto) — klíč v `ekc_lockouts` je síť (`203.0.113.0/24`,
  `2001:db8:1:2::/64`) místo IP. `ekc_login_locked_until( $ip )` kontroluje IP i její síť, takže
  přepnutí režimu neobejde běžící blokace.
- Záznam v `ekc_lockouts` má navíc `i` (poslední IP); tabulka blokací ukazuje síť i poslední IP,
  rušení blokace přijímá IP i síť (`ekc_login_guard_valid_key()`).
- Nové funkce: `ekc_login_window()`, `ekc_ip_subnet()`, `ekc_login_guard_key()`,
  `ekc_login_guard_valid_key()`, `ekc_login_guard_summary()`.
- Výchozí hodnoty se nemění, chování stávajících webů zůstává stejné.

##### login-log 1.1.0

- `login_log_max` (int, výchozí `EKC_LOGIN_LOG_MAX` = 200, 50–5000) a `login_log_days`
  (int, výchozí `EKC_LOGIN_LOG_DAYS` = 90, 7–365); funkce `ekc_login_log_max()`,
  `ekc_login_log_days()`. Konstanty v common zůstávají jako výchozí hodnoty (a pro migraci logu).
- Událost `twofactor` = správné heslo, chybný kód 2FA (červená tečka „správné heslo, chybný kód
  2FA“) — heslo nejspíš zná někdo cizí.

##### Do dokumentace skillu (doplněno)

- `references/modules-api.md`: tabulka nastavení (`login_window`, `login_subnet`, `login_log_max`,
  `login_log_days`, `two_factor`, `two_factor_required`), datový formát `ekc_login_log`
  (`e: success|failed|twofactor|lockout`) a `ekc_lockouts` (klíč IP nebo síť, pole `i`),
  user meta `ekc_2fa_*`.
- `references/plugin.md` / seznam modulů: `two-factor` jako volitelný modul (Replaces:
  two-factor, wp-2fa, wordfence-login-security, google-authenticator).

### Starší

- v0.16.0 — hardening 1.1.0, security-headers 1.1.0, updater 1.1.0, term-image 1.1.0,
  lightbox 1.1.0 (z projektového kódu tohoto webu).
- v0.14.0 — comment-notify, pageviews, typography (šablona), uninstall.
