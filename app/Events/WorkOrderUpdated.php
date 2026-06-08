<?php

namespace App\Events;

use App\Models\WorkOrder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkOrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkOrder $workOrder,
        public string $eventType  // assigned, status_changed, completed, comment_added
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("company.{$this->workOrder->company_id}"),
        ];

        if ($this->workOrder->assigned_to) {
            $channels[] = new PrivateChannel("user.{$this->workOrder->assigned_to}");
        }

        if ($this->workOrder->created_by) {
            $channels[] = new PrivateChannel("user.{$this->workOrder->created_by}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'work-order.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_type'     => $this->eventType,
            'work_order_id'  => $this->workOrder->id,
            'code'           => $this->workOrder->code,
            'title'          => $this->workOrder->title,
            'status'         => $this->workOrder->status,
            'priority'       => $this->workOrder->priority,
            'assigned_to'    => $this->workOrder->assigned_to,
            'company_id'     => $this->workOrder->company_id,
        ];
    }
}
