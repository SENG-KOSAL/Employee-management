<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayslipDeliveredNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $payrollId,
        private readonly string $periodLabel,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Payslip available')
            ->line('Your payslip is available in the employee portal.')
            ->line('Period: ' . $this->periodLabel)
            ->action('Open Payroll', url('/'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payslip_delivered',
            'payroll_id' => $this->payrollId,
            'period' => $this->periodLabel,
            'message' => 'Payslip has been generated and distributed.',
        ];
    }
}
