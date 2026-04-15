# Meridian Records & Compliance Backend

A comprehensive records management and compliance platform built with Laravel, providing music library management, behavioral analytics, notification workflows, disciplinary tracking, lab-style result entry, and full audit logging.

## Tech Stack

- **Runtime:** PHP 8.3
- **Framework:** Laravel 11 with Sanctum authentication
- **Database:** MySQL 8.0
- **Web Server:** Nginx 1.25 (Alpine)
- **Containerization:** Docker / Docker Compose

## Quick Start

```bash
# Clone and start
git clone <repository-url>
cd repo
docker compose up -d --build
```

The application will be available at **http://localhost:8080**.

## Default Admin Credentials

| Field    | Value              |
|----------|--------------------|
| Username | `admin`            |
| Password | `Admin@Password1`  |

Login via `POST /api/v1/auth/login` with `{ "username": "admin", "password": "Admin@Password1" }`.

## API Overview

All API endpoints are prefixed with `/api/v1`. Authentication is via Laravel Sanctum bearer tokens.

### Authentication

| Method | Endpoint              | Description          |
|--------|-----------------------|----------------------|
| POST   | `/auth/login`         | Obtain bearer token  |
| POST   | `/auth/logout`        | Revoke current token |
| POST   | `/auth/logout-all`    | Revoke all tokens    |
| GET    | `/auth/me`            | Current user profile |

### Admin (Users, Roles, Permissions)

| Method | Endpoint                            | Description              |
|--------|-------------------------------------|--------------------------|
| GET    | `/admin/users`                      | List users               |
| POST   | `/admin/users`                      | Create user              |
| PUT    | `/admin/users/{id}`                 | Update user              |
| POST   | `/admin/users/{id}/roles`           | Assign roles             |
| DELETE | `/admin/users/{id}/roles/{roleId}`  | Remove role              |
| POST   | `/admin/service-accounts`           | Create service account   |
| POST   | `/admin/service-accounts/{id}/rotate` | Rotate credentials     |
| GET    | `/admin/roles`                      | List roles               |
| POST   | `/admin/roles`                      | Create role              |
| PUT    | `/admin/roles/{id}`                 | Update role              |
| POST   | `/admin/roles/{id}/permissions`     | Assign permissions       |
| GET    | `/admin/permissions`                | List permissions         |

### Music Library (Songs, Albums, Playlists)

| Method | Endpoint                                  | Description            |
|--------|-------------------------------------------|------------------------|
| GET    | `/songs`                                  | List songs             |
| POST   | `/songs`                                  | Create song            |
| GET    | `/songs/{id}`                             | Show song              |
| PUT    | `/songs/{id}`                             | Update song            |
| DELETE | `/songs/{id}`                             | Delete song            |
| POST   | `/songs/{id}/publish`                     | Publish song           |
| POST   | `/songs/{id}/unpublish`                   | Unpublish song         |
| POST   | `/songs/{id}/version`                     | Bump version           |
| POST   | `/songs/{id}/cover-art`                   | Upload cover art       |
| GET    | `/albums`                                 | List albums            |
| POST   | `/albums`                                 | Create album           |
| GET    | `/albums/{id}`                            | Show album             |
| PUT    | `/albums/{id}`                            | Update album           |
| DELETE | `/albums/{id}`                            | Delete album           |
| POST   | `/albums/{id}/publish`                    | Publish album          |
| POST   | `/albums/{id}/unpublish`                  | Unpublish album        |
| POST   | `/albums/{id}/version`                    | Bump version           |
| POST   | `/albums/{id}/cover-art`                  | Upload cover art       |
| GET    | `/albums/{id}/songs`                      | List album songs       |
| POST   | `/albums/{id}/songs`                      | Add song to album      |
| DELETE | `/albums/{id}/songs/{songId}`             | Remove song from album |
| GET    | `/playlists`                              | List playlists         |
| POST   | `/playlists`                              | Create playlist        |
| GET    | `/playlists/{id}`                         | Show playlist          |
| PUT    | `/playlists/{id}`                         | Update playlist        |
| DELETE | `/playlists/{id}`                         | Delete playlist        |
| POST   | `/playlists/{id}/publish`                 | Publish playlist       |
| POST   | `/playlists/{id}/unpublish`               | Unpublish playlist     |
| POST   | `/playlists/{id}/version`                 | Bump version           |
| GET    | `/playlists/{id}/songs`                   | List playlist songs    |
| POST   | `/playlists/{id}/songs`                   | Add song to playlist   |
| DELETE | `/playlists/{id}/songs/{songId}`          | Remove song            |

