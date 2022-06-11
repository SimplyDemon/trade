<?php

require_once 'vendor/autoload.php';

// ID ce0ac435-354b-4365-9c25-72a5fce90a38
$payeerTradeApi = new Inc\Classes\PayeerTradeApi([
    'secret' => 'YuHnoB3hwbAtccL8',
    'id' => 'ce0ac435-354b-4365-9c25-72a5fce90a38',
]);
$pairs = ['BTC_USDT', 'BTC_RUB'];
echo '<pre>';
try {
//    var_dump($payeerTradeApi->trades());
//    var_dump($payeerTradeApi->info());
//    var_dump($payeerTradeApi->checkConnection());
//    var_dump($payeerTradeApi->ticker());
    var_dump($payeerTradeApi->orders());
} catch (\Exception $exception) {
    var_dump($exception->getMessage());
}
