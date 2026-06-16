<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Models\WorkRequest;
use App\Notifications\AssetWorkRequestNotification;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckSlaBreaches extends Command
{
    protected $signature   = 'cmms:check-sla';
    protected $description = 'Comprueba vencimientos de SLA y emite alertas por WebSocket';

    // Minutos antes del vencimiento para emitir sla_warning
    private const WARNING_MINUTES = 30;

    public function handle(): int
    {
        $now     = now();
        $warned  = 0;
        $breached = 0;

        // ── sla_warning: próximos a vencer ───────────────────────────────────
        // Solicitudes aún no breach, con deadline entre ahora y +30 min
        $approaching = WorkRequest::query()
            ->where('sla_breached', false)
            ->whereNotIn('status', ['approved', 'rejected', 'closed', 'cancelled'])
            ->where(function ($q) use ($now) {
                $threshold = $now->copy()->addMinutes(self::WARNING_MINUTES);
                $q->whereBetween('response_due_at', [$now, $threshold])
                  ->orWhereBetween('resolution_due_at', [$now, $threshold]);
            })
            ->get();

        foreach ($approaching as $wr) {
            NotificationDispatcher::sla($wr, 'sla_warning');

            $wr->loadMissing(['requester', 'asset']);

            // Notificar al solicitante si la solicitud es interna
            if ($wr->requester && $wr->asset) {
                $wr->requester->notify(
                    new AssetWorkRequestNotification($wr, $wr->asset, 'sla_warning')
                );
            }

            // Log para dashboard de supervisores (sin usuario específico)
            NotificationLog::create([
                'notification_type' => NotificationLog::TYPE_WORK_REQUEST,
                'event_type'        => 'sla_warning',
                'work_request_id'   => $wr->id,
                'channel'           => NotificationLog::CHANNEL_IN_APP,
                'status'            => NotificationLog::STATUS_SENT,
                'sent_at'           => now(),
                'subject'           => "SLA por vencer: {$wr->code}",
                'message'           => "La solicitud {$wr->code} vence en menos de 30 minutos.",
                'metadata'          => [
                    'module'    => 'work_requests',
                    'entity_id' => $wr->id,
                    'route'     => "/work-requests/{$wr->id}",
                    'code'      => $wr->code,
                    'alert'     => 'sla_warning',
                ],
            ]);

            $warned++;
        }

        // ── sla_breached: SLA incumplido y aún no marcado ────────────────────
        $breaches = WorkRequest::query()
            ->where('sla_breached', false)
            ->whereNotIn('status', ['approved', 'rejected', 'closed', 'cancelled'])
            ->where(function ($q) use ($now) {
                $q->where('response_due_at', '<', $now)
                  ->orWhere('resolution_due_at', '<', $now);
            })
            ->get();

        foreach ($breaches as $wr) {
            $wr->update(['sla_breached' => true]);

            NotificationDispatcher::sla($wr, 'sla_breached');

            $wr->loadMissing(['requester', 'asset']);

            if ($wr->requester && $wr->asset) {
                $wr->requester->notify(
                    new AssetWorkRequestNotification($wr, $wr->asset, 'sla_breached')
                );
            }

            NotificationLog::create([
                'notification_type' => NotificationLog::TYPE_WORK_REQUEST,
                'event_type'        => 'sla_breached',
                'work_request_id'   => $wr->id,
                'channel'           => NotificationLog::CHANNEL_IN_APP,
                'status'            => NotificationLog::STATUS_SENT,
                'sent_at'           => now(),
                'subject'           => "SLA incumplido: {$wr->code}",
                'message'           => "La solicitud {$wr->code} ha superado su tiempo límite de atención.",
                'metadata'          => [
                    'module'    => 'work_requests',
                    'entity_id' => $wr->id,
                    'route'     => "/work-requests/{$wr->id}",
                    'code'      => $wr->code,
                    'alert'     => 'sla_breached',
                ],
            ]);

            $breached++;
        }

        Log::info("cmms:check-sla — warnings: {$warned}, breached: {$breached}");
        $this->info("SLA warnings: {$warned} | SLA breached: {$breached}");

        return self::SUCCESS;
    }
}