### Behavior & Analytics

| Method | Endpoint                              | Description              |
|--------|---------------------------------------|--------------------------|
| POST   | `/behavior/events`                    | Record behavior event    |
| GET    | `/behavior/events`                    | List behavior events     |
| GET    | `/users/{id}/profile`                 | Get user profile         |
| POST   | `/users/{id}/profile/recompute`       | Recompute user profile   |
| GET    | `/recommendations/{userId}`           | Get recommendations      |

### Notifications

| Method | Endpoint                              | Description                |
|--------|---------------------------------------|----------------------------|
| GET    | `/notification-templates`             | List templates             |
| POST   | `/notification-templates`             | Create template            |
| PUT    | `/notification-templates/{id}`        | Update template            |
| DELETE | `/notification-templates/{id}`        | Delete template            |
| POST   | `/notifications/send`                 | Send notification          |
| POST   | `/notifications/send-bulk`            | Send bulk notifications    |
| GET    | `/notifications`                      | List user notifications    |
| POST   | `/notifications/read-all`             | Mark all as read           |
| GET    | `/notifications/unread-count`         | Get unread count           |
| POST   | `/notifications/{id}/read`            | Mark notification as read  |
| GET    | `/subscriptions`                      | List subscriptions         |
| PUT    | `/subscriptions`                      | Update subscriptions       |

### Rewards & Penalties

| Method | Endpoint                                    | Description              |
|--------|---------------------------------------------|--------------------------|
| GET    | `/evaluation-cycles`                        | List cycles              |
| POST   | `/evaluation-cycles`                        | Create cycle             |
| GET    | `/evaluation-cycles/{id}`                   | Show cycle               |
| PUT    | `/evaluation-cycles/{id}`                   | Update cycle             |
| POST   | `/evaluation-cycles/{id}/activate`          | Activate cycle           |
| POST   | `/evaluation-cycles/{id}/close`             | Close cycle              |
| GET    | `/leader-profiles`                          | List leader profiles     |
| POST   | `/leader-profiles`                          | Create leader profile    |
| GET    | `/leader-profiles/{id}`                     | Show leader profile      |
| PUT    | `/leader-profiles/{id}`                     | Update leader profile    |
| GET    | `/reward-penalty-types`                     | List types               |
| POST   | `/reward-penalty-types`                     | Create type              |
| PUT    | `/reward-penalty-types/{id}`                | Update type              |
| GET    | `/disciplinary-records/stats`               | Get statistics           |
| GET    | `/disciplinary-records`                     | List records             |
| POST   | `/disciplinary-records`                     | Create record            |
| GET    | `/disciplinary-records/{id}`                | Show record              |
| POST   | `/disciplinary-records/{id}/appeal`         | Appeal record            |
| POST   | `/disciplinary-records/{id}/clear`          | Clear record             |

### Result Entry (Lab-style Data Collection)

| Method | Endpoint                              | Description                  |
|--------|---------------------------------------|------------------------------|
| GET    | `/measurement-codes`                  | List measurement codes       |
| POST   | `/measurement-codes`                  | Create measurement code      |
| GET    | `/measurement-codes/{id}`             | Show measurement code        |
| PUT    | `/measurement-codes/{id}`             | Update measurement code      |
| GET    | `/unit-conversions`                   | List unit conversions        |
| POST   | `/unit-conversions`                   | Create unit conversion       |
| GET    | `/subjects`                           | List subjects                |
| POST   | `/subjects`                           | Create subject               |
| GET    | `/subjects/{id}`                      | Show subject                 |
| PUT    | `/subjects/{id}`                      | Update subject               |
| POST   | `/results/batch`                      | Batch create results         |
| POST   | `/results/import-csv`                 | Import results from CSV      |
| GET    | `/results/flagged`                    | List flagged results         |
| POST   | `/results/recompute-stats`            | Recompute result statistics  |
| POST   | `/results`                            | Create result                |
| GET    | `/results`                            | List results                 |
| GET    | `/results/{id}`                       | Show result                  |
| POST   | `/results/{id}/review`                | Review result                |

