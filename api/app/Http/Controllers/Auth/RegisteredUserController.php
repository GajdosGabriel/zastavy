<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Str;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'username' => trim($request->firstName . ' ' . $request->lastName),
            'slug' => Str::slug($request->firstName . ' ' . $request->lastName),
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
        ]);

        event(new Registered($user));

        // Ak chceš automaticky prihlásiť používateľa po registrácii, odkomentuj:
        // Auth::login($user);

        // Ak vraciaš len potvrdenie o úspechu bez tokenu:
        return response()->json([
            'message' => 'Registration successful.',
            // 'user' => $user,
            // 'token' => $user->createToken('auth_token')->plainTextToken,
        ]);
    }
}
