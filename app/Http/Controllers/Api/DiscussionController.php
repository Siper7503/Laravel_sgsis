<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discussion;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscussionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json([
            'data' => $user->discussions()
                ->with('creator:id,name,role', 'participants:id,name,role')
                ->withCount('messages')
                ->orderByDesc('updated_at')
                ->paginate(20),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'participant_ids' => ['required', 'array', 'min:1', 'max:50'],
            'participant_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::notIn([$user->getKey()]),
                Rule::exists('users', 'id')->whereIn('role', [
                    UserRole::Admin->value,
                    UserRole::Agent->value,
                ])->where('is_active', true),
            ],
            'message' => ['required', 'string', 'max:10000'],
        ]);

        /** @var array<int, int|string> $rawParticipantIds */
        $rawParticipantIds = $validated['participant_ids'];
        $participantIds = collect(array_map(
            fn (int|string $id): int => (int) $id,
            $rawParticipantIds,
        ))
            ->reject(fn (int $id): bool => $id === $user->id)
            ->push($user->id)
            ->unique()
            ->values();

        $discussion = DB::transaction(function () use ($user, $validated, $participantIds): Discussion {
            $discussion = Discussion::create([
                'created_by' => $user->id,
                'subject' => $validated['subject'],
            ]);
            $discussion->participants()->sync($participantIds);
            $discussion->messages()->create([
                'sender_id' => $user->id,
                'body' => $validated['message'],
            ]);

            return $discussion;
        });

        return response()->json([
            'data' => $discussion->load([
                'creator:id,name,role',
                'participants:id,name,role',
                'messages.sender:id,name,role',
            ]),
        ], 201);
    }

    public function show(Request $request, Discussion $discussion): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($discussion->participants()->whereKey($user->getKey())->exists(), 404);
        $discussion->load([
            'creator:id,name,role',
            'participants:id,name,role',
        ]);
        $messages = $discussion->messages()
            ->with('sender:id,name,role')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(50);

        return response()->json([
            'data' => [
                'discussion' => $discussion,
                'messages' => $messages,
            ],
        ]);
    }

    public function storeMessage(Request $request, Discussion $discussion): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($discussion->participants()->whereKey($user->getKey())->exists(), 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $message = $discussion->messages()->create([
            'sender_id' => $user->id,
            'body' => $validated['body'],
        ]);
        $discussion->touch();

        return response()->json([
            'data' => $message->load('sender:id,name,role'),
        ], 201);
    }
}
