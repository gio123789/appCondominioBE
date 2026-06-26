<?php

namespace App\Http\Controllers;

use App\Events\CondoNotificationCreated;
use App\Events\DepartmentMessageSent;
use App\Models\CondoNotification;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ChatMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $limit = $data['limit'] ?? 50;

        $query = Mensaje::query()->latest('id')->limit($limit);

        if ($user->role !== 'admin') {
            $query->where('departamento', $user->departamento);
        }

        $mensajes = $query
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
            'mensaje' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! $user->departamento) {
            return response()->json([
                'message' => 'El usuario no tiene departamento asignado.',
            ], 422);
        }

        $mensaje = Mensaje::create([
            'remitente' => $user->name,
            'departamento' => $user->departamento,
            'mensaje' => $data['mensaje'],
        ]);

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
