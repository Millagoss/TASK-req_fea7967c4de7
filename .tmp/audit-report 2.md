# Meridian Records & Compliance Backend — Static Audit Report

## 1. Verdict

**Partial Pass**

The project is a substantial, structurally sound Laravel + MySQL delivery that covers most core functional requirements from the Prompt. However, it has multiple material issues — several Blocker and High severity — that constitute real risk in production. The project is not ready for acceptance without either fixing these issues or explicitly documenting them as acceptable tradeoffs.

---

## 2. Scope and Static Verification Boundary

### What was reviewed

- All 246 lines of `routes/api.php`
- All 19 API controller files (`app/Http/Controllers/Api/V1/`)
- All 11 service files (`app/Services/`)
- All 3 middleware files (`app/Http/Middleware/`)
- All 3 logging/processor files (`app/Logging/`, `app/Traits/`)
- All 31 migration files (`database/migrations/`)
- All 11 model files (`app/Models/`)
- All 25 form request validator files
- All 17 API test files (`API_tests/`)
- All 11 unit test files (`unit_tests/`)
- `README.md`, `docker-compose.yml`, `Dockerfile`, all docker support files
- `config/logging.php`, `config/auth.php`
- `bootstrap/app.php`, `routes/console.php`
- `docs/design.md`, `docs/api-spec.md`

### What was NOT reviewed

- Runtime behavior (no execution performed)
- Database seeding logic beyond the seeder files read
- The `public/index.php` entry point (standard Laravel)
- The `database/factories/` directory
- `config/app.php`, `config/database.php`, `config/session.php`, `config/cache.php`, `config/queue.php`, `config/filesystems.php`, `config/sanctum.php`
- The `app/Providers/` directory
- The `app/Http/Resources/` directory (not all resources read; focused on SubjectResource for PII and core resources)
- The `rosy-popping-firefly.md` file (project metadata)

### What was intentionally not executed

- No Docker, no `php artisan`, no `composer install`, no test runs
- No `docker compose up`, no `./run_tests.sh`
- No attempt to start MySQL or the PHP server

### Claims requiring manual verification

- Runtime p95 latency < 300 ms at 200 RPS (no benchmarking evidence present)
- Nightly backup command actually runs successfully in Docker environment
- The LDAP SSO integration works end-to-end when `SSO_ENABLED=true`
- Credential rotation completely invalidates all prior tokens (service account)
- Rate limit enforcement is consistent across all notification pathways
- Data scoping middleware actually filters query results (static evidence shows it only attaches scopes to request attributes, never applies them)

---

## 3. Repository / Requirement Mapping Summary

### Core business goal
A single-node, offline-capable Laravel + MySQL backend for Meridian Records & Compliance, covering: music catalog management, behavioral analytics, governance workflows, lab-style result entry, notifications, and secure administration.

### Core flows implemented

| Prompt Requirement | Implementation | Status |
|---|---|---|
| Music Library CRUD + publish/unpublish + semver | `SongController`, `AlbumController`, `PlaylistController`, `VersioningService` | Implemented |
| Metadata constraints (title/artist 1-200, duration 1-7200, audio_quality enum, tags normalized, cover_art SHA-256 + 5MB) | `StoreSongRequest`, `UpdateSongRequest`, `CoverArtService` | **Partially — title/artist have no min-length validation** |
| Search with filtering/sorting/deterministic pagination | `SongController::index`, `AlbumController::index`, `PlaylistController::index` | Implemented |
| Behavior events (browse/search/click/favorite/rate/comment) | `BehaviorEventController`, `RecordEventRequest` | Implemented |
| Server-side timestamping | `server_timestamp` column in `behavior_events` migration | Implemented |
| Dedup by (user_id, event_type, target_id, 5-second window) | `BehaviorEventController::store` lines 27-39 | Implemented |
| Profile modeling with configurable weights | `ProfileComputationService::WEIGHTS` — **hardcoded, not configurable** | Partial |
| 30-day half-life time decay | `ProfileComputationService::HALF_LIFE_DAYS = 30` | Implemented |
| Cold-start fallback | `RecommendationService::coldStartRecommendations` | Implemented |
| Notifications in-app only (no SMS/email/push) | Notification system — no external integrations present | Implemented |
| Templates with variable placeholders | `NotificationService::render` | Implemented |
| Bulk send capped at 10,000 | `NotificationService::BULK_CAP = 10000`, `SendBulkNotificationRequest` | Implemented |
| Rate limit 3 per user per hour per template | `NotificationService::RATE_LIMIT_PER_HOUR = 3` | Implemented |
| Read receipts | `Notification::read_at`, `NotificationController::markRead` | Implemented |
| Rewards/Penalties: configurable types | `RewardPenaltyType` model and controller | Implemented |
| Status transitions (active/appealed/cleared) | `DisciplinaryRecordController` | Implemented |
| Default expiration 365 days | `default_expiration_days` field on `RewardPenaltyType` | Implemented |
| Statistical reporting by role/period/category | `DisciplinaryRecordController::stats` | Implemented |
| Result entry: manual, CSV, REST integration | `ResultController::store`, `importCsv`, `batch` | Implemented |
| HL7/FHIR-like semantics | `ResultValidationService` — `code`, `unit`, `reference_range`, `observed_at` fields | Implemented |
| Type/format validation, unit normalization, range checks | `ResultValidationService::process` steps 1-6 | Implemented |
| observed_at not more than 5 min in future | `ResultValidationService::process` line 79-84 | Implemented |
| Z-score outlier detection (default \|z\| >= 3.0) | `ResultValidationService::process` lines 86-98 | Implemented |
| Reviewer sign-off required for outliers | `review_status = 'pending'` for outliers, `ResultController::review` | Implemented |
| Audit tables (actor_id, action, resource_type/id, request_id, before/after hashes) | `AuditLog` model, `Auditable` trait, `AuditAdminAction` middleware | Implemented |
| Key indexes (publish_state, updated_at), (artist, title), (user_id, created_at) | Migration files | Implemented |
| Local username/password with bcrypt cost 12, min 12 chars | `AuthService::BCRYPT_COST = 12`, `CreateUserRequest::rules` | Implemented |
| Lockout after 10 failed attempts per 15 minutes | `AuthService::MAX_ATTEMPTS = 10`, `WINDOW_MINUTES = 15`, `LOCKOUT_MINUTES = 15` | Implemented |
| Token revocation on logout | `AuthController::logout` | Implemented |
| Optional offline SSO (LDAP/Kerberos only) | `AuthService::validateViaSso` | Implemented |
| RBAC with function permissions and data scopes | `CheckPermission` middleware, `ApplyDataScopes` middleware | **Partial — data scopes not applied to queries** |
| Service accounts with throttling (default 60 req/min) | `ThrottleServiceAccount` | Implemented |
| Credential rotation, encrypted at rest | `ServiceAccountService`, `Hash::make` | Implemented |
| Single-machine Docker | `docker-compose.yml`, `Dockerfile` | Implemented |
| Nightly local backups | `backup:run` scheduled command, `BackupDatabase` artisan command | Implemented |
| Structured logs with request_id correlation | `config/logging.php`, `RequestIdProcessor`, `SensitiveFieldProcessor` | Implemented |
| Field-level masking for sensitive identifiers | `FieldMaskingService` | Implemented |

