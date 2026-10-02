<?php

namespace App\Http\Controllers\Api;

use Auth;
use Illuminate\Support\Facades\Hash;
use App\Enums\ModelStatus;
use App\Http\Middleware\AuthTokenFromCookie;
use App\Models\User;
use App\Services\SystemLog\Recorder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

class SanctumController extends Controller
{
    public function login(Request $request)
    {

        // Ten istý e-mail môže mať viac kontaktných záznamov (tá istá osoba
        // objednáva za viac firiem). Prihlasuje sa najstarší — pôvodný účet.
        $user = User::where('email', $request->email)->orderBy('id')->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            Recorder::warning('auth', 'failed', $user ? 'Nesprávne heslo' : 'Neznámy účet',
                status: 'failed',
                recipient: is_string($request->email) ? $request->email : null,
                userId: $user?->id,
                ip: $request->ip(),
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === ModelStatus::Cancelled) {
            throw ValidationException::withMessages([
                'email' => ['Váš účet bol zrušený. Kontaktujte administrátora.'],
            ]);
        }

        if ($user->status === ModelStatus::Blocked) {
            throw ValidationException::withMessages([
                'email' => ['Váš účet je blokovaný. Kontaktujte administrátora.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Váš účet je neaktívny. Kontaktujte administrátora.'],
            ]);
        }

        $user->recordLogin($request->ip());

        Recorder::info('auth', 'login', 'Prihlásenie',
            status: 'ok',
            recipient: $user->email,
            userId: $user->id,
            ip: $request->ip(),
        );

        $token = $user->createToken('API Token')->plainTextToken;

        // Token odchádza iba v httpOnly cookie; v tele odpovede ho klient nedostane.
        return response()->json(['message' => 'Logged in'])
            ->withCookie(cookie(
                AuthTokenFromCookie::COOKIE, $token, 60 * 24 * 30, '/',
                config('session.domain'), $request->isSecure(), true, false,
                config('session.same_site', 'lax'),
            ));
    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete(); // Vymaže aktuálny token

        return response()->json(['message' => 'Logged out'])
            ->withCookie(cookie()->forget(AuthTokenFromCookie::COOKIE, '/', config('session.domain')));
    }
}
