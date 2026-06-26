<?php

namespace App\Http\Controllers;

use App\Events\CondoNotificationCreated;
use App\Models\CondoNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $limit = $data['limit'] ?? 30;

        $query = CondoNotification::query()->latest('id')->limit($limit);

        if ($user->role !== 'admin') {
            $query->where('departamento', $user->departamento);
        }

        $notifications = $query
            ->get()
            ->map(fn (CondoNotification $notification) => $this->transform($notification));

        return response()->json([
            'data' => $notifications,
        ]);
    }

    public function show(Request $request, CondoNotification $notification): JsonResponse
    {
        $this->authorizeNotification($request->user(), $notification);

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

    public function markAsRead(Request $request, CondoNotification $notification): JsonResponse
    {
        $this->authorizeNotification($request->user(), $notification);

        $notification->update([
            'leida' => true,
        ]);

        return response()->json([
            'data' => $this->transform($notification),
        ]);
    }

    private function authorizeNotification(User $user, CondoNotification $notification): void
    {
        if ($user->role === 'admin') {
            return;
        }

        abort_if(
            $notification->departamento !== $user->departamento,
            403,
            'No tienes permisos para acceder a esta notificacion.'
        );
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