---

## 4. Section-by-Section Review

### 4.1 Documentation and Static Verifiability

**Conclusion: Partial Pass**

- **Conclusion**: Partial Pass
- **Rationale**: README.md provides comprehensive API overview, Docker quick start, environment variable table, scheduled tasks, architecture description, and error format. Default admin credentials are documented. `run_tests.sh` provides test commands. `.env.example` covers all key variables. However, `docs/api-spec.md` only covers Phase 4 (Notifications) rather than all phases. `docs/design.md` also covers only Phase 4. No explicit documentation of the "offline-only" constraint, the p95 latency target, or the SSO/LDAP configuration approach. The project structure in the README (`app/Logging/`, `app/Traits/`) is accurate.
- **Evidence**: `README.md:1-282`, `docker-compose.yml:1-88`, `.env.example:1-45`

### 4.2 Prompt-to-Code Alignment

**Conclusion: Fail**

- **Conclusion**: Fail
- **Rationale**: The implementation is centered on the business goal overall, but material deviations exist. (1) Title and artist fields have no minimum length constraint — Prompt requires "1-200 chars" but validation only enforces `max:200`. Empty strings will be accepted. (2) Profile weights are hardcoded in `ProfileComputationService::WEIGHTS` (`app/Services/ProfileComputationService.php:15-22`) rather than configurable. The Prompt says "using configurable weights." (3) The data scoping middleware (`ApplyDataScopes`) only attaches scope constraints to `$request->attributes` but never actually applies them to any query — the Prompt requires data scopes (campus/organization, subject, time-range) to filter results. (4) Playlist search (`PlaylistController::index`, line 32-34) searches only title and description, not the artist field, while the Prompt requires search across artist for all catalog entities.
- **Evidence**: `app/Services/ProfileComputationService.php:15-22`, `app/Http\Middleware\ApplyDataScopes.php:1-36`, `app\Http\Controllers\Api\V1\PlaylistController.php:28-34`

### 4.3 Core Functional Requirements Coverage

**Conclusion: Partial Pass**

- **Conclusion**: Partial Pass
- **Rationale**: All explicitly stated core functional requirements are addressed at the API surface level. Music library, behavior analytics, notifications, rewards/penalties, result entry, authentication, authorization, audit logging, Docker, logging, and masking are all implemented. However, three core requirements are only partially implemented due to the specific issues noted above: metadata constraint validation (missing min-length), configurable weights (hardcoded), and data scope enforcement (middleware sets scopes but never applies them).
- **Evidence**: See mapping table in Section 3.

### 4.4 End-to-End Deliverable Completeness

**Conclusion: Pass**

- **Conclusion**: Pass
- **Rationale**: The project is a complete 0-to-1 Laravel application with a full project structure. It includes migrations, models, controllers, services, middleware, validators, resources, tests (both unit and API), Docker configuration, seeders, documentation, and configuration files. No mock or hardcoded behavior is used in place of real logic — all business logic has genuine implementation. The codebase is not scattered or single-file; it follows Laravel conventions throughout.
- **Evidence**: `app/`, `routes/`, `database/`, `config/`, `docker/`, `API_tests/`, `unit_tests/`, `README.md`, `docker-compose.yml`

### 4.5 Engineering Structure and Module Decomposition

**Conclusion: Pass**

- **Conclusion**: Pass
- **Rationale**: The project follows a clear layered architecture: Controllers handle HTTP, Services handle business logic, Models handle data persistence, Middleware handles cross-cutting concerns (auth, RBAC, audit, throttle, scoping), and Resources handle API response formatting. Module responsibilities are reasonably defined. No single file is excessively overloaded. The `app/Services/` directory separates concerns well (AuthService, NotificationService, RecommendationService, etc.). Redundant files are minimal — the boilerplate `Controller.php` base class and a few resource files could potentially be combined, but nothing is structurally problematic.
- **Evidence**: `app/Services/`, `app/Http/Controllers/Api/V1/`, `app/Models/`, `app/Http/Middleware/`

### 4.6 Maintainability and Extensibility

**Conclusion: Pass**

- **Conclusion**: Pass
- **Rationale**: The codebase uses Laravel conventions throughout, making it extensible. Services are injectable via constructor dependency injection. Form Request validators separate input validation from controller logic. Resources provide a clean API response layer. The Auditable trait can be applied to any model. The ProfileComputationService uses constants that can be updated. The z-score threshold in ResultValidationService is a local constant (though ideally configurable via config). The architecture leaves room for extension; for example, data scopes could be implemented by following the middleware pattern already established.
- **Evidence**: `app/Traits/Auditable.php:1-54`, `app/Services/ResultValidationService.php:89`, `app/Http/Requests/` directory

### 4.7 Error Handling, Logging, and Validation

**Conclusion: Partial Pass**

