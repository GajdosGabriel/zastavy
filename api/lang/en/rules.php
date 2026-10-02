<?php

return [
    'company' => [
        'min' => 'The company name must be at least 2 characters.',
    ],
    'ico' => [
        'digits' => 'IČO may contain digits only.',
        'unique' => 'A company with this company ID already exists.',
        'length' => 'The company ID may have at most 8 digits.',
        'checksum' => 'The company ID fails its check digit — please check for a typo.',
    ],
    'phone' => [
        'invalid' => 'The phone number is not valid (e.g. +421 905 123 456).',
    ],
    'postcode' => [
        'invalid' => 'The postcode must have 5 digits.',
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
