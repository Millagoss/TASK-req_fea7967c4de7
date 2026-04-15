# Meridian Records & Compliance Backend - API Specification

## Base URL

```
http://localhost:8080/api/v1
```

All endpoints are prefixed with `/api/v1` unless otherwise noted. The health check is at `/api/health`.

## Authentication

All protected endpoints require a Bearer token in the `Authorization` header:

```
Authorization: Bearer <sanctum-token>
```

## Response Format

### Success Responses

Single resource:
```json
{ "data": { ... } }
```

Paginated collection:
```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

### Error Responses

```json
{
  "code": <HTTP status code>,
  "msg": "<Human-readable message>"
}
```

### Common Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 401 | Unauthenticated (missing/invalid token) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Resource not found |
| 422 | Validation error |
| 429 | Rate limited |

---

## 1. Health Check

### `GET /api/health`

Check system health (no authentication required).

**Response 200:**
```json
{
  "status": "ok",
  "timestamp": "2024-01-15T10:30:00+00:00",
  "services": {
    "database": "ok",
    "cache": "ok"
  },
  "version": "1.0.0"
}
```

**Response 503** (degraded):
```json
{
  "status": "degraded",
  "timestamp": "2024-01-15T10:30:00+00:00",
  "services": {
    "database": "ok",
    "cache": "unavailable"
  },
  "version": "1.0.0"
}
```

---

## 2. Authentication

### `POST /auth/login`

Authenticate a user and receive a Sanctum token.

**Auth:** None

**Request Body:**
```json
{
  "username": "admin",
  "password": "Admin@Password1"
}
```

**Validation:**
- `username`: required, string
- `password`: required, string

**Response 200:**
```json
{
  "data": {
    "token": "1|abc123...",
    "user": {
      "id": 1,
      "username": "admin",
      "display_name": "Administrator",
      "is_active": true,
      "roles": [
        {
          "id": 1,
          "name": "admin",
          "permissions": [
            { "id": 1, "name": "users.list" },
            { "id": 2, "name": "users.create" }
          ]
        }
      ]
    }
  }
}
```

**Response 401** (invalid credentials):
```json
{ "code": 401, "msg": "Invalid credentials." }
```

**Response 423** (locked):
```json
{ "code": 423, "msg": "Account locked due to too many failed attempts. Try again later." }
```

**Business Rules:**
- 10 failed attempts in 15 minutes triggers lockout
- All login attempts are logged to `login_attempts` table
- If `SSO_ENABLED=true`, LDAP bind is attempted first

---

### `POST /auth/logout`

Revoke the current token.

**Auth:** Required

**Response 200:**
```json
{ "msg": "Logged out successfully." }
```

---

### `POST /auth/logout-all`

Revoke all tokens for the current user (forced logout from all sessions).

**Auth:** Required

**Response 200:**
```json
{ "msg": "All sessions revoked." }
```

---

### `GET /auth/me`

Get current user profile with roles and permissions.

**Auth:** Required

**Response 200:**
```json
{
  "data": {
    "id": 1,
    "username": "admin",
    "display_name": "Administrator",
    "is_service_account": false,
    "is_active": true,
    "roles": [
      {
        "id": 1,
        "name": "admin",
        "description": "Full system access",
        "permissions": [
          { "id": 1, "name": "users.list", "description": "View user listings" }
        ]
      }
    ],
    "all_permissions": ["users.list", "users.create", "users.update", "..."]
  }
}
```

---

## 3. Admin - User Management

### `GET /admin/users`

List all users (paginated).

**Auth:** Required | **Permission:** `users.list`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page (1-100) |
| `search` | string | — | Search by username or display_name |
| `is_active` | boolean | — | Filter by active status |

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "username": "admin",
      "display_name": "Administrator",
      "is_service_account": false,
      "is_active": true,
      "locked_until": null,
      "roles": [ { "id": 1, "name": "admin" } ],
      "created_at": "2024-01-01T00:00:00+00:00",
      "updated_at": "2024-01-01T00:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}
```

---

### `POST /admin/users`

Create a new user.

**Auth:** Required | **Permission:** `users.create`

**Request Body:**
```json
{
  "username": "jsmith",
  "password": "SecurePassword1!",
  "display_name": "John Smith",
  "is_active": true,
  "role_ids": [2, 3]
}
```

**Validation:**
- `username`: required, string, unique, max 255
- `password`: required, string, min 12 characters
- `display_name`: required, string, max 255
- `is_active`: optional, boolean (default true)
- `role_ids`: optional, array of existing role IDs

**Response 201:**
```json
{
  "data": {
    "id": 2,
    "username": "jsmith",
    "display_name": "John Smith",
    "is_active": true,
    "roles": [ ... ],
    "created_at": "2024-01-15T10:00:00+00:00"
  }
}
```

---

### `PUT /admin/users/{id}`

Update an existing user.

**Auth:** Required | **Permission:** `users.update`

**Request Body** (all fields optional):
```json
{
  "display_name": "John D. Smith",
  "password": "NewPassword123!",
  "is_active": false
}
```

**Validation:**
- `display_name`: optional, string, max 255
- `password`: optional, string, min 12 characters
- `is_active`: optional, boolean

**Response 200:**
```json
{ "data": { ... } }
```

---

### `POST /admin/users/{id}/roles`

Assign roles to a user.

**Auth:** Required | **Permission:** `users.update`

**Request Body:**
```json
{
  "role_ids": [1, 2]
}
```

**Validation:**
- `role_ids`: required, array of existing role IDs

**Response 200:**
```json
{ "data": { ... } }
```

---

### `DELETE /admin/users/{id}/roles/{roleId}`

Remove a specific role from a user.

**Auth:** Required | **Permission:** `users.update`

**Response 200:**
```json
{ "msg": "Role removed successfully." }
```

---

## 4. Admin - Service Accounts

### `POST /admin/service-accounts`

Create a service account. The credential is shown once in the response.

**Auth:** Required | **Permission:** `service_accounts.create`

**Request Body:**
```json
{
  "username": "integration-bot",
  "display_name": "Integration Bot"
}
```

**Response 201:**
```json
{
  "data": {
    "user": {
      "id": 5,
      "username": "integration-bot",
      "display_name": "Integration Bot",
      "is_service_account": true
    },
    "credential": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
  }
}
```

> The `credential` field is shown only once. Store it securely.

---

### `POST /admin/service-accounts/{id}/rotate`

Rotate a service account's credential. Revokes all existing tokens.

**Auth:** Required | **Permission:** `service_accounts.create`

**Response 200:**
```json
{
  "data": {
    "user": { ... },
    "credential": "new-32-character-random-credential"
  }
}
```

---

