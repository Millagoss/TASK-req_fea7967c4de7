<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationSubscription;
use App\Models\NotificationTemplate;
use Illuminate\Validation\ValidationException;

class NotificationService
{
    const RATE_LIMIT_PER_HOUR = 3;
    const BULK_CAP = 10000;

    /**
     * Send notification to specific recipient(s).
     *
     * @param int         $templateId
     * @param array       $recipientIds  list of user IDs
     * @param array       $variables     key-value map to fill template placeholders
     * @param string|null $batchId       optional batch UUID
     * @return array{sent: int, skipped: int, skipped_reasons: array}
     */
    public function send(int $templateId, array $recipientIds, array $variables, ?string $batchId = null): array
    {
        $template = NotificationTemplate::findOrFail($templateId);

        // Validate all declared variables are supplied
        $this->validateVariables($template, $variables);

        // Render subject and body
        $subjectRendered = $this->render($template->subject, $variables);
        $bodyRendered    = $this->render($template->body, $variables);

        $sent = 0;
        $skipped = 0;
        $skippedReasons = [];

        foreach ($recipientIds as $recipientId) {
            // Check subscription
            if ($this->isUnsubscribed($recipientId, $templateId)) {
                $skipped++;
                $skippedReasons[] = ['user_id' => $recipientId, 'reason' => 'unsubscribed'];
                continue;
            }

            // Check rate limit: count notifications to this recipient for this template in last 60 min
            if ($this->isRateLimited($recipientId, $templateId)) {
                $skipped++;
                $skippedReasons[] = ['user_id' => $recipientId, 'reason' => 'rate_limited'];
                continue;
            }

            // Create notification
            Notification::create([
                'template_id'      => $templateId,
                'recipient_id'     => $recipientId,
                'subject_rendered' => $subjectRendered,
                'body_rendered'    => $bodyRendered,
                'variables_used'   => $variables,
                'batch_id'         => $batchId,
            ]);

            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'skipped_reasons' => $skippedReasons];
    }

    /**
     * Render a template string by replacing {{variable}} placeholders.
     */
    public function render(string $template, array $variables): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($variables) {
            return $variables[$matches[1]] ?? $matches[0];
        }, $template);
    }

    /**
     * Validate that all template-declared variables are present in the provided values.
     * Throws ValidationException if any are missing.
     */
    private function validateVariables(NotificationTemplate $template, array $variables): void
    {
        $required = $template->variables ?? [];
        $missing = array_diff($required, array_keys($variables));

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'variables' => ['Missing required template variables: ' . implode(', ', $missing)],
            ]);
        }
    }

    /**
     * Check if user is unsubscribed from the given template.
     */
    private function isUnsubscribed(int $userId, int $templateId): bool
    {
        $sub = NotificationSubscription::where('user_id', $userId)
            ->where('template_id', $templateId)
            ->first();

        // Default is subscribed — only unsubscribed if explicit record with is_subscribed=false
        return $sub && !$sub->is_subscribed;
    }

    /**
     * Check if user has exceeded the rate limit for this template.
     */
    private function isRateLimited(int $recipientId, int $templateId): bool
    {
        $oneHourAgo = now()->subHour();

        $count = Notification::where('recipient_id', $recipientId)
            ->where('template_id', $templateId)
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        return $count >= self::RATE_LIMIT_PER_HOUR;
    }
}
