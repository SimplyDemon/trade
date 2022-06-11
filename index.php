<?php

require_once 'vendor/autoload.php';

// ID ce0ac435-354b-4365-9c25-72a5fce90a38
$payeerTradeApi = new Inc\Classes\PayeerTradeApi([
    'secret' => 'YuHnoB3hwbAtccL8',
    'id' => 'ce0ac435-354b-4365-9c25-72a5fce90a38',
]);
echo '<pre>';
try {
    var_dump($payeerTradeApi->trades());
} catch (\Exception $exception) {
    var_dump($exception->getMessage());
}
