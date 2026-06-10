<?php

namespace App\Http\Controllers\Api;

use Auth;
use Illuminate\Support\Facades\Hash;
use App\Enums\ModelStatus;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

class SanctumController extends Controller
{
    public function login(Request $request)
    {

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
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

        return response()->json([
            'token' => $user->createToken('API Token')->plainTextToken
        ]);
    }


    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete(); // Vymaže aktuálny token
        return response()->json(['message' => 'Logged out']);
    }
}
