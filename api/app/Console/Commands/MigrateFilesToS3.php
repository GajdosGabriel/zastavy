<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Presunie existujúce obrázky a prílohy z lokálneho disku na S3.
 *
 * Beží tam, kde súbory fyzicky ležia (na produkčnom serveri) — číta aj zapisuje
 * cez Storage facade. Idempotentný: presunuté záznamy majú disk='s3', ďalší beh
 * ich preskočí.
 */
class MigrateFilesToS3 extends Command
{
    protected $signature = 'files:migrate-to-s3
        {--dry-run : Len vypíše, čo by sa presunulo, bez zápisu}
        {--delete : Po overenom uploade zmaže lokálnu kópiu}
        {--chunk=100 : Počet záznamov spracovaných naraz}';

    protected $description = 'Presunie obrázky a prílohy z lokálneho disku na S3 a aktualizuje ich stĺpec disk';

    private const SOURCE_DISKS = ['local', 'public'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $delete = (bool) $this->option('delete');
        $chunkSize = max(1, (int) $this->option('chunk'));

        if (! $this->preflight()) {
            return self::FAILURE;
        }

        $migrated = 0;
        $failed = 0;

        foreach ([Image::class, Attachment::class] as $modelClass) {
            $this->line(sprintf('— %s', class_basename($modelClass)));

            $modelClass::query()
                ->where(function ($query) {
                    $query->whereIn('disk', self::SOURCE_DISKS)->orWhereNull('disk');
                })
                ->orderBy('id')
                ->chunkById($chunkSize, function ($records) use ($dryRun, $delete, &$migrated, &$failed) {
                    foreach ($records as $record) {
                        try {
                            $this->migrateRecord($record, $dryRun, $delete);
                            $migrated++;
                        } catch (\Throwable $e) {
                            $failed++;
                            $this->error(sprintf('%s #%d (%s): %s', class_basename($record), $record->id, $record->path, $e->getMessage()));
                        }
                    }
                });
        }

        $this->info(sprintf('%sPresunuté: %d, chyby: %d', $dryRun ? '[DRY RUN] ' : '', $migrated, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Overí cieľový disk ešte pred prvým zápisom. --dry-run číta iba lokálny
     * disk, takže by prešiel aj s nenastaveným S3; bez tejto kontroly sa dá
     * ľahko spustiť migrácia do prázdneho bucketu alebo cudzieho prefixu.
     */
    private function preflight(): bool
    {
        $bucket = (string) config('filesystems.disks.s3.bucket');
        $root = (string) config('filesystems.disks.s3.root');

        if ($bucket === '') {
            $this->error('AWS_BUCKET nie je nastavený — S3 disk nie je nakonfigurovaný.');

            return false;
        }

        $this->line(sprintf(
            'Cieľ: bucket=%s region=%s prefix=%s',
            $bucket,
            (string) config('filesystems.disks.s3.region'),
            $root !== '' ? $root . '/' : '(koreň bucketu)',
        ));

        if ($root === '') {
            $this->warn('AWS_ROOT je prázdny — súbory pôjdu do koreňa bucketu a môžu sa miešať s iným prostredím.');
        }

        // Skutočný zápis overí kľúče, región aj práva naraz. Disk má throw=false,
        // takže put() pri chybe nevyhodí výnimku — kontrolujeme exists().
        $probe = '_preflight-' . uniqid() . '.txt';

        try {
            $disk = Storage::disk('s3');
            $disk->put($probe, 'ok');

            if (! $disk->exists($probe)) {
                $this->error('Zápis na S3 neprešiel. Skontroluj kľúče, región a práva.');

                return false;
            }

            $disk->delete($probe);
        } catch (\Throwable $e) {
            $this->error('S3 nie je dostupné: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    private function migrateRecord(Model $record, bool $dryRun, bool $delete): void
    {
        $sourceDisk = $record->disk ?: 'public';
        $path = preg_replace('#^public/#', '', (string) $record->path);
        $source = Storage::disk($sourceDisk);

        if (! $source->exists($path)) {
            throw new \RuntimeException('Zdrojový súbor neexistuje na disku ' . $sourceDisk);
        }

        $this->line(sprintf('  %s → s3/%s', $path, $path));

        if ($dryRun) {
            return;
        }

        $target = Storage::disk('s3');
        $stream = $source->readStream($path);

        try {
            $target->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $target->exists($path)) {
            throw new \RuntimeException('Súbor po uploade na S3 neexistuje.');
        }

        $record->forceFill(['disk' => 's3', 'path' => $path])->save();

        if ($delete) {
            $source->delete($path);
        }
    }
}