- **Conclusion**: Partial Pass
- **Rationale**: Error handling is generally reliable — custom exception rendering in `bootstrap/app.php` converts exceptions to the standard `{code, msg}` format. Validation uses Laravel Form Requests with custom error messages for key fields. Logs are structured JSON with request_id and actor_id correlation. Sensitive field masking is implemented. However: (1) The logout response in `AuthController::logout` returns `{code, msg}` but the test at `API_tests/AuthApiTest.php:122` asserts `message` — a mismatch. (2) `RequestIdProcessor::15` calls `auth()->id()` which can return null for unauthenticated requests, creating noisy log entries. (3) The health endpoint hardcodes `'cache' => 'ok'` (`routes/api.php:44`) regardless of actual cache status. (4) The `Auditable` trait catches exceptions in the audit log write but logs them as warnings, silently ignoring failures.
- **Evidence**: `bootstrap/app.php:27-48`, `routes/api.php:44`, `app/Traits/Auditable.php:49-52`

### 4.8 Real Product / Service Maturity

**Conclusion: Pass**

- **Conclusion**: Pass
- **Rationale**: The project has the characteristics of a real product: full Docker deployment, health check endpoint, scheduled tasks, audit trail, service accounts with credential rotation, database-backed sessions/cache/queue, database migrations, seeders for initial data, proper authentication with lockout, structured logging, and comprehensive API documentation. It is not a teaching sample or demo.
- **Evidence**: `docker-compose.yml:1-88`, `routes/console.php:1-19`, `database/seeders/RolePermissionSeeder.php:1-88`

### 4.9 Business Goal and Implicit Constraint Fit

**Conclusion: Fail**

- **Conclusion**: Fail
- **Rationale**: The core business objective is addressed, but key implicit constraints are changed or weakened. Specifically: (1) "configurable weights" for profile modeling is not configurable — hardcoded constants in the service. (2) Data scopes for RBAC are collected but never applied to filter query results. (3) Title/artist minimum length validation is missing, changing the metadata constraint from "1-200 chars" to "0-200 chars". (4) Playlist search is missing artist filtering.
- **Evidence**: See Section 4.2.

### 4.10 Aesthetics (Frontend-only / Full-stack)

**Conclusion: Not Applicable**

- **Conclusion**: Not Applicable
- **Rationale**: This is a pure backend API project with no frontend UI. Section 6 of the acceptance criteria is not applicable.

---

## 5. Issues / Suggestions (Severity-Rated)

### BLOCKER Issues

#### Issue 1: Missing Minimum Length Validation for Title and Artist
- **Severity**: Blocker
- **Title**: Song/album metadata accepts empty strings — violates Prompt constraint "title and artist are required strings (1-200 chars)"
- **Conclusion**: The `StoreSongRequest` and `UpdateSongRequest` validators only enforce `max:200` on title and artist. No minimum length is enforced. The database column is `string()` (non-nullable but no min length). Empty strings will be accepted and stored.
- **Evidence**: `app/Http/Requests/StoreSongRequest.php:17-18`, `app/Http\Requests/UpdateSongRequest.php:17-18`
- **Impact**: Metadata constraint violation. Songs can be created with empty title and artist fields, which the Prompt explicitly forbids.
- **Minimum actionable fix**: Add `'min:1'` rule to both title and artist validation in `StoreSongRequest` and `UpdateSongRequest`. Also add a DB-level CHECK constraint if MySQL version supports it, or handle in the model.
- **Minimal verification**: Create a song with `title: ""` and verify it returns 422.

#### Issue 2: Data Scopes Are Collected But Never Applied to Queries
- **Severity**: Blocker
- **Title**: RBAC data scopes middleware sets attributes but does not filter any data
- **Conclusion**: `ApplyDataScopes` (`app/Http/Middleware/ApplyDataScopes.php:19-34`) reads the user's data scopes and sets them as `$request->attributes`, but no downstream code ever reads this attribute or applies scope filters to database queries. Every controller that should filter by data scope (campus/organization, subject, time-range) currently returns unfiltered results. The Prompt explicitly requires "authorization uses role-based access control with function permissions and data scopes."
- **Evidence**: `app/Http/Middleware/ApplyDataScopes.php:1-36`; grep for `data_scopes` usage in all controller/service files — **returns no results** beyond the middleware itself.
- **Impact**: Data scope authorization is completely non-functional. Users with restricted scopes can access all data. This is a fundamental authorization failure.
- **Minimum actionable fix**: Implement a query scope or trait that checks `$request->attributes->get('data_scopes')` and applies `where` clauses to relevant models (Subject, Result, DisciplinaryRecord). At minimum, add a `scopeByDataScopes` method to the base controller or a reusable trait that controllers can call.
- **Minimal verification**: Create a user with a campus-scoped data scope and verify they can only see subjects/songs from that campus.

#### Issue 3: Playlist Search Does Not Include Artist Field
- **Severity**: Blocker
- **Title**: Playlist search API omits artist filtering, violating Prompt search requirement
- **Conclusion**: The Prompt requires "Search APIs support keyword match plus filtering and sorting across artist, tag, duration range, and publish_state." The `PlaylistController::index` (`app/Http/Controllers/Api/V1/PlaylistController.php:28-34`) only searches `title` and `description`. There is no artist field on playlists in the schema, but keyword search should still match across all relevant fields. Additionally, there is no tag filtering for playlists (songs within playlists can be searched via their own tags, but playlist-level search does not check tags of constituent songs).
- **Evidence**: `app/Http/Controllers/Api/V1/PlaylistController.php:28-34`
- **Impact**: Incomplete search functionality for playlists. Users cannot find playlists by artist name.
- **Minimum actionable fix**: Either add artist field to playlists, or search songs within matching playlists. Update the search logic to check tags of constituent songs if that is the intended design.
- **Minimal verification**: Create a playlist with an artist in the title/description, search for it, and verify it appears.

### HIGH Severity Issues

#### Issue 4: Profile Weights Are Hardcoded, Not Configurable
- **Severity**: High
- **Title**: Profile computation weights are constants, not configurable
- **Conclusion**: The Prompt requires "profile modeling that maintains interest tags and preference vectors using configurable weights." The `ProfileComputationService` (`app/Services/ProfileComputationService.php:15-22`) defines weights as a private PHP constant array. There is no configuration mechanism (config file, database table, or environment variable) to change these weights at runtime or per-deployment.
- **Evidence**: `app/Services/ProfileComputationService.php:15-22`
- **Impact**: The "configurable" requirement is not met. The system cannot adapt weights to different use cases without code changes.
- **Minimum actionable fix**: Move weights to `config/services.php` or a database-driven configuration table. Read from config in `computeForUser()`.
- **Minimal verification**: Static check — verify no configuration mechanism exists for weights. Search for weight-related config keys returns no results.

