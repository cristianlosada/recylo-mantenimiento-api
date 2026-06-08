<?php

namespace App\Notifications;

use App\Models\WorkRequest;
use App\Models\Asset;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssetWorkRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $workRequest;
    protected $asset;
    protected $eventType;

    /**
     * Create a new notification instance.
     *
     * @param WorkRequest $workRequest
     * @param Asset $asset
     * @param string $eventType (create, approve, reject, close)
     */
    public function __construct(WorkRequest $workRequest, Asset $asset, string $eventType)
    {
        $this->workRequest = $workRequest;
        $this->asset = $asset;
        $this->eventType = $eventType;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['mail', 'broadcast', FcmChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $subject = $this->getSubject();
        $greeting = $this->getGreeting();
        $content = $this->getContent();

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($content)
            ->line("**Activo:** {$this->asset->name} ({$this->asset->code})")
            ->line("**Solicitud:** {$this->workRequest->code}")
            ->line("**Título:** {$this->workRequest->title}")
            ->line("**Prioridad:** " . $this->getPriorityLabel())
            ->line("**Estado:** " . $this->getStatusLabel());

        if ($this->workRequest->description) {
            $message->line("**Descripción:** {$this->workRequest->description}");
        }

        return $message
            ->action('Ver Solicitud', url("/work-requests/{$this->workRequest->id}"))
            ->line('Gracias por usar RECYLO CMMS!');
    }

    /**
     * Get subject based on event type
     */
    protected function getSubject(): string
    {
        switch ($this->eventType) {
            case 'create':
                return "Nueva Solicitud de Trabajo: {$this->workRequest->code}";
            case 'approve':
                return "Solicitud Aprobada: {$this->workRequest->code}";
            case 'reject':
                return "Solicitud Rechazada: {$this->workRequest->code}";
            case 'close':
                return "Solicitud Cerrada: {$this->workRequest->code}";
            default:
                return "Actualización de Solicitud: {$this->workRequest->code}";
        }
    }

    /**
     * Get greeting based on event type
     */
    protected function getGreeting(): string
    {
        switch ($this->eventType) {
            case 'create':
                return '¡Nueva Solicitud de Trabajo Reportada!';
            case 'approve':
                return 'Solicitud Aprobada';
            case 'reject':
                return 'Solicitud Rechazada';
            case 'close':
                return 'Solicitud Cerrada';
            default:
                return 'Actualización de Solicitud';
        }
    }

    /**
     * Get content based on event type
     */
    protected function getContent(): string
    {
        switch ($this->eventType) {
            case 'create':
                return "Se ha reportado una nueva solicitud de trabajo para el activo {$this->asset->name}.";
            case 'approve':
                return "La solicitud {$this->workRequest->code} ha sido aprobada y será procesada.";
            case 'reject':
                return "La solicitud {$this->workRequest->code} ha sido rechazada.";
            case 'close':
                return "La solicitud {$this->workRequest->code} ha sido cerrada.";
            default:
                return "La solicitud {$this->workRequest->code} ha sido actualizada.";
        }
    }

    /**
     * Get priority label
     */
    protected function getPriorityLabel(): string
    {
        $priorities = [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ];

        return $priorities[$this->workRequest->priority] ?? $this->workRequest->priority;
    }

    /**
     * Get status label
     */
    protected function getStatusLabel(): string
    {
        $statuses = [
            'open' => 'Abierta',
            'pending_approval' => 'Pendiente de Aprobación',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'in_progress' => 'En Progreso',
            'closed' => 'Cerrada',
        ];

        return $statuses[$this->workRequest->status] ?? $this->workRequest->status;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type'            => 'work_request',
            'event_type'      => $this->eventType,
            'work_request_id' => $this->workRequest->id,
            'code'            => $this->workRequest->code,
            'title'           => $this->workRequest->title,
            'status'          => $this->workRequest->status,
            'asset_name'      => $this->asset->name,
            'message'         => $this->getContent(),
        ]);
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => $this->getSubject(),
            'body'  => $this->getContent(),
            'data'  => [
                'type'            => 'work_request_status',
                'work_request_id' => (string) $this->workRequest->id,
                'event_type'      => $this->eventType,
            ],
        ];
    }

    public function toArray($notifiable): array
    {
        return [
            'work_request_id'   => $this->workRequest->id,
            'work_request_code' => $this->workRequest->code,
            'asset_id'          => $this->asset->id,
            'asset_name'        => $this->asset->name,
            'event_type'        => $this->eventType,
        ];
    }
}
