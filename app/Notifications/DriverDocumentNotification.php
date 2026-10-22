<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class DriverDocumentNotification extends Notification
{
    public function __construct(public string $documentId, public string $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $titles = [
            'approved' => ['تم قبول مستندك', 'Document approved'],
            'rejected' => ['يرجى تحديث مستندك', 'Document needs an update'],
            'expiring' => ['مستندك يقترب من الانتهاء', 'Document expires soon'],
            'expired' => ['انتهت صلاحية مستندك', 'Document expired'],
        ];
        [$ar, $en] = $titles[$this->event];

        return ['type' => 'driver_document', 'document_id' => $this->documentId, 'status' => $this->event,
            'title' => $ar, 'title_ar' => $ar, 'title_en' => $en,
            'message' => 'راجع مستنداتي في حسابك.', 'message_ar' => 'راجع مستنداتي في حسابك.',
            'message_en' => 'Open My documents in your account.'];
    }
}
