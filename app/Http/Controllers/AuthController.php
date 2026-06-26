<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const RESET_CODE_TTL_MINUTES = 10;

    private const MAX_RESET_ATTEMPTS = 5;

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'departamento' => ['required', 'integer', 'min:1'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'resident',
            'departamento' => $data['departamento'],
        ]);

        return response()->json([
            'message' => 'Registro exitoso.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $abilities = $user->role === 'admin'
            ? ['*']
            : ['chat:read', 'chat:write', 'notifications:read'];

        $deviceName = trim($data['device_name'] ?? 'web-app');

        if ($deviceName === '') {
            $deviceName = 'web-app';
        }

        // Keep one active token per device name.
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken(
            $deviceName,
            $abilities,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $this->transformUser($user),
        ]);
    }

    public function resendVerificationByEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'Si el correo existe, enviaremos un enlace de verificacion.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'El correo ya esta verificado.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Si el correo existe, enviaremos un enlace de verificacion.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user) {
            PasswordResetCode::query()
                ->where('email', $user->email)
                ->whereNull('used_at')
                ->delete();

            $plainCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordResetCode::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'code_hash' => Hash::make($plainCode),
                'expires_at' => now()->addMinutes(self::RESET_CODE_TTL_MINUTES),
            ]);

            Mail::raw(
                "Tu codigo de recuperacion es: {$plainCode}\n\nEste codigo vence en " . self::RESET_CODE_TTL_MINUTES . " minutos.",
                static function ($message) use ($user): void {
                    $message->to($user->email)
                        ->subject('Codigo de recuperacion de contrasena');
                }
            );
        }

        return response()->json([
            'message' => 'Si el correo existe, enviaremos un codigo de recuperacion.',
        ]);
    }

    public function verifyResetCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $resetCode = $this->getActiveResetCode($data['email']);

        if (! $resetCode) {
            throw ValidationException::withMessages([
                'code' => ['El codigo no es valido o ha expirado.'],
            ]);
        }

        if ($resetCode->attempts >= self::MAX_RESET_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => ['Se supero el numero maximo de intentos. Solicita un nuevo codigo.'],
            ]);
        }

        if (! Hash::check($data['code'], $resetCode->code_hash)) {
            $resetCode->increment('attempts');

            throw ValidationException::withMessages([
                'code' => ['El codigo ingresado es incorrecto.'],
            ]);
        }

        return response()->json([
            'message' => 'Codigo valido.',
        ]);
    }

    public function resetPasswordWithCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $resetCode = $this->getActiveResetCode($data['email']);

        if (! $resetCode) {
            throw ValidationException::withMessages([
                'code' => ['El codigo no es valido o ha expirado.'],
            ]);
        }

        if ($resetCode->attempts >= self::MAX_RESET_ATTEMPTS) {
            throw ValidationException::withMessages([
                'code' => ['Se supero el numero maximo de intentos. Solicita un nuevo codigo.'],
            ]);
        }

        if (! Hash::check($data['code'], $resetCode->code_hash)) {
            $resetCode->increment('attempts');

            throw ValidationException::withMessages([
                'code' => ['El codigo ingresado es incorrecto.'],
            ]);
        }

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No existe una cuenta con ese correo.'],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        $user->tokens()->delete();

        $resetCode->forceFill([
            'used_at' => now(),
        ])->save();

        PasswordResetCode::query()
            ->where('email', $data['email'])
            ->whereNull('used_at')
            ->where('id', '!=', $resetCode->id)
            ->delete();

        return response()->json([
            'message' => 'Contrasena restablecida correctamente.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->transformUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contrasena actual es incorrecta.'],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        // Close session in every device after password change.
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contrasena actualizada. Se cerraron todas las sesiones.',
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Tu correo ya esta verificado.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Enlace de verificacion reenviado.',
        ]);
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'departamento' => $user->departamento,
            'email_verified_at' => optional($user->email_verified_at)?->toISOString(),
        ];
    }

    private function getActiveResetCode(string $email): ?PasswordResetCode
    {
        $resetCode = PasswordResetCode::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->latest('id')
            ->first();

        if (! $resetCode) {
            return null;
        }

        if ($resetCode->expires_at->isPast()) {
            $resetCode->delete();

            return null;
        }

        return $resetCode;
    }
}
