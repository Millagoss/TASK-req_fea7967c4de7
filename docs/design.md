# Meridian Records & Compliance Backend - Design Document

## 1. Overview

Meridian is a single-node Laravel 11 + MySQL 8.0 backend that provides offline music catalog operations, internal behavioral analytics, governance workflows, lab-style result entry, and secure administration. It runs entirely inside Docker with zero external dependencies and no internet requirement.

**Tech Stack:** PHP 8.3, Laravel 11, MySQL 8.0, Nginx 1.25, Laravel Sanctum, Docker Compose

---

## 2. Architecture

### 2.1 Layered Design

The system follows a strict three-layer architecture:

```
HTTP Request
    |
    v
[Middleware Pipeline]
  AssignRequestId -> auth:sanctum -> ThrottleServiceAccount -> ApplyDataScopes -> CheckPermission -> AuditAdminAction
    |
    v
[Controller Layer]        -- Request validation via FormRequest classes, response formatting via API Resources
    |
    v
[Service Layer]           -- Business logic, validation pipelines, rate limiting, profile computation
    |
    v
[Data Layer]              -- Eloquent Models with traits (Auditable), relationships, scopes
    |
    v
[MySQL 8.0]
```

### 2.2 Module Breakdown

The application is organized into six functional modules built in phases:

| Phase | Module | Purpose |
|-------|--------|---------|
| 1 | Auth & RBAC | Authentication, roles, permissions, data scopes, auditing |
| 2 | Music Library | Songs, albums, playlists with versioning and lifecycle |
| 3 | Behavior & Analytics | Event collection, profile modeling, recommendations |
| 4 | Notifications | Templates, delivery, subscriptions, rate limiting |
| 5 | Rewards/Penalties | Evaluation cycles, disciplinary records, state machine |
| 6 | Result Entry | Measurement codes, subjects, validation, outlier detection |

### 2.3 Infrastructure

```
docker-compose.yml
  |
  +-- app (PHP 8.3-FPM)          -- Laravel application + scheduler
  +-- web (Nginx 1.25-alpine)    -- Reverse proxy on port 8080
  +-- db (MySQL 8.0)             -- Persistent data with health checks
  |
  Volumes: db_data, app_public, app_storage, app_bootstrap_cache, backups
  Network: meridian_network (bridge)
```

The entrypoint script (`docker/entrypoint.sh`) handles:
1. Wait for MySQL readiness
2. Run all migrations
3. Seed default admin user, roles, and permissions
4. Generate `APP_KEY` if missing
5. Start `php-fpm` and `schedule:work` in parallel

---

## 3. Authentication & Authorization

### 3.1 Authentication

- **Method:** Laravel Sanctum personal access tokens
- **Identifier:** Username (not email)
- **Password hashing:** bcrypt with cost factor 12
- **Lockout policy:** 10 failed attempts within a 15-minute sliding window locks the account. The `locked_until` timestamp is set and checked on each login attempt.
- **SSO (optional):** When `SSO_ENABLED=true`, the system attempts LDAP bind first, then find-or-creates a local user record. Limited to on-prem LDAP/Kerberos connectors with no external calls.
- **Service accounts:** Flagged via `is_service_account` on the `users` table. Credentials are generated as 32-character random strings, shown once on create/rotate, and stored as bcrypt hashes. All tokens are revoked on credential rotation.

### 3.2 Role-Based Access Control (RBAC)

The authorization model uses three tables: `roles`, `permissions`, and `role_permission` (pivot).

**17 Permissions:**

