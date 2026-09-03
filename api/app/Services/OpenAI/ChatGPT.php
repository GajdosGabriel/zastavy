<?php

namespace App\Services\OpenAI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * Jediné miesto, kde tento projekt hovorí s OpenAI.
 *
 * Postavené rovnako ako v projekte event, len oveľa menšie — tu ide o jednu
 * úlohu (posúdiť riadok v `customers`) a nie o extrakciu podujatí z plagátov.
 * Tvar volania sa zámerne nelíši, nech je čo porovnávať, keď sa niečo pokazí:
 * chat/completions, teplota 0, vynútená JSON schéma, validácia odpovede.
 *
 * Model NIKDY nedostane e-mail ani telefón zákazníka — viď
 * PromptCustomerReview::prompt(). Posudzovať sa dá aj bez nich a osobné údaje
 * nemajú čo chodiť k tretej strane kvôli kontrole veľkých písmen.
 */
class ChatGPT
{
    public function __construct(
        private readonly PromptCustomerReview $promptCustomerReview = new PromptCustomerReview(),
    ) {
    }

    /**
     * @param  array<string, string|null>  $customer  polia zákazníka
     * @param  array<string, string|null>|null  $registry  ten istý subjekt podľa registra
     * @return array{score: int, summary: string, issues: array<int, array<string, mixed>>}
     */
    public function extractCustomerReview(array $customer, ?array $registry = null): array
    {
        $content = $this->chatComplete(
            (string) config('customer_review.model', 'gpt-4o-mini'),
            0,
            $this->promptCustomerReview->prompt($customer, $registry),
            $this->promptCustomerReview->jsonSchema(),
        );

        $data = $this->decodeJson($content);

        $data['issues'] = array_values(array_filter(
            (array) ($data['issues'] ?? []),
            static fn ($issue) => is_array($issue),
        ));

        $validator = Validator::make($data, $this->promptCustomerReview->validator());

        if ($validator->fails()) {
            throw new \RuntimeException('Neplatná štruktúra odpovede: '.$validator->errors()->toJson());
        }

        return [
            'score' => (int) $data['score'],
            'summary' => trim((string) $data['summary']),
            'issues' => $data['issues'],
        ];
    }

    private function chatComplete(
        string $model,
        float $temperature,
        array $messages,
        ?array $responseFormat = null,
        int $timeout = 45,
    ): string {
        $apiKey = (string) config('services.openai.key', '');

        if ($apiKey === '') {
            throw new \RuntimeException('OPENAI_API_KEY nie je nastavený.');
        }

        $response = Http::timeout($timeout)
            ->withToken($apiKey)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => $temperature,
                'response_format' => $responseFormat ?? ['type' => 'json_object'],
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI API error: '.$response->status().' '.$response->body());
        }

        $data = $response->json();

        // Odpoveď useknutá na limite tokenov je nedokončený JSON a o riadok
        // nižšie by z toho vypadlo len „Syntax error", čo vyzerá ako chyba
        // modelu — pritom stačí kratší vstup alebo vyšší strop.
        if (($data['choices'][0]['message']['content'] ?? null) === null
            || ($data['choices'][0]['finish_reason'] ?? null) === 'length') {
            throw new \RuntimeException('Odpoveď modelu je prázdna alebo useknutá na limite tokenov.');
        }

        return (string) $data['choices'][0]['message']['content'];
    }

    private function decodeJson(string $content): array
    {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            throw new \RuntimeException('Neplatný JSON: '.json_last_error_msg());
        }

        return $data;
    }
}
