<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(
            prepend: [\App\Http\Middleware\AuthTokenFromCookie::class],
            append: [\App\Http\Middleware\EnsureAccountIsActive::class],
        );
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->append(SetLocale::class);
        // API nemá prihlasovaciu stránku: neprihlásený dostane 401, nie redirect na neexistujúcu route login.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Chyba odoslania mailu (SMTP) skončí v denníku udalostí.
        $exceptions->reportable(function (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            \App\Listeners\SystemLogSubscriber::mailFailed($e);
        });

        // Text SQL chyby (dotaz, názov databázy, host) nepatrí do odpovede.
        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            report($e);

            return response()->json([
                'message' => 'Údaje sa nepodarilo uložiť. Skontrolujte formulár a skúste to znova.',
            ], 500);
        });

        // API vždy odpovedá v JSON (aj chyby), nie HTML/redirect.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
