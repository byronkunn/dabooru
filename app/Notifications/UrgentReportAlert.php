<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to the moderation address when an urgent report is filed.
 *
 * Sent immediately rather than queued: this is a safety alert, and a queued
 * notification silently never arrives if no queue worker is running.
 */
class UrgentReportAlert extends Notification
{
    use Queueable;

    public function __construct(private readonly int $reportId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('BooruHub: urgent report awaiting review')
            ->line('An urgent report has been added to the moderation queue.')
            ->action('Review report', route('admin.reports', ['report' => $this->reportId]));
    }
}
