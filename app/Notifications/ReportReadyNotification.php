<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $reportName,
        private readonly string $format,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Scheduled report ready')
            ->line('Report "' . $this->reportName . '" has been generated.')
            ->line('Format: ' . strtoupper($this->format));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'report_ready',
            'report_name' => $this->reportName,
            'format' => $this->format,
            'message' => 'Scheduled report is ready.',
        ];
    }
}
