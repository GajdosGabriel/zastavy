<?php

namespace App\Services;

use App\Models\SalesQuote;
use App\Models\SalesQuoteVersion;
use Barryvdh\DomPDF\Facade\Pdf;

/** PDF cenovej ponuky — rovnaký obsah dostane zákazník v prílohe e-mailu aj na stiahnutie. */
class QuotePdf
{
    public function render(SalesQuote $quote, SalesQuoteVersion $version): string
    {
        return Pdf::loadView('pdf.quote', ['quote' => $quote, 'version' => $version, 'company' => config('shop.company'), 'bank' => config('shop.bank')])
            ->setPaper('a4')
            // Bez podmnožiny písma by každé PDF nieslo celé DejaVu (~800 kB) — príloha e-mailu by zbytočne rástla.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function filename(SalesQuote $quote, SalesQuoteVersion $version): string
    {
        return 'Cenova-ponuka-'.($quote->number ?? $quote->id).($version->version > 1 ? '-v'.$version->version : '').'.pdf';
    }

    public function download(SalesQuote $quote, SalesQuoteVersion $version)
    {
        return response($this->render($quote, $version), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($quote, $version).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
