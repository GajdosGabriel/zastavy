<?php

namespace App\Services\Account;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Prílohy e-mailu z Accountu (dočasná URL vo webhooku) – stiahnu sa na disk
 * `local` a pripoja k záznamu cez `attachments()` (Attachment).
 */
class AccountAttachments
{
    /**
     * @param  array<string, mixed>  $file
     * @param  array<int, string>  $paths  uložené súbory – volajúci ich pri chybe zmaže
     */
    public static function store(Model $row, array $file, string $directory, array &$paths): void
    {
        if (empty($file['url'])) {
            return;
        }

        $response = Http::timeout(30)->get($file['url']);

        if (! $response->successful()) {
            return;
        }

        $name = (string) ($file['filename'] ?? 'priloha');
        $path = trim($directory, '/').'/'.Str::random(40).'.'.(pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');

        Storage::disk('local')->put($path, $response->body());
        $paths[] = $path;

        $row->attachments()->create([
            'disk' => 'local',
            'path' => $path,
            'name' => $name,
            'mime' => $file['mime'] ?? null,
            'size' => strlen($response->body()),
        ]);
    }
}