## 5. Admin - Roles & Permissions

### `GET /admin/roles`

List all roles with their permissions.

**Auth:** Required | **Permission:** `roles.list`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "admin",
      "description": "Full system access",
      "permissions": [
        { "id": 1, "name": "users.list", "description": "View user listings" }
      ],
      "created_at": "2024-01-01T00:00:00+00:00"
    }
  ]
}
```

---

### `POST /admin/roles`

Create a new role.

**Auth:** Required | **Permission:** `roles.create`

**Request Body:**
```json
{
  "name": "editor",
  "description": "Content editor role"
}
```

**Validation:**
- `name`: required, string, unique, max 255
- `description`: optional, string, max 1000

**Response 201:**
```json
{ "data": { ... } }
```

---

### `PUT /admin/roles/{id}`

Update a role.

**Auth:** Required | **Permission:** `roles.update`

**Request Body:**
```json
{
  "name": "senior-editor",
  "description": "Senior content editor"
}
```

**Response 200:**
```json
{ "data": { ... } }
```

---

### `POST /admin/roles/{id}/permissions`

Assign permissions to a role (replaces existing).

**Auth:** Required | **Permission:** `roles.update`

**Request Body:**
```json
{
  "permission_ids": [1, 2, 3]
}
```

**Response 200:**
```json
{ "data": { ... } }
```

---

### `GET /admin/permissions`

List all available permissions.

**Auth:** Required | **Permission:** `roles.list`

**Response 200:**
```json
{
  "data": [
    { "id": 1, "name": "users.list", "description": "View user listings" },
    { "id": 2, "name": "users.create", "description": "Create users" }
  ]
}
```

---

## 6. Music Library - Songs

### `GET /songs`

List/search songs (paginated).

**Auth:** Required | **Permission:** `music.read`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `q` | string | — | Keyword search (title, artist via FULLTEXT) |
| `artist` | string | — | Filter by exact artist |
| `tags` | string | — | Comma-separated tag filter |
| `audio_quality` | string | — | Filter by enum: MP3_320, FLAC_16_44, FLAC_24_96 |
| `publish_state` | string | — | Filter: draft, published, unpublished |
| `duration_min` | integer | — | Minimum duration in seconds |
| `duration_max` | integer | — | Maximum duration in seconds |
| `sort_by` | string | updated_at | Sort column: title, artist, duration_seconds, created_at, updated_at |
| `sort_dir` | string | desc | Sort direction: asc, desc |
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page (1-100) |

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Moonlight Sonata",
      "artist": "Beethoven",
      "duration_seconds": 360,
      "audio_quality": "FLAC_16_44",
      "cover_art_path": "cover-art/songs/1/abc123.jpg",
      "cover_art_sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
      "publish_state": "published",
      "version": "1.2.0",
      "tags": [
        { "id": 1, "tag": "classical" },
        { "id": 2, "tag": "piano" }
      ],
      "created_by": 1,
      "created_at": "2024-01-01T00:00:00+00:00",
      "updated_at": "2024-01-15T10:00:00+00:00"
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 100 }
}
```

**Pagination:** Results are ordered by `{sort_by} {sort_dir}, id ASC` for deterministic pagination.

---

### `POST /songs`

Create a new song.

**Auth:** Required | **Permission:** `music.create`

**Request Body:**
```json
{
  "title": "New Song",
  "artist": "Artist Name",
  "duration_seconds": 240,
  "audio_quality": "MP3_320",
  "tags": ["rock", "indie"]
}
```

**Validation:**
- `title`: required, string, 1-200 characters
- `artist`: required, string, 1-200 characters
- `duration_seconds`: required, integer, 1-7200
- `audio_quality`: required, enum: MP3_320, FLAC_16_44, FLAC_24_96
- `tags`: optional, array, max 20 items
- `tags.*`: string, regex `^[a-z0-9-]+$`, 2-24 characters

**Response 201:**
```json
{ "data": { "id": 2, "publish_state": "draft", "version": "1.0.0", ... } }
```

**Notes:**
- New songs start as `draft` with version `1.0.0`
- `created_by` is set to the authenticated user

---

### `GET /songs/{id}`

Get a single song with tags.

**Auth:** Required | **Permission:** `music.read`

**Response 200:**
```json
{ "data": { ... } }
```

**Response 404:**
```json
{ "code": 404, "msg": "Song not found." }
```

---

### `PUT /songs/{id}`

Update a song's metadata.

**Auth:** Required | **Permission:** `music.update`

**Request Body** (all fields optional):
```json
{
  "title": "Updated Title",
  "artist": "New Artist",
  "duration_seconds": 300,
  "audio_quality": "FLAC_24_96",
  "tags": ["electronic", "ambient"]
}
```

**Response 200:**
```json
{ "data": { ... } }
```

**Notes:**
- Updating a published song auto-increments the patch version
- Tags are replaced entirely (not merged)

---

### `DELETE /songs/{id}`

Delete a song (only draft state).

**Auth:** Required | **Permission:** `music.delete`

**Response 200:**
```json
{ "msg": "Song deleted successfully." }
```

**Response 422** (non-draft):
```json
{ "code": 422, "msg": "Only draft songs can be deleted." }
```

---

### `POST /songs/{id}/publish`

Transition song to `published` state.

**Auth:** Required | **Permission:** `music.publish`

**Valid transitions:** `draft` -> `published`, `unpublished` -> `published`

**Response 200:**
```json
{ "data": { "publish_state": "published", ... } }
```

**Response 422** (invalid transition):
```json
{ "code": 422, "msg": "Song is already published." }
```

---

### `POST /songs/{id}/unpublish`

Transition song to `unpublished` state.

**Auth:** Required | **Permission:** `music.publish`

**Valid transitions:** `published` -> `unpublished`

**Response 200:**
```json
{ "data": { "publish_state": "unpublished", ... } }
```

---

### `POST /songs/{id}/version`

Manually bump the semantic version.

**Auth:** Required | **Permission:** `music.update`

**Request Body:**
```json
{
  "bump": "major"
}
```

**Validation:**
- `bump`: required, enum: `major`, `minor`

**Versioning logic:**
- `major`: increments major, resets minor and patch to 0
- `minor`: increments minor, resets patch to 0

**Response 200:**
```json
{ "data": { "version": "2.0.0", ... } }
```

---

### `POST /songs/{id}/cover-art`

Upload cover art image.

**Auth:** Required | **Permission:** `music.update`

**Request:** `multipart/form-data`

| Field | Type | Description |
|-------|------|-------------|
| `cover_art` | file | Image file (jpeg, png, webp), max 5 MB |

**Response 200:**
```json
{
  "data": {
    "cover_art_path": "cover-art/songs/1/e3b0c4...jpg",
    "cover_art_sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
    ...
  }
}
```

