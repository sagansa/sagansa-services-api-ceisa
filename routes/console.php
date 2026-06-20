<?php

use App\Jobs\NotulReminderJob;
use Illuminate\Support\Facades\Schedule;

/**
 * Scheduled jobs.
 */
// Fase 4 — Reminder NOTUL tiap jam (H-3, H-1 sebelum jatuh tempo SSP)
Schedule::job(new NotulReminderJob())->hourly()->name('notul-reminder')->withoutOverlapping();