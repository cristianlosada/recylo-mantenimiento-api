<?php

namespace App\Events;

use App\Models\WorkRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WorkRequest $workRequest,
        public string $eventType  // created, approved, rejected, comment_added
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("company.{$this->workRequest->company_id}"),
        ];

        if ($this->workRequest->requester_id) {
            $channels[] = new PrivateChannel("user.{$this->workRequest->requester_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'work-request.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_type'      => $this->eventType,
            'work_request_id' => $this->workRequest->id,
            'code'            => $this->workRequest->code,
            'title'           => $this->workRequest->title,
            'status'          => $this->workRequest->status,
            'priority'        => $this->workRequest->priority,
            'requester_id'    => $this->workRequest->requester_id,
            'company_id'      => $this->workRequest->company_id,
        ];
    }
}
