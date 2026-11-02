# Změny kanonických modulů z tohoto webu

www.elvisek.cz je **referenční web** skillu `wp-custom-theme`. Nové a upravené kanonické moduly
vznikají a testují se tady, skill je pak přebírá do kanonu (`scripts/modules.py add` / ruční
převzetí). Po převzetí se tady položka označí jako převzatá a web se srovná přes
`modules.py status` / `sync`.

Stav proti kanonu: `python3 -I scripts/modules.py status <repo>/plugins/elvisek-core`
(spouští se ze složky skillu).

## Čeká na převzetí do skillu

### v0.17.1 — two-factor 1.0.1

- Klíč k ručnímu zadání se zobrazuje vcelku, bez mezer po čtyřech znacích (snazší kopírování do Apple Hesel a dalších aplikací).

### v0.17.0 — dvoufázové ověření, blokace sítí, delší log přihlášení

Motivace: log přihlášení ukázal pomalý distribuovaný útok na dvě jména (ElvisEK, Claude Laura) —
pokus každých 9–14 minut, pokaždé z jiné IP ve stejné /24. Blokace po 5 pokusech z jedné IP
v 15 minutách nezabrala ani jednou za den, log se 200 událostmi držel jen 4 dny.

| Modul | Verze | Změna |
|---|---|---|
| `two-factor` | **nový 1.0.0** (v0.17.1: 1.0.1) | TOTP (RFC 6238) se záložními kódy, QR kód v SVG bez knihoven |
| `login-guard` | 1.0.0 → 1.1.0 | blokace podle sítě /24 (IPv6 /64), nastavitelné okno pro počítání pokusů |
| `login-log` | 1.0.0 → 1.1.0 | nastavitelná délka a stáří logu, nová událost `twofactor` |
| `health` | 1.0.0 → 1.1.0 | čte celý log (`ekc_login_log_max()`), počítá i `twofactor`, řádek „Chybný kód 2FA za 24 h“ |
| `common` | 1.0.1 → 1.0.2 | `ekc_login_log_row()` propustí událost `twofactor` |

#### two-factor 1.0.0 (nový)

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

#### login-guard 1.1.0

- `login_window` (int, minuty, výchozí 15, 5–1440) — okno pro počítání chybných pokusů.
- `login_subnet` (bool, výchozí vypnuto) — klíč v `ekc_lockouts` je síť (`203.0.113.0/24`,
  `2001:db8:1:2::/64`) místo IP. `ekc_login_locked_until( $ip )` kontroluje IP i její síť, takže
  přepnutí režimu neobejde běžící blokace.
- Záznam v `ekc_lockouts` má navíc `i` (poslední IP); tabulka blokací ukazuje síť i poslední IP,
  rušení blokace přijímá IP i síť (`ekc_login_guard_valid_key()`).
- Nové funkce: `ekc_login_window()`, `ekc_ip_subnet()`, `ekc_login_guard_key()`,
  `ekc_login_guard_valid_key()`, `ekc_login_guard_summary()`.
- Výchozí hodnoty se nemění, chování stávajících webů zůstává stejné.

#### login-log 1.1.0

- `login_log_max` (int, výchozí `EKC_LOGIN_LOG_MAX` = 200, 50–5000) a `login_log_days`
  (int, výchozí `EKC_LOGIN_LOG_DAYS` = 90, 7–365); funkce `ekc_login_log_max()`,
  `ekc_login_log_days()`. Konstanty v common zůstávají jako výchozí hodnoty (a pro migraci logu).
- Událost `twofactor` = správné heslo, chybný kód 2FA (červená tečka „správné heslo, chybný kód
  2FA“) — heslo nejspíš zná někdo cizí.

#### Do dokumentace skillu

- `references/modules-api.md`: tabulka nastavení (`login_window`, `login_subnet`, `login_log_max`,
  `login_log_days`, `two_factor`, `two_factor_required`), datový formát `ekc_login_log`
  (`e: success|failed|twofactor|lockout`) a `ekc_lockouts` (klíč IP nebo síť, pole `i`),
  user meta `ekc_2fa_*`.
- `references/plugin.md` / seznam modulů: `two-factor` jako volitelný modul (Replaces:
  two-factor, wp-2fa, wordfence-login-security, google-authenticator).

## Převzato do skillu

- v0.16.0 — hardening 1.1.0, security-headers 1.1.0, updater 1.1.0, term-image 1.1.0,
  lightbox 1.1.0 (z projektového kódu tohoto webu).
- v0.14.0 — comment-notify, pageviews, typography (šablona), uninstall.