#### Issue 5: SSO Configuration Key Mismatch
- **Severity**: High
- **Title**: SSO enabled check uses wrong config key — `config('app.sso_enabled')` instead of `env('SSO_ENABLED')`
- **Conclusion**: In `AuthService::validateCredentials` (`app/Services/AuthService.php:71`), the code checks `config('app.sso_enabled', (bool) env('SSO_ENABLED', false))`. Laravel's `config()` function looks up `app.sso_enabled` in `config/app.php`, which is not defined there. The environment variable `SSO_ENABLED` in `.env.example` and `docker-compose.yml` is never loaded into any config key. The actual check will always use the default value (false), making `SSO_ENABLED=true` in `.env` ineffective. Additionally, `config('app.sso_enabled')` on line 71 would return null or the default if not set, and the cast `(bool)` on null gives false.
- **Evidence**: `app/Services/AuthService.php:71`, `.env.example:35`, `docker-compose.yml:19`
- **Impact**: SSO/LDAP login cannot be enabled via environment variable. The feature is present in code but unreachable via configuration.
- **Minimum actionable fix**: Either add `'sso_enabled' => env('SSO_ENABLED', false)` to `config/app.php`, or change the check to `env('SSO_ENABLED', false)`.
- **Minimal verification**: Set `SSO_ENABLED=true` in `.env`, attempt LDAP login, verify it attempts LDAP bind.

#### Issue 6: Music Library Routes Lack Any Authorization Check
- **Severity**: High
- **Title**: Song/album/playlist endpoints have no permission guards — any authenticated user can perform any action
- **Conclusion**: The Prompt requires RBAC with function permissions for all operations. The music library routes in `routes/api.php:100-144` are wrapped only in `'auth:sanctum'` — no permission middleware. Any authenticated user can create, update, delete, publish, unpublish, or upload cover art for any song/album/playlist, regardless of their role. The only CRUD restriction is that draft songs cannot be deleted when published.
- **Evidence**: `routes/api.php:101-144`
- **Impact**: Privilege escalation — any authenticated user has full music library administrative privileges. No function-level permission enforcement.
- **Minimum actionable fix**: Add permission middleware to music library routes, e.g., `->middleware('permission:music.create')`, `->middleware('permission:music.update')`, etc. Define appropriate permissions in `RolePermissionSeeder`.
- **Minimal verification**: Create a user without any admin role, authenticate them, and verify they can still create songs.

#### Issue 7: Recommendation Endpoint Allows Viewing Any User's Recommendations
- **Severity**: High
- **Title**: `GET /recommendations/{userId}` has no authorization check — any authenticated user can view another user's recommendations
- **Conclusion**: `RecommendationController::show` (`app/Http/Controllers/Api/V1/RecommendationController.php:16-24`) takes `userId` as a path parameter and returns recommendations for that user without checking whether the authenticated user is authorized to view them. Any user can read another user's behavioral profile and recommendations.
- **Evidence**: `app/Http/Controllers/Api\V1\RecommendationController.php:16-24`, `routes/api.php:156`
- **Impact**: Unauthorized data disclosure — user behavioral profiles are exposed to any authenticated user.
- **Minimum actionable fix**: Add authorization check: `$request->user()->id === $userId || $request->user()->hasPermission('users.list')`.
- **Minimal verification**: Authenticate as User A, call `GET /recommendations/{userB_id}`, verify User A can see User B's recommendations.

#### Issue 8: AuditAdminAction Middleware Only Audits Admin Routes
- **Severity**: High
- **Title**: Non-admin write operations on music/behavior/notification/result endpoints are not audited
- **Conclusion**: `AuditAdminAction` middleware (`app/Http/Middleware/AuditAdminAction.php:20-24`) only audits write operations on `api/v1/admin/*` routes. All other write operations — creating songs, recording behavior events, sending notifications, entering results, updating subjects — are not audited. The `Auditable` trait on models creates audit log entries for model lifecycle events, but only for models that use the trait. Not all models use it.
- **Evidence**: `app/Http\Middleware\AuditAdminAction.php:20-24`; models not using `Auditable`: `MeasurementCode`, `UnitConversion`, `DataScope`, `Permission`, `Role`, `Subject` (checked by reading model files).
- **Impact**: Incomplete audit trail. Admin actions are logged but non-admin write operations (which include data creation and modification across the entire system) are not consistently audited.
- **Minimum actionable fix**: Either expand `AuditAdminAction` to cover all write operations, or ensure all relevant models use the `Auditable` trait consistently.
- **Minimal verification**: Create a song as a non-admin user, verify an audit log entry is created for that action.

### MEDIUM Severity Issues

#### Issue 9: Login Request Has No Password Length Validation
- **Severity**: Medium
- **Title**: `LoginRequest` accepts any password length, contradicting the minimum 12-character requirement
- **Conclusion**: `LoginRequest::rules()` (`app/Http/Requests/LoginRequest.php:14-20`) only requires password as a non-empty string. A password of any length (including 1 character) will be accepted. While `CreateUserRequest` enforces `min:12`, an existing user with a short password (created directly in DB or via a migration) could log in with a password that does not meet the current policy.
- **Evidence**: `app/Http/Requests/LoginRequest.php:14-20`, `app/Http\Requests\CreateUserRequest.php:18`
- **Impact**: Password policy enforcement gap. Short passwords can still be used for login.
- **Minimum actionable fix**: Add `'password' => ['required', 'string', 'min:12']` to `LoginRequest::rules()`.
- **Minimal verification**: Attempt login with a 5-character password against a user with a valid bcrypt hash, verify it is rejected.

#### Issue 10: Audit Log `before_hash` Captures Pre-Request State, Not Pre-Mutation State
- **Severity**: Medium
- **Title**: Auditable trait captures model state before the current request's processing begins
- **Conclusion**: In `Auditable::writeAuditLog` (`app/Traits/Auditable.php:30`), `$model->getOriginal()` is called inside the `updated` callback. If other middleware or listeners modified the model before the audit callback fires, those modifications are already baked into `$model->getOriginal()`. The `before_hash` captures the state at the start of the request lifecycle, not the state immediately before the specific mutation being audited.
- **Evidence**: `app/Traits/Auditable.php:29-30`
- **Impact**: Audit trail integrity degradation. The before/after hash pair may not accurately represent the specific change made by the current operation.
- **Minimum actionable fix**: Capture `getOriginal()` in a `updating` callback instead of `updated`, or store the original in a model property at the start of the update cycle.
- **Minimal verification**: Static analysis — review model event ordering.

