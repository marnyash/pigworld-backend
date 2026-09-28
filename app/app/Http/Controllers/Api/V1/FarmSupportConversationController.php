<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FarmSupportConversationController extends Controller
{
    public function show(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeMember($request, $farm);
        $conversation = SupportConversation::query()
            ->where('farm_id', $farm->id)
            ->where('app_user_id', $request->user()->id)
            ->with(['appUser:id,name', 'assignee:id,name,crm_role', 'messages.sender:id,name,crm_role,role'])
            ->first();

        return response()->json(['data' => $conversation ? $this->conversationData($conversation) : null]);
    }

    public function storeMessage(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeMember($request, $farm);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $conversation = DB::transaction(function () use ($farm, $request, $data): SupportConversation {
            $conversation = SupportConversation::query()
                ->where('farm_id', $farm->id)
                ->where('app_user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if ($conversation === null) {
                $conversation = SupportConversation::create([
                    'farm_id' => $farm->id,
                    'app_user_id' => $request->user()->id,
                    'status' => 'open',
                ]);
            }

            $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'sender_role' => 'app',
                'body' => trim($data['message']),
            ]);
            $conversation->update(['status' => 'open', 'last_message_at' => now()]);

            return $conversation;
        });

        return response()->json([
            'data' => $this->conversationData($conversation->fresh([
                'appUser:id,name',
                'assignee:id,name,crm_role',
                'messages.sender:id,name,crm_role,role',
            ])),
        ], 201);
    }

    public function markAsRead(Request $request, Farm $farm): JsonResponse
    {
        $this->authorizeMember($request, $farm);
        $conversation = SupportConversation::query()
            ->where('farm_id', $farm->id)
            ->where('app_user_id', $request->user()->id)
            ->firstOrFail();

        $conversation->messages()
            ->where('sender_role', 'crm')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => ['marked_read' => true]]);
    }

    private function authorizeMember(Request $request, Farm $farm): void
    {
        $user = $request->user();
        abort_unless($user->farms()->where('farms.id', $farm->id)->exists(), 403, 'You do not have access to this farm.');
        abort_unless(
            in_array($user->role, ['farmOwner', 'farmManager', 'farmWorker'], true)
                && ($user->crm_role === null || $user->role === 'farmOwner'),
            403,
            'Only farm app members can access this support conversation.',
        );
    }

    private function conversationData(SupportConversation $conversation): array
    {
        return [
            'id' => (string) $conversation->id,
            'farm_id' => (string) $conversation->farm_id,
            'status' => $conversation->status,
            'assigned_agent' => $conversation->assignee ? [
                'id' => (string) $conversation->assignee->id,
                'name' => $conversation->assignee->name,
                'role' => $conversation->assignee->crm_role,
            ] : null,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'messages' => $conversation->messages->map(fn (SupportMessage $message): array => [
                'id' => (string) $message->id,
                'sender_id' => $message->sender_id === null ? null : (string) $message->sender_id,
                'sender_role' => $message->sender_role,
                'sender_name' => $message->sender?->name ?? ($message->sender_role === 'crm' ? 'Customer Support' : 'Farm member'),
                'body' => $message->body,
                'read_at' => $message->read_at?->toIso8601String(),
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}