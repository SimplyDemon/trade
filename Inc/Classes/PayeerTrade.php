<?php

namespace Inc\Classes;

use Exception;

class PayeerTradeApi
{
    private array $arParams = [];
    private array $arError = [];

    public function __construct($params = [])
    {
        $this->arParams = $params;
    }

    /**
     * @throws Exception
     */
    private function request(string $method, bool $isMethodPublic = false, array $post = [])
    {
        $url = 'https://payeer.com/api/trade/';
        $headers = [
            'Content-Type: application/json',
        ];

        if (!$isMethodPublic) {
            $post['ts'] = round(microtime(true) * 1000);
            $postJson = json_encode($post);
            $sign = $this->generateSign($method, $postJson);

            if (empty($sign)) {
                throw new Exception('Can\'t create sign, try again.');
            }

            $headers = array_merge($headers, [
                "API-ID: {$this->arParams['id']}",
                "API-SIGN: {$sign}",
            ]);
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url . $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if (!empty($post)) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postJson ?? json_encode($post));
        }

        $response = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($response, true);

        if ($response['success'] !== true) {
            $this->arError = $response['error'];
            throw new Exception($response['error']['code']);
        }

        return $response;
    }

    private function generateSign(string $method, string $postJson): bool|string
    {
        return hash_hmac('sha256', $method . $postJson, $this->arParams['secret']);
    }

    public function getError(): array
    {
        return $this->arError;
    }

    /**
     * @throws Exception
     */
    public function checkConnection()
    {
        return $this->request('time', true);
    }

    /**
     * @throws Exception
     */
    public function info()
    {
        return $this->request('info', true);
    }

    /**
     * @throws Exception
     */
    public function ticker()
    {
        return $this->request('ticker', true);
    }

    /**
     * @throws Exception
     */
    public function orders(array $pairs)
    {
        $pairsString = $this->convertPairsArrayToString($pairs);
        $post = [
            'pair' => $pairsString,
        ];
        $response = $this->request('orders', true, $post);

        return $response['pairs'];
    }

    /**
     * @throws Exception
     */
    public function trades(array $pairs)
    {
        $pairsString = $this->convertPairsArrayToString($pairs);
        $post = [
            'pair' => $pairsString,
        ];
        $response = $this->request('trades', true, $post);

        return $response['pairs'];
    }


    /**
     * @throws Exception
     */
    public function account()
    {
        return $this->request('account');
    }

    /**
     * @throws Exception
     */
    public function orderCreate(
        array $pairs,
        string $type,
        string $action,
        int|float $amount = 0,
        int|float $price = 0,
        int|float $value = 0,
        int|float $stopPrice = 0
    ) {
        if (!empty($amount) && !empty($value)) {
            throw new Exception('Must be selected only one option: amount or value.');
        }

        $pairsString = $this->convertPairsArrayToString($pairs);
        $post = [
            'pair' => $pairsString,
            'type' => $type,
            'action' => $action,
        ];

        if ($type === 'limit') {
            $response = $this->orderCreateLimit($post, $amount, $price);
        } elseif ($type === 'market') {
            $response = $this->orderCreateMarket($post, $amount, $value);
        } elseif ($type === 'stop_limit') {
            $response = $this->orderCreateStopLimit($post, $amount, $price, $stopPrice);
        } else {
            throw new Exception('Wrong order type.');
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    private function orderCreateLimit(array $post, int|float $amount, int|float $price)
    {
        $post = array_merge($post, [
            'amount' => $amount,
            'price' => $price,
        ]);

        return $this->request('order_create', post: $post);
    }

    /**
     * @throws Exception
     */
    private function orderCreateMarket(array $post, int|float $amount = 0, int|float $value = 0)
    {
        if (!empty($amount) && !empty($value)) {
            throw new Exception('Must be selected only one option: amount or value.');
        } else {
            if (empty($amount) && empty($value)) {
                throw new Exception('Must be selected one option: amount or value.');
            }
        }

        if (!empty($amount)) {
            $post['amount'] = $amount;
        } elseif (!empty($value)) {
            $post['value'] = $value;
        }

        return $this->request('order_create', post: $post);
    }


    /**
     * @throws Exception
     */
    private function orderCreateStopLimit(array $post, int|float $amount, int|float $price, int|float $stopPrice)
    {
        $post = array_merge($post, [
            'amount' => $amount,
            'price' => $price,
            'stopPrice' => $stopPrice,
        ]);

        return $this->request('order_create', post: $post);
    }

    /**
     * @throws Exception
     */
    public function orderStatus(int $orderId)
    {
        $post = [
            'order_id' => $orderId
        ];

        return $this->request('order_status', post: $post);
    }

    /**
     * @throws Exception
     */
    public function orderCancel(int $orderId)
    {
        $post = [
            'order_id' => $orderId
        ];

        return $this->request('order_cancel', post: $post);
    }

    /**
     * @throws Exception
     */
    public function ordersCancel(array $pairs = [], string $action = '')
    {
        $post = [];
        if (!empty($pairs)) {
            $pairsString = $this->convertPairsArrayToString($pairs);
            $post['pair'] = $pairsString;
        }
        if (!empty($action)) {
            $post['action'] = $action;
        }

        return $this->request('order_cancel', post: $post);
    }


    /**
     * @throws Exception
     */
    public function myOrders(array $pairs = [], string $action = '')
    {
        $post = [];
        if (!empty($pairs)) {
            $pairsString = $this->convertPairsArrayToString($pairs);
            $post['pair'] = $pairsString;
        }
        if (!empty($action)) {
            $post['action'] = $action;
        }

        return $this->request('my_orders', post: $post);
    }

    /**
     * @throws Exception
     */
    public function myHistory(
        array $pairs = [],
        string $action = '',
        string $status = '',
        int $dateFrom = 0,
        int $dateTo = 0,
        int $append = 0,
        int $limit = 0
    ) {
        $post = $this->generateFilterArgs(
            $pairs,
            $action,
            $status,
            $dateFrom,
            $dateTo,
            $append,
            $limit,
        );

        return $this->request('my_history', post: $post);
    }

    /**
     * @throws Exception
     */
    public function myTrades(
        array $pairs = [],
        string $action = '',
        int $dateFrom = 0,
        int $dateTo = 0,
        int $append = 0,
        int $limit = 0
    ) {
        $post = $this->generateFilterArgs(
            $pairs,
            $action,
            '',
            $dateFrom,
            $dateTo,
            $append,
            $limit,
        );

        return $this->request('my_trades', post: $post);
    }

    private function generateFilterArgs(
        array $pairs = [],
        string $action = '',
        string $status = '',
        int $dateFrom = 0,
        int $dateTo = 0,
        int $append = 0,
        int $limit = 0
    ): array {
        $post = [];
        if (!empty($pairs)) {
            $pairsString = $this->convertPairsArrayToString($pairs);
            $post['pair'] = $pairsString;
        }
        if (!empty($action)) {
            $post['action'] = $action;
        }
        if (!empty($status)) {
            $post['status'] = $status;
        }
        if (!empty($dateFrom)) {
            $post['date_from'] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $post['date_to'] = $dateTo;
        }
        if (!empty($append)) {
            $post['append'] = $append;
        }
        if (!empty($limit)) {
            $post['limit'] = $limit;
        }

        return $post;
    }

    private function convertPairsArrayToString(array $pairs): string
    {
        return implode(',', $pairs);
    }

}
