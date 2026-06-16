<?php

namespace App\Notifications;

use App\Models\NotificationLog;
use App\Models\WorkOrder;
use App\Models\Asset;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\NotificationLogChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssetWorkOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $workOrder;
    protected $asset;
    protected $eventType;

    /**
     * Create a new notification instance.
     *
     * @param WorkOrder $workOrder
     * @param Asset $asset
     * @param string $eventType (create, open, close)
     */
    public function __construct(WorkOrder $workOrder, Asset $asset, string $eventType)
    {
        $this->workOrder = $workOrder;
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
        return ['mail', 'broadcast', FcmChannel::class, NotificationLogChannel::class];
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

        return (new MailMessage)
            ->subject($subject)
            ->greeting($greeting)
            ->line($content)
            ->line("**Activo:** {$this->asset->name} ({$this->asset->code})")
            ->line("**Orden de Trabajo:** {$this->workOrder->code}")
            ->line("**Título:** {$this->workOrder->title}")
            ->line("**Prioridad:** " . $this->getPriorityLabel())
            ->line("**Estado:** " . $this->getStatusLabel())
            ->action('Ver Orden de Trabajo', url("/work-orders/{$this->workOrder->id}"))
            ->line('Gracias por usar RECYLO CMMS!');
    }

    /**
     * Get subject based on event type
     */
    protected function getSubject(): string
    {
        switch ($this->eventType) {
            case 'create':
                return "Nueva Orden de Trabajo: {$this->workOrder->code}";
            case 'open':
                return "Orden de Trabajo Iniciada: {$this->workOrder->code}";
            case 'close':
                return "Orden de Trabajo Completada: {$this->workOrder->code}";
            default:
                return "Actualización de Orden de Trabajo: {$this->workOrder->code}";
        }
    }

    /**
     * Get greeting based on event type
     */
    protected function getGreeting(): string
    {
        switch ($this->eventType) {
            case 'create':
                return '¡Nueva Orden de Trabajo Creada!';
            case 'open':
                return 'Orden de Trabajo Iniciada';
            case 'close':
                return 'Orden de Trabajo Completada';
            default:
                return 'Actualización de Orden de Trabajo';
        }
    }

    /**
     * Get content based on event type
     */
    protected function getContent(): string
    {
        switch ($this->eventType) {
            case 'create':
                return "Se ha creado una nueva orden de trabajo para el activo {$this->asset->name}.";
            case 'open':
                return "La orden de trabajo {$this->workOrder->code} ha sido iniciada.";
            case 'close':
                return "La orden de trabajo {$this->workOrder->code} ha sido completada exitosamente.";
            default:
                return "La orden de trabajo {$this->workOrder->code} ha sido actualizada.";
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

        return $priorities[$this->workOrder->priority] ?? $this->workOrder->priority;
    }

    /**
     * Get status label
     */
    protected function getStatusLabel(): string
    {
        $statuses = [
            'pending' => 'Pendiente',
            'scheduled' => 'Programada',
            'in_progress' => 'En Progreso',
            'paused' => 'Pausada',
            'completed' => 'Completada',
            'validated' => 'Validada',
            'cancelled' => 'Cancelada',
        ];

        return $statuses[$this->workOrder->status] ?? $this->workOrder->status;
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
            'type'           => 'work_order',
            'event_type'     => $this->eventType,
            'work_order_id'  => $this->workOrder->id,
            'code'           => $this->workOrder->code,
            'title'          => $this->workOrder->title,
            'status'         => $this->workOrder->status,
            'asset_name'     => $this->asset->name,
            'message'        => $this->getContent(),
        ]);
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => $this->getSubject(),
            'body'  => $this->getContent(),
            'data'  => [
                'type'          => 'work_order_assigned',
                'work_order_id' => (string) $this->workOrder->id,
                'event_type'    => $this->eventType,
            ],
        ];
    }

    public function toArray($notifiable): array
    {
        return [
            'work_order_id'   => $this->workOrder->id,
            'work_order_code' => $this->workOrder->code,
            'asset_id'        => $this->asset->id,
            'asset_name'      => $this->asset->name,
            'event_type'      => $this->eventType,
        ];
    }

    public function toNotificationLog($notifiable): array
    {
        $section = match($this->eventType) {
            'assigned'   => '#sec-team',
            'completed'  => '#sec-history',
            'validated'  => '#sec-history',
            'cancelled'  => '#sec-history',
            'reopened'   => '#sec-info',
            default      => '',
        };

        return [
            'notification_type' => NotificationLog::TYPE_WORK_ORDER,
            'event_type'        => $this->eventType,
            'work_order_id'     => $this->workOrder->id,
            'asset_id'          => $this->asset->id,
            'subject'           => $this->getSubject(),
            'message'           => $this->getContent(),
            'metadata'          => [
                'module'    => 'work_orders',
                'entity_id' => $this->workOrder->id,
                'route'     => "/work-orders/{$this->workOrder->id}{$section}",
                'code'      => $this->workOrder->code,
                'priority'  => $this->workOrder->priority,
                'status'    => $this->workOrder->status,
                'asset'     => [
                    'id'   => $this->asset->id,
                    'name' => $this->asset->name,
                    'code' => $this->asset->code,
                ],
            ],
        ];
    }
}