| Permission | Description |
|------------|-------------|
| `users.list` | View user listings |
| `users.create` | Create users |
| `users.update` | Update users, assign/remove roles |
| `roles.list` | View roles and permissions |
| `roles.create` | Create roles, templates, types, cycles, measurement codes |
| `roles.update` | Update roles, templates, types, cycles, measurement codes |
| `service_accounts.create` | Create and rotate service accounts |
| `music.read` | View songs, albums, playlists |
| `music.create` | Create songs, albums, playlists |
| `music.update` | Update songs, albums, playlists, manage tracks |
| `music.delete` | Delete draft songs, albums, playlists |
| `music.publish` | Publish/unpublish music entities |
| `music.manage_all` | Full music management (admin override) |
| `disciplinary.appeal` | Appeal disciplinary records |
| `disciplinary.clear` | Clear appealed disciplinary records |
| `results.review` | Review flagged results, view result listings |
| `subjects.view_pii` | View unmasked PII on subjects |

**Default Roles:**

| Role | Permissions |
|------|-------------|
| admin | All 17 permissions |
| analyst | users.list, roles.list, results.review, music.read |
| librarian | users.list, roles.list, subjects.view_pii, music.read/create/update/delete/publish |
| reviewer | results.review, roles.list, music.read |

### 3.3 Data Scopes

The `data_scopes` table associates users with row-level visibility constraints. Each scope has a `scope_type` (enum: `campus`, `org`, `subject`, `time_range`) and a `scope_value` (JSON array).

The `ApplyDataScopes` middleware reads the authenticated user's scopes and injects query constraints. Multiple scopes of the same type are OR'd; different types are AND'd. This restricts what data a user can see across subjects, results, disciplinary records, and evaluation cycles.

### 3.4 Audit Trail

The `Auditable` trait is applied to all primary models. It hooks into Eloquent's `creating`, `updating`, and `deleting` events to automatically write immutable records to `audit_logs` containing:

- `actor_id` (who performed the action)
- `action` (created, updated, deleted)
- `resource_type` / `resource_id` (what was changed)
- `request_id` (UUID correlation from middleware)
- `before_hash` / `after_hash` (SHA-256 of attribute state, excluding id/timestamps)
- `metadata` (JSON of changed fields)

The `AuditAdminAction` middleware provides an additional layer that logs all POST/PUT/PATCH/DELETE requests to admin endpoints regardless of model events.

---

## 4. Music Library

### 4.1 Data Model

```
songs ----< song_tags
songs >----< albums    (via album_songs with position)
songs >----< playlists (via playlist_songs with position)
```

**Songs:** Core entity with title (1-200 chars), artist (1-200 chars), duration_seconds (1-7200), audio_quality enum (MP3_320, FLAC_16_44, FLAC_24_96), cover art (local file with SHA-256), and semantic version.

**Tags:** Immutable child records. Format: lowercase alphanumeric + hyphens, 2-24 chars, max 20 per song. Stored in `song_tags` with unique constraint on `(song_id, tag)`.

**Albums & Playlists:** Share the same versioning and publish lifecycle pattern. Track ordering is maintained via a `position` column on pivot tables.

### 4.2 Publish Lifecycle

```
draft ---[publish]--> published ---[unpublish]--> unpublished ---[publish]--> published
```

- New entities start as `draft`
- Only `draft` entities can be deleted
- Publish/unpublish transitions enforce valid state transitions (e.g., cannot publish already-published)

### 4.3 Semantic Versioning

Entities carry `version_major`, `version_minor`, and `version_patch` columns (default: 1.0.0).

- **Patch:** Auto-incremented on metadata edits to published items
- **Minor/Major:** Manually bumped via `POST .../version { bump: "major"|"minor" }`
- `VersioningService` handles the increment logic: major resets minor+patch, minor resets patch

### 4.4 Cover Art

`CoverArtService` handles upload, validation, and storage:
- Allowed MIME types: jpeg, png, webp
- Max file size: 5 MB
- Storage path: `storage/app/cover-art/{entityType}/{entityId}/{sha256}.{ext}`
- SHA-256 fingerprint stored on the entity for integrity verification

### 4.5 Search & Pagination

