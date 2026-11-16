<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\InAppNotification;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Visit;
use VentureDrake\LaravelCrm\Support\AttendanceOverview;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Models\Invoice;

class DashboardController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $myLeadsCount = Lead::where('user_owner_id', $user->id)
            ->whereNull('converted_at')
            ->count();

        $myDealsCount = Deal::where('user_owner_id', $user->id)
            ->whereNull('closed_status')
            ->count();

        $myTasksCount = Task::where('user_assigned_id', $user->id)
            ->whereNull('completed_at')
            ->count();

        $myOverdueTasksCount = Task::where('user_assigned_id', $user->id)
            ->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $openDeals = Deal::where('user_owner_id', $user->id)->whereNull('closed_status')->get(['amount']);
        $wonMonth = Deal::where('user_owner_id', $user->id)->where('closed_status', 'won')
            ->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])->get(['amount']);
        $tasksToday = Task::where('user_assigned_id', $user->id)->whereNull('completed_at')
            ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count();
        // --- Tugas hari ini (progres selesai / total, seperti kartu "Tugas Hari Ini" di HRIS) ---
        $todayTasks = Task::where('user_assigned_id', $user->id)
            ->where(function ($q) {
                $q->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])
                    ->orWhereBetween('completed_at', [now()->startOfDay(), now()->endOfDay()]);
            })
            ->orderByRaw('completed_at is null desc')->orderBy('due_at')
            ->get(['id', 'name', 'due_at', 'completed_at']);
        $tasksTodayTotal = $todayTasks->count();
        $tasksTodayDone = $todayTasks->whereNotNull('completed_at')->count();

        // --- Visit hari ini (dihitung di server, bukan dari halaman pertama daftar visit) ---
        $visitsToday = Visit::where('user_id', $user->id)
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->get(['checked_in_at']);

        // --- Penjualan (invoice) hari ini & bulan ini milik user ---
        $salesToday = Invoice::where('user_owner_id', $user->id)
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->get(['total', 'amount_due', 'fully_paid_at']);
        $salesMonthTotal = Invoice::where('user_owner_id', $user->id)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total');

        $nextVisit = Visit::where('user_id', $user->id)->whereNull('checked_in_at')
            ->where('scheduled_at', '>=', now()->startOfDay())->orderBy('scheduled_at')->first();

        return response()->json([
            'pipeline' => [
                'open_value' => round($openDeals->sum('amount') / 100, 2),
                'won_month_count' => $wonMonth->count(),
                'won_month_value' => round($wonMonth->sum('amount') / 100, 2),
            ],
            'tasks_today' => [
                'count' => $tasksToday,
                'total' => $tasksTodayTotal,
                'done' => $tasksTodayDone,
                'percent' => $tasksTodayTotal > 0 ? (int) round($tasksTodayDone / $tasksTodayTotal * 100) : 0,
                'items' => $todayTasks->take(4)->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'due_at' => $t->due_at?->toIso8601String(),
                    'done' => $t->completed_at !== null,
                ])->values(),
            ],
            'visits_today' => [
                'total' => $visitsToday->count(),
                'completed' => $visitsToday->whereNotNull('checked_in_at')->count(),
            ],
            'sales' => [
                'today_count' => $salesToday->count(),
                'today_total' => round($salesToday->sum('total') / 100, 2),
                'today_paid' => round($salesToday->whereNotNull('fully_paid_at')->sum('total') / 100, 2),
                'today_unpaid' => round($salesToday->whereNull('fully_paid_at')->sum('amount_due') / 100, 2),
                'month_total' => round($salesMonthTotal / 100, 2),
            ],
            'next_visit' => $nextVisit ? [
                'title' => $nextVisit->title,
                'scheduled_at' => $nextVisit->scheduled_at?->toIso8601String(),
                'address' => $nextVisit->target_address,
            ] : null,
            'unread_notifications' => InAppNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
            'attendance' => AttendanceOverview::forUser($user),
            'leads' => [
                'count' => $myLeadsCount,
            ],
            'deals' => [
                'count' => $myDealsCount,
            ],
            'tasks' => [
                'count' => $myTasksCount,
            ],
            'overdue_tasks' => [
                'count' => $myOverdueTasksCount,
            ],
        ]);
    }
}
