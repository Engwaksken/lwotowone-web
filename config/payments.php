<?php

$types = [
    'IOTEC'=>[
        'merchant_code'=>['label'=>'IOTEC merchant code','required'=>true],
        'account_name'=>['label'=>'Merchant / business name','required'=>false],
        'payment_reference'=>['label'=>'Payment reference instructions','required'=>false],
    ],
    'Bank'=>[
        'provider'=>['label'=>'Bank name','required'=>true],
        'account_name'=>['label'=>'Account holder name','required'=>true],
        'account_number'=>['label'=>'Bank account number','required'=>true],
        'bank_branch'=>['label'=>'Bank branch','required'=>false],
        'swift_code'=>['label'=>'SWIFT / BIC code','required'=>false],
        'iban'=>['label'=>'IBAN','required'=>false],
        'routing_number'=>['label'=>'Routing / sort code','required'=>false],
        'payment_reference'=>['label'=>'Payment reference instructions','required'=>false],
    ],
    'Mobile Money'=>[
        'provider'=>['label'=>'Mobile money network / provider','required'=>true],
        'account_name'=>['label'=>'Registered recipient name','required'=>true],
        'account_number'=>['label'=>'Mobile money phone number (with country code)','required'=>true],
        'payment_reference'=>['label'=>'Payment reference instructions','required'=>false],
    ],
    'Merchant Code'=>[
        'provider'=>['label'=>'Payment network / provider','required'=>true],
        'account_name'=>['label'=>'Merchant / business name','required'=>true],
        'merchant_code'=>['label'=>'Merchant / till / paybill code','required'=>true],
        'payment_reference'=>['label'=>'Account / payment reference instructions','required'=>false],
    ],
    'Other'=>[
        'provider'=>['label'=>'Payment service name','required'=>true],
        'account_name'=>['label'=>'Recipient / account holder','required'=>false],
        'account_number'=>['label'=>'Payment destination / account identifier','required'=>false],
        'payment_url'=>['label'=>'Payment link (HTTPS)','required'=>false,'type'=>'url'],
        'payment_reference'=>['label'=>'Payment reference instructions','required'=>false],
    ],
];
foreach ($types as $type => &$fields) {
    $fields['api_base_url']=['label'=>'Provider API base URL (HTTPS)','required'=>false,'type'=>'url','private'=>true,'api_required'=>true];
    if(in_array($type,['IOTEC','Bank'])) {
        $fields['client_id']=['label'=>'API client ID','required'=>false,'private'=>true,'api_required'=>true];
        $fields['client_secret']=['label'=>'API client secret','required'=>false,'private'=>true,'secret'=>true,'api_required'=>true];
    } else {
        $fields['api_key']=['label'=>'Provider API key','required'=>false,'private'=>true,'secret'=>true,'api_required'=>true];
        $fields['api_secret']=['label'=>'Provider API secret (if required)','required'=>false,'private'=>true,'secret'=>true];
    }
    $fields['webhook_secret']=['label'=>'Webhook signing secret (if provided)','required'=>false,'private'=>true,'secret'=>true];
}
unset($fields);
return ['types'=>$types];