Song listing supports:
- Keyword search (`?q=`) against title and artist via MySQL FULLTEXT
- Filters: `artist`, `tags`, `audio_quality`, `publish_state`, `duration_min`, `duration_max`
- Sorting: any allowed column + direction, with tie-breaking by `id ASC` for deterministic pagination
- Standard page/per_page pagination

---

## 5. User Behavior & Analytics

### 5.1 Event Collection

The `behavior_events` table is immutable (no `updated_at`). Events are:

| Event Type | Weight |
|-----------|--------|
| browse | 1 |
| search | 1 |
| click | 2 |
| favorite | 3 |
| rate | 5 |
| comment | 2 |

**Server-side timestamping:** The `server_timestamp` is always set by the server (UTC). Client-provided timestamps are ignored.

**Deduplication:** A 5-second sliding window prevents duplicate events for the same `(user_id, event_type, target_id)`. If a duplicate is detected within the window, the existing event is returned with HTTP 200 (not an error).

### 5.2 Profile Computation

`ProfileComputationService` builds user profiles by:

1. Fetching events from the last 90 days
2. Applying exponential time decay: `score = weight * 0.5^(age_days / 30)` (30-day half-life)
3. Aggregating into `interest_tags` (tag -> score map) and `preference_vector` (artist -> score map)
4. Normalizing scores to 0-1.0 range

Profiles are stored in `user_profiles` and recomputed hourly via the `profiles:recompute` scheduled command.

### 5.3 Recommendations

`RecommendationService` provides personalized song recommendations:

- **Cold-start threshold:** < 5 events
- **Cold-start strategy:** Popular songs in the last 7 days + content-similar by shared tags/artists
- **Warm strategy:** Personalized by top interest tags and artists from the user's profile
- **Output:** Max 20 deduplicated recommendations with scores

---

## 6. Notifications & Task Center

### 6.1 Data Model

```
notification_templates ----< notifications ----| recipient (user)
notification_templates ----< notification_subscriptions ----| user
```

**Templates:** Contain `{{variable}}` placeholders in subject/body. The `variables` JSON column declares required field names. Templates are validated to ensure all declared variables exist in the body/subject.

**Notifications:** Immutable records (no `updated_at`). Only `read_at` can be updated. Each stores the rendered subject/body, variables used, and an optional `batch_id` (UUID) for bulk sends.

**Subscriptions:** Default behavior is subscribed. Only an explicit `is_subscribed=false` record opts a user out. Unique constraint on `(user_id, template_id)`.

### 6.2 Rate Limiting

Enforced at the service level (not middleware) for per-template, per-recipient granularity:
- **Limit:** 3 notifications per user per template per rolling 60-minute window
- Rate-limited recipients are skipped with a reason in the response

### 6.3 Bulk Delivery

- Cap: 10,000 recipients per send. Requests exceeding this return HTTP 422.
- Bulk sends generate a UUID `batch_id` for tracking
- Processed synchronously (no queue needed for single-node)

### 6.4 Key Design Decisions

1. **Immutable notifications** ensure audit trail integrity
2. **Rate limiting at service level** allows granular per-template control
3. **Default subscribed** reduces initial setup overhead
4. **Static routes before parameterized** routes prevent matching conflicts

---

## 7. Rewards & Penalties

### 7.1 Data Model

```
evaluation_cycles ----< disciplinary_records
leader_profiles ----< disciplinary_records
reward_penalty_types ----< disciplinary_records
users (subject) ----< disciplinary_records
users (issuer) ----< disciplinary_records
```

**Evaluation Cycles:** Named time periods with a status state machine: `draft -> active -> closed`.

**Leader Profiles:** One-per-user records linking a user to leadership metadata (title, department, campus).

**Reward/Penalty Types:** Configurable categories (reward/penalty) with severity levels (low/medium/high/critical), default points, and default expiration days (365).

### 7.2 Disciplinary State Machine

```
active ---[appeal]---> appealed ---[clear]---> cleared
                           |
                           +---[reject appeal]---> active
```

