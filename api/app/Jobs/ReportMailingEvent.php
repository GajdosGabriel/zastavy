<?php

namespace App\Jobs;

use App\Enums\MailingTrigger;
use App\Services\Account\AccountClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Udalosť objednávky pre automatizácie v Accounte.
 *
 * Cez frontu, aby výpadok Accountu nezhodil objednávku. Opakovanie je
 * bezpečné – Account deduplikuje podľa `dedupe_key`.
 *
 * Pri storne najprv zruší čakajúce maily k objednávke (okrem kampaní na
 * storno) – stornovaná objednávka už nemá dostať napr. „ako ste spokojní“.
 */
class ReportMailingEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600, 10800];

    /**
     * @param  array<string, mixed>|null  $event  null = len zrušenie čakajúcich mailov
     * @param  string|null  $cancel  uuid objednávky, ktorej čakajúce maily sa zrušia
     */
    public function __construct(public ?array $event, public ?string $cancel = null) {}

    public function handle(AccountClient $account): void
    {
        if ($this->cancel) {
            $account->cancelMailing('order', $this->cancel, [MailingTrigger::OrderCancelled->value]);
        }

        if ($this->event) {
            $account->mailingEvent($this->event);
        }
    }
}
