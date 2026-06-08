<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * event_type:
     *   status_changed   — el proyecto cambió de estado
     *   log_submitted    — un técnico registró una bitácora pendiente de revisión
     *   progress_updated — el avance de una fase fue actualizado automáticamente
     */
    public function __construct(
        public Project $project,
        public string  $eventType,
        public array   $extra = []   // datos adicionales opcionales (fase, log, etc.)
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("company.{$this->project->company_id}"),
        ];

        if ($this->project->leader_id) {
            $channels[] = new PrivateChannel("user.{$this->project->leader_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'project.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'event_type'  => $this->eventType,
            'project_id'  => $this->project->id,
            'code'        => $this->project->code,
            'name'        => $this->project->name,
            'company_id'  => $this->project->company_id,
            'leader_id'   => $this->project->leader_id,
            ...$this->extra,
        ];
    }
}
