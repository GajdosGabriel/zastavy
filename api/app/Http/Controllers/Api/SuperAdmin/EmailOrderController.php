<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\EmailOrderImport;
use Illuminate\Http\Request;

class EmailOrderController extends Controller
{
    public function __invoke(Request $request, EmailOrderImport $import)
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $data = $request->validate(['text' => ['required', 'string', 'min:20', 'max:20000']]);
        if (! config('services.openai.key')) {
            return response()->json(['message' => 'AI import vyžaduje nastavený OPENAI_API_KEY na serveri.'], 503);
        }
        try {
            return response()->json(['data' => $import->preview($data['text'])]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'E-mail sa nepodarilo spracovať. Skúste to znova alebo doplňte údaje ručne.'], 502);
        }
    }
}
