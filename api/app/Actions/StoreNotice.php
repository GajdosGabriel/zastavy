<?php

namespace App\Actions;


use App\Contracts\StoreNoticeContract;


class StoreNotice implements StoreNoticeContract
{

    function __construct($order, $input)
    {
        $this->input = $input;
        $this->order = $order;
        $this->handle();
    }


    

    public function handle()
    {
        if ($this->input) {
            $this->order->notices()->create([
                'notice' => $this->input
            ]);
        }
    }
}
