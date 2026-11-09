<?php

namespace VentureDrake\LaravelCrm\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use VentureDrake\LaravelCrm\Models\Attendance;
use VentureDrake\LaravelCrm\Models\Holiday;
use VentureDrake\LaravelCrm\Models\Leave;
use VentureDrake\LaravelCrm\Models\Shift;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Models\Tenant;
use VentureDrake\LaravelCrm\Models\Visit;
use VentureDrake\LaravelCrm\Support\CurrentTenant;
use VentureDrake\LaravelCrm\Support\FcmService;

/**
 * Dijalankan scheduler tiap menit (lihat LaravelCrmServiceProvider). Per tenant:
 *  - Task jatuh tempo <= 60 menit  -> push ke yang ditugaskan
 *  - Visit terjadwal <= 30 menit   -> push ke petugas
 *  - Belum absen masuk (10 menit setelah jam masuk shift, maks 2 jam) -> push ke user
 * Dedupe lewat Cache (gunakan cache driver permanen: file/database/redis, bukan "array").
 */
class PushReminders extends Command
{
    protected $signature = 'laravelcrm:push-reminders';

    protected $description = 'Kirim push notification pengingat (task, visit, belum absen)';

    public function handle(): int
    {
        if (! FcmService::enabled()) {
            return self::SUCCESS;
        }

        foreach (Tenant::where('status', 'active')->get() as $tenant) {
            if (! $tenant->isUsable()) {
                continue;
            }

            CurrentTenant::runAs($tenant->id, function () use ($tenant) {
                $this->tasks();
                $this->visits();
                $this->attendance($tenant);
            });
        }

        return self::SUCCESS;
    }

    protected function once(string $key, int $minutes = 1440): bool
    {
        return Cache::add('laravel-crm:push:'.$key, 1, now()->addMinutes($minutes));
    }

    protected function tasks(): void
    {
        $tasks = Task::whereNull('completed_at')
            ->whereBetween('due_at', [now(), now()->addMinutes(60)])
            ->get();

        foreach ($tasks as $task) {
            $userId = $task->user_assigned_id ?: $task->user_owner_id;

            if ($userId && $this->once("task:{$task->id}:{$task->due_at?->timestamp}")) {
                FcmService::toUsers([$userId], 'Task segera jatuh tempo', (string) $task->name, ['type' => 'task', 'id' => (string) $task->external_id]);
            }
        }
    }

    protected function visits(): void
    {
        $visits = Visit::whereNull('checked_in_at')
            ->whereBetween('scheduled_at', [now(), now()->addMinutes(30)])
            ->get();

        foreach ($visits as $visit) {
            if ($visit->user_id && $this->once("visit:{$visit->id}:{$visit->scheduled_at?->timestamp}")) {
                FcmService::toUsers([$visit->user_id], 'Visit sebentar lagi', (string) $visit->title, ['type' => 'visit', 'id' => (string) $visit->external_id]);
            }
        }
    }

    protected function attendance(Tenant $tenant): void
    {
        $tz = $tenant->timezone ?: config('app.timezone', 'UTC');
        $local = Carbon::now($tz);

        if (Holiday::whereDate('date', $local->toDateString())->exists()) {
            return;
        }

        $userModel = config('auth.providers.users.model');

        foreach ($userModel::all() as $user) {
            $shift = Shift::forUser($user);

            if (! $shift || ! $shift->worksOn($local->dayOfWeekIso)) {
                continue;
            }

            $start = $local->copy()->setTimeFromTimeString($shift->start_time);
            $minutesAfter = intdiv($local->timestamp - $start->timestamp, 60);

            if ($minutesAfter < 10 || $minutesAfter > 120) {
                continue;
            }

            $date = $local->toDateString();

            if (Leave::where('user_id', $user->id)->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
                continue;
            }

            $hasIn = Attendance::where('user_id', $user->id)->where('type', 'in')
                ->whereBetween('recorded_at', [$local->copy()->startOfDay()->utc(), $local->copy()->endOfDay()->utc()])
                ->exists();

            if (! $hasIn && $this->once("absen:{$user->id}:{$date}")) {
                FcmService::toUsers([$user->id], 'Anda belum absen masuk', "Jam masuk {$shift->start_time}. Segera lakukan Check In.", ['type' => 'attendance']);
            }
        }
    }
}
