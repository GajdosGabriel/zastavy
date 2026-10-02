<?php

return [
    'company' => [
        'min' => 'Firma musí obsahovat minimálně 2 znaky.',
    ],
    'ico' => [
        'digits' => 'IČO smie obsahovať len číslice.',
        'unique' => 'Firma s tímto IČO už existuje.',
        'length' => 'IČO musí mít nejvýše 8 číslic.',
        'checksum' => 'IČO nesedí na kontrolní číslici — zkontrolujte, zda není překlep.',
    ],
    'phone' => [
        'invalid' => 'Telefon není v platném tvaru (např. +421 905 123 456).',
    ],
    'postcode' => [
        'invalid' => 'PSČ musí mít 5 číslic.',
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
