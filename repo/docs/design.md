# Phase 4: Notifications & Task Center - Design Document

## Overview

Phase 4 adds a notification system to the Meridian Records & Compliance Backend. It provides template-based notifications with variable interpolation, subscription management, rate limiting, and bulk send capabilities.

## Architecture

### Data Layer

Three new database tables:

1. **notification_templates** - Stores reusable notification templates with `{{variable}}` placeholders in subject and body fields. Each template declares which variables it expects via a JSON `variables` column.

2. **notifications** - Immutable notification records. Once created, only `read_at` can be updated. Stores rendered subject/body (post-interpolation), the variables used, and an optional `batch_id` for grouping bulk sends.

3. **notification_subscriptions** - Per-user, per-template subscription preferences. Uses a composite unique constraint on `(user_id, template_id)`. Default behavior is subscribed; only an explicit `is_subscribed=false` record opts a user out.

### Service Layer

**NotificationService** handles the core send logic:

- Template variable validation (ensures all declared variables are supplied)
- Template rendering via regex-based `{{variable}}` replacement
- Subscription checks (skips unsubscribed users)
- Rate limiting (max 3 notifications per user per template per hour)
- Returns structured results with sent/skipped counts and skip reasons

### Controller Layer

Three controllers handle the API surface:

- **NotificationTemplateController** - CRUD for templates (admin permissions)
- **NotificationController** - Send notifications, list user notifications, mark read, unread count
- **SubscriptionController** - View and update per-user subscription preferences

### Key Design Decisions

1. **Immutable notifications**: The `notifications` table has no `updated_at` column. This ensures audit trail integrity - once a notification is sent, its content cannot be modified.

2. **Rate limiting at service level**: Rate limits are enforced in the NotificationService, not via middleware, allowing for granular per-template, per-recipient limiting.

3. **Subscription defaults**: Users are subscribed to all templates by default. Only explicit opt-out records change this behavior, reducing initial setup overhead.

4. **Bulk send batching**: Bulk sends generate a UUID `batch_id` for tracking and grouping. The 10,000 recipient cap prevents resource exhaustion.

5. **Route ordering**: Static routes (`send`, `send-bulk`, `read-all`, `unread-count`) are declared before parameterized routes (`{id}/read`) to prevent route matching conflicts.

## Database Indexes

- `(recipient_id, created_at)` - User notification timeline queries
- `(batch_id)` - Batch tracking lookups
- `(recipient_id, template_id, created_at)` - Rate limit checks (count recent notifications per user per template)
- `(user_id, template_id)` UNIQUE on subscriptions - Ensures one subscription record per user-template pair

## Error Handling

All errors follow the standard API format:
```json
{ "code": <status>, "msg": "<message>" }
```

Validation errors return 422 with field-level error details. Permission errors return 403. Authentication errors return 401.
