<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SalesAccess
{
    public static function staff(Request $request): void
    {
        $user = $request->user('sanctum');
        abort_unless($user?->isStaff() && $user->can('orders.create') && $user->can('orders.viewAny'), 403);
    }

    public static function token(Request $request, $model): void
    {
        $token = $request->header('X-Sales-Token', '');
        abort_unless(is_string($token) && strlen($token) === 64 && $model->token_hash && hash_equals($model->token_hash, hash('sha256', $token)) && $model->token_expires_at?->isFuture(), 404);
    }

    public static function renew($model): string
    {
        $token = Str::random(64);
        $model->forceFill(['token_hash' => hash('sha256', $token), 'token_expires_at' => now()->addDays(90)])->save();

        return $token;
    }

    public static function download(string $disk, string $path, string $name)
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, $name, ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
