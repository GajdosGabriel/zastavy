<?php

return [
    'company' => [
        'min' => 'Firma musí obsahovať minimálne 2 znaky.',
    ],
    'ico' => [
        'unique' => 'Firma s týmto IČO už existuje.',
        'length' => 'IČO musí mať najviac 8 číslic.',
        'checksum' => 'IČO nesedí na kontrolnú číslicu — skontrolujte, či nie je preklep.',
    ],
    'dic' => [
        'length' => 'DIČ musí mať 10 číslic.',
    ],
    'ic_dic' => [
        'shape' => 'IČ DPH musí byť v tvare SK a 10 číslic.',
    ],
    'vat' => [
        'exists' => 'DPH tejto hodnoty neexistuje.',
    ],
];
