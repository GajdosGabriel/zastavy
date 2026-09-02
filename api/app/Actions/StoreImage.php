<?php

namespace App\Actions;


use App\Contracts\StoreImageContract;
use App\Support\Media;

class StoreImage implements StoreImageContract
{
    public $product;
    public $input;

    function __construct($product, $input)
    {
        $this->product = $product;
        $this->input = $input;

        $this->handle();
    }

    public function handle()
    {
        if ($this->input) {

            $disk = Media::disk();

            foreach ($this->input as $image) {

                // Priečinok podľa produktu — na S3 sa tak dá objednávka/produkt
                // zmazať jedným prefixom a objekty sa nemiešajú v koreni.
                $path = $image->store('products/' . $this->product->id, $disk);

                $this->product->images()->create([
                    'path' => $path,
                    'disk' => $disk,
                    'name' => $this->product->slug,
                    'org_name' => $image->getClientOriginalName(),
                    'size' => $image->getSize(),
                    'mime' => $image->getClientMimeType(),
                ]);
            }
        };
    }
}
