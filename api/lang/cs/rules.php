<?php

return [
    'company' => [
        'min' => 'Firma musí obsahovat minimálně 2 znaky.',
    ],
    'ico' => [
        'unique' => 'Firma s tímto IČO už existuje.',
        'length' => 'IČO musí mít nejvýše 8 číslic.',
        'checksum' => 'IČO nesedí na kontrolní číslici — zkontrolujte, zda není překlep.',
    ],
    'dic' => [
        'length' => 'DIČ musí mít 10 číslic.',
    ],
    'ic_dic' => [
        'shape' => 'DIČ plátce DPH musí být ve tvaru SK a 10 číslic.',
    ],
    'vat' => [
        'exists' => 'DPH této hodnoty neexistuje.',
    ],
];