**Response 422:**
```json
{ "code": 422, "msg": "The cover art must be a file of type: jpeg, png, webp." }
```

---

## 7. Music Library - Albums

### `GET /albums`

List/search albums (paginated). Same query parameters as songs (excluding `duration_*` and `audio_quality`).

**Auth:** Required | **Permission:** `music.read`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Greatest Hits",
      "artist": "Various",
      "cover_art_path": null,
      "cover_art_sha256": null,
      "publish_state": "draft",
      "version": "1.0.0",
      "songs_count": 12,
      "created_by": 1,
      "created_at": "2024-01-01T00:00:00+00:00",
      "updated_at": "2024-01-01T00:00:00+00:00"
    }
  ],
  "meta": { ... }
}
```

---

### `POST /albums`

Create a new album.

**Auth:** Required | **Permission:** `music.create`

**Request Body:**
```json
{
  "title": "New Album",
  "artist": "Artist Name"
}
```

**Validation:**
- `title`: required, string, 1-200 characters
- `artist`: required, string, 1-200 characters

**Response 201:**
```json
{ "data": { "id": 1, "publish_state": "draft", "version": "1.0.0", ... } }
```

---

### `GET /albums/{id}`

Get album details with songs.

**Auth:** Required | **Permission:** `music.read`

---

### `PUT /albums/{id}`

Update album metadata.

**Auth:** Required | **Permission:** `music.update`

---

### `DELETE /albums/{id}`

Delete album (draft only).

**Auth:** Required | **Permission:** `music.delete`

---

### `POST /albums/{id}/publish`

Publish album. Same rules as songs.

**Auth:** Required | **Permission:** `music.publish`

---

### `POST /albums/{id}/unpublish`

Unpublish album. Same rules as songs.

**Auth:** Required | **Permission:** `music.publish`

---

### `POST /albums/{id}/version`

Bump album version. Same rules as songs.

**Auth:** Required | **Permission:** `music.update`

---

### `POST /albums/{id}/cover-art`

Upload album cover art. Same rules as songs.

**Auth:** Required | **Permission:** `music.update`

---

### `GET /albums/{id}/songs`

List songs in an album (ordered by position).

**Auth:** Required | **Permission:** `music.read`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Track 1",
      "artist": "Artist",
      "position": 1,
      ...
    }
  ]
}
```

---

### `POST /albums/{id}/songs`

Add a song to an album.

**Auth:** Required | **Permission:** `music.update`

**Request Body:**
```json
{
  "song_id": 5,
  "position": 3
}
```

**Validation:**
- `song_id`: required, integer, must exist in songs table
- `position`: optional, integer, min 1

**Response 200:**
```json
{ "data": { ... } }
```

---

### `DELETE /albums/{id}/songs/{songId}`

Remove a song from an album.

**Auth:** Required | **Permission:** `music.update`

**Response 200:**
```json
{ "msg": "Song removed from album." }
```

---

## 8. Music Library - Playlists

### `GET /playlists`

List playlists (paginated).

