## Phase 4: Notifications & Task Center

Question: Should notification template deletion be a hard delete or soft delete when notifications reference the template?
Assumption: Hard delete is blocked when notifications reference the template. The FK is SET NULL on delete, but we protect at the application level by checking for existing notifications before allowing deletion.
Solution: The `destroy` endpoint returns 422 if any notifications reference the template. This preserves historical notification data integrity.

Question: How should the `read` filter parameter on GET /notifications be parsed?
Assumption: The `read` query parameter accepts boolean-like strings (true/false, 1/0). When true, only read notifications are returned. When false, only unread.
Solution: Used `filter_var` with `FILTER_VALIDATE_BOOLEAN` and `FILTER_NULL_ON_FAILURE` to handle various truthy/falsy input formats.

Question: Should the subscriptions endpoint return templates that were created after a user last checked their subscriptions?
Assumption: Yes, all templates should be returned. Templates without an explicit subscription record default to is_subscribed=true.
Solution: The GET /subscriptions endpoint fetches all templates and left-joins with the user's subscription records, defaulting to true for templates without an explicit record.

Question: What permissions should the notification template CRUD operations require?
Assumption: Template creation requires `roles.create` and template update/delete requires `roles.update`, matching the spec.
Solution: Route middleware enforces `permission:roles.create` for POST and `permission:roles.update` for PUT/DELETE on notification templates.

Question: How should the Notification model coexist with Laravel's built-in Illuminate\Notifications\Notification class?
Assumption: Since we use our own `App\Models\Notification` class, we must be careful with imports throughout the codebase.
Solution: The model is placed at `App\Models\Notification` with an explicit `$table = 'notifications'` property. All references use the fully qualified namespace to avoid conflicts with Laravel's built-in notification class.
