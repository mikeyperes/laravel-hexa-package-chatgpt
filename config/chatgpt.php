<?php
return [
    'version' => '3.1.8',

    'models' => [
        ['id' => 'gpt-4o',         'name' => 'GPT-4o',         'type' => 'api', 'price_input' => 2.5,  'price_output' => 10.0],
        ['id' => 'gpt-4-turbo',    'name' => 'GPT-4 Turbo',    'type' => 'api', 'price_input' => 10.0, 'price_output' => 30.0],
        ['id' => 'gpt-4',          'name' => 'GPT-4',          'type' => 'api', 'price_input' => 30.0, 'price_output' => 60.0],
        ['id' => 'gpt-3.5-turbo',  'name' => 'GPT-3.5 Turbo',  'type' => 'api', 'price_input' => 0.5,  'price_output' => 1.5],
    ],
];