**Auth:** Required | **Permission:** `music.read`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "My Playlist",
      "description": "A curated collection",
      "publish_state": "draft",
      "version": "1.0.0",
      "songs_count": 25,
      "created_by": 1,
      "created_at": "2024-01-01T00:00:00+00:00"
    }
  ],
  "meta": { ... }
}
```

---

### `POST /playlists`

Create a playlist.

**Auth:** Required | **Permission:** `music.create`

**Request Body:**
```json
{
  "title": "Weekend Vibes",
  "description": "Relaxing weekend tracks"
}
```

**Validation:**
- `title`: required, string, 1-200 characters
- `description`: optional, string, max 1000

**Response 201:**
```json
{ "data": { ... } }
```

---

### `GET /playlists/{id}`

Get playlist details with songs.

**Auth:** Required | **Permission:** `music.read`

---

### `PUT /playlists/{id}`

Update playlist metadata.

**Auth:** Required | **Permission:** `music.update`

---

### `DELETE /playlists/{id}`

Delete playlist (draft only).

**Auth:** Required | **Permission:** `music.delete`

---

### `POST /playlists/{id}/publish`

Publish playlist.

**Auth:** Required | **Permission:** `music.publish`

---

### `POST /playlists/{id}/unpublish`

Unpublish playlist.

**Auth:** Required | **Permission:** `music.publish`

---

### `POST /playlists/{id}/version`

Bump playlist version.

**Auth:** Required | **Permission:** `music.update`

---

### `GET /playlists/{id}/songs`

List songs in a playlist (ordered by position).

**Auth:** Required | **Permission:** `music.read`

---

### `POST /playlists/{id}/songs`

Add a song to a playlist.

**Auth:** Required | **Permission:** `music.update`

**Request Body:**
```json
{
  "song_id": 10,
  "position": 5
}
```

---

### `DELETE /playlists/{id}/songs/{songId}`

Remove a song from a playlist.

**Auth:** Required | **Permission:** `music.update`

---

## 9. Behavior & Analytics

### `POST /behavior/events`

Record a user behavior event.

**Auth:** Required

**Request Body:**
```json
{
  "event_type": "favorite",
  "target_type": "song",
  "target_id": 42,
  "payload": { "context": "album_view" }
}
```

**Validation:**
- `event_type`: required, enum: browse, search, click, favorite, rate, comment
- `target_type`: required, string, max 50
- `target_id`: required, integer
- `payload`: optional, JSON object

**Response 201** (new event):
```json
{
  "data": {
    "id": 1,
    "user_id": 3,
    "event_type": "favorite",
    "target_type": "song",
    "target_id": 42,
    "payload": { "context": "album_view" },
    "server_timestamp": "2024-01-15T10:30:00.123+00:00",
    "request_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

**Response 200** (duplicate within 5s window):
```json
{ "data": { ... } }
```

Returns the existing event if the same `(user_id, event_type, target_id)` was recorded within the last 5 seconds.

**Business Rules:**
- `server_timestamp` is always set server-side (UTC). Client timestamps are ignored.
- `user_id` is set from the authenticated user.
- `request_id` is set from the middleware.

---

### `GET /behavior/events`

List behavior events (admin/analyst view).

**Auth:** Required | **Permission:** `users.list`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `user_id` | integer | — | Filter by user |
| `event_type` | string | — | Filter by type |
| `target_type` | string | — | Filter by target type |
| `target_id` | integer | — | Filter by target |
| `from` | datetime | — | Start timestamp |
| `to` | datetime | — | End timestamp |
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page (1-100) |

**Response 200:**
```json
{
  "data": [ ... ],
  "meta": { ... }
}
```

---

### `GET /users/{id}/profile`

Get the behavior profile for a user.

**Auth:** Required

**Response 200:**
```json
{
  "data": {
    "id": 1,
    "user_id": 3,
    "interest_tags": {
      "rock": 0.85,
      "indie": 0.72,
      "electronic": 0.45
    },
    "preference_vector": {
      "Radiohead": 0.92,
      "Tame Impala": 0.78,
      "Aphex Twin": 0.55
    },
    "last_computed_at": "2024-01-15T09:00:00+00:00"
  }
}
```

**Response 404** (no profile yet):
```json
{ "code": 404, "msg": "Profile not found." }
```

---

### `POST /users/{id}/profile/recompute`

Trigger an immediate profile recomputation.

**Auth:** Required | **Permission:** `users.list`

**Response 200:**
```json
{ "data": { ... } }
```

**Computation logic:**
1. Fetch events from the last 90 days
2. Apply weights: browse=1, search=1, click=2, favorite=3, rate=5, comment=2
3. Apply decay: `score = weight * 0.5^(age_days / 30)`
4. Aggregate into tag scores and artist scores
5. Normalize to 0-1.0 range

---

### `GET /recommendations/{userId}`

Get personalized song recommendations.

**Auth:** Required

**Response 200:**
```json
{
  "data": [
    {
      "song": {
        "id": 15,
        "title": "Recommended Song",
        "artist": "Some Artist",
        ...
      },
      "score": 0.92,
      "reason": "Based on your interest in rock"
    }
  ]
}
```

**Business Rules:**
- Returns max 20 recommendations, deduplicated
- **Cold-start** (< 5 events): popular songs in last 7 days + content-similar
- **Warm** (>= 5 events): personalized by top interest tags + artists from profile

---

## 10. Notification Templates

### `GET /notification-templates`

List all templates (paginated, ordered by name).

**Auth:** Required

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "welcome_email",
      "subject": "Welcome {{username}}",
      "body": "Hello {{username}}, welcome to Meridian!",
      "variables": ["username"],
      "created_by": 1,
      "created_at": "2024-01-01T00:00:00+00:00",
      "updated_at": "2024-01-01T00:00:00+00:00"
    }
  ],
  "meta": { ... }
}
```

---

### `POST /notification-templates`

Create a template.

**Auth:** Required | **Permission:** `roles.create`

**Request Body:**
```json
{
  "name": "song_published",
  "subject": "New song: {{song_title}}",
  "body": "{{artist}} just published {{song_title}}. Check it out!",
  "variables": ["song_title", "artist"]
}
```

**Validation:**
- `name`: required, string, unique, max 255
- `subject`: required, string, max 500
- `body`: required, string, max 10000
- `variables`: required, array of strings

**Response 201:**
```json
{ "data": { ... } }
```

---

### `PUT /notification-templates/{id}`

Update a template.

**Auth:** Required | **Permission:** `roles.update`

**Request Body** (all fields optional):
```json
{
  "name": "updated_name",
  "subject": "New Subject",
  "body": "New Body",
  "variables": ["var1", "var2"]
}
```

**Response 200:**
```json
{ "data": { ... } }
```

---

### `DELETE /notification-templates/{id}`

Delete a template. Fails if notifications reference it.

**Auth:** Required | **Permission:** `roles.update`

**Response 200:**
```json
{ "msg": "Template deleted successfully." }
```

**Response 422** (referenced by notifications):
```json
{ "code": 422, "msg": "Cannot delete template that has associated notifications." }
```

---

## 11. Notifications

### `POST /notifications/send`

Send notifications to specific users.

**Auth:** Required | **Permission:** `users.list`

**Request Body:**
```json
{
  "template_id": 1,
  "recipient_ids": [2, 3, 4],
  "variables": {
    "username": "Alice",
    "song_title": "Moonlight"
  }
}
```

**Validation:**
- `template_id`: required, integer, must exist
- `recipient_ids`: required, array of user IDs
- `variables`: required, object, must contain all variables declared in the template

**Response 200:**
```json
{
  "sent": 2,
  "skipped": 1,
  "skipped_reasons": [
    { "user_id": 4, "reason": "unsubscribed" }
  ]
}
```

**Skip Reasons:**
- `unsubscribed` — user opted out of this template
- `rate_limited` — exceeded 3 notifications per template per hour

---

### `POST /notifications/send-bulk`

Bulk send notifications (max 10,000 recipients).

**Auth:** Required | **Permission:** `users.list`

**Request Body:** Same as `/notifications/send` but allows up to 10,000 recipient IDs.

**Response 200:**
```json
{
  "sent": 9500,
  "skipped": 500,
  "skipped_reasons": [ ... ],
  "batch_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Response 422** (too many recipients):
```json
{ "code": 422, "msg": "Bulk send cannot exceed 10,000 recipients." }
```

---

### `GET /notifications`

List current user's notifications (paginated).

**Auth:** Required

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `read` | boolean | — | Filter: true=only read, false=only unread |
| `template_id` | integer | — | Filter by template |
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page (1-100) |

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "template_id": 1,
      "subject_rendered": "Welcome Alice",
      "body_rendered": "Hello Alice, welcome to Meridian!",
      "variables_used": { "username": "Alice" },
      "batch_id": null,
      "read_at": null,
      "created_at": "2024-01-15T10:00:00+00:00"
    }
  ],
  "meta": { ... }
}
```

---

### `POST /notifications/{id}/read`

Mark a notification as read (idempotent).

**Auth:** Required (must own the notification)

**Response 200:**
```json
{ "data": { "read_at": "2024-01-15T11:00:00+00:00", ... } }
```

---

### `POST /notifications/read-all`

Mark all unread notifications as read for the current user.

**Auth:** Required

**Response 200:**
```json
{ "updated": 5 }
```

---

### `GET /notifications/unread-count`

Get the count of unread notifications.

**Auth:** Required

**Response 200:**
```json
{ "unread_count": 12 }
```

---

## 12. Subscriptions

### `GET /subscriptions`

List all templates with subscription status for the current user.

**Auth:** Required

**Response 200:**
```json
{
  "data": [
    { "template_id": 1, "template_name": "welcome_email", "is_subscribed": true },
    { "template_id": 2, "template_name": "song_published", "is_subscribed": false }
  ]
}
```

---

### `PUT /subscriptions`

Update subscription preferences.

**Auth:** Required

**Request Body:**
```json
{
  "subscriptions": [
    { "template_id": 1, "is_subscribed": false },
    { "template_id": 2, "is_subscribed": true }
  ]
}
```

**Validation:**
- `subscriptions`: required, array
- `subscriptions.*.template_id`: required, integer, must exist
- `subscriptions.*.is_subscribed`: required, boolean

**Response 200:**
```json
{ "data": [ ... ] }
```

---

## 13. Evaluation Cycles

### `GET /evaluation-cycles`

List evaluation cycles (paginated).

**Auth:** Required | **Permission:** `users.list`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | — | Filter by: draft, active, closed |
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page |

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Q1 2024 Evaluation",
      "start_date": "2024-01-01",
      "end_date": "2024-03-31",
      "status": "active",
      "created_by": 1,
      "created_at": "2024-01-01T00:00:00+00:00"
    }
  ],
  "meta": { ... }
}
```

