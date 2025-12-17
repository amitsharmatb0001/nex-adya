<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    /**
     * GET /api/v2/notifications
     * Returns paginated notifications for the current authenticated user (owner).
     * Query params:
     *  - page (pagination)
     *  - per_page (default 20)
     *  - unread_only (optional boolean)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(5, min(100, $perPage)); // clamp for safety

        $query = $user->notifications()->orderByDesc('created_at');

        if ($request->boolean('unread_only')) {
            $query = $user->unreadNotifications()->orderByDesc('created_at');
            // unreadNotifications() returns a relation for DB notifications
        }

        $paginator = $query->paginate($perPage);

        $data = $paginator->through(function (DatabaseNotification $n) {
            return $this->formatNotification($n);
        });

        return response()->json([
            'ok' => true,
            'data' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'notifications'=> $data->values(),
            ],
        ]);
    }

    /** GET /api/v2/notifications/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->unreadNotifications()->count();

        return response()->json(['ok' => true, 'data' => ['unread' => $count]]);
    }

    /**
     * POST /api/v2/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Notification not found']
            ], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json(['ok' => true, 'data' => ['id' => $id, 'read_at' => $notification->read_at]]);
    }

    /**
     * POST /api/v2/notifications/read-all
     * Mark ALL unread notifications as read for the current user.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = $user->unreadNotifications()->count();

        $user->unreadNotifications->markAsRead(); // Eloquent Collection helper

        return response()->json([
            'ok' => true,
            'data' => ['marked' => $count],
        ]);
    }

    /**
     * DELETE /api/v2/notifications/{id}
     * Delete notification (soft remove from user's list).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();
        if (! $notification) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Notification not found']
            ], 404);
        }

        $notification->delete();

        return response()->json(['ok' => true, 'data' => ['id' => $id]]);
    }

    /**
     * Helper: unify notification shape sent to clients.
     */
    protected function formatNotification(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => class_basename($n->type),
            'data' => $n->data,
            'read_at' => $n->read_at ? $n->read_at->toIso8601String() : null,
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
