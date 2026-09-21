<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Lead;
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

        return response()->json([
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