#### Issue 11: `SensitiveFieldProcessor` Only Masks Array Keys, Not Array Values for Non-String Types
- **Severity**: Medium
- **Title**: Sensitive field masking misses non-string sensitive values and nested objects
- **Conclusion**: `FieldMaskingService::maskArray` (`app/Services/FieldMaskingService.php:37-47`) only masks fields where the value is a string. If a sensitive field contains an integer (e.g., a user ID stored in a sensitive context), it will not be masked. Additionally, objects are not handled — only arrays.
- **Evidence**: `app/Services/FieldMaskingService.php:39-41`
- **Impact**: Sensitive data leakage risk. Some sensitive values may appear unmasked in logs.
- **Minimum actionable fix**: Handle integer and other scalar types by casting to string before masking.
- **Minimal verification**: Pass an array with an integer `password` value and verify masking behavior.

#### Issue 12: Disciplinatory Record Appeal by Subject Has No Appeal Deadline
- **Severity**: Medium
- **Title**: Subjects can appeal disciplinary records with no time limit
- **Conclusion**: `DisciplinaryRecordController::appeal` (`app/Http/Controllers/Api\V1/DisciplinaryRecordController.php:141-178`) only checks that the record status is 'active' before allowing an appeal. There is no `appealed_at` null check, no `expires_at` check, and no deadline enforcement. An appealed record can be re-appealed indefinitely. An expired record (status 'active' with `expires_at` in the past) can still be appealed.
- **Evidence**: `app/Http/Controllers/Api\V1\DisciplinaryRecordController.php:154-158`
- **Impact**: Business logic gap — no enforcement of appeal deadlines. Records can be appealed even after expiration.
- **Minimum actionable fix**: Add checks: (1) `appealed_at` must be null for appeal eligibility, (2) `expires_at` must be null or in the future for appeal eligibility.
- **Minimal verification**: Create an appealed record, attempt to appeal it again, verify 422 response.

#### Issue 13: Bulk Notification Validation Allows 10,000 Empty Array (Zero Recipients)
- **Severity**: Medium
- **Title**: `SendBulkNotificationRequest` allows empty recipient_ids array — sends 0 notifications without error
- **Conclusion**: `SendBulkNotificationRequest::rules()` (`app/Http/Requests/SendBulkNotificationRequest.php:18`) uses `'recipient_ids' => ['required', 'array', 'max:10000']`. An empty array `[]` satisfies these rules. The controller checks `count($recipientIds) > 10000` but not `count($recipientIds) < 1`. Sending bulk notifications to zero recipients succeeds with `{sent: 0, skipped: 0}`.
- **Evidence**: `app/Http/Requests/SendBulkNotificationRequest.php:18`, `app/Http\Controllers/Api/V1\NotificationController.php:47-52`
- **Impact**: Silent failure case — no validation error, but zero notifications sent.
- **Minimum actionable fix**: Change `'recipient_ids' => ['required', 'array', 'min:1', 'max:10000']` or add a count check in the controller.
- **Minimal verification**: Send bulk notification with `recipient_ids: []` and verify validation error.

#### Issue 14: Behavior Event Deduplication Uses DB Read-Then-Write Without Transaction
- **Severity**: Medium
- **Title**: Race condition in event deduplication — two concurrent identical events can both pass the dedup check
- **Conclusion**: `BehaviorEventController::store` (`app/Http/Controllers/Api/V1/BehaviorEventController.php:27-40`) performs a SELECT query to check for existing events, then performs an INSERT if none found. Under concurrent requests with the same (user_id, event_type, target_id) within the 5-second window, both requests can pass the SELECT simultaneously, then both INSERT.
- **Evidence**: `app/Http\Controllers\Api\V1\BehaviorEventController.php:27-55`
- **Impact**: Dedup is not atomic — duplicate events can be created under concurrent load.
- **Minimum actionable fix**: Use a database-level unique constraint on `(user_id, event_type, target_id, server_timestamp_bucket)` with a 5-second time bucket, or wrap in a transaction with a locking read.
- **Minimal verification**: Simulate concurrent POST requests with identical parameters and verify only one record is created.

