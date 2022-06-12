<?php

namespace Inc\Classes;

use Exception;
use JetBrains\PhpStorm\Pure;

class PayeerTradeApi
{
    /**
     * Parameters are required for private api.
     *
     * @param string $secret
     * @param string $id
     */
    public function __construct(
        private string $secret = '',
        private string $id = '',
    ) {
    }

    /**
     * Main method for requests to api.
     *
     * @param string $method // Method name
     * @param bool $isMethodPublic // Public methods not require secret and key
     * @param array $post // Method parameters
     * @return mixed
     * @throws Exception
     */
    private function request(string $method, bool $isMethodPublic = false, array $post = []): mixed
    {
        $url = 'https://payeer.com/api/trade/';
        $headers = [
            'Content-Type: application/json',
        ];

        if (!$isMethodPublic) {
            if (empty($this->secret) || empty($this->id)) {
                throw new Exception('For use private methods secret and id must be filled.');
            }

            /*
             * The request will be processed if it reaches api server within 60 seconds,
             * 'ts' parameter is required for private api requests.
             */
            $post['ts'] = round(microtime(true) * 1000);
            $postJson = json_encode($post);
            $sign = $this->generateSign($method, $postJson);

            if (empty($sign)) {
                throw new Exception('Can\'t create sign, try again.');
            }

            $headers = array_merge($headers, [
                "API-ID: {$this->id}",
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

        if ($response['success'] !== true && !empty($response['error'])) {
            $error = $response['error'];
            if (!empty($response['parameter'])) {
                $error .= " {$response['parameter']}";
            }

            throw new Exception($error);
        }

        return $response;
    }

    /**
     * Sign is required for every private api request
     *
     * @param string $method // Method name
     * @param string $postJson // Method parameters as json string
     * @return bool|string
     */
    private function generateSign(string $method, string $postJson): bool|string
    {
        return hash_hmac('sha256', $method . $postJson, $this->secret);
    }

    /**
     * Test connection
     *
     * @return array
     * @throws Exception
     */
    public function checkConnection(): array
    {
        return $this->request('time', true);
    }

    /**
     * Getting limits, available pairs and their parameters.
     *
     * @param array $pairs // If empty, return info about all pairs
     * @return array
     * @throws Exception
     */
    public function info(array $pairs = []): array
    {
        $post = $this->generatePostFromPairsArray($pairs);

        return $this->request('info', true, $post);
    }

    /**
     * Getting price statistics and their changes over the last 24 hours.
     *
     * @param array $pairs // If empty, return info about all pairs
     * @return array
     * @throws Exception
     */
    public function ticker(array $pairs = []): array
    {
        $post = $this->generatePostFromPairsArray($pairs);

        return $this->request('ticker', true, $post);
    }

    /**
     * Getting available orders for the specified pairs.
     *
     * @param array $pairs
     * @return array
     * @throws Exception
     */
    public function orders(array $pairs): array
    {
        $post = $this->generatePostFromPairsArray($pairs);
        $response = $this->request('orders', true, $post);

        return $response['pairs'];
    }

    /**
     * Getting the history of transactions for the specified pairs.
     *
     * @param array $pairs
     * @return array
     * @throws Exception
     */
    public function trades(array $pairs): array
    {
        $post = $this->generatePostFromPairsArray($pairs);
        $response = $this->request('trades', true, $post);

        return $response['pairs'];
    }


    /**
     * Getting the user's balance.
     *
     * @return array
     * @throws Exception
     */
    public function account(): array
    {
        return $this->request('account');
    }

    /**
     * Creating an order.
     *
     * @param array $pairs
     * @param string $type // Available types is 'limit', 'market', 'stop_limit'
     * @param string $action // Available types is 'buy', 'sell'
     * @param int|float $amount
     * @param int|float $price
     * @param int|float $value
     * @param int|float $stopPrice
     * @return array
     * @throws Exception
     */
    public function orderCreate(
        array $pairs,
        string $type,
        string $action,
        int|float $amount = 0,
        int|float $price = 0,
        int|float $value = 0,
        int|float $stopPrice = 0,
    ): array {
        if (!empty($amount) && !empty($value)) {
            throw new Exception('Must be selected only one option: amount or value.');
        }

        $pairsString = $this->convertPairsArrayToString($pairs);

        /* Create post with parameters required for all types */
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
     * Limit type.
     *
     * @param array $post
     * @param int|float $amount
     * @param int|float $price
     * @return array
     * @throws Exception
     */
    private function orderCreateLimit(array $post, int|float $amount, int|float $price): array
    {
        $post = array_merge($post, [
            'amount' => $amount,
            'price' => $price,
        ]);

        return $this->request('order_create', post: $post);
    }

    /**
     * Market type.
     * It is possible to specify one of two parameters for creating a market order (amount or value).
     *
     * @param array $post
     * @param int|float $amount
     * @param int|float $value
     * @return array
     * @throws Exception
     */
    private function orderCreateMarket(array $post, int|float $amount = 0, int|float $value = 0): array
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
     * Stop limit type.
     *
     * @param array $post
     * @param int|float $amount
     * @param int|float $price
     * @param int|float $stopPrice
     * @return array
     * @throws Exception
     */
    private function orderCreateStopLimit(array $post, int|float $amount, int|float $price, int|float $stopPrice): array
    {
        $post = array_merge($post, [
            'amount' => $amount,
            'price' => $price,
            'stopPrice' => $stopPrice,
        ]);

        return $this->request('order_create', post: $post);
    }

    /**
     * Getting detailed information about your order by id.
     *
     * @param int $orderId
     * @return array
     * @throws Exception
     */
    public function orderStatus(int $orderId): array
    {
        $post = [
            'order_id' => $orderId
        ];

        return $this->request('order_status', post: $post);
    }

    /**
     * Cancellation of your order by id.
     *
     * @param int $orderId
     * @return array
     * @throws Exception
     */
    public function orderCancel(int $orderId): array
    {
        $post = [
            'order_id' => $orderId
        ];

        return $this->request('order_cancel', post: $post);
    }

    /**
     * Cancellation all/partially of orders.
     * If not parameters is given - cancel all orders.
     *
     * @param array $pairs // Cancel all orders with pairs.
     * @param string $action // Cancel all orders with action ('buy' or 'sell').
     * @return array
     * @throws Exception
     */
    public function ordersCancel(array $pairs = [], string $action = ''): array
    {
        $post = [];
        if (!empty($pairs)) {
            $pairsString = $this->convertPairsArrayToString($pairs);
            $post['pair'] = $pairsString;
        }
        if (!empty($action)) {
            $post['action'] = $action;
        }

        return $this->request('orders_cancel', post: $post);
    }


    /**
     * Getting your open orders with the ability to filtering.
     *
     * @param array $pairs
     * @param string $action // Available types is 'buy', 'sell'
     * @return array
     * @throws Exception
     */
    public function myOrders(array $pairs = [], string $action = ''): array
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
     * Getting the history of your orders with the possibility of filtering and page-by-page loading.
     *
     * @param array $pairs
     * @param string $action // Available types is 'buy', 'sell'
     * @param string $status // Available types is 'success', 'processing', 'waiting', 'canceled'
     * @param int $dateFrom // Timestamp of the beginning of the filtering period
     * @param int $dateTo // Timestamp of the end of the filtering period, the filtering period should not exceed 32 days
     * @param int $append // Last order id for page navigation
     * @param int $limit // The count of returned items
     * @return array
     * @throws Exception
     */
    public function myHistory(
        array $pairs = [],
        string $action = '',
        string $status = '',
        int $dateFrom = 0,
        int $dateTo = 0,
        int $append = 0,
        int $limit = 0,
    ): array {
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
     * Getting your trades with the possibility of filtering and page-by-page loading.
     *
     * @param array $pairs
     * @param string $action // Available types is 'buy', 'sell'
     * @param int $dateFrom // Timestamp of the beginning of the filtering period
     * @param int $dateTo // Timestamp of the end of the filtering period, the filtering period should not exceed 32 days
     * @param int $append // Last order id for page navigation
     * @param int $limit // The count of returned items
     * @return array
     * @throws Exception
     */
    public function myTrades(
        array $pairs = [],
        string $action = '',
        int $dateFrom = 0,
        int $dateTo = 0,
        int $append = 0,
        int $limit = 0,
    ): array {
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

    /**
     * Trades and history has almost same filter args, use that method for avoid code duplication
     */
    #[Pure] private function generateFilterArgs(
        array $pairs = [],
        string $action = '',
        string $status = '',
        int $dateFrom = 0,
        int $dateTo = 0,
        int $append = 0,
        int $limit = 0,
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

    /**
     * Convert pairs to string.
     *
     * @param array $pairs
     * @return string
     */
    private function convertPairsArrayToString(array $pairs): string
    {
        return implode(',', $pairs);
    }

    /**
     * Few of request has only one parameter for api, that method generate post from pairs.
     *
     * @param array $pairs
     * @return array
     */
    #[Pure] private function generatePostFromPairsArray(array $pairs): array
    {
        if (empty($pairs)) {
            return [];
        }

        return [
            'pair' => $this->convertPairsArrayToString($pairs),
        ];
    }
}
