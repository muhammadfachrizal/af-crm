<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Call;
use VentureDrake\LaravelCrm\Models\File;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Lunch;
use VentureDrake\LaravelCrm\Models\Meeting;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\ActivityResource;

class ActivityController extends ApiController
{
    public function index(Request $request)
    {
        $query = Activity::query()
            ->with([
                'causeable',
                'timelineable',
                'recordable',
            ])
            ->latest();

        if ($request->filled('timelineable_type') && $request->filled('timelineable_id')) {
            $query
            ->where('causeable_type', $request->user()->getMorphClass())
            ->where('causeable_id', $request->user()->id);
        
            $timelineable = $this->resolveTimelineable(
                $request->timelineable_type,
                $request->timelineable_id
            );

            if (! $timelineable) {
                return response()->json([
                    'message' => 'Invalid timelineable.',
                ], 422);
            }

            $query
                ->where('timelineable_type', $timelineable->getMorphClass())
                ->where('timelineable_id', $timelineable->id);
        }

        if ($request->filled('type')) {
            $recordableClass = $this->resolveRecordableClass($request->type);

            if (! $recordableClass) {
                return response()->json([
                    'message' => 'Invalid activity type.',
                ], 422);
            }

            $query->where('recordable_type', $recordableClass);
        }

        $activities = $query
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ActivityResource::collection($activities);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timelineable_type' => [
                'required',
                'string',
                'in:lead,deal,person,organization,product,quote,order,invoice',
            ],
            'timelineable_id' => [
                'required',
                'string',
            ],
            'type' => [
                'required',
                'string',
                'in:task,note,call,meeting,lunch,file',
            ],
        ]);

        $timelineable = $this->resolveTimelineable(
            $validated['timelineable_type'],
            $validated['timelineable_id']
        );

        if (! $timelineable) {
            return response()->json([
                'message' => 'CRM record not found.',
            ], 404);
        }

        $record = match ($validated['type']) {
            'task' => $this->createTask($request, $timelineable),
            'note' => $this->createNote($request, $timelineable),
            'call' => $this->createCall($request, $timelineable),
            'meeting' => $this->createMeeting($request, $timelineable),
            'lunch' => $this->createLunch($request, $timelineable),
            'file' => $this->createFile($request, $timelineable),
        };

        if (! $record) {
            return response()->json([
                'message' => 'Unable to create activity.',
            ], 422);
        }

        $activity = $timelineable->activities()->create([
            'causeable_type' => $request->user()->getMorphClass(),
            'causeable_id' => $request->user()->id,
            'timelineable_type' => $timelineable->getMorphClass(),
            'timelineable_id' => $timelineable->id,
            'recordable_type' => $record->getMorphClass(),
            'recordable_id' => $record->id,
        ]);

        $activity->load([
            'causeable',
            'timelineable',
            'recordable',
        ]);

        return (new ActivityResource($activity))
            ->response()
            ->setStatusCode(201);
    }

    private function resolveTimelineable(string $type, string $id)
    {
        $models = [
            'lead' => Lead::class,
            'deal' => Deal::class,
            'person' => Person::class,
            'organization' => Organization::class,
            'product' => Product::class,
            'quote' => Quote::class,
            'order' => Order::class,
            'invoice' => Invoice::class,
        ];

        $class = $models[$type] ?? null;

        return $class
            ? $class::where('external_id', $id)->first()
            : null;
    }

    private function resolveRecordableClass(string $type): ?string
    {
        return [
            'task' => Task::class,
            'note' => Note::class,
            'call' => Call::class,
            'meeting' => Meeting::class,
            'lunch' => Lunch::class,
            'file' => File::class,
        ][$type] ?? null;
    }

    private function createTask(Request $request, $model): ?Task
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ]);

        return $model->tasks()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'user_owner_id' => $request->user()->id,
            'user_assigned_id' => $request->user()->id,
        ]);
    }

    private function createNote(Request $request, $model): ?Note
    {
        $data = $request->validate([
            'content' => ['required', 'string'],
            'noted_at' => ['nullable', 'date'],
        ]);

        return $model->notes()->create([
            'external_id' => Uuid::uuid4()->toString(),
            'content' => $data['content'],
            'noted_at' => $data['noted_at'] ?? null,
        ]);
    }

    private function createCall(Request $request, $model): ?Call
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'finish_at' => ['required', 'date'],
            'location' => ['nullable', 'string'],
            'guest_ids' => ['nullable', 'array'],
            'guest_ids.*' => ['integer'],
        ]);

        $call = $model->calls()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_at' => $data['start_at'],
            'finish_at' => $data['finish_at'],
            'location' => $data['location'] ?? null,
            'user_owner_id' => $request->user()->id,
            'user_assigned_id' => $request->user()->id,
        ]);

        $this->attachGuests($call, $data['guest_ids'] ?? []);

        return $call;
    }

    private function createMeeting(Request $request, $model): ?Meeting
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'finish_at' => ['required', 'date'],
            'location' => ['nullable', 'string'],
            'guest_ids' => ['nullable', 'array'],
            'guest_ids.*' => ['integer'],
        ]);

        $meeting = $model->meetings()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_at' => $data['start_at'],
            'finish_at' => $data['finish_at'],
            'location' => $data['location'] ?? null,
            'user_owner_id' => $request->user()->id,
            'user_assigned_id' => $request->user()->id,
        ]);

        $this->attachGuests($meeting, $data['guest_ids'] ?? []);

        return $meeting;
    }

    private function createLunch(Request $request, $model): ?Lunch
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_at' => ['required', 'date'],
            'finish_at' => ['required', 'date'],
            'location' => ['nullable', 'string'],
            'guest_ids' => ['nullable', 'array'],
            'guest_ids.*' => ['integer'],
        ]);

        $lunch = $model->lunches()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'start_at' => $data['start_at'],
            'finish_at' => $data['finish_at'],
            'location' => $data['location'] ?? null,
            'user_owner_id' => $request->user()->id,
            'user_assigned_id' => $request->user()->id,
        ]);

        $this->attachGuests($lunch, $data['guest_ids'] ?? []);

        return $lunch;
    }

    private function createFile(Request $request, $model): ?File
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,tsv,txt,zip',
            ],
        ]);

        $uploadedFile = $request->file('file');
        $disk = config('filesystems.default', 'local');

        $path = $uploadedFile->store(
            'laravel-crm/'.strtolower(class_basename($model)).'/'.$model->id.'/files',
            $disk
        );

        return $model->files()->create([
            'external_id' => Uuid::uuid4()->toString(),
            'file' => $path,
            'name' => $uploadedFile->getClientOriginalName(),
            'filesize' => $uploadedFile->getSize(),
            'mime' => $uploadedFile->getMimeType(),
            'disk' => $disk,
        ]);
    }

    private function attachGuests($activity, array $guestIds): void
    {
        foreach ($guestIds as $personId) {
            $person = Person::find($personId);

            if ($person) {
                $activity->contacts()->create([
                    'entityable_type' => $person->getMorphClass(),
                    'entityable_id' => $person->id,
                ]);
            }
        }
    }
}