### Health Check

| Method | Endpoint   | Description            |
|--------|------------|------------------------|
| GET    | `/health`  | Service health status  |

## Authentication

1. Obtain a token: `POST /api/v1/auth/login` with `{ "username": "...", "password": "..." }`
2. Include the token in subsequent requests: `Authorization: Bearer <token>`
3. Token expiration is managed by Laravel Sanctum (expired tokens are pruned daily).

## Environment Variables

Key environment variables (see `.env.example` for the full list):

| Variable                       | Default       | Description                      |
|--------------------------------|---------------|----------------------------------|
| `APP_ENV`                      | `production`  | Application environment          |
| `APP_KEY`                      | (auto-gen)    | Encryption key                   |
| `APP_DEBUG`                    | `false`       | Debug mode                       |
| `DB_HOST`                      | `db`          | Database host                    |
| `DB_DATABASE`                  | `meridian`    | Database name                    |
| `DB_USERNAME`                  | `meridian`    | Database user                    |
| `DB_PASSWORD`                  | `secret`      | Database password                |
| `LOG_CHANNEL`                  | `stack`       | Log channel                      |
| `LOG_STACK`                    | `json`        | Stack channels (comma-separated) |
| `SSO_ENABLED`                  | `false`       | Enable LDAP/SSO login            |

## Backup & Restore

### Automated Backups

The system runs nightly MySQL backups at 02:00 UTC via `php artisan backup:run`. Backups are stored in the `/backups` Docker volume as gzipped SQL dumps with a 30-day retention policy.

A standalone backup script is also available at `docker/backup.sh`.

### Manual Backup

```bash
docker compose exec app php artisan backup:run
```

### Restore

```bash
# Copy backup to container
docker cp meridian_backup_20260415_020000.sql.gz meridian_app:/tmp/

# Restore
docker compose exec app bash -c \
  'gunzip -c /tmp/meridian_backup_20260415_020000.sql.gz | mysql -h db -u meridian -psecret meridian'
```

## Scheduled Tasks

| Command                                   | Schedule  | Description                              |
|-------------------------------------------|-----------|------------------------------------------|
| `sanctum:prune-expired --hours=24`        | Daily     | Remove expired Sanctum tokens            |
| `profiles:recompute`                      | Hourly    | Recompute user behavior profiles         |
| `records:expire`                          | Daily     | Expire old disciplinary records          |
| `results:recompute-stats`                 | Hourly    | Recompute result statistics              |
| `backup:run`                              | Daily 02:00 | Nightly MySQL backup                   |

## Architecture

The application follows a layered architecture:

```
app/
  Console/Commands/     # Artisan commands (backup, stats, expiry)
  Http/
    Controllers/Api/V1/ # Versioned API controllers
    Middleware/          # Auth, RBAC, data scoping, audit, throttle
    Requests/            # Form request validation
  Logging/              # Monolog processors (request ID, sensitive fields)
  Models/               # Eloquent models with Auditable trait
  Services/             # Business logic (statistics, masking, recommendations)
  Traits/               # Reusable model traits (Auditable)
config/                 # Laravel configuration (logging, auth, etc.)
database/
  migrations/           # Schema definitions
  seeders/              # Default data (roles, permissions, admin user)
docker/                 # Docker support (entrypoint, nginx, php-fpm, backup)
routes/                 # API and console route definitions
```

### Cross-cutting Concerns

- **Structured JSON Logging:** All logs are written in JSON format via Monolog with request ID and actor ID correlation.
- **Sensitive Field Masking:** Passwords, credentials, and PII are automatically masked in log output.
- **Audit Trail:** All create/update/delete operations on key models are recorded in `audit_logs` with before/after hashes.
- **Nightly Backups:** Automated MySQL dumps with 30-day retention stored in a dedicated Docker volume.

## Error Response Format

All API errors follow a consistent format:

```json
{
    "code": 400,
    "msg": "Validation failed: The title field is required."
}
```

## License

Proprietary. All rights reserved.
