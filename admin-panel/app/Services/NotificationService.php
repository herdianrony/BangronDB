<?php

namespace App\Services;

class NotificationService
{
    private SystemService $system;

    public function __construct()
    {
        $this->system = new SystemService();
    }

    public function listForUser(?string $userId, int $limit = 20): array
    {
        $collection = $this->system->systemDb()->notifications;
        $criteria = $userId ? ['user_id' => $userId] : [];

        return $collection->find($criteria)->sort(['created_at' => -1])->limit($limit)->toArray();
    }

    public function create(?string $userId, string $title, string $message, string $level = 'info'): array
    {
        $notification = [
            '_id' => uuid(),
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'level' => $level,
            'is_read' => false,
            'created_at' => date('c'),
        ];
        $this->system->systemDb()->notifications->insert($notification);

        return $notification;
    }

    public function markRead(string $id): void
    {
        $this->system->systemDb()->notifications->update(['_id' => $id], ['is_read' => true]);
    }
}

