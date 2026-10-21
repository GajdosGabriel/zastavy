<?php

namespace App\Jobs;

use App\Models\EmailOrderDraft;
use App\Services\EmailOrders\EmailOrderInbox;
use App\Services\SystemLog\Recorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Rozpoznanie objednávky z e-mailu (AI + dohľadanie zákazníka). Trvá aj
 * dve minúty, preto vo fronte; výpadok AI sa skúsi znova.
 */
class ProcessEmailOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    /** @var array<int, int> */
    public array $backoff = [300, 1800];

    public function __construct(public int $draftId) {}

    public function handle(EmailOrderInbox $inbox): void
    {
        $draft = EmailOrderDraft::find($this->draftId);

        if ($draft) {
            $inbox->process($draft);
        }
    }

    public function failed(Throwable $e): void
    {
        // Text výnimky môže niesť obsah mailu – obsluhe stačí, že treba ručne.
        EmailOrderDraft::whereKey($this->draftId)->where('status', EmailOrderDraft::PENDING)
            ->update(['status' => EmailOrderDraft::FAILED, 'error' => 'E-mail sa nepodarilo automaticky spracovať.']);
        Recorder::warning('order', 'email_order_failed', 'Objednávku z e-mailu sa nepodarilo spracovať', context: ['draft' => $this->draftId]);
    }
}
