# Phase 4: Notifications & Task Center - API Specification

## Base URL

All endpoints are prefixed with `/api/v1`.

---

## Notification Templates

### GET /notification-templates

List all notification templates (paginated, ordered by name).

**Auth**: Required (any authenticated user)

**Response 200**:
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
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

### POST /notification-templates

Create a new notification template.

**Auth**: Required + `roles.create` permission

**Request Body**:
```json
{
  "name": "welcome_email",
  "subject": "Welcome {{username}}",
  "body": "Hello {{username}}, welcome to Meridian!",
  "variables": ["username"]
}
```

**Response 201**:
```json
{
  "data": { ... }
}
```

### PUT /notification-templates/{id}

Update an existing notification template.

**Auth**: Required + `roles.update` permission

**Request Body** (all fields optional):
```json
{
  "name": "updated_name",
  "subject": "New Subject",
  "body": "New Body",
  "variables": ["var1", "var2"]
}
```

**Response 200**:
```json
{
  "data": { ... }
}
```

### DELETE /notification-templates/{id}

Delete a notification template. Fails if notifications reference it.

**Auth**: Required + `roles.update` permission

**Response 200**:
```json
{
  "msg": "Template deleted successfully."
}
```

**Response 422** (if notifications exist):
```json
{
  "code": 422,
  "msg": "Cannot delete template that has associated notifications. Notifications still reference this template."
}
```

---

## Notifications

### POST /notifications/send

Send notifications to specific users.

**Auth**: Required + `users.list` permission

**Request Body**:
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

**Response 200**:
```json
{
  "sent": 2,
  "skipped": 1,
  "skipped_reasons": [
    { "user_id": 4, "reason": "unsubscribed" }
  ]
}
```

### POST /notifications/send-bulk

Bulk send notifications (max 10,000 recipients).

**Auth**: Required + `users.list` permission

**Request Body**: Same as `/send` but allows up to 10,000 recipient IDs.

**Response 200**:
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
{
  "code": 422,
  "msg": "Bulk send cannot exceed 10,000 recipients"
}
```

### GET /notifications

List current user's notifications.

**Auth**: Required

**Query Parameters**:
- `read` (boolean) - Filter by read status (`true`=only read, `false`=only unread)
- `template_id` (integer) - Filter by template
- `per_page` (integer, 1-100, default 20) - Page size
- `page` (integer) - Page number

**Response 200**:
```json
{
  "data": [
    {
      "id": 1,
      "template_id": 1,
      "subject_rendered": "Hello Alice",
      "body_rendered": "Message for Alice",
      "variables_used": { "username": "Alice" },
      "batch_id": null,
      "read_at": null,
      "created_at": "2024-01-01T00:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

### POST /notifications/{id}/read

Mark a notification as read (idempotent).

**Auth**: Required (must own the notification)

**Response 200**:
```json
{
  "data": { ... }
}
```

### POST /notifications/read-all

Mark all unread notifications as read.

**Auth**: Required

**Response 200**:
```json
{
  "updated": 5
}
```

### GET /notifications/unread-count

Get count of unread notifications.

**Auth**: Required

**Response 200**:
```json
{
  "unread_count": 12
}
```

---

## Subscriptions

### GET /subscriptions

List all templates with subscription status for the current user.

**Auth**: Required

**Response 200**:
```json
{
  "data": [
    {
      "template_id": 1,
      "template_name": "welcome_email",
      "is_subscribed": true
    },
    {
      "template_id": 2,
      "template_name": "song_published",
      "is_subscribed": false
    }
  ]
}
```

### PUT /subscriptions

Update subscription preferences.

**Auth**: Required

**Request Body**:
```json
{
  "subscriptions": [
    { "template_id": 1, "is_subscribed": false },
    { "template_id": 2, "is_subscribed": true }
  ]
}
```

**Response 200**:
```json
{
  "data": [ ... ]
}
```
