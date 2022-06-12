<?php

require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$secret = $_ENV['PAYEER_API_SECRET'] ?? '';
$id = $_ENV['PAYEER_API_ID'] ?? '';

$payeerTradeApi = new Inc\Classes\PayeerTradeApi($secret, $id);
$pairs = ['BTC_USDT', 'BTC_RUB'];
echo '<pre>';
try {
//    var_dump($payeerTradeApi->trades());
//    var_dump($payeerTradeApi->info());
    var_dump($payeerTradeApi->checkConnection());
//    var_dump($payeerTradeApi->ticker());
//    var_dump($payeerTradeApi->orders($pairs));
//    var_dump($payeerTradeApi->myTrades());
//    var_dump($payeerTradeApi->ordersCancel(action: 'buy'));
} catch (\Exception $exception) {
    var_dump('Error!');
    var_dump($exception->getMessage());
}
