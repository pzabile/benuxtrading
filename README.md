# BENUX Trading — Forex Trading Dashboard

A self-hosted PHP/MySQL trading journal & analytics dashboard, branded **BENUX Trading**.
Log every trade, attach screenshots (uploads or external links like TradingView),
mark TP / SL / BE / Manual close, and watch your stats — win rate, profit factor,
expectancy, max drawdown, equity curve, by-pair / by-hour / by-session / by-strategy
breakdowns, plus automated coaching insights.

> Drop the `public/` folder into Hostinger's `public_html` and you're live.

---

## Features

- **Trade journal** — pair, direction, lot size, entry / exit / SL / TP, fees, risk %, R:R planned & actual, outcome (`OPEN`, `TP`, `SL`, `BE`, `MANUAL`), session (auto-detected), strategy, setup, tags, emotion, confidence, notes, mistakes / lessons.
- **Screenshots** — multiple per trade. Either paste external URLs (TradingView snapshot links etc.), or upload PNG/JPG/WebP files (stored under `public/uploads/u<userId>/`).
- **Auto P&L math** — pip count and dollar P&L computed from price diff × pip-value × lot size. Works for majors, JPY pairs and gold.
- **Analytics**
  - Total / net / fees, win rate, profit factor, expectancy
  - Avg win / avg loss / best / worst trade
  - Equity curve, max drawdown, max winning / losing streak
  - P&L by pair, by hour-of-day, by day-of-week, by session, by strategy
  - "Coaching insights" — plain-English suggestions for what to change.
- **Filters & CSV export** — by pair, outcome, strategy, date range.
- **Multi-user** — register your own account, each user's data is isolated.
- **Branded** — black / gold BENUX. theme. Drop your own `logo.svg` to override.
- **Security** — PDO prepared statements, password hashing, CSRF tokens, HttpOnly session cookies, PHP execution disabled in `uploads/`.

---

## File map

```
benuxtrading/
├── includes/                 ← server-only PHP (NOT web-accessible on Hostinger)
│   ├── config.php            ← edit DB credentials here
│   ├── db.php
│   ├── auth.php
│   ├── helpers.php
│   ├── header.php
│   └── footer.php
├── public/                   ← upload contents to public_html/
│   ├── index.php
│   ├── install.php           ← run once, then DELETE
│   ├── register.php / login.php / logout.php
│   ├── dashboard.php
│   ├── trades.php / add_trade.php / edit_trade.php / trade_view.php / delete_trade.php
│   ├── analytics.php
│   ├── settings.php
│   ├── export.php            ← CSV download
│   ├── uploads/              ← screenshot storage (writable)
│   └── assets/css|js|img
├── sql/schema.sql            ← table definitions
└── README.md
```

---

## Deploy on Hostinger (step by step)

### 1. Create a MySQL database

In hPanel → **Databases → MySQL Databases**, create:

- a database (e.g. `u123456_benux`)
- a user with full privileges on that DB
- note the **host**, **db name**, **user**, **password**

### 2. Upload the files

You can use Hostinger File Manager or FTP.

- Upload the **contents of `public/`** into `public_html/` (so `index.php` lives at the web root).
- Upload the `includes/` and `sql/` folders **one level above** `public_html/` (recommended), e.g. into `domains/yourdomain.com/`.

If your hosting plan does not allow files above the web root, you can put `includes/` and `sql/` next to `public_html/` (Hostinger does support this) or, as a fallback, upload them inside `public_html/` — the bundled `.htaccess` blocks direct access to `*.sql`, `*.md`, `*.env`, `*.ini`. PHP files outside the document root remain protected as long as they're not under `public_html/`.

> If you put `includes/` somewhere non-default, edit the `require __DIR__ . '/../includes/...'` paths at the top of each file under `public/`.

### 3. Edit `includes/config.php`

```php
'db' => [
    'host' => 'localhost',
    'name' => 'u123456_benux',
    'user' => 'u123456_benux',
    'pass' => 'YOUR_PASSWORD',
],
'cookie_secure' => true,        // turn on once HTTPS is active
```

### 4. Run the installer

Visit `https://yourdomain.com/install.php`. It creates the tables.
**Then delete `install.php`.**

### 5. Create your account

Visit `https://yourdomain.com/register.php` and start logging trades.

### 6. Make sure uploads work

The folder `public_html/uploads/` must be **writable** by PHP (chmod `755` is usually fine on Hostinger). The bundled `uploads/.htaccess` disables PHP execution inside it.

---

## Local development (optional)

```bash
# from the project root
php -S localhost:8000 -t public
# open http://localhost:8000/install.php
```

Set DB credentials via env vars if you don't want to edit `config.php`:

```bash
DB_HOST=127.0.0.1 DB_NAME=benux_trading DB_USER=root DB_PASS= php -S localhost:8000 -t public
```

---

## How the math works

- **Pips** are computed from the price diff, with the standard convention:
  - JPY pairs: 1 pip = 0.01 → diff × 100
  - XAU/USD:  1 pip = 0.10 → diff × 10
  - everything else: 1 pip = 0.0001 → diff × 10,000
- **Dollar P&L** = pips × pip-value-per-lot × lot-size − fees.
  Default pip values per 1.0 lot: ~$10 majors, ~$10 gold, ~$9 JPY pairs (rough; you can refine in `includes/helpers.php`).
- **R:R planned** = |TP − entry| / |entry − SL|.
- **R:R actual** = |exit − entry| / |entry − SL|, signed by P&L.
- **Session** is auto-detected from open time (UTC):
  Asian 00–08, London 07–16, NY 13–22, Overlap when London & NY both apply.
- **Equity curve**: starts from your `starting_balance` (set in Register or Settings), then accumulates closed P&L in chronological order. Max drawdown = max distance below the running peak.

---

## Customization

- **Logo**: replace `public/assets/img/logo.svg` with your own (or a PNG; update `header.php`).
- **Brand colors**: edit the CSS variables at the top of `public/assets/css/style.css`:
  - `--ink`, `--gold`, `--cream`, `--pos`, `--neg`.
- **Pairs list**: edit the `<datalist id="pairs">` in `add_trade.php` to add your favourites.
- **Pip values**: tune `pip_value_per_lot()` in `includes/helpers.php` for your broker.

---

## Roadmap ideas

- Tag-based filtering, monthly P&L heatmap, MAE/MFE tracking,
  account-import from MT4/MT5 CSV, multi-account support, dark/light toggle.

PRs welcome.
