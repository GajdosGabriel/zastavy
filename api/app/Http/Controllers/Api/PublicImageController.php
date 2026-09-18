<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Product;

/**
 * Trvalá adresa obrázka produktu pre e-maily. Súbory na S3 majú podpísané URL
 * s krátkou platnosťou — v doručenom maile by po hodine zmizli. Táto adresa
 * sa nemení a pri každom otvorení presmeruje na čerstvo podpísanú URL.
 */
class PublicImageController extends Controller
{
    public function show(Image $image)
    {
        // Len obrázky produktov — tie sú aj tak verejné na e-shope.
        abort_unless($image->fileable_type === Product::class && $image->url, 404);

        return redirect()->away($image->url)
            ->header('Cache-Control', 'public, max-age=' . max(60, (int) config('media.signed_ttl', 60) * 60 - 300));
    }
}