- **Appeal:** Subject user OR user with `disciplinary.appeal` permission. Sets `appealed_at` and `appeal_reason`.
- **Clear:** Only users with `disciplinary.clear` permission. Sets `cleared_at`, `cleared_by`, `cleared_reason`.
- **Points:** Positive for rewards, negative for penalties.

### 7.3 Auto-Expiration

The `records:expire` daily scheduled command automatically clears active records where `expires_at < now()`. Default expiration is 365 days from issuance.

### 7.4 Statistics

The stats endpoint aggregates disciplinary data groupable by `role`, `period`, or `category`, filterable by `evaluation_cycle_id`. Returns counts, total points, and breakdowns.

---

## 8. Result Entry (Lab-style Data Collection)

### 8.1 Data Model

```
measurement_codes ----< unit_conversions
measurement_codes ----< results
measurement_codes ----< result_statistics
subjects ----< results
```

**Measurement Codes:** Define what can be measured (code, display_name, unit, value_type: numeric/text/coded, reference ranges).

**Unit Conversions:** Per-code conversion rules: `value_normalized = value_input * factor + offset`.

**Subjects:** Entities being measured. PII fields (identifier, name) are masked unless the user has `subjects.view_pii` permission.

**Results:** Individual measurements with full traceability (source, batch_id, created_by).

**Result Statistics:** Precomputed per-code statistics (count, mean, stddev) used for z-score calculations.

### 8.2 Validation Pipeline

`ResultValidationService` processes each result through a sequential pipeline:

```
1. Code exists & is active
2. Value type matches code's value_type (numeric/text/coded)
3. Unit normalization (apply factor + offset if conversion exists)
4. Reference range check (generates warning only, does not reject)
5. Timestamp check: observed_at <= now + 5 minutes
6. Z-score outlier detection
```

### 8.3 Outlier Detection

- **Z-score formula:** `z = (value - mean) / stddev`
- **Threshold:** |z| >= 3.0 (configurable per result via `outlier_threshold`)
- **Minimum sample:** Statistics must have count >= 30 before z-score flagging activates
- **Flagged results:** Set `is_outlier=true`, `review_status=pending`
- **Non-outlier results:** Set `review_status=approved` automatically

### 8.4 Review Workflow

- Only users with `results.review` permission can approve/reject
- Reviewer cannot review their own submissions
- Review sets `reviewed_by`, `reviewed_at`, `review_comment`, and new `review_status`

### 8.5 Data Ingestion Methods

| Method | Endpoint | Notes |
|--------|----------|-------|
| Manual single | `POST /results` | One result at a time |
| Batch REST | `POST /results/batch` | Array of observations, FHIR-like semantics |
| CSV import | `POST /results/import-csv` | Columns: code, subject_identifier, value, unit, observed_at. Returns {imported, errors[], batch_id} |

### 8.6 Statistics Recomputation

`ResultStatisticsService` recomputes per-code statistics hourly via `results:recompute-stats`. Only approved numeric results are included in the calculation.

---

## 9. Middleware Pipeline

| Middleware | Scope | Purpose |
|-----------|-------|---------|
| `AssignRequestId` | Global | Generates UUID v4, sets `X-Request-Id` response header, available for log correlation |
| `auth:sanctum` | Protected routes | Validates Sanctum bearer token |
| `ThrottleServiceAccount` | Auth routes | Rate limits service accounts to 60 req/min per account |
| `ApplyDataScopes` | Admin + Phase 5-6 routes | Reads user's data scopes, injects query constraints |
| `CheckPermission:{perm}` | Per-route | Route-level RBAC gate. Returns 403 if user lacks the named permission |
| `AuditAdminAction` | Write routes | Logs all POST/PUT/PATCH/DELETE operations to audit_logs |

---

## 10. Cross-Cutting Concerns

### 10.1 Structured Logging

