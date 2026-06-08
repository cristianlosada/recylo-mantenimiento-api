<?php

namespace App\Events;

use App\Models\InductionProcess;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InductionProcessCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public InductionProcess $process;

    /**
     * Create a new event instance.
     */
    public function __construct(InductionProcess $process)
    {
        $this->process = $process;
    }
}
