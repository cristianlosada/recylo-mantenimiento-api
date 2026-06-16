<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;

class NotificationLogChannel
{
    public function send(mixed $notifiable, mixed $notification): void
    {
        if (!method_exists($notification, 'toNotificationLog')) {
            return;
        }

        $data = $notification->toNotificationLog($notifiable);

        if (empty($data)) {
            return;
        }

        NotificationLog::create(array_merge([
            'recipient_user_id' => $notifiable->id,
            'recipient_email'   => $notifiable->email,
            'channel'           => NotificationLog::CHANNEL_IN_APP,
            'status'            => NotificationLog::STATUS_SENT,
            'sent_at'           => now(),
        ], $data));
    }
}
