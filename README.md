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

## Spuštění bez Dockeru

### 1. Naklonuj repozitář a přejdi do složky

```bash
git clone <url-repozitare>
cd SkyblockHub.play
```

### 2. Nainstaluj PHP závislosti

```bash
composer install
```

### 3. Nastav prostředí

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Discord OAuth

Vytvoř aplikaci na [Discord Developer Portal](https://discord.com/developers/applications), zkopíruj Client ID a Secret a vlož do `.env`:

```dotenv
DISCORD_CLIENT_ID=tvoje_client_id
DISCORD_CLIENT_SECRET=tvuj_client_secret
DISCORD_REDIRECT_URI=http://localhost:8000/auth/discord/callback
```

### 5. Databáze a migrace

SQLite databáze se vytvoří automaticky:

```bash
php artisan migrate
```

### 6. Nainstaluj Node závislosti a zkompiluj assets

Pro vývoj (live reload):
```bash
npm install
npm run dev
```

Pro produkci:
```bash
npm install
npm run build
```

### 7. Spusť aplikaci

Každý z následujících příkazů spusť v **samostatném terminálu**:

```bash
# Webový server
php artisan serve

# WebSocket server (potřebný pro real-time aktualizace Bazaaru)
php artisan reverb:start

# Queue worker (potřebný pro zpracování úloh na pozadí)
php artisan queue:work
```

Aplikace poběží na **http://localhost:8000**.

## Užitečné odkazy

- [Laravel dokumentace](https://laravel.com/docs/11.x)
- [Inertia.js dokumentace](https://inertiajs.com/)
- [Laravel Reverb](https://reverb.laravel.com/)
- [Hypixel Skyblock Wiki](https://wiki.hypixel.net/)
- [Hypixel API](https://api.hypixel.net/)
- [Discord Developer Portal](https://discord.com/developers/applications)

