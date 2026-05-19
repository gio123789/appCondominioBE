<?php

namespace App\Events;

use App\Models\Mensaje;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DepartmentMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Mensaje $mensaje)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('departamentos.'.$this->mensaje->departamento)];
    }

    public function broadcastAs(): string
    {
        return 'mensaje.enviado';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->mensaje->id,
            'remitente' => $this->mensaje->remitente,
            'departamento' => $this->mensaje->departamento,
            'mensaje' => $this->mensaje->mensaje,
            'fecha' => optional($this->mensaje->created_at)->toISOString(),
        ];
    }
}