---

### `POST /evaluation-cycles`

Create an evaluation cycle.

**Auth:** Required | **Permission:** `roles.create`

**Request Body:**
```json
{
  "name": "Q2 2024 Evaluation",
  "start_date": "2024-04-01",
  "end_date": "2024-06-30"
}
```

**Validation:**
- `name`: required, string, max 255
- `start_date`: required, date
- `end_date`: required, date, after start_date

**Response 201:**
```json
{ "data": { "status": "draft", ... } }
```

---

### `GET /evaluation-cycles/{id}`

Get cycle details.

**Auth:** Required | **Permission:** `users.list`

---

### `PUT /evaluation-cycles/{id}`

Update a cycle (only in draft status).

**Auth:** Required | **Permission:** `roles.update`

---

### `POST /evaluation-cycles/{id}/activate`

Transition: `draft` -> `active`.

**Auth:** Required | **Permission:** `roles.update`

**Response 200:**
```json
{ "data": { "status": "active", ... } }
```

**Response 422:**
```json
{ "code": 422, "msg": "Cycle can only be activated from draft status." }
```

---

### `POST /evaluation-cycles/{id}/close`

Transition: `active` -> `closed`.

**Auth:** Required | **Permission:** `roles.update`

**Response 200:**
```json
{ "data": { "status": "closed", ... } }
```

---

## 14. Leader Profiles

### `GET /leader-profiles`

List leader profiles.

**Auth:** Required | **Permission:** `users.list`

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": 5,
      "title": "Department Head",
      "department": "Engineering",
      "campus": "Main Campus",
      "user": { "id": 5, "username": "jdoe", "display_name": "Jane Doe" },
      "created_at": "2024-01-01T00:00:00+00:00"
    }
  ]
}
```

---

### `POST /leader-profiles`

Create a leader profile (one per user).

**Auth:** Required | **Permission:** `users.create`

**Request Body:**
```json
{
  "user_id": 5,
  "title": "Department Head",
  "department": "Engineering",
  "campus": "Main Campus"
}
```

**Validation:**
- `user_id`: required, integer, must exist, unique (one profile per user)
- `title`: required, string, max 255
- `department`: required, string, max 255
- `campus`: optional, string, max 255

**Response 201:**
```json
{ "data": { ... } }
```

---

### `GET /leader-profiles/{id}`

Get a leader profile.

**Auth:** Required | **Permission:** `users.list`

---

### `PUT /leader-profiles/{id}`

Update a leader profile.

**Auth:** Required | **Permission:** `users.update`

---

## 15. Reward/Penalty Types

### `GET /reward-penalty-types`

List all reward/penalty types.

**Auth:** Required

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Late Submission",
      "category": "penalty",
      "severity": "low",
      "default_points": -5,
      "default_expiration_days": 365,
      "is_active": true,
      "created_at": "2024-01-01T00:00:00+00:00"
    }
  ]
}
```

---

### `POST /reward-penalty-types`

Create a reward/penalty type.

**Auth:** Required | **Permission:** `roles.create`

**Request Body:**
```json
{
  "name": "Outstanding Performance",
  "category": "reward",
  "severity": null,
  "default_points": 10,
  "default_expiration_days": 365,
  "is_active": true
}
```

**Validation:**
- `name`: required, string, max 255
- `category`: required, enum: reward, penalty
- `severity`: nullable, enum: low, medium, high, critical
- `default_points`: required, integer
- `default_expiration_days`: optional, integer, default 365
- `is_active`: optional, boolean, default true

**Response 201:**
```json
{ "data": { ... } }
```

---

### `PUT /reward-penalty-types/{id}`

Update a type.

**Auth:** Required | **Permission:** `roles.update`

---

## 16. Disciplinary Records

### `GET /disciplinary-records`

List disciplinary records (paginated, data-scope aware).

