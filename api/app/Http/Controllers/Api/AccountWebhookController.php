<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Account\AccountMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Príjem udalostí z Accountu – odpovede zákazníkov na e-maily k objednávkam.
 *
 * Endpoint sa v Accounte registruje v „API a webhooky“ (produkt zastavy)
 * s udalosťami message.received, message.sent, message.bounced, message.failed.
 * Podpis: HMAC-SHA256 z „timestamp.telo“ tajomstvom ACCOUNT_WEBHOOK_SECRET.
 */
class AccountWebhookController extends Controller
{
    public function __construct(private readonly AccountMessages $messages) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('account.webhook_secret');

        if ($secret === '') {
            // Bez tajomstva podpis neoveríme – prijať by znamenalo pustiť hocikoho.
            return response()->json(['message' => 'Webhook z Accountu nie je nastavený.'], 503);
        }

        $timestamp = (int) $request->header('X-Accounts-Timestamp');
        $signature = (string) $request->header('X-Accounts-Signature');
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Neplatný podpis.'], 400);
        }

        // Ochrana proti replay útoku
        if (abs(time() - $timestamp) > 300) {
            return response()->json(['message' => 'Zastaraná požiadavka.'], 400);
        }

        $event = (string) $request->input('event');

        if (str_starts_with($event, 'message.')) {
            $this->messages->handle(
                $event,
                (array) $request->input('data.conversation', []),
                (array) $request->input('data.message', []),
            );
        }

        return response()->json(['ok' => true]);
    }
}