- Format: JSON via Monolog
- Every log line includes: `request_id`, `actor_id`, `timestamp`
- Log file: `storage/logs/meridian.log`
- Sensitive fields (password, ip_address, identifier, username) are automatically masked by `FieldMaskingService`

### 10.2 Field Masking

`FieldMaskingService` applies partial redaction: first 2 characters + `****` + last 2 characters. Applied in:
- API Resources (based on permission checks, e.g., subject PII)
- Log formatter (for registered sensitive fields)

### 10.3 Error Response Format

All error responses follow a consistent structure:

```json
{
  "code": <HTTP status code>,
  "msg": "<Human-readable error message>"
}
```

Validation errors (422) include field-level details. No stack traces are exposed.

### 10.4 Scheduled Commands

| Command | Schedule | Purpose |
|---------|----------|---------|
| `sanctum:prune-expired --hours=24` | Daily | Remove expired Sanctum tokens |
| `profiles:recompute` | Hourly | Recompute user behavior profiles |
| `records:expire` | Daily | Auto-clear expired disciplinary records |
| `results:recompute-stats` | Hourly | Recompute z-score statistics |
| `backup:run` | Daily 02:00 UTC | mysqldump + gzip, 30-day retention |

### 10.5 Nightly Backups

`docker/backup.sh` performs a mysqldump with gzip compression. Backups are stored in the `/backups` Docker volume with 30-day retention (older files auto-deleted).

### 10.6 Performance Considerations

- All composite indexes are defined in migrations for indexed query paths
- `select()` used on queries to avoid `SELECT *`
- Eager loading (`with()`) used in controllers to prevent N+1 queries
- Config and route caching applied in the Dockerfile build stage
- Database-backed cache/session/queue (no Redis required for single-node)

---

## 11. Database Schema Summary

### Tables (32 total)

| # | Table | Module | Mutable | Key Indexes |
|---|-------|--------|---------|-------------|
| 1 | users | Auth | Yes | username (unique) |
| 2 | roles | Auth | Yes | name (unique) |
| 3 | permissions | Auth | No | name (unique) |
| 4 | role_permission | Auth | Yes | (role_id, permission_id) |
| 5 | user_role | Auth | Yes | (user_id, role_id) |
| 6 | data_scopes | Auth | Yes | (user_id, role_id) |
| 7 | login_attempts | Auth | Immutable | (username, attempted_at) |
| 8 | personal_access_tokens | Auth | Yes | Sanctum default |
| 9 | audit_logs | Auth | Immutable | (resource_type, resource_id), (actor_id, created_at) |
| 10 | cache | Framework | Yes | key (primary) |
| 11 | sessions | Framework | Yes | user_id, last_activity |
| 12 | jobs | Framework | Yes | queue, reserved_at |
| 13 | songs | Music | Yes | (publish_state, updated_at), (artist, title), FULLTEXT(title, artist) |
| 14 | song_tags | Music | Immutable | (song_id, tag) unique, (tag) |
| 15 | albums | Music | Yes | (publish_state, updated_at), (artist, title) |
| 16 | album_songs | Music | Yes | (album_id, song_id, position) |
| 17 | playlists | Music | Yes | (publish_state) |
| 18 | playlist_songs | Music | Yes | (playlist_id, song_id, position) |
| 19 | behavior_events | Analytics | Immutable | (user_id, created_at), (user_id, event_type, target_id, server_timestamp) |
| 20 | user_profiles | Analytics | Yes | user_id (unique) |
| 21 | notification_templates | Notifications | Yes | name (unique) |
| 22 | notifications | Notifications | Immutable* | (recipient_id, created_at), (batch_id) |
| 23 | notification_subscriptions | Notifications | Yes | (user_id, template_id) unique |
| 24 | evaluation_cycles | Rewards | Yes | (status) |
| 25 | leader_profiles | Rewards | Yes | user_id (unique) |
| 26 | reward_penalty_types | Rewards | Yes | (category, is_active) |
| 27 | disciplinary_records | Rewards | Yes | (subject_user_id, status), (evaluation_cycle_id), (expires_at) |
| 28 | measurement_codes | Results | Yes | code (unique) |
| 29 | unit_conversions | Results | Yes | (measurement_code_id, from_unit) |
| 30 | subjects | Results | Yes | identifier (unique) |
| 31 | results | Results | Yes | (subject_id, measurement_code_id, observed_at), (measurement_code_id, review_status) |
| 32 | result_statistics | Results | Yes | measurement_code_id (unique) |

