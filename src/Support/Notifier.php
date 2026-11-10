<?php

namespace VentureDrake\LaravelCrm\Support;

use VentureDrake\LaravelCrm\Models\InAppNotification;

/**
 * Satu pintu notifikasi: simpan di kotak notifikasi (web + mobile) DAN kirim push FCM bila dikonfigurasi.
 * Panggil di dalam konteks tenant (request user login atau CurrentTenant::runAs).
 */
class Notifier
{
    public static function send(array $userIds, string $title, string $body = '', string $type = 'info', array $data = []): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if (empty($userIds)) {
            return;
        }

        foreach ($userIds as $id) {
            InAppNotification::create([
                'user_id' => $id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data ?: null,
            ]);
        }

        FcmService::toUsers($userIds, $title, $body, ['type' => $type] + $data);
    }
}
