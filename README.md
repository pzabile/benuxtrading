# BENUX Trading — Forex Trading Dashboard

A self-hosted PHP/MySQL trading journal & analytics dashboard, branded **BENUX Trading**.
Log every trade, attach screenshots (uploads or external links like TradingView),
mark TP / SL / BE / Manual close, and watch your stats — win rate, profit factor,
expectancy, max drawdown, equity curve, by-pair / by-hour / by-session / by-strategy
breakdowns, plus automated coaching insights.

> Upload the whole project folder to Hostinger and you're live — no path juggling.

---

## Features

- **Trade journal** — pair, direction, lot size, entry / exit / SL / TP, fees, risk %, R:R planned & actual, outcome (`OPEN`, `TP`, `SL`, `BE`, `MANUAL`), session (auto-detected), strategy, setup, tags, emotion, confidence, notes, mistakes / lessons.
- **Screenshots** — multiple per trade. Either paste external URLs (TradingView snapshot links etc.), or upload PNG/JPG/WebP files (stored under `uploads/u<userId>/`).
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
benuxtrading/                ← upload this whole folder (or its contents)
├── index.php                ← web entry, redirects to login or dashboard
├── install.php              ← run ONCE, then DELETE
├── register.php / login.php / logout.php
├── dashboard.php
├── trades.php / add_trade.php / edit_trade.php / trade_view.php / delete_trade.php
├── analytics.php
├── settings.php
├── export.php               ← CSV download
├── .htaccess                ← blocks .sql / .md / .env / .ini at the web root
├── assets/                  ← css / js / logo
├── uploads/                 ← screenshot storage (writable, PHP execution disabled)
├── includes/                ← PHP includes (.htaccess denies all direct access)
│   ├── config.php           ← EDIT: DB credentials go here
│   ├── db.php / auth.php / helpers.php / header.php / footer.php
└── sql/schema.sql           ← table definitions, used by install.php
```

---

## Deploy on Hostinger (step by step)

### 1. Create a MySQL database

In hPanel → **Databases → MySQL Databases**, create:

- a database (e.g. `u123456_benux`)
- a user with full privileges on that DB
- note the **host**, **db name**, **user**, **password**

### 2. Upload the files (single-folder layout)

Using Hostinger File Manager (or FTP), upload **everything in this repo** into your domain's web root. Two common targets:

- **As the whole site**: drop the contents into `public_html/` so `index.php` lives at `https://yourdomain.com/`.
- **As a sub-app** (recommended if you already have a site): upload into a subfolder like `public_html/trading/` so it lives at `https://yourdomain.com/trading/`.

You do **not** need to put anything outside `public_html`. The bundled `.htaccess` files inside `includes/` and `sql/` already block direct browser access to those folders.

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

The `uploads/` folder must be **writable** by PHP (chmod `755` is usually fine on Hostinger). The bundled `uploads/.htaccess` disables PHP execution inside it.

---

## Local development (optional)

```bash
# from the project root
php -S localhost:8000
# open http://localhost:8000/install.php
```

Set DB credentials via env vars if you don't want to edit `config.php`:

```bash
DB_HOST=127.0.0.1 DB_NAME=benux_trading DB_USER=root DB_PASS= php -S localhost:8000
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