*Notifications: only `read_at` is updatable.

---

## 12. Security Design

| Concern | Implementation |
|---------|---------------|
| Authentication | Sanctum tokens, bcrypt cost 12, account lockout |
| Authorization | 17-permission RBAC with 4 default roles, data scopes |
| Audit trail | Immutable audit_logs with before/after SHA-256 hashes |
| Rate limiting | Service accounts: 60 req/min. Notifications: 3/user/template/hour |
| Input validation | FormRequest classes on all endpoints (36 total) |
| PII protection | Field masking in logs, API responses gated by permission |
| Credential security | Service account credentials shown once, stored as bcrypt |
| Request tracing | UUID X-Request-Id on every request, correlated across logs |
| CSV validation | Type/format enforcement, batch_id tracking |
| Result review | Outliers require reviewer approval (cannot self-approve) |

---

## 13. File Structure

```
repo/
+-- docker-compose.yml
+-- Dockerfile
+-- .env.example
+-- docker/
|   +-- nginx/default.conf
|   +-- php/php-fpm.conf
|   +-- entrypoint.sh
|   +-- backup.sh
+-- app/
|   +-- Console/Commands/         (4 scheduled commands)
|   +-- Http/
|   |   +-- Controllers/Api/V1/   (17 controllers)
|   |   +-- Middleware/            (5 middleware classes)
|   |   +-- Requests/             (36 form requests)
|   |   +-- Resources/            (23 API resources)
|   +-- Models/                   (~20 models)
|   +-- Services/                 (10 service classes)
|   +-- Traits/                   (Auditable, AppliesDataScopes)
|   +-- Providers/
+-- config/
+-- database/
|   +-- migrations/               (32 migrations)
|   +-- seeders/                  (RolePermissionSeeder, MusicLibrarySeeder)
+-- routes/
|   +-- api.php                   (all API routes)
|   +-- console.php               (scheduled commands)
+-- tests/
+-- unit_tests/
+-- API_tests/
+-- storage/
+-- public/
```

---

## 14. Environment Configuration

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_ENV` | production | Application environment |
| `APP_KEY` | (auto-generated) | Encryption key |
| `APP_DEBUG` | false | Debug mode |
| `DB_HOST` | db | MySQL host (Docker service name) |
| `DB_PORT` | 3306 | MySQL port |
| `DB_DATABASE` | meridian | Database name |
| `DB_USERNAME` | meridian | Database user |
| `DB_PASSWORD` | secret | Database password |
| `DB_ROOT_PASSWORD` | rootsecret | MySQL root password |
| `SSO_ENABLED` | false | Enable LDAP/Kerberos SSO |
| `LDAP_HOST` | (empty) | LDAP server hostname |
| `LDAP_PORT` | 389 | LDAP port |
| `LDAP_BASE_DN` | (empty) | LDAP base DN |
| `LDAP_USER_FILTER` | (uid={username}) | LDAP user search filter |
| `CACHE_STORE` | database | Cache backend |
| `QUEUE_CONNECTION` | database | Queue backend |
| `SESSION_DRIVER` | database | Session backend |

---

## 15. Default Credentials

On first boot, the seeder creates:

- **Username:** `admin`
- **Password:** `Admin@Password1`
- **Role:** admin (all permissions)

The application is accessible at `http://localhost:8080`.
