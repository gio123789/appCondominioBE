<?php

namespace App\Http\Controllers;

use App\Events\CondoNotificationCreated;
use App\Events\DepartmentMessageSent;
use App\Models\CondoNotification;
use App\Models\Mensaje;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ChatMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'departamento' => ['required', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $data['limit'] ?? 50;

        $mensajes = Mensaje::query()
            ->where('departamento', $data['departamento'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Mensaje $mensaje) => [
                'id' => $mensaje->id,
                'remitente' => $mensaje->remitente,
                'departamento' => $mensaje->departamento,
                'mensaje' => $mensaje->mensaje,
                'fecha' => optional($mensaje->created_at)->toISOString(),
            ]);

        return response()->json([
            'data' => $mensajes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'remitente' => ['required', 'string', 'max:80'],
            'departamento' => ['required', 'integer', 'min:1'],
            'mensaje' => ['required', 'string', 'max:500'],
        ]);

        $mensaje = Mensaje::create($data);

        event(new DepartmentMessageSent($mensaje));

        try {
            $notification = CondoNotification::create([
                'departamento' => $mensaje->departamento,
                'tipo' => 'mensaje',
                'titulo' => 'Nuevo mensaje en chat',
                'detalle' => $mensaje->remitente.': '.$mensaje->mensaje,
                'leida' => false,
            ]);

            event(new CondoNotificationCreated($notification));
        } catch (Throwable $e) {
            report($e);
        }

        return response()->json([
            'data' => [
                'id' => $mensaje->id,
                'remitente' => $mensaje->remitente,
                'departamento' => $mensaje->departamento,
                'mensaje' => $mensaje->mensaje,
                'fecha' => optional($mensaje->created_at)->toISOString(),
            ],
        ], 201);
    }
}
