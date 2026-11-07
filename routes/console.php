<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => DB::table('site_events')->where('created_at', '<', now()->subDays(30))->delete())
    ->name('admin:prune-site-events')->daily();

Artisan::command('messages:privatize-attachments {--dry-run : Count legacy records without moving files}', function () {
    $legacy = \App\Models\Media::where('path', 'like', 'uploads/%/dm/%');
    if ($this->option('dry-run')) {
        $this->info($legacy->count().' legacy message attachment records.');

        return 0;
    }

    $counts = ['moved' => 0, 'removed' => 0, 'shared' => 0, 'skipped' => 0, 'failed' => 0];
    $legacy->select('id')->chunkById(100, function ($records) use (&$counts) {
        foreach ($records as $record) {
            try {
                $result = \App\Support\MessageAttachments::privatizeLegacy($record->id);
                $counts[$result]++;
                if ($result === 'shared' || $result === 'skipped') {
                    $this->warn("Media #{$record->id} requires manual review.");
                }
            } catch (\Throwable $error) {
                report($error);
                $counts['failed']++;
                $this->warn("Media #{$record->id} could not be moved. Retry after checking storage.");
            }
        }
    });

    foreach ($counts as $status => $count) {
        $this->line("{$status}: {$count}");
    }

    return $counts['failed'] || $counts['shared'] || $counts['skipped'] ? 1 : 0;
})->purpose('Move old DM files to private storage and remove detached public copies');
