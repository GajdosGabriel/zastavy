<?php

namespace App\Actions;

use App\Models\Attachment;
use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Uloží prílohy nahrané zákazníkom (podklady k objednávke) na media disk
 * a zapíše ich k modelu. Disk sa ukladá ku každému záznamu, takže prepnutie
 * FILESYSTEM_DISK neznefunkční staršie súbory.
 */
class StoreAttachments
{
    /**
     * @param  UploadedFile|array<int, UploadedFile>|null  $files
     * @return Collection<int, Attachment>
     */
    public function handle(Model $model, $files, ?int $userId = null): Collection
    {
        $files = collect(is_array($files) ? $files : ($files ? [$files] : []))
            ->filter(fn ($file) => $file instanceof UploadedFile && $file->isValid())
            ->take((int) config('media.attachments.max_files', 5));

        if ($files->isEmpty()) {
            return collect();
        }

        $disk = Media::disk();
        $directory = strtolower(class_basename($model)) . 's/' . $model->getKey() . '/attachments';

        return $files->map(function (UploadedFile $file) use ($model, $disk, $directory, $userId) {
            $path = $file->store($directory, $disk);

            return $model->attachments()->create([
                'disk' => $disk,
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'user_id' => $userId,
            ]);
        })->values();
    }
}
