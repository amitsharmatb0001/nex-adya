<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications
     * List all notifications for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $notifications = $user->notifications()
                ->latest()
                ->paginate(20);

            return response()->json([
                'ok'   => true,
                'data' => $notifications
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'message' => 'Failed to fetch notifications',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * GET /api/v1/notifications/{id}
     * Show a single notification
     */
    public function show(string $id)
    {
        try {
            $notification = Auth::user()
                ->notifications()
                ->findOrFail($id);

            return response()->json([
                'ok'   => true,
                'data' => $notification
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'message' => 'Notification not found',
                    'details' => $e->getMessage(),
                ],
            ], 404);
        }
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     * Mark a notification as read
     */
    public function markAsRead(string $id)
    {
        try {
            $notification = Auth::user()
                ->notifications()
                ->findOrFail($id);

            $notification->markAsRead();

            return response()->json([
                'ok'   => true,
                'data' => ['message' => 'Notification marked as read']
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'message' => 'Failed to mark notification',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/notifications/{id}
     * Delete a notification
     */
    public function destroy(string $id)
    {
        try {
            $notification = Auth::user()
                ->notifications()
                ->findOrFail($id);

            $notification->delete();

            return response()->json([
                'ok'   => true,
                'data' => ['message' => 'Notification deleted']
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => [
                    'message' => 'Failed to delete notification',
                    'details' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
