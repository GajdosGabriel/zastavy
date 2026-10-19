<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Jediné miesto, kde sa rozhoduje, na ktorý disk sa nahráva a ako sa z neho
 * skladá URL. Modely držia v DB stĺpec `disk`, takže staré (lokálne) a nové
 * (S3) súbory môžu existovať vedľa seba počas migrácie.
 */
class Media
{
    /** Disk, na ktorý sa ukladajú nové súbory. */
    public static function disk(): string
    {
        return (string) config('media.disk') ?: 'public';
    }

    /** Disk uloženého záznamu; staré záznamy bez stĺpca ležia na 'public'. */
    public static function diskFor(?string $disk): string
    {
        return $disk ?: 'public';
    }

    public static function isCloud(string $disk): bool
    {
        return config("filesystems.disks.{$disk}.driver") === 's3';
    }

    /**
     * Verejná URL súboru. Pri súkromnom S3 buckete sa podpisuje na obmedzený čas,
     * inak sa vracia priama adresa (cacheovateľná).
     */
    public static function url(?string $disk, ?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = self::diskFor($disk);
        // Historické cesty z čias `store('public/...')`.
        $path = preg_replace('#^public/#', '', $path);
        $filesystem = Storage::disk($disk);

        if (self::isCloud($disk) && ! config('media.public_read')) {
            try {
                return $filesystem->temporaryUrl($path, now()->addMinutes((int) config('media.signed_ttl', 60)));
            } catch (\Throwable) {
                return $filesystem->url($path);
            }
        }

        $url = $filesystem->url($path);

        // Lokálny disk: relatívna cesta, aby obrázky fungovali na akejkoľvek doméne (dev proxy, iný host než APP_URL).
        if (! self::isCloud($disk)) {
            $relative = parse_url($url, PHP_URL_PATH);

            return $relative ?: $url;
        }

        return $url;
    }

    /**
     * Absolútna URL obrázka. Cesta z url() už nesie prefix aplikácie
     * (napr. /web/storage/...) — tú lepíme len na origin APP_URL, lebo helper
     * url() by prefix zdvojil (/web/web/storage/...). Ostatné cesty
     * (placeholder /images/...) sú relatívne k aplikácii a idú cez url().
     */
    public static function absolute(?string $url): ?string
    {
        if (! $url || preg_match('#^(https?:)?//#i', $url)) {
            return $url;
        }

        $app = parse_url((string) config('app.url'));
        $prefix = rtrim($app['path'] ?? '', '/');

        if ($prefix === '' || ! str_starts_with($url, $prefix . '/')) {
            return url($url);
        }

        $origin = ($app['scheme'] ?? 'https') . '://' . ($app['host'] ?? '') . (isset($app['port']) ? ':' . $app['port'] : '');

        return $origin . $url;
    }

    public static function delete(?string $disk, ?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk(self::diskFor($disk))->delete(preg_replace('#^public/#', '', $path));
    }
}
