<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', Rules\Password::defaults()],
        ]);

        $name = trim($request->firstName.' '.$request->lastName);

        $user = User::create([
            'name' => $name,
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'username' => $name,
            'slug' => Str::slug($name),
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
