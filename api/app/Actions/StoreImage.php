<?php

namespace App\Actions;


use App\Contracts\StoreImageContract;

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

            foreach ($this->input as $image) {

                $path = $image->store('', 'public');

                $this->product->images()->create([
                    'path' => $path,
                    'name' => $this->product->slug,
                    // 'thumb' => $this->folderPath() . 'thumb/' . basename($url),
                    'org_name' => $image->getClientOriginalName(),
                    'size' => $image->getClientOriginalExtension(),
                    'mime' => $image->extension()
                ]);
            }
        };
    }
}
