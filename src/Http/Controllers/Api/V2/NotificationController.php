<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Models\InAppNotification;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        $q = InAppNotification::where('user_id', $request->user()->id);

        $items = (clone $q)->orderByDesc('id')->limit(50)->get()->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'data' => $n->data,
            'is_read' => (bool) $n->read_at,
            'created_at' => $n->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $items,
            'unread_count' => (clone $q)->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, int $id)
    {
        InAppNotification::where('user_id', $request->user()->id)->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'ok']);
    }

    public function readAll(Request $request)
    {
        InAppNotification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'ok']);
    }
}
