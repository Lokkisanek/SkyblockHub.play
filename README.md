# SkyblockHub

Webová aplikace pro hráče [Hypixel Skyblock](https://hypixel.net). Poskytuje nástroje pro sledování Bazaaru, správu portfolia, hledání dungeon party, crafting arbitráž a BIN sniper.

## Tech Stack

- **Backend:** [Laravel 11](https://laravel.com/docs/11.x) (PHP 8.2+)
- **Frontend:** [Vue 3](https://vuejs.org/) + [Inertia.js](https://inertiajs.com/) + [Tailwind CSS](https://tailwindcss.com/)
- **WebSockets:** [Laravel Reverb](https://reverb.laravel.com/)
- **Databáze:** SQLite (výchozí)
- **Build tool:** [Vite](https://vitejs.dev/)
- **Přihlašování:** Discord OAuth přes [Laravel Socialite](https://laravel.com/docs/11.x/socialite)

## Funkce

| Stránka | Popis |
|---|---|
| `/bazaar` | Sledování cen na Bazaaru v reálném čase |
| `/portfolio` | Správa a sledování vlastních investic |
| `/crafting` | Crafting arbitráž – kde vydělat craftěním |
| `/bin-sniper` | Sledování nejnižších BIN aukcí s alertem |
| `/dungeon-party` | Hledání hráčů do Dungeon party |
| `/profile-stats` | Statistiky Skyblock profilů |

## Požadavky

- [PHP 8.2+](https://www.php.net/downloads) s extensions: `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`
- [Composer](https://getcomposer.org/)
- [Node.js 18+](https://nodejs.org/) + npm

## Lokální spuštění (Windows - PowerShell)

Krátké shrnutí: potřebujete nainstalované `PHP` (8.2+), `Composer`, `Node.js` (18+), a volitelně databázi (SQLite funguje výchozí).

1) Klonování repozitáře a přechod do složky

```powershell
git clone <url-repozitare>
Set-Location SkyblockHub.play
```

2) PHP závislosti

```powershell
composer install
```

3) Vytvoření a nastavení prostředí

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Otevřete `.env` v editoru a nastavte minimálně hodnoty pro databázi a Discord OAuth:

- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET`, `DISCORD_REDIRECT_URI`

Příklad pro Discord callback (lokálně):

```dotenv
DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback
```

4) Migrace databáze (volitelné seed)

```powershell
php artisan migrate --seed
```

5) Node závislosti a kompilace frontend assets

Pro vývoj (hot-reload):

```powershell
npm install
npm run dev
```

Pro produkci (minifikace):

```powershell
npm install
npm run build
```

6) Spuštění aplikace a služeb (v samostatných PowerShell oknech)

```powershell
# Webový server (hlavní aplikace)
php artisan serve --host=127.0.0.1 --port=8000

# WebSocket server (Reverb) - pro real-time aktualizace
php artisan reverb:start

# Queue worker - zpracování úloh na pozadí
php artisan queue:work
```

Aplikace bude dostupná na `http://localhost:8000`.

7) Poznámky pro produkci

- Spusťte `npm run build` a nastavte webserver (Nginx/Apache) směrovaný na `public/`.
- Nakonfigurujte environment proměnné (databáze, cache, queue, reverb) a použijte proces manager (supervisor) pro `php artisan queue:work` a `php artisan reverb:start`.

Pokud chcete, mohu připravit krátký PowerShell script, který provede většinu kroků automaticky.

## Užitečné odkazy

- [Laravel dokumentace](https://laravel.com/docs/11.x)
- [Inertia.js dokumentace](https://inertiajs.com/)
- [Laravel Reverb](https://reverb.laravel.com/)
- [Hypixel Skyblock Wiki](https://wiki.hypixel.net/)
- [Hypixel API](https://api.hypixel.net/)
- [Discord Developer Portal](https://discord.com/developers/applications)

