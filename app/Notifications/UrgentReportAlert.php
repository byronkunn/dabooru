<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UrgentReportAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $reportId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Inkwell: urgent report awaiting review')
            ->line('An urgent report has been added to the moderation queue.')
            ->action('Review report', route('admin.reports', ['report' => $this->reportId]));
    }
}
