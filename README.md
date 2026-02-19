# Secure Realtime Direct Chat (Laravel + Reverb)

A secure direct-messaging chat app built with Laravel 12, Blade, Reverb, and Echo.

## What This Project Does

- User registration and login with:
  - unique `username` (used as user ID)
  - password
- Private realtime messaging between users by recipient ID (`username`)
- Browser voice-note recording and sending
- Private broadcast channels (only sender/recipient receive events)
- Automatic message cleanup:
  - messages older than 10 minutes are deleted
  - uploaded voice files for deleted messages are also removed

## Tech Stack

- Laravel 12
- Laravel Reverb + Echo
- PHP 8.2+
- SQLite (default local DB)
- Vite + Tailwind CSS

## Security Measures Implemented

- Authentication required for chat routes
- Private broadcast channel authorization (`chat.user.{username}`)
- Strict input validation for usernames, recipient IDs, text, and voice uploads
- Session regeneration on login
- CSRF protection on forms/requests
- Rate limiting:
  - login
  - registration
  - sending messages
  - finding users
- Dependency security checks:
  - `composer audit` (no vulnerabilities found)
  - `npm audit --audit-level=high` (no vulnerabilities found)

## Requirements

- PHP 8.2+
- Composer 2+
- Node.js 20.19+ recommended (current lower versions may still build with warning)
- npm

## Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan storage:link
```

## Docker (Just Up)

If you want to run everything with one command:

```bash
docker compose up -d
```

This starts one container that runs:

- Laravel HTTP server on `3000`
- Reverb WebSocket server on host `3080` (mapped to container `8080`)
- Laravel scheduler worker (auto-prune)

Data persistence:

- SQLite database and uploaded voice files are stored in the Docker volume `chatroom-storage`.

Open:

- [http://localhost:3000](http://localhost:3000)

## Server `.env` Checklist

Before running on server, copy and edit:

```bash
cp .env.example .env
```

Set these values for your server:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=...` (generate with `php artisan key:generate`)
- `APP_URL=https://your-domain`
- `APP_PORT=3000`
- `PHP_SERVER_HOST=0.0.0.0`
- `PHP_SERVER_PORT=3000`
- `DB_CONNECTION=sqlite` (or your DB driver)
- `DB_DATABASE=/var/www/html/storage/app/database.sqlite` (if sqlite in Docker)
- `BROADCAST_CONNECTION=reverb`
- `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`
- `REVERB_HOST=127.0.0.1`
- `REVERB_PORT=8080`
- `REVERB_SCHEME=http`
- `REVERB_SERVER_HOST=0.0.0.0`
- `REVERB_SERVER_PORT=8080`
- `REVERB_PUBLIC_HOST=your-domain`
- `REVERB_PUBLIC_PORT=443` (or `3080` without TLS)
- `REVERB_PUBLIC_SCHEME=https` (or `http` without TLS)
- `MESSAGE_TTL_MINUTES=10`
- `VOICE_WIPE_PASSES=1` (increase for stricter best-effort wipe)

Important:

- Frontend websocket config is now injected from Laravel runtime config, so changing Reverb host/port/scheme in `.env` does not require rebuilding frontend assets.

Stop:

```bash
docker compose down
```

Remove containers + volumes:

```bash
docker compose down -v
```

## Run (Development)

```bash
composer dev
```

`composer dev` starts:

- Laravel HTTP server
- Reverb websocket server
- Scheduler worker (for auto-delete every 10 minutes by TTL)
- Vite dev server
- Laravel logs stream

Open:

- [http://127.0.0.1:3000](http://127.0.0.1:3000) (or `PHP_SERVER_PORT` from `.env`)

## Main Routes

Guest routes:

- `GET /login`
- `POST /login`
- `GET /register`
- `POST /register`

Authenticated routes:

- `GET /chat`
- `POST /messages`
- `GET /users/find?id={username}`
- `POST /logout`

Root:

- `GET /` redirects to `/login` (guest) or `/chat` (authenticated)

## Message API (`POST /messages`)

Fields:

- `recipient_id` (required): recipient username
- `message` (optional): text
- `voice` (optional): audio file

Rule:

- At least one of `message` or `voice` is required.

## Auto Deletion Logic

Command:

- `php artisan messages:prune`

Behavior:

- Deletes messages with `created_at <= now() - MESSAGE_TTL_MINUTES` (`10` by default)
- Deletes matching voice files from `storage/app/public/chat-voices`
- Overwrites voice file bytes before delete (best-effort secure wipe, configurable by `VOICE_WIPE_PASSES`)
- For SQLite: runs `secure_delete`, WAL truncate checkpoint, and `VACUUM` after prune

Scheduling:

- Registered in `routes/console.php` with `everyMinute()`
- Threshold enforces 10-minute TTL

## Testing

Run all tests:

```bash
php artisan test
```

Current tests cover:

- auth flow (register/login/invalid login)
- private message send rules
- user lookup by ID
- guest access blocking
- voice upload
- pruning old messages

## Quality / Build

```bash
./vendor/bin/pint
npm run build
composer validate --no-check-publish
```

## Key Files

- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/ChatMessageController.php`
- `app/Http/Controllers/UserLookupController.php`
- `app/Events/MessageSent.php`
- `routes/web.php`
- `routes/channels.php`
- `app/Console/Commands/PruneExpiredMessages.php`
- `routes/console.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/chat.blade.php`
- `resources/js/chat.js`

## Notes

- User ID is exactly the `username` and must be unique.
- For production, keep HTTPS enabled and ensure scheduler is always running.
- If you keep external backups/snapshots, deleted data can still exist there. Disable or shorten backup retention for strict data destruction policies.