**Auth:** Required | **Permission:** `users.list`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `subject_user_id` | integer | — | Filter by subject |
| `issuer_user_id` | integer | — | Filter by issuer |
| `status` | string | — | Filter: active, appealed, cleared |
| `category` | string | — | Filter by type category: reward, penalty |
| `evaluation_cycle_id` | integer | — | Filter by cycle |
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page |

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "type": { "id": 1, "name": "Late Submission", "category": "penalty" },
      "subject_user": { "id": 3, "username": "jsmith" },
      "issuer_user": { "id": 1, "username": "admin" },
      "evaluation_cycle": { "id": 1, "name": "Q1 2024" },
      "leader_profile": { "id": 1, "title": "Dept Head" },
      "status": "active",
      "reason": "Submitted report 3 days late",
      "points": -5,
      "issued_at": "2024-01-15T10:00:00+00:00",
      "expires_at": "2025-01-15T10:00:00+00:00",
      "appealed_at": null,
      "appeal_reason": null,
      "cleared_at": null,
      "cleared_by": null,
      "cleared_reason": null,
      "created_at": "2024-01-15T10:00:00+00:00"
    }
  ],
  "meta": { ... }
}
```

---

### `POST /disciplinary-records`

Create a disciplinary record.

**Auth:** Required | **Permission:** `users.create`

**Request Body:**
```json
{
  "type_id": 1,
  "subject_user_id": 3,
  "evaluation_cycle_id": 1,
  "leader_profile_id": 1,
  "reason": "Submitted report 3 days late",
  "points": -5,
  "expires_at": "2025-01-15T10:00:00+00:00"
}
```

**Validation:**
- `type_id`: required, integer, must exist
- `subject_user_id`: required, integer, must exist
- `evaluation_cycle_id`: optional, integer, must exist
- `leader_profile_id`: optional, integer, must exist
- `reason`: required, string, max 2000
- `points`: optional, integer (defaults from type's default_points)
- `expires_at`: optional, datetime (defaults to issued_at + type's default_expiration_days)

**Response 201:**
```json
{ "data": { "status": "active", ... } }
```

---

### `GET /disciplinary-records/{id}`

Get a single record with all related data.

**Auth:** Required | **Permission:** `users.list`

---

### `POST /disciplinary-records/{id}/appeal`

Appeal a disciplinary record.

**Auth:** Required (subject user OR user with `disciplinary.appeal` permission)

**Valid transition:** `active` -> `appealed`

**Request Body:**
```json
{
  "appeal_reason": "The deadline was not communicated clearly."
}
```

**Validation:**
- `appeal_reason`: required, string, max 2000

**Response 200:**
```json
{
  "data": {
    "status": "appealed",
    "appealed_at": "2024-01-20T14:00:00+00:00",
    "appeal_reason": "The deadline was not communicated clearly.",
    ...
  }
}
```

**Response 403:**
```json
{ "code": 403, "msg": "You do not have permission to appeal this record." }
```

**Response 422:**
```json
{ "code": 422, "msg": "Only active records can be appealed." }
```

---

### `POST /disciplinary-records/{id}/clear`

Clear a disciplinary record.

**Auth:** Required | **Permission:** `disciplinary.clear`

**Valid transition:** `appealed` -> `cleared`

**Request Body:**
```json
{
  "cleared_reason": "Appeal upheld after review."
}
```

**Validation:**
- `cleared_reason`: required, string, max 2000

**Response 200:**
```json
{
  "data": {
    "status": "cleared",
    "cleared_at": "2024-01-25T10:00:00+00:00",
    "cleared_by": 1,
    "cleared_reason": "Appeal upheld after review.",
    ...
  }
}
```

---

### `GET /disciplinary-records/stats`

Get aggregated statistics.

**Auth:** Required | **Permission:** `users.list`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `group_by` | string | — | Group by: role, period, category |
| `evaluation_cycle_id` | integer | — | Filter by cycle |

**Response 200:**
```json
{
  "data": {
    "total_records": 150,
    "total_points": -320,
    "by_status": {
      "active": 80,
      "appealed": 25,
      "cleared": 45
    },
    "groups": [
      {
        "group": "penalty",
        "count": 100,
        "total_points": -450
      },
      {
        "group": "reward",
        "count": 50,
        "total_points": 130
      }
    ]
  }
}
```

---

## 17. Measurement Codes

### `GET /measurement-codes`

List all measurement codes.

**Auth:** Required

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "code": "GLU",
      "display_name": "Glucose",
      "unit": "mg/dL",
      "value_type": "numeric",
      "reference_range_low": 70.0,
      "reference_range_high": 100.0,
      "is_active": true,
      "created_at": "2024-01-01T00:00:00+00:00"
    }
  ]
}
```

---

### `POST /measurement-codes`

Create a measurement code.

**Auth:** Required | **Permission:** `roles.create`

**Request Body:**
```json
{
  "code": "GLU",
  "display_name": "Glucose",
  "unit": "mg/dL",
  "value_type": "numeric",
  "reference_range_low": 70.0,
  "reference_range_high": 100.0,
  "is_active": true
}
```

**Validation:**
- `code`: required, string, unique, max 50
- `display_name`: required, string, max 255
- `unit`: required, string, max 50
- `value_type`: required, enum: numeric, text, coded
- `reference_range_low`: optional, numeric (required if value_type=numeric)
- `reference_range_high`: optional, numeric (required if value_type=numeric)
- `is_active`: optional, boolean, default true

**Response 201:**
```json
{ "data": { ... } }
```

---

### `GET /measurement-codes/{id}`

Get a measurement code with its unit conversions.

**Auth:** Required

---

### `PUT /measurement-codes/{id}`

Update a measurement code.

**Auth:** Required | **Permission:** `roles.update`

---

## 18. Unit Conversions

### `GET /unit-conversions`

List all unit conversions.

**Auth:** Required

**Response 200:**
```json
{
  "data": [
    {
      "id": 1,
      "measurement_code_id": 1,
      "from_unit": "mmol/L",
      "to_unit": "mg/dL",
      "factor": 18.0,
      "offset": 0.0
    }
  ]
}
```

---

### `POST /unit-conversions`

Create a unit conversion rule.

**Auth:** Required | **Permission:** `roles.create`

**Request Body:**
```json
{
  "measurement_code_id": 1,
  "from_unit": "mmol/L",
  "to_unit": "mg/dL",
  "factor": 18.0,
  "offset": 0.0
}
```

**Validation:**
- `measurement_code_id`: required, integer, must exist
- `from_unit`: required, string, max 50
- `to_unit`: required, string, max 50
- `factor`: required, numeric
- `offset`: optional, numeric, default 0

**Conversion formula:** `value_normalized = value_input * factor + offset`

**Response 201:**
```json
{ "data": { ... } }
```

---

## 19. Subjects

### `GET /subjects`

List subjects (PII-aware).

**Auth:** Required

**Response 200** (with `subjects.view_pii`):
```json
{
  "data": [
    {
      "id": 1,
      "identifier": "SUB-001",
      "name": "John Smith",
      "metadata": { "age": 45, "group": "A" },
      "campus": "Main Campus"
    }
  ]
}
```

**Response 200** (without `subjects.view_pii`):
```json
{
  "data": [
    {
      "id": 1,
      "identifier": "SU****01",
      "name": "Jo****th",
      "metadata": { "age": 45, "group": "A" },
      "campus": "Main Campus"
    }
  ]
}
```

PII fields (`identifier`, `name`) are masked using partial redaction (first 2 + `****` + last 2 chars).

---

### `POST /subjects`

Create a subject.

**Auth:** Required | **Permission:** `users.create`

**Request Body:**
```json
{
  "identifier": "SUB-001",
  "name": "John Smith",
  "metadata": { "age": 45, "group": "A" },
  "campus": "Main Campus"
}
```

**Validation:**
- `identifier`: required, string, unique, max 100
- `name`: required, string, max 255
- `metadata`: optional, JSON object
- `campus`: optional, string, max 255

**Response 201:**
```json
{ "data": { ... } }
```

---

### `GET /subjects/{id}`

Get a subject (PII-masked based on permission).

**Auth:** Required

---

### `PUT /subjects/{id}`

Update a subject.

**Auth:** Required | **Permission:** `users.update`

---

## 20. Results

### `POST /results`

Submit a single result (manual entry).

**Auth:** Required

**Request Body:**
```json
{
  "subject_id": 1,
  "measurement_code_id": 1,
  "value_raw": "95.5",
  "unit_input": "mg/dL",
  "observed_at": "2024-01-15T10:00:00+00:00"
}
```

