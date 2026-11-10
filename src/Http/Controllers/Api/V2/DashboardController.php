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
        $nextVisit = Visit::where('user_id', $user->id)->whereNull('checked_in_at')
            ->where('scheduled_at', '>=', now()->startOfDay())->orderBy('scheduled_at')->first();

        return response()->json([
            'pipeline' => [
                'open_value' => round($openDeals->sum('amount') / 100, 2),
                'won_month_count' => $wonMonth->count(),
                'won_month_value' => round($wonMonth->sum('amount') / 100, 2),
            ],
            'tasks_today' => ['count' => $tasksToday],
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
