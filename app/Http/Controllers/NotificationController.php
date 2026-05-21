<?php

namespace App\Http\Controllers;

use App\Events\CondoNotificationCreated;
use App\Models\CondoNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'departamento' => ['required', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = $data['limit'] ?? 30;

        $notifications = CondoNotification::query()
            ->where('departamento', $data['departamento'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (CondoNotification $notification) => $this->transform($notification));

        return response()->json([
            'data' => $notifications,
        ]);
    }

    public function show(CondoNotification $notification): JsonResponse
    {
        return response()->json([
            'data' => $this->transform($notification),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'departamento' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', 'string', 'in:mensaje,multa,asamblea,pago_atrasado'],
            'titulo' => ['required', 'string', 'max:120'],
            'detalle' => ['required', 'string', 'max:1000'],
        ]);

        $notification = CondoNotification::create([
            ...$data,
            'leida' => false,
        ]);

        event(new CondoNotificationCreated($notification));

        return response()->json([
            'data' => $this->transform($notification),
        ], 201);
    }

    public function markAsRead(CondoNotification $notification): JsonResponse
    {
        $notification->update([
            'leida' => true,
        ]);

        return response()->json([
            'data' => $this->transform($notification),
        ]);
    }

    private function transform(CondoNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'departamento' => $notification->departamento,
            'tipo' => $notification->tipo,
            'titulo' => $notification->titulo,
            'detalle' => $notification->detalle,
            'leida' => $notification->leida,
            'fecha' => optional($notification->created_at)->toISOString(),
        ];
    }
}
