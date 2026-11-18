# Running ProSpective locally

Laravel is installed directly in this workspace. The folder was empty before
installation. No dashboard or other application features have been added.

Verified installation: Laravel **12.72.0**, PHP **8.2.12**, Composer **2.9.3**,
Node.js **24.16.0**, npm **11.13.0**, and Vite **6.4.6**. Laravel 12 is the
latest major compatible with the installed PHP 8.2; Laravel 13 requires PHP 8.3.

Verification passed: Composer validation and platform requirements, both bundled
Laravel tests, file cache and session round trips, encryption, the Vite build,
and HTTP 200 responses for the homepage, health endpoint, and compiled assets.
Composer and npm reported no security advisories. No database was connected.

Package downloads initially failed under the terminal sandbox's network
restrictions; rerunning the installers with network access resolved this.
An unrelated Composer home Git ownership warning was avoided by isolating
Composer's temporary home and cache during installation; no global Git settings
were changed.

## Requirements

- PHP 8.2 or later within Laravel 12's supported PHP versions
- Composer 2
- Node.js and npm (the installed Node.js 24 works with this project's Vite build)

## Start the application

In PowerShell, from `C:\xampp\htdocs\Acalytics`:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Open <http://127.0.0.1:8000>. The compiled frontend assets are in `public/build`,
so a separate Vite server is only needed while editing frontend assets.

For frontend development, run this in a second terminal:

```powershell
npm.cmd run dev -- --host=127.0.0.1
```

Alternatively, `composer run dev` starts both Laravel and Vite together.
Stop foreground development servers with Ctrl+C. Stop the already running
Laravel server before starting another server on port 8000.

## Build and verify

```powershell
npm.cmd run build
php artisan test
php artisan about
```

Use `npm.cmd` in PowerShell because this computer's execution policy blocks the
`npm.ps1` launcher. No system execution policy changes are necessary.

## Local configuration

The local `.env` contains the generated application key and is excluded from
version control. `.env.example` contains the same database-free defaults without
the key. Do not regenerate the key during normal startup.

- Application name: `ProSpective`
- URL: `http://127.0.0.1:8000`
- Sessions: `file`
- Cache: `file`
- Queues: `sync`
- Failed queue job storage: disabled
- Database: disabled using `DB_CONNECTION=none`

There is intentionally no configured connection named `none`. Database access
will fail explicitly until a real connection is deliberately configured. No
database file has been created and no migrations have been run. The standard
Laravel migration files are unused scaffolding.

Composer's automatic database creation and migration commands have also been
removed. The development command runs Laravel and Vite without a queue worker
or the Unix-specific Pail log viewer.

## Restore dependencies if needed

```powershell
composer install
npm.cmd ci
npm.cmd run build
```

Only if `.env` is missing, copy `.env.example` to `.env` and run
`php artisan key:generate`. After changing configuration, run
`php artisan optimize:clear`.

## Deploy ProSpective to Vercel

Vercel builds the Laravel application and the Vite assets from `Dockerfile.vercel`.
The image serves `public/` through FrankenPHP and routes dashboard requests to Laravel.
The demonstration data is included with the application and uses no database.

From the project directory in PowerShell, sign in and link the Vercel project:

```powershell
npx.cmd vercel@66.1.0 login
npx.cmd vercel@66.1.0 link --project prospective
```

After linking, add a dedicated Laravel key to the Preview environment as a secret:

```powershell
$prospectivePreviewKey = (php artisan key:generate --show).Trim()
$prospectivePreviewKey | npx.cmd vercel@66.1.0 env add APP_KEY --environment preview --sensitive
Remove-Variable prospectivePreviewKey
npx.cmd vercel@66.1.0 deploy
```

Use the preview URL to check `/up`, the dashboard, the filter and table requests,
and CSV export. For a production deployment, add a separate `APP_KEY` to the
Production environment and run `npx.cmd vercel@66.1.0 deploy --prod`.

`.vercelignore` includes only the deployment source, and `.dockerignore` omits
local runtime files. Vercel supplies the deployment key at runtime. The
container sets `APP_ENV=production`, `APP_DEBUG=false`, and in-memory session
and cache drivers for this demo.

[Vercel's Laravel deployment guide](https://vercel.com/kb/guide/laravel-php-with-docker)
