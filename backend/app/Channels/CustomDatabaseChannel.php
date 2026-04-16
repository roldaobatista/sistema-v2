<?php

namespace App\Channels;

use App\Models\Notification as NotificationModel;
use Illuminate\Notifications\Notification;

class CustomDatabaseChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $data = $notification->toCustomDatabase($notifiable);

        NotificationModel::create(array_merge($data, [
            'user_id' => $notifiable->getKey(),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey(),
        ]));
    }
}
