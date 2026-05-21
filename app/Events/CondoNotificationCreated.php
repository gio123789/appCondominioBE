<?php

namespace App\Events;

use App\Models\CondoNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CondoNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CondoNotification $notification)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('departamentos.'.$this->notification->departamento)];
    }

    public function broadcastAs(): string
    {
        return 'notificacion.nueva';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'departamento' => $this->notification->departamento,
            'tipo' => $this->notification->tipo,
            'titulo' => $this->notification->titulo,
            'detalle' => $this->notification->detalle,
            'leida' => $this->notification->leida,
            'fecha' => optional($this->notification->created_at)->toISOString(),
        ];
    }
}
