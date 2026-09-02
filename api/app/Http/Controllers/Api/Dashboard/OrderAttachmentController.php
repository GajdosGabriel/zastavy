<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\StoreAttachments;
use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Order;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OrderAttachmentController extends Controller
{
    public function index(Order $order)
    {
        Gate::authorize('view', $order);

        return AttachmentResource::collection($order->attachments()->latest()->get());
    }

    public function store(Order $order, Request $request)
    {
        Gate::authorize('update', $order);

        $request->validate([
            'attachments' => ['required', 'array', 'max:' . config('media.attachments.max_files')],
            'attachments.*' => OrderRequest::attachmentRules(),
        ]);

        $attachments = (new StoreAttachments())->handle($order, $request->file('attachments'), $request->user()?->id);

        return AttachmentResource::collection($attachments);
    }

    /**
     * Stiahnutie prebieha cez API, nie priamym odkazom do bucketu — prílohy sú
     * súkromné a rovnaký kód funguje pre lokálny disk aj pre S3.
     */
    public function show(Order $order, Attachment $attachment)
    {
        Gate::authorize('view', $order);

        return self::download($order, $attachment);
    }

    public function destroy(Order $order, Attachment $attachment)
    {
        Gate::authorize('update', $order);

        self::assertBelongsTo($order, $attachment);

        $attachment->deleteWithFile();

        return response()->noContent();
    }

    /** Spoločné pre dashboard aj verejný (uuid) detail objednávky. */
    public static function download(Order $order, Attachment $attachment)
    {
        self::assertBelongsTo($order, $attachment);

        $disk = Storage::disk(Media::diskFor($attachment->disk));

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->name);
    }

    private static function assertBelongsTo(Order $order, Attachment $attachment): void
    {
        abort_unless(
            $attachment->attachable_type === Order::class && (int) $attachment->attachable_id === (int) $order->id,
            404
        );
    }
}
