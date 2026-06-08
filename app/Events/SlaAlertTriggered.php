<?php

namespace App\Events;

use App\Models\WorkRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SlaAlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkRequest $workRequest,
        public string $alertType  // sla_warning, sla_breached
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("company.{$this->workRequest->company_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sla.alert';
    }

    public function broadcastWith(): array
    {
        return [
            'alert_type'      => $this->alertType,
            'work_request_id' => $this->workRequest->id,
            'code'            => $this->workRequest->code,
            'title'           => $this->workRequest->title,
            'company_id'      => $this->workRequest->company_id,
        ];
    }
}
