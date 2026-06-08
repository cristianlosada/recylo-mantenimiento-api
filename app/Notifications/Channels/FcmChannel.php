<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class FcmChannel
{
    public function __construct(protected Messaging $messaging) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $tokens = DeviceToken::where('user_id', $notifiable->id)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return;
        }

        $data = $notification->toFcm($notifiable);

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($data['title'], $data['body']))
            ->withData($data['data'] ?? []);

        try {
            $this->messaging->sendMulticast($message, $tokens);
        } catch (\Throwable $e) {
            Log::error('FCM send error: ' . $e->getMessage());
        }
    }
}
