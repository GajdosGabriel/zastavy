<?php

return [
    'company' => [
        'min' => 'The company name must be at least 2 characters.',
    ],
    'ico' => [
        'unique' => 'A company with this company ID already exists.',
        'length' => 'The company ID may have at most 8 digits.',
        'checksum' => 'The company ID fails its check digit — please check for a typo.',
    ],
    'dic' => [
        'length' => 'The tax ID must have 10 digits.',
    ],
    'ic_dic' => [
        'shape' => 'The VAT ID must be SK followed by 10 digits.',
    ],
    'vat' => [
        'exists' => 'This VAT value does not exist.',
    ],
];
