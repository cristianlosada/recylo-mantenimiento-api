<?php

namespace App\Jobs;

use App\Models\InductionProcess;
use App\Models\InductionNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Job para enviar recordatorios de procesos pendientes
 * Debe ejecutarse diariamente
 */
class SendInductionReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Iniciando envío de recordatorios de inducción');

        // Obtener procesos pendientes próximos a vencer (3 días antes)
        $processesNearDue = InductionProcess::with(['employee', 'template'])
            ->whereIn('status', ['sent', 'in_progress'])
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addDays(3))
            ->get();

        $remindersNearDue = 0;
        foreach ($processesNearDue as $process) {
            // Verificar si ya se envió recordatorio hoy
            $reminderToday = InductionNotification::where('process_id', $process->id)
                ->where('notification_type', 'reminder')
                ->whereDate('created_at', now())
                ->exists();

            if (!$reminderToday) {
                $this->sendReminder($process, 'near_due');
                $remindersNearDue++;
            }
        }

        // Obtener procesos vencidos
        $overdueProcesses = InductionProcess::with(['employee', 'template'])
            ->whereIn('status', ['sent', 'in_progress'])
            ->where('due_date', '<', now())
            ->get();

        $remindersOverdue = 0;
        foreach ($overdueProcesses as $process) {
            // Actualizar estado a overdue
            if ($process->status !== 'overdue') {
                $process->update(['status' => 'overdue']);
            }

            // Enviar recordatorio de vencido cada 3 días
            $lastOverdueNotification = InductionNotification::where('process_id', $process->id)
                ->where('notification_type', 'overdue')
                ->orderBy('created_at', 'desc')
                ->first();

            $shouldSend = !$lastOverdueNotification || 
                         $lastOverdueNotification->created_at->diffInDays(now()) >= 3;

            if ($shouldSend) {
                $this->sendReminder($process, 'overdue');
                $remindersOverdue++;
            }
        }

        Log::info('Recordatorios enviados', [
            'near_due' => $remindersNearDue,
            'overdue' => $remindersOverdue
        ]);
    }

    /**
     * Enviar recordatorio
     */
    protected function sendReminder(InductionProcess $process, string $type): void
    {
        $link = config('app.frontend_url') . "/induction/complete/{$process->access_token}";

        if ($type === 'near_due') {
            $daysRemaining = now()->diffInDays($process->due_date, false);
            $subject = "Recordatorio: Proceso de {$process->type} - {$daysRemaining} días restantes";
            $message = "Le recordamos que debe completar su proceso de {$process->type} antes del {$process->due_date->format('d/m/Y')}. Quedan {$daysRemaining} días.";
            $notificationType = 'reminder';
        } else {
            $daysOverdue = now()->diffInDays($process->due_date);
            $subject = "URGENTE: Proceso de {$process->type} vencido";
            $message = "Su proceso de {$process->type} venció hace {$daysOverdue} días. Por favor complete el proceso lo antes posible.";
            $notificationType = 'overdue';
        }

        InductionNotification::create([
            'process_id' => $process->id,
            'notification_type' => $notificationType,
            'channel' => 'email',
            'recipient_email' => $process->employee->email,
            'subject' => $subject,
            'message' => $message . " Link: {$link}",
            'sent' => true,
            'sent_at' => now(),
            'metadata' => [
                'link' => $link,
                'type' => $type,
                'days_remaining' => $type === 'near_due' ? now()->diffInDays($process->due_date, false) : null,
                'days_overdue' => $type === 'overdue' ? now()->diffInDays($process->due_date) : null,
            ],
        ]);

        Log::info("Recordatorio enviado", [
            'process_id' => $process->id,
            'employee_id' => $process->employee_id,
            'type' => $type
        ]);

        // Aquí se implementaría el envío real del email
        // Mail::to($process->employee->email)->send(new InductionReminder($process, $link, $type));
    }
}
