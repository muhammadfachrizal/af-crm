<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\TaskResource;

class TaskController extends ApiController
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::query()->with([
            'ownerUser',
            'assignedToUser',
        ]);

        // Mobile "My Tasks":
        // Only show tasks assigned to the currently authenticated user.
        $query->where('user_assigned_id', $request->user()->id);

        if ($request->has('completed')) {
            if ($request->boolean('completed')) {
                $query->whereNotNull('completed_at');
            } else {
                $query->whereNull('completed_at');
            }
        }

        $query = $this->applySearch(
            $query,
            $request,
            [
                'name',
                'description',
            ]
        );

        $query = $this->applySort(
            $query,
            $request,
            ['created_at', 'updated_at', 'due_at', 'name'],
            'due_at'
        );

        $tasks = $query->paginate($this->perPage($request))->withQueryString();

        return TaskResource::collection($tasks);
    }

    public function show(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        if ($task->user_assigned_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Task not found.',
            ], 404);
        }

        $task->load([
            'ownerUser',
            'assignedToUser',
        ]);

        return new TaskResource($task);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Task::class);

        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task = Task::create([
            'external_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
            'user_created_id' => $user->id,
            'user_owner_id' => $user->id,
            'user_assigned_id' => $user->id,
        ]);

        $task->load([
            'ownerUser',
            'assignedToUser',
        ]);

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        if ($task->user_assigned_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Task not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task->update([
            ...$validated,
            'user_updated_id' => $request->user()->id,
        ]);

        $task->refresh()->load([
            'ownerUser',
            'assignedToUser',
        ]);

        return new TaskResource($task);
    }

    public function complete(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        
        if ($task->user_assigned_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Task not found.',
            ], 404);
        }

        $task->update([
            'completed_at' => now(),
            'user_updated_id' => $request->user()->id,
        ]);

        $task->refresh()->load([
            'ownerUser',
            'assignedToUser',
        ]);

        return new TaskResource($task);
    }

    public function destroy(Request $request, Task $task)
    {
        $this->authorize('delete', $task);

        if ($task->user_assigned_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Task not found.',
            ], 404);
        }

        $task->update([
            'user_deleted_id' => $request->user()->id,
        ]);

        $task->delete();

        return response()->noContent();
    }
}
