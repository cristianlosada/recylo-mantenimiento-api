<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId    = Auth::id();
        $companyId = $request->header('x-company-id');

        $query = NotificationLog::where('recipient_user_id', $userId)
            ->when($companyId, function ($q) use ($companyId) {
                $q->where(function ($sub) use ($companyId) {
                    $sub->whereHas('workOrder', fn($w) => $w->where('company_id', $companyId))
                        ->orWhereHas('workRequest', fn($w) => $w->where('company_id', $companyId))
                        ->orWhereNull('work_order_id')->whereNull('work_request_id');
                });
            })
            ->orderBy('created_at', 'desc')
            ->limit(50);

        $notifications = $query->get()->map(fn($n) => [
            'id'          => $n->id,
            'type'        => $n->notification_type,
            'event_type'  => $n->event_type,
            'subject'     => $n->subject,
            'message'     => $n->message,
            'read'        => $n->opened_at !== null,
            'metadata'    => $n->metadata,
            'received_at' => $n->created_at->toISOString(),
        ]);

        return ApiResponse::success($notifications);
    }

    public function markRead(int $id): JsonResponse
    {
        $notification = NotificationLog::where('recipient_user_id', Auth::id())->find($id);

        if (!$notification) {
            return ApiResponse::notFound('Notificación no encontrada');
        }

        if (!$notification->opened_at) {
            $notification->update(['opened_at' => now()]);
        }

        return ApiResponse::success(null, 'Notificación marcada como leída');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        NotificationLog::where('recipient_user_id', Auth::id())
            ->whereNull('opened_at')
            ->update(['opened_at' => now()]);

        return ApiResponse::success(null, 'Todas las notificaciones marcadas como leídas');
    }
}
