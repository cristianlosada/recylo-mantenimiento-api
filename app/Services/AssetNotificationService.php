<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\WorkOrder;
use App\Models\WorkRequest;
use App\Models\NotificationLog;
use App\Notifications\AssetWorkOrderNotification;
use App\Notifications\AssetWorkRequestNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class AssetNotificationService
{
    /**
     * Enviar notificaciones cuando se crea una orden de trabajo
     *
     * @param WorkOrder $workOrder
     * @return void
     */
    public function notifyWorkOrderCreated(WorkOrder $workOrder): void
    {
        if (!$workOrder->asset_id) {
            return;
        }

        $asset = $workOrder->asset;
        $this->sendWorkOrderNotifications($workOrder, $asset, 'create', 'notify_on_create');
    }

    /**
     * Enviar notificaciones cuando se inicia una orden de trabajo
     *
     * @param WorkOrder $workOrder
     * @return void
     */
    public function notifyWorkOrderStarted(WorkOrder $workOrder): void
    {
        if (!$workOrder->asset_id) {
            return;
        }

        $asset = $workOrder->asset;
        $this->sendWorkOrderNotifications($workOrder, $asset, 'open', 'notify_on_open');
    }

    /**
     * Enviar notificaciones cuando se completa una orden de trabajo
     *
     * @param WorkOrder $workOrder
     * @return void
     */
    public function notifyWorkOrderCompleted(WorkOrder $workOrder): void
    {
        if (!$workOrder->asset_id) {
            return;
        }

        $asset = $workOrder->asset;
        $this->sendWorkOrderNotifications($workOrder, $asset, 'close', 'notify_on_close');
    }

    /**
     * Enviar notificaciones cuando se crea una solicitud de trabajo
     *
     * @param WorkRequest $workRequest
     * @return void
     */
    public function notifyWorkRequestCreated(WorkRequest $workRequest): void
    {
        if (!$workRequest->asset_id) {
            return;
        }

        $asset = $workRequest->asset;
        $this->sendWorkRequestNotifications($workRequest, $asset, 'create', 'notify_on_create');
    }

    /**
     * Enviar notificaciones cuando se aprueba una solicitud de trabajo
     *
     * @param WorkRequest $workRequest
     * @return void
     */
    public function notifyWorkRequestApproved(WorkRequest $workRequest): void
    {
        if (!$workRequest->asset_id) {
            return;
        }

        $asset = $workRequest->asset;
        $this->sendWorkRequestNotifications($workRequest, $asset, 'approve', 'notify_on_close');
    }

    /**
     * Enviar notificaciones cuando se rechaza una solicitud de trabajo
     *
     * @param WorkRequest $workRequest
     * @return void
     */
    public function notifyWorkRequestRejected(WorkRequest $workRequest): void
    {
        if (!$workRequest->asset_id) {
            return;
        }

        $asset = $workRequest->asset;
        $this->sendWorkRequestNotifications($workRequest, $asset, 'reject', 'notify_on_close');
    }

    /**
     * Enviar notificaciones cuando se cierra una solicitud de trabajo
     *
     * @param WorkRequest $workRequest
     * @return void
     */
    public function notifyWorkRequestClosed(WorkRequest $workRequest): void
    {
        if (!$workRequest->asset_id) {
            return;
        }

        $asset = $workRequest->asset;
        $this->sendWorkRequestNotifications($workRequest, $asset, 'close', 'notify_on_close');
    }

    /**
     * Enviar notificaciones de orden de trabajo a los emails configurados
     *
     * @param WorkOrder $workOrder
     * @param Asset $asset
     * @param string $eventType
     * @param string $configField
     * @return void
     */
    protected function sendWorkOrderNotifications(WorkOrder $workOrder, Asset $asset, string $eventType, string $configField): void
    {
        try {
            // Obtener configuraciones de notificación que tienen habilitado este evento
            $notificationConfigs = $asset->notifications()
                ->where($configField, true)
                ->get();

            if ($notificationConfigs->isEmpty()) {
                Log::info("No hay configuraciones de notificación para el activo {$asset->id} con {$configField} habilitado");
                return;
            }

            // Crear array de emails para notificar
            $emails = $notificationConfigs->pluck('email')->toArray();

            // Crear notificación
            $notification = new AssetWorkOrderNotification($workOrder, $asset, $eventType);

            // 💾 GUARDAR EN BASE DE DATOS para cada email
            foreach ($emails as $email) {
                $this->logNotification(
                    NotificationLog::TYPE_WORK_ORDER,
                    $eventType,
                    $workOrder->id,
                    null,
                    $asset->id,
                    $email,
                    $notification->toMail(null)->subject,
                    $this->getNotificationMessage($notification)
                );
            }

            // 📧 Enviar notificación a todos los emails configurados
            Notification::route('mail', $emails)
                ->notify($notification);

            // ✅ Marcar como enviadas
            NotificationLog::where('notification_type', NotificationLog::TYPE_WORK_ORDER)
                ->where('work_order_id', $workOrder->id)
                ->where('status', NotificationLog::STATUS_PENDING)
                ->update([
                    'status' => NotificationLog::STATUS_SENT,
                    'sent_at' => now(),
                ]);

            Log::info("Notificaciones de OT {$workOrder->code} enviadas a: " . implode(', ', $emails));
        } catch (\Exception $e) {
            // ❌ Registrar error en base de datos
            if (isset($emails)) {
                foreach ($emails as $email) {
                    NotificationLog::where('notification_type', NotificationLog::TYPE_WORK_ORDER)
                        ->where('work_order_id', $workOrder->id)
                        ->where('recipient_email', $email)
                        ->where('status', NotificationLog::STATUS_PENDING)
                        ->first()?->markAsFailed($e->getMessage());
                }
            }

            Log::error("Error al enviar notificaciones de OT {$workOrder->code}: " . $e->getMessage());
        }
    }

    /**
     * Enviar notificaciones de solicitud de trabajo a los emails configurados
     *
     * @param WorkRequest $workRequest
     * @param Asset $asset
     * @param string $eventType
     * @param string $configField
     * @return void
     */
    protected function sendWorkRequestNotifications(WorkRequest $workRequest, Asset $asset, string $eventType, string $configField): void
    {
        try {
            // Obtener configuraciones de notificación que tienen habilitado este evento
            $notificationConfigs = $asset->notifications()
                ->where($configField, true)
                ->get();

            if ($notificationConfigs->isEmpty()) {
                Log::info("No hay configuraciones de notificación para el activo {$asset->id} con {$configField} habilitado");
                return;
            }

            // Crear array de emails para notificar
            $emails = $notificationConfigs->pluck('email')->toArray();

            // Crear notificación
            $notification = new AssetWorkRequestNotification($workRequest, $asset, $eventType);

            // 💾 GUARDAR EN BASE DE DATOS para cada email
            foreach ($emails as $email) {
                $this->logNotification(
                    NotificationLog::TYPE_WORK_REQUEST,
                    $eventType,
                    null,
                    $workRequest->id,
                    $asset->id,
                    $email,
                    $notification->toMail(null)->subject,
                    $this->getNotificationMessage($notification)
                );
            }

            // 📧 Enviar notificación a todos los emails configurados
            Notification::route('mail', $emails)
                ->notify($notification);

            // ✅ Marcar como enviadas
            NotificationLog::where('notification_type', NotificationLog::TYPE_WORK_REQUEST)
                ->where('work_request_id', $workRequest->id)
                ->where('status', NotificationLog::STATUS_PENDING)
                ->update([
                    'status' => NotificationLog::STATUS_SENT,
                    'sent_at' => now(),
                ]);

            Log::info("Notificaciones de Solicitud {$workRequest->code} enviadas a: " . implode(', ', $emails));
        } catch (\Exception $e) {
            // ❌ Registrar error en base de datos
            if (isset($emails)) {
                foreach ($emails as $email) {
                    NotificationLog::where('notification_type', NotificationLog::TYPE_WORK_REQUEST)
                        ->where('work_request_id', $workRequest->id)
                        ->where('recipient_email', $email)
                        ->where('status', NotificationLog::STATUS_PENDING)
                        ->first()?->markAsFailed($e->getMessage());
                }
            }

            Log::error("Error al enviar notificaciones de Solicitud {$workRequest->code}: " . $e->getMessage());
        }
    }

    /**
     * Registrar notificación en la base de datos
     *
     * @param string $notificationType
     * @param string $eventType
     * @param ?int $workOrderId
     * @param ?int $workRequestId
     * @param int $assetId
     * @param string $recipientEmail
     * @param string $subject
     * @param string $message
     * @return NotificationLog
     */
    protected function logNotification(
        string $notificationType,
        string $eventType,
        ?int $workOrderId,
        ?int $workRequestId,
        int $assetId,
        string $recipientEmail,
        string $subject,
        string $message
    ): NotificationLog {
        return NotificationLog::create([
            'notification_type' => $notificationType,
            'event_type' => $eventType,
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'status' => NotificationLog::STATUS_PENDING,
            'work_order_id' => $workOrderId,
            'work_request_id' => $workRequestId,
            'asset_id' => $assetId,
            'recipient_email' => $recipientEmail,
            'subject' => $subject,
            'message' => $message,
            'scheduled_at' => now(),
        ]);
    }

    /**
     * Extraer mensaje de la notificación
     *
     * @param AssetWorkOrderNotification|AssetWorkRequestNotification $notification
     * @return string
     */
    protected function getNotificationMessage($notification): string
    {
        try {
            $mailMessage = $notification->toMail(null);
            // Extraer el primer párrafo como mensaje
            $lines = $mailMessage->introLines;
            return $lines[0] ?? 'Notificación automática';
        } catch (\Exception $e) {
            return 'Notificación automática';
        }
    }
}