**Validation:**
- `subject_id`: required, integer, must exist
- `measurement_code_id`: required, integer, must exist and be active
- `value_raw`: required, string
- `unit_input`: optional, string (defaults to measurement code's canonical unit)
- `observed_at`: required, datetime, cannot be more than 5 minutes in the future

**Response 201:**
```json
{
  "data": {
    "id": 1,
    "subject_id": 1,
    "measurement_code_id": 1,
    "value_raw": "95.5",
    "value_numeric": 95.5,
    "value_text": null,
    "unit_input": "mg/dL",
    "unit_normalized": "mg/dL",
    "observed_at": "2024-01-15T10:00:00+00:00",
    "source": "manual",
    "is_outlier": false,
    "z_score": 0.45,
    "outlier_threshold": 3.0,
    "review_status": "approved",
    "reviewed_by": null,
    "reviewed_at": null,
    "review_comment": null,
    "batch_id": null,
    "created_by": 1
  },
  "warnings": []
}
```

**With warnings:**
```json
{
  "data": { "is_outlier": true, "review_status": "pending", "z_score": 3.85, ... },
  "warnings": [
    "Value 250.0 is outside reference range [70.0, 100.0] for GLU",
    "Outlier detected: z-score 3.85 exceeds threshold 3.0"
  ]
}
```

**Validation pipeline:**
1. Measurement code exists and is active
2. Value matches code's `value_type` (numeric values must be parseable)
3. Unit normalization (if `unit_input` differs from canonical, applies conversion factor + offset)
4. Reference range check (warning only, does not reject)
5. Timestamp check: `observed_at` <= now + 5 minutes
6. Z-score outlier detection (if statistics count >= 30)

---

### `POST /results/batch`

Submit multiple results at once (FHIR-inspired).

**Auth:** Required

**Request Body:**
```json
{
  "observations": [
    {
      "subject_id": 1,
      "measurement_code_id": 1,
      "value_raw": "95.5",
      "unit_input": "mg/dL",
      "observed_at": "2024-01-15T10:00:00+00:00"
    },
    {
      "subject_id": 1,
      "measurement_code_id": 2,
      "value_raw": "7.2",
      "unit_input": "mmol/L",
      "observed_at": "2024-01-15T10:00:00+00:00"
    }
  ]
}
```

**Response 200:**
```json
{
  "imported": 2,
  "errors": [],
  "batch_id": "550e8400-e29b-41d4-a716-446655440000",
  "results": [ ... ]
}
```

**Response with partial errors:**
```json
{
  "imported": 1,
  "errors": [
    { "index": 1, "msg": "Measurement code XYZ not found or inactive." }
  ],
  "batch_id": "550e8400-...",
  "results": [ ... ]
}
```

---

### `POST /results/import-csv`

Import results from a CSV file.

**Auth:** Required

**Request:** `multipart/form-data`

| Field | Type | Description |
|-------|------|-------------|
| `file` | file | CSV file |

**CSV Format:**
```csv
code,subject_identifier,value,unit,observed_at
GLU,SUB-001,95.5,mg/dL,2024-01-15T10:00:00+00:00
HGB,SUB-001,14.2,g/dL,2024-01-15T10:00:00+00:00
```

**Response 200:**
```json
{
  "imported": 50,
  "errors": [
    { "row": 12, "msg": "Subject identifier 'SUB-999' not found." }
  ],
  "batch_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

---

### `GET /results`

List results (paginated, data-scope aware).

**Auth:** Required | **Permission:** `results.review`

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `subject_id` | integer | — | Filter by subject |
| `measurement_code_id` | integer | — | Filter by code |
| `review_status` | string | — | Filter: pending, approved, rejected |
| `is_outlier` | boolean | — | Filter by outlier flag |
| `source` | string | — | Filter: manual, csv_import, rest_integration |
| `batch_id` | string | — | Filter by batch UUID |
| `from` | datetime | — | Observed at start |
| `to` | datetime | — | Observed at end |
| `page` | integer | 1 | Page number |
| `per_page` | integer | 20 | Items per page |

**Response 200:**
```json
{
  "data": [ ... ],
  "meta": { ... }
}
```

---

### `GET /results/{id}`

Get a single result with related subject and measurement code.

**Auth:** Required | **Permission:** `results.review`

---

### `GET /results/flagged`

List outlier results needing review (review_status=pending, is_outlier=true).

**Auth:** Required | **Permission:** `results.review`

**Response 200:**
```json
{
  "data": [
    {
      "id": 5,
      "subject": { "id": 1, "identifier": "SUB-001" },
      "measurement_code": { "id": 1, "code": "GLU", "display_name": "Glucose" },
      "value_raw": "250.0",
      "value_numeric": 250.0,
      "z_score": 3.85,
      "outlier_threshold": 3.0,
      "review_status": "pending",
      "source": "manual",
      "observed_at": "2024-01-15T10:00:00+00:00",
      "created_by": 2
    }
  ],
  "meta": { ... }
}
```

---

### `POST /results/{id}/review`

Approve or reject a flagged result.

**Auth:** Required | **Permission:** `results.review`

**Request Body:**
```json
{
  "review_status": "approved",
  "review_comment": "Value confirmed by repeat measurement."
}
```

**Validation:**
- `review_status`: required, enum: approved, rejected
- `review_comment`: optional, string, max 2000
- Reviewer cannot review their own submission (`created_by` != authenticated user)

**Response 200:**
```json
{
  "data": {
    "review_status": "approved",
    "reviewed_by": 1,
    "reviewed_at": "2024-01-16T09:00:00+00:00",
    "review_comment": "Value confirmed by repeat measurement.",
    ...
  }
}
```

**Response 403:**
```json
{ "code": 403, "msg": "You cannot review your own submission." }
```

---

### `POST /results/recompute-stats`

Trigger recomputation of result statistics for all measurement codes.

**Auth:** Required | **Permission:** `roles.update`

**Response 200:**
```json
{
  "recomputed": 15,
  "msg": "Statistics recomputed for 15 measurement codes."
}
```

**Notes:**
- Only approved numeric results are included
- Computes: count, mean, stddev per measurement code
- Used for z-score outlier detection

---

## Appendix A: Complete Endpoint Reference

| Method | Path | Auth | Permission |
|--------|------|------|------------|
| `GET` | `/api/health` | No | — |
| `POST` | `/auth/login` | No | — |
| `POST` | `/auth/logout` | Yes | — |
| `POST` | `/auth/logout-all` | Yes | — |
| `GET` | `/auth/me` | Yes | — |
| `GET` | `/admin/users` | Yes | `users.list` |
| `POST` | `/admin/users` | Yes | `users.create` |
| `PUT` | `/admin/users/{id}` | Yes | `users.update` |
| `POST` | `/admin/users/{id}/roles` | Yes | `users.update` |
| `DELETE` | `/admin/users/{id}/roles/{roleId}` | Yes | `users.update` |
| `POST` | `/admin/service-accounts` | Yes | `service_accounts.create` |
| `POST` | `/admin/service-accounts/{id}/rotate` | Yes | `service_accounts.create` |
| `GET` | `/admin/roles` | Yes | `roles.list` |
| `POST` | `/admin/roles` | Yes | `roles.create` |
| `PUT` | `/admin/roles/{id}` | Yes | `roles.update` |
| `POST` | `/admin/roles/{id}/permissions` | Yes | `roles.update` |
| `GET` | `/admin/permissions` | Yes | `roles.list` |
| `GET` | `/songs` | Yes | `music.read` |
| `POST` | `/songs` | Yes | `music.create` |
| `GET` | `/songs/{id}` | Yes | `music.read` |
| `PUT` | `/songs/{id}` | Yes | `music.update` |
| `DELETE` | `/songs/{id}` | Yes | `music.delete` |
| `POST` | `/songs/{id}/publish` | Yes | `music.publish` |
| `POST` | `/songs/{id}/unpublish` | Yes | `music.publish` |
| `POST` | `/songs/{id}/version` | Yes | `music.update` |
| `POST` | `/songs/{id}/cover-art` | Yes | `music.update` |
| `GET` | `/albums` | Yes | `music.read` |
| `POST` | `/albums` | Yes | `music.create` |
| `GET` | `/albums/{id}` | Yes | `music.read` |
| `PUT` | `/albums/{id}` | Yes | `music.update` |
| `DELETE` | `/albums/{id}` | Yes | `music.delete` |
| `POST` | `/albums/{id}/publish` | Yes | `music.publish` |
| `POST` | `/albums/{id}/unpublish` | Yes | `music.publish` |
| `POST` | `/albums/{id}/version` | Yes | `music.update` |
| `POST` | `/albums/{id}/cover-art` | Yes | `music.update` |
| `GET` | `/albums/{id}/songs` | Yes | `music.read` |
| `POST` | `/albums/{id}/songs` | Yes | `music.update` |
| `DELETE` | `/albums/{id}/songs/{songId}` | Yes | `music.update` |
| `GET` | `/playlists` | Yes | `music.read` |
| `POST` | `/playlists` | Yes | `music.create` |
| `GET` | `/playlists/{id}` | Yes | `music.read` |
| `PUT` | `/playlists/{id}` | Yes | `music.update` |
| `DELETE` | `/playlists/{id}` | Yes | `music.delete` |
| `POST` | `/playlists/{id}/publish` | Yes | `music.publish` |
| `POST` | `/playlists/{id}/unpublish` | Yes | `music.publish` |
| `POST` | `/playlists/{id}/version` | Yes | `music.update` |
| `GET` | `/playlists/{id}/songs` | Yes | `music.read` |
| `POST` | `/playlists/{id}/songs` | Yes | `music.update` |
| `DELETE` | `/playlists/{id}/songs/{songId}` | Yes | `music.update` |
| `POST` | `/behavior/events` | Yes | — |
| `GET` | `/behavior/events` | Yes | `users.list` |
| `GET` | `/users/{id}/profile` | Yes | — |
| `POST` | `/users/{id}/profile/recompute` | Yes | `users.list` |
| `GET` | `/recommendations/{userId}` | Yes | — |
| `GET` | `/notification-templates` | Yes | — |
| `POST` | `/notification-templates` | Yes | `roles.create` |
| `PUT` | `/notification-templates/{id}` | Yes | `roles.update` |
| `DELETE` | `/notification-templates/{id}` | Yes | `roles.update` |
| `POST` | `/notifications/send` | Yes | `users.list` |
| `POST` | `/notifications/send-bulk` | Yes | `users.list` |
| `GET` | `/notifications` | Yes | — |
| `POST` | `/notifications/{id}/read` | Yes | — |
| `POST` | `/notifications/read-all` | Yes | — |
| `GET` | `/notifications/unread-count` | Yes | — |
| `GET` | `/subscriptions` | Yes | — |
| `PUT` | `/subscriptions` | Yes | — |
| `GET` | `/evaluation-cycles` | Yes | `users.list` |
| `POST` | `/evaluation-cycles` | Yes | `roles.create` |
| `GET` | `/evaluation-cycles/{id}` | Yes | `users.list` |
| `PUT` | `/evaluation-cycles/{id}` | Yes | `roles.update` |
| `POST` | `/evaluation-cycles/{id}/activate` | Yes | `roles.update` |
| `POST` | `/evaluation-cycles/{id}/close` | Yes | `roles.update` |
| `GET` | `/leader-profiles` | Yes | `users.list` |
| `POST` | `/leader-profiles` | Yes | `users.create` |
| `GET` | `/leader-profiles/{id}` | Yes | `users.list` |
| `PUT` | `/leader-profiles/{id}` | Yes | `users.update` |
| `GET` | `/reward-penalty-types` | Yes | — |
| `POST` | `/reward-penalty-types` | Yes | `roles.create` |
| `PUT` | `/reward-penalty-types/{id}` | Yes | `roles.update` |
| `GET` | `/disciplinary-records/stats` | Yes | `users.list` |
| `GET` | `/disciplinary-records` | Yes | `users.list` |
| `POST` | `/disciplinary-records` | Yes | `users.create` |
| `GET` | `/disciplinary-records/{id}` | Yes | `users.list` |
| `POST` | `/disciplinary-records/{id}/appeal` | Yes | * |
| `POST` | `/disciplinary-records/{id}/clear` | Yes | `disciplinary.clear` |
| `GET` | `/measurement-codes` | Yes | — |
| `POST` | `/measurement-codes` | Yes | `roles.create` |
| `GET` | `/measurement-codes/{id}` | Yes | — |
| `PUT` | `/measurement-codes/{id}` | Yes | `roles.update` |
| `GET` | `/unit-conversions` | Yes | — |
| `POST` | `/unit-conversions` | Yes | `roles.create` |
| `GET` | `/subjects` | Yes | — |
| `POST` | `/subjects` | Yes | `users.create` |
| `GET` | `/subjects/{id}` | Yes | — |
| `PUT` | `/subjects/{id}` | Yes | `users.update` |
| `POST` | `/results` | Yes | — |
| `POST` | `/results/batch` | Yes | — |
| `POST` | `/results/import-csv` | Yes | — |
| `GET` | `/results` | Yes | `results.review` |
| `GET` | `/results/{id}` | Yes | `results.review` |
| `GET` | `/results/flagged` | Yes | `results.review` |
| `POST` | `/results/{id}/review` | Yes | `results.review` |
| `POST` | `/results/recompute-stats` | Yes | `roles.update` |

*Appeal: subject user OR user with `disciplinary.appeal` permission.

**Total: 89 endpoints** (1 public health check + 1 public login + 87 authenticated)