#### Issue 15: Song Model Missing `created_by` Validation
- **Severity**: Medium
- **Title**: No authorization check that the requesting user can modify a specific song/album/playlist
- **Conclusion**: While the music library routes lack permission middleware (Issue #6), even within the controller methods, there is no object-level authorization. Any authenticated user can update, delete, or publish/unpublish any song, regardless of who created it. The `created_by` field is stored but never checked.
- **Evidence**: `app/Http/Controllers/Api/V1/SongController.php:144-180`
- **Impact**: Any authenticated user can modify any catalog item. No ownership or role-based restriction at the object level.
- **Minimum actionable fix**: Add ownership checks in controller methods, or implement a policy class for Song/Album/Playlist.
- **Minimal verification**: Authenticate as User A, create a song, authenticate as User B, update User A's song, verify it succeeds.

#### Issue 16: `before_hash` in Auditable Trait Includes All Fields Including IDs and Timestamps
- **Severity**: Medium
- **Title**: Audit before/after hashes include non-meaningful fields, reducing their forensic value
- **Conclusion**: `Auditable::writeAuditLog` (`app/Traits/Auditable.php:30-31`) hashes `getOriginal()` and `getAttributes()` which include all columns: `id`, `created_at`, `updated_at`, `created_by`, etc. The hash includes non-meaningful fields, making it harder to detect meaningful content changes.
- **Evidence**: `app/Traits/Auditable.php:29-35`
- **Impact**: Reduced forensic value of audit hashes. Hash comparison becomes less meaningful.
- **Minimum actionable fix**: Exclude `id`, `created_at`, `updated_at`, and other metadata fields from the hash by filtering `getOriginal()` and `getAttributes()`.
- **Minimal verification**: Static analysis — compare hash before and after changing only a data field.

### LOW Severity Issues

#### Issue 17: Disciplinatory Record Statistics Uses Raw SQL Join on `subject_user_id` — N+1 Risk
- **Severity**: Low
- **Title**: Stats query joins users table but may miss users with multiple roles
- **Conclusion**: `DisciplinaryRecordController::stats` (`app/Http/Controllers/Api/V1/DisciplinaryRecordController.php:239-249`) uses a raw JOIN to `user_role` table. If a user has multiple roles, their points will be counted multiple times in the stats. The query uses `SUM(disciplinary_records.points)` without `DISTINCT`.
- **Evidence**: `app/Http/Controllers/Api\V1\DisciplinaryRecordController.php:240-249`
- **Impact**: Statistical reporting inflation for multi-role users.
- **Minimum actionable fix**: Use `COUNT(DISTINCT disciplinary_records.id)` or aggregate in a subquery first, then join.

#### Issue 18: Health Endpoint Hardcodes Cache Status
- **Severity**: Low
- **Title**: Health check always reports cache as 'ok' regardless of actual status
- **Conclusion**: `routes/api.php:44` has `'cache' => 'ok'` hardcoded. The actual cache configuration uses `database` driver (`CACHE_STORE=database`). If the cache database table is unavailable or corrupted, the health check still reports 'ok'.
- **Evidence**: `routes/api.php:44`
- **Impact**: Misleading health status — cache could be degraded while endpoint reports healthy.
- **Minimum actionable fix**: Actually check the cache: `Cache::get('health_check') !== null`.
- **Minimal verification**: Drop the cache table, call `/health`, verify cache status.

#### Issue 19: No File Size Limit on `SERVICE_ACCOUNT_ENCRYPTION_KEY`
- **Severity**: Low
- **Title**: No validation that the service account encryption key is properly sized
- **Conclusion**: `SERVICE_ACCOUNT_ENCRYPTION_KEY` is referenced in `docker-compose.yml` and `.env.example` but never validated or used in the codebase. The service account credentials are hashed with bcrypt, not encrypted with a separate key. If the intent was to encrypt stored credentials, no encryption mechanism uses this key.
- **Evidence**: `docker-compose.yml:27`, `.env.example:45`; grep for `SERVICE_ACCOUNT_ENCRYPTION_KEY` usage in all PHP files — **returns no results**.
- **Impact**: Misleading configuration — the encryption key exists in config but is never used.
- **Minimum actionable fix**: Either implement encryption using the key, or remove the configuration variable.
- **Minimal verification**: Static check — verify the key is not referenced in any service or middleware.

#### Issue 20: `RequestIdProcessor` Calls `auth()->id()` on Every Log Line
- **Severity**: Low
- **Title**: Anonymous/unauthenticated log entries include null actor_id, adding noise
- **Conclusion**: `RequestIdProcessor` (`app/Logging/RequestIdProcessor.php:15`) calls `auth()->id()` on every log entry, even for unauthenticated requests. This returns null and adds it to every log extra context.
- **Evidence**: `app/Logging\RequestIdProcessor.php:15`
- **Impact**: Log noise and potential null-handling issues downstream.
- **Minimum actionable fix**: Null-coalesce: `'actor_id' => auth()->id() ?? 'anonymous'`.
- **Minimal verification**: Call `/api/health` (no auth), check logs for actor_id field.

#### Issue 21: `logout` Response Format Inconsistency
- **Severity**: Low
- **Title**: `AuthController::logout` returns `{code, msg}` but test expects `{message}`
- **Conclusion**: `AuthController::logout` (`app/Http/Controllers/Api/V1/AuthController.php:46`) returns `['code' => 200, 'msg' => 'Logged out successfully.']` but the test at `API_tests/AuthApiTest.php:122` asserts `assertJsonPath('message', ...)`. The response format is `{code, msg}` per the README's error format section, but the test is incorrect.
- **Evidence**: `app/Http/Controllers/Api\V1\AuthController.php:46`, `API_tests/AuthApiTest.php:122`
- **Impact**: Test failure — the logout response format test will fail.
- **Minimum actionable fix**: Update the test to use `assertJsonPath('msg', ...)` to match the actual response format.
- **Minimal verification**: Run the test suite.

---

## 6. Security Review Summary

### Authentication Entry Points
- **Conclusion**: Pass
- **Evidence**: `AuthController::login` (`app/Http/Controllers/Api/V1/AuthController.php:20-35`) is the sole login entry point. Bcrypt with cost 12 enforced (`AuthService.php:16`). Lockout after 10 failed attempts (`AuthService.php:13`). Minimum 12-character password required for user creation (`CreateUserRequest.php:18`). Service accounts use separate credential hash with bcrypt (`ServiceAccountService.php:32`). Anonymous tokens cannot be created — Sanctum requires authentication.
- **Note**: SSO configuration key mismatch is a HIGH issue (Issue #5 above) that prevents LDAP from being enabled.

### Route-Level Authorization
- **Conclusion**: Partial Pass
- **Evidence**: Admin routes use `permission:` middleware (`routes/api.php:78-95`). Music library routes have no permission middleware (Issue #6). Notification send/bulk requires `users.list` (`routes/api.php:170-171`). Result listing/review requires `results.review` (`routes/api.php:242-244`). Most non-admin routes require only `auth:sanctum`.
- **Gap**: Music library and many other routes have no authorization beyond authentication.

### Object-Level Authorization
- **Conclusion**: Fail
- **Evidence**: No object-level ownership checks anywhere in the codebase. `SongController`, `AlbumController`, `PlaylistController`, `SubjectController`, `RecommendationController` all accept resource IDs from the request and operate on them without checking ownership or scope. The `ApplyDataScopes` middleware is present but non-functional (Issue #2). The disciplinary record appeal has a basic self-appeal check (`DisciplinaryRecordController.php:147`). Notification reading checks recipient ownership (`NotificationController.php:122-124`).

### Function-Level Authorization
- **Conclusion**: Partial Pass
- **Evidence**: Permission checks are implemented via `CheckPermission` middleware. The `hasPermission` method on User aggregates permissions from all roles. Admin routes consistently use permission middleware. Non-admin routes vary — some have permission requirements (notification send, result review), others have none (music library CRUD).

### Tenant / User Data Isolation
- **Conclusion**: Fail
- **Evidence**: `NotificationController::index` filters by `recipient_id = $request->user()->id` — correct. `NotificationController::markRead` checks `recipient_id` — correct. `SubjectController` has no data scope filtering — subjects are not filtered by user. `DisciplinaryRecordController::index` has no scope filtering. `ResultController::index` has no scope filtering. All data for all subjects/results is visible to any authenticated user with the appropriate permission, regardless of campus or organization scope.

### Admin / Internal / Debug Protection
- **Conclusion**: Pass
- **Evidence**: All admin routes require `auth:sanctum` + `ThrottleServiceAccount` + `ApplyDataScopes` + `AuditAdminAction`. The health endpoint (`/api/health`) is public but only exposes generic status — no debug information. No debug routes or debug controllers are present in the codebase. Service account credentials are shown only once on creation and rotation, never retrievable afterward.

---

## 7. Tests and Logging Review

### Unit Tests
- **Conclusion**: Pass (for what is covered)
- **Evidence**: 11 unit test files covering: `AuthService`, `ProfileComputationService`, `RecommendationService`, `ResultValidationService`, `ResultStatisticsService`, `ServiceAccountService`, `FieldMaskingService`, `NotificationService`, `NotificationModel`, `AuditableTrait`, `VersioningService`. Test frameworks use PHPUnit 11 with RefreshDatabase trait. Tests cover core service logic including happy paths, validation failures, edge cases (e.g., z-score thresholds, rate limits, normalization, expiration).
- **Gap**: Missing unit tests for `ApplyDataScopes` middleware behavior.

### API / Integration Tests
- **Conclusion**: Pass (for what is covered)
- **Evidence**: 17 API test files covering: Auth, Songs, Albums, Playlists, Behavior Events, Notifications, Templates, Subscriptions, Users, Roles, Subjects, Measurement Codes, Results, Disciplinary Records, Evaluation Cycles, Leader Profiles, User Profiles, Health. Tests use `RefreshDatabase`, create test users with appropriate permissions, and assert status codes, JSON structure, and data values.

### Logging Categories / Observability
- **Conclusion**: Pass
- **Evidence**: Structured JSON logging via `config/logging.php` with custom Monolog processors: `RequestIdProcessor` (adds request_id, actor_id, timestamp to all log entries) and `SensitiveFieldProcessor` (masks sensitive fields in log context). All logs use JSON format via `JsonFormatter`. `LOG_STACK=json` in `.env.example`. Log channel is configurable.

### Sensitive-Data Leakage Risk in Logs / Responses
- **Conclusion**: Partial Pass
- **Evidence**: `FieldMaskingService` masks `password`, `password_hash`, `service_credential_hash`, `ip_address`, `identifier` — but not `display_name`, `subject_identifier` in all contexts, or other potentially sensitive fields. The `SubjectResource` masks PII at the API response layer (identifier partially masked, name fully masked for users without `subjects.view_pii`). `UserResource` does not mask any user fields. The `before_hash` and `after_hash` in audit logs include all model fields including potentially sensitive data — the hash obscures the values but the hash itself is deterministic.
- **Gap**: `display_name` is returned in API responses and logged in audit metadata without masking.

---

## 8. Test Coverage Assessment (Static Audit)

### 8.1 Test Overview

| Aspect | Evidence |
|---|---|
| Unit tests exist | 11 files in `unit_tests/` |
| API/integration tests exist | 17 files in `API_tests/` |
| Test framework | PHPUnit 11 (`composer.json:19`) |
| Test entry points | `phpunit.xml` defines `Unit` and `API` testsuites (`phpunit.xml:8-15`) |
| Test commands documented | `run_tests.sh:1-16`, `README.md` does not document test commands |
| Test isolation | `RefreshDatabase` trait used in all tests |

### 8.2 Coverage Mapping Table

| Requirement / Risk Point | Mapped Test Case(s) | Key Assertion / Fixture | Coverage | Gap | Minimum Test Addition |
|---|---|---|---|---|---|
| Login success | `AuthApiTest::test_login_success` | `assertStatus(200)`, token returned | Sufficient | — | — |
| Login wrong password | `AuthApiTest::test_login_wrong_password` | `assertStatus(401)` | Sufficient | — | — |
| Login lockout after 10 failed | `AuthServiceTest::test_lockout_after_10_failed_attempts` | `locked_until` is future | Sufficient | — | — |
| Bcrypt cost 12 | `AuthServiceTest::test_make_hash_produces_valid_bcrypt_hash` | regex `/^\$2[aby]\$12\$/` | Sufficient | — | — |
| Unauthenticated 401 | `SongApiTest::test_unauthenticated_access` | `assertStatus(401)` | Sufficient | — | — |
| Unauthorized 403 | `UserApiTest::test_unauthorized_user_gets_403` | `assertStatus(403)` | Sufficient | — | — |
| Song CRUD | `SongApiTest::test_create_song`, `test_show_song`, `test_update_song`, `test_delete_draft_song` | Status 201, 200, 204 | Sufficient | — | — |
| Song publish/unpublish lifecycle | `SongApiTest::test_publish_song`, `test_unpublish_song`, `test_delete_published_song_fails` | Status 200, 422 | Sufficient | — | — |
| Song version bumping | `SongApiTest::test_bump_version` | version_minor = 1 | Sufficient | — | — |
| Song search/filter | `SongApiTest::test_filter_songs_by_artist`, `test_filter_songs_by_publish_state` | 1 result in meta.total | Sufficient | — | — |
| Album CRUD + lifecycle | `AlbumApiTest::test_crud_album`, `test_publish_unpublish_album`, `test_add_song_to_album` | Status 200/201, publish_state checks | Sufficient | — | — |
| Playlist CRUD | (PlaylistApiTest.php does not exist) | — | Missing | No Playlist API test file | Add PlaylistApiTest with CRUD, publish/unpublish, song management tests |
| Notification send/receive | `NotificationApiTest::test_send_notification_succeeds` | `sent: 1`, DB check | Sufficient | — | — |
| Notification rate limiting | `NotificationServiceTest::test_send_enforces_rate_limit` | 4th send skipped | Sufficient | — | — |
| Notification bulk cap | (No test for 10,000 cap enforcement) | — | Missing | No test for cap > 10000 rejection | Add test sending > 10000 recipients |
| Notification unsubscribe | `NotificationApiTest::test_list_notifications_returns_own_only` | recipient isolation | Sufficient | — | — |
| Notification read receipts | `NotificationApiTest::test_mark_notification_as_read`, `test_mark_read_is_idempotent` | read_at not null, idempotent | Sufficient | — | — |
| Behavior event dedup | `BehaviorEventApiTest::test_dedup_within_5_seconds` | `deduplicated: true` on 2nd call | Sufficient | — | — |
| Behavior event permission | `BehaviorEventApiTest::test_list_events_requires_permission` | `assertStatus(403)` | Sufficient | — | — |
| Result entry validation | `ResultApiTest::test_manual_result_entry`, `ResultValidationServiceTest` | Status 201, result created | Sufficient | — | — |
| Result z-score outlier | `ResultValidationServiceTest::test_detects_z_score_outlier` | `is_outlier: true`, `pending` status | Sufficient | — | — |
| Result self-review rejection | `ResultApiTest::test_cannot_self_review` | `assertStatus(422)` | Sufficient | — | — |
| Result CSV import | `ResultApiTest::test_csv_import` | `imported: 1` | Sufficient | — | — |
| Result batch import | `ResultApiTest::test_batch_result_entry` | `imported: 2` | Sufficient | — | — |
| observed_at max 5min future | `ResultValidationServiceTest::test_rejects_observed_at_more_than_5_min_in_future` | throws ValidationException | Sufficient | — | — |
| Disciplinary record create | `DisciplinaryRecordApiTest::test_create_disciplinary_record` | `status: active` | Sufficient | — | — |
| Disciplinary status transitions | `DisciplinaryRecordApiTest::test_appeal_active_record`, `test_clear_appealed_record`, `test_cannot_appeal_cleared_record` | status checks | Sufficient | — | — |
| Disciplinary stats | `DisciplinaryRecordApiTest::test_stats_by_category` | 2 groups returned | Sufficient | — | — |
| Data scoping | (No test for ApplyDataScopes) | — | Missing | No test verifies scope filtering | Add test with scoped user, verify filtered results |
| Object-level auth | (No test for cross-user access) | — | Missing | No test verifies User A cannot modify User B's resource | Add test: authenticate as User A, modify User B's song, verify 403 |
| SSO/LDAP | (No test for LDAP authentication) | — | Missing | No test for SSO path | Add unit test for `validateViaSso` method |
| Service account throttling | (No test for ThrottleServiceAccount) | — | Missing | No test verifies 429 response after 60 requests | Add test: make 60 requests, verify 429 on 61st |
| Service account rotation | `ServiceAccountServiceTest::test_old_credential_no_longer_works` | old hash doesn't match new | Sufficient | — | — |
| PII masking | `SubjectApiTest::test_list_subjects_pii_masked` | identifier masked `***5678` | Sufficient | — | — |
| Audit trail | `AuditableTraitTest::test_updating_a_model_writes_audit_log` | before/after hashes differ | Sufficient | — | — |
| Cold-start recommendations | `RecommendationServiceTest::test_cold_start_returns_popular_songs` | score = 0 | Sufficient | — | — |
| Personalized recommendations | `RecommendationServiceTest::test_personalized_returns_scored_songs` | candidate song in results, excluded interacted | Sufficient | — | — |
| Profile computation weights | (No test verifying configurable weights) | — | Missing | Hardcoded weights — no test for configuration | Add test for custom weight configuration |

### 8.3 Security Coverage Audit

| Security Risk | Coverage | Explanation |
|---|---|---|
| Authentication | Sufficient | Login, wrong password, lockout, bcrypt cost all tested. |
| Route authorization | Basically covered | Unauthorized 403 tested for admin routes. Music library route auth gaps (Issue #6) are not tested. |
| Object-level authorization | Missing | No test verifies that User A cannot modify User B's resources. Critical gap. |
| Tenant / data isolation | Missing | No test for data scoping enforcement. ApplyDataScopes middleware has no test. |
| Admin/internal protection | Basically covered | Permission-based access tested for several admin routes. |
| Sensitive data masking | Sufficient | PII masking tested via SubjectResource. FieldMaskingService has unit tests. |

### 8.4 Final Coverage Judgment

**Fail**

The test suite covers the majority of happy paths, input validation failures, and authorization checks for admin routes. However, critical uncovered risks include:

1. **Object-level authorization** — No test verifies that users cannot access or modify other users' resources. This gap, combined with Issues #6 (no permission middleware on music library) and #7 (recommendations endpoint), means the authorization model is untested at the most important enforcement points.

2. **Data scoping enforcement** — The `ApplyDataScopes` middleware (which is already non-functional per Issue #2) has zero test coverage. This means even if the implementation were fixed, there would be no regression protection.

3. **Service account throttling** — No test verifies that service account rate limiting actually returns 429 after 60 requests.

4. **SSO/LDAP path** — The `validateViaSso` method has no test coverage.

5. **Bulk notification cap** — No test verifies that sending >10,000 recipients is rejected.

The tests could pass while severe authorization defects remain undetected. The coverage is broad but thin on enforcement validation.

---

## 9. Final Notes

This is a well-structured Laravel application with comprehensive implementation across all major Prompt requirements. The codebase is production-like in its architecture: layered services, proper validation, structured logging, audit trails, Docker deployment, and extensive test coverage.

However, three issues are blockers that prevent a Pass verdict:

1. **Title/artist min-length validation** is absent, directly contradicting the Prompt's "1-200 chars" requirement.
2. **Data scopes are collected but never applied** — the RBAC data scoping requirement is implemented as infrastructure without the enforcement mechanism.
3. **Music library routes have no permission authorization** — any authenticated user can fully administer the entire music catalog.

These are not edge cases or cosmetic issues. They represent deviations from explicit Prompt requirements and fundamental security/authorization gaps. A production deployment with these issues would fail compliance audits and expose data to unauthorized modification.

The overall engineering quality is high — the codebase demonstrates Laravel proficiency, security awareness in most areas (bcrypt, lockout, token revocation, audit logs, masking), and thorough testing of service logic. With the identified issues addressed, this would be a strong Pass.

**Priority for remediation**: Fix Issues #1, #2, #6, #7, and #5 first (Blockers and High). Then address Medium issues. Then expand test coverage for authorization gaps.
