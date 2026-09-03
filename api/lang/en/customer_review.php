<?php

/*
|--------------------------------------------------------------------------
| Customer data review
|--------------------------------------------------------------------------
|
| Findings are stored in `customer_reviews.issues` as a key plus parameters,
| never as a finished sentence — see the Slovak file for the reasoning.
*/

return [
    'fields' => [
        'name' => 'Contact person',
        'company' => 'Company name',
        'email' => 'E-mail',
        'phone' => 'Phone',
        'street' => 'Street and number',
        'postcode' => 'Postcode',
        'city' => 'City',
        'ico' => 'Company ID',
        'dic' => 'Tax ID',
        'ic_dic' => 'VAT ID',
    ],

    'severities' => [
        'error' => 'Error',
        'warning' => 'Warning',
        'notice' => 'Notice',
    ],

    'sources' => [
        'rule' => 'rule',
        'registry' => 'registry',
        'ai' => 'AI',
    ],

    'issues' => [
        'blank_string' => 'The field holds an empty string rather than an empty value, so "not filled in" filters will not find it.',
        'blank_required' => 'The field is empty even though it is required.',
        'blank_placeholder' => 'Instead of an empty value the field holds the placeholder ":value", which will be printed on the invoice.',
        'whitespace' => 'The value has extra spaces or a line break.',

        'ico_no_digits' => 'The company ID contains no digits at all.',
        'ico_too_long' => 'The company ID has more than 8 digits.',
        'ico_format' => 'The company ID is not in the form the registry uses — 8 digits with no spaces or slashes.',
        'ico_with_text' => 'The company ID field contains text besides the number.',
        'ico_checksum' => 'The company ID fails its check digit — it almost certainly holds a typo and does not exist in the registry.',

        'dic_missing' => 'The customer has a company ID but no tax ID — the invoice will be missing a value the registry knows.',
        'dic_format' => 'The tax ID contains spaces or an SK prefix.',
        'dic_with_text' => 'The tax ID field contains text besides the number.',
        'dic_length' => 'The tax ID does not have 10 digits.',
        'dic_checksum' => 'The tax ID fails its checksum — it probably holds a typo. Verify it in the registry.',

        'ic_dic_format' => 'The VAT ID has spaces or lowercase letters.',
        'ic_dic_shape' => 'The VAT ID is not in the form SK followed by 10 digits.',
        'ic_dic_mismatch' => 'The VAT ID does not match the tax ID — expected SK:dic.',

        'phone_unreadable' => 'The phone number cannot be read as a Slovak or international number.',
        'phone_format' => 'The phone number is not in international form — such a value falls apart in exports and bulk mail.',

        'email_case' => 'The e-mail contains uppercase letters, which creates duplicates when matching an existing contact.',
        'email_invalid' => 'The e-mail is not a valid address — the order confirmation will not be delivered.',

        'postcode_format' => 'The postcode is not stored as 5 digits without spaces.',
        'postcode_mixed' => 'Another value is stuck to the postcode — check what belongs in the field.',
        'postcode_length' => 'The postcode does not have 5 digits.',

        'company_is_number' => 'The company name is a number, not a name — this looks like a company ID typed into the wrong field.',
        'company_has_address' => 'The company name also contains the address, so it will be printed twice on the invoice.',

        'street_number_only' => 'The street holds only a number. That is correct in villages without street names, but the envelope has no street.',

        'registry_tax_mismatch' => 'Does not match the business registry — check whether the company ID belongs to this entity.',
        'registry_company_differs' => 'The company name differs from the official wording in the registry.',
    ],

    'summary' => [
        'clean' => 'The data is fine.',
        'found' => 'Found: :list.',
        'error' => '{1} 1 error|[2,*] :count errors',
        'warning' => '{1} 1 warning|[2,*] :count warnings',
        'notice' => '{1} 1 notice|[2,*] :count notices',
    ],

    'messages' => [
        'disabled' => 'The review is disabled by configuration.',
        'no_review' => 'This customer has no review.',
        'run_failed' => 'The review could not be completed: :error',
        'nothing_reverted' => 'Nothing was reverted — someone changed the value in the meantime.',
    ],

    'mail' => [
        'subject' => 'Customer review — :fixed fixed, :open to look at',
        'greeting' => 'Hello,',
        'intro' => 'The review went through :total customers. Here is what came out of it.',
        'fixed_heading' => '**Fixed automatically** (formatting, empty values, tax IDs filled in from the registry):',
        'fixed_line' => '• :customer — :field: ":from" → ":to"',
        'open_heading' => '**To look at** — these changes would alter the meaning of a value, so we leave them to you:',
        'open_line' => '• :customer (score :score) — :message',
        'more' => '…and :count more customers.',
        'action' => 'Open the customer list',
        'outro' => 'Fixes can be reverted — the original value is in this e-mail and in the customer detail.',
        'empty' => 'empty',
    ],

    'duplicates' => [
        'reason_ico' => 'Same company ID :ico',
        'reason_name' => 'Same name and city, no company ID',
        'merged_note' => 'Merged into customer #:id.',
        'nothing_to_merge' => 'Nothing to merge.',
        'merged' => 'Merged :customers records, moved :orders orders.',
    ],
];
