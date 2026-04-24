<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $decision,
        private readonly string $from,
        private readonly string $to,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Leave request ' . ucfirst($this->decision))
            ->line('Your leave request has been ' . $this->decision . '.')
            ->line('Period: ' . $this->from . ' to ' . $this->to);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_decision',
            'decision' => $this->decision,
            'from' => $this->from,
            'to' => $this->to,
            'message' => 'Leave request has been ' . $this->decision . '.',
        ];
    }
}
