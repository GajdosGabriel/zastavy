<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * Odkazy z kampaní odoslaných ešte zo zastáv (odhlásenie, klik, otvorenie).
 * Emailing beží v Accounte a tokeny sa preniesli bez zmeny, takže stačí presmerovať.
 */
class EmailingRedirectController extends Controller
{
    /** 307 zachová aj POST odhlásenia jedným klikom (List-Unsubscribe). */
    public function unsubscribe(string $token)
    {
        return $this->account('/m/u/'.$token, 307);
    }

    public function click(string $token)
    {
        return $this->account('/m/c/'.$token);
    }

    public function open(string $token)
    {
        return $this->account('/m/o/'.$token.'.gif');
    }

    private function account(string $path, int $status = 302)
    {
        return redirect()->away(config('account.public_url').$path, $status)->withHeaders([
            'Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
