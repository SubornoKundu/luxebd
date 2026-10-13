# Vireluxe

Online perfume shop for Bangladesh. Laravel 13 (PHP 8.4), Vue 3, Inertia, Tailwind 4, MySQL.

## Setup

```bash
composer install
npm install
cp .env.example .env      # then fill DB_* and MAIL_*
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=VireluxeSeeder          # brands, categories, banners, shop details
php artisan shop:admin xopsharip@gmail.com --password='YourStrongPassword' --replace
npm run build
php artisan shop:check
```

Admin sign-in: `/admin/login`. Customers sign in at `/login`.

## How ordering works

- Customer pays 50% advance by bKash (to the shop's bKash number) and enters the sending number and Transaction ID.
- The remaining 50% is paid in cash to the delivery man.
- Cash on delivery (full amount) is also available.
- There is no online card gateway and no demo or test login.

## Accounts

- Admins are created with `php artisan shop:admin` or in Admin > Admins. At least one admin always remains.
- Forgot password sends a 6-digit code by email (Gmail SMTP App Password, see `.env.example`).

## Emails

Only two kinds are sent: password reset codes, and order processing updates (`SHOP_ORDER_EMAILS=processing`).

## Tests

```bash
php artisan test
```

## Security notes

Rate limiting on sign-in, reset and checkout; security headers; uploads are re-encoded and cannot run scripts; `.env` is blocked by `public/.htaccess`. Keep `APP_DEBUG=false` in production and use a strong admin password.
