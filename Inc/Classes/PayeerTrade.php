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

    private function generateSign(string $method, string $postJson)
    {
        return hash_hmac('sha256', $method . $postJson, $this->arParams['secret']);
    }

    public function getError()
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
    public function orders($pair = 'BTC_USDT,BTC_RUB')
    {
        $post = [
            'pair' => $pair,
        ];
        $response = $this->request('orders', true, $post);

        return $response['pairs'];
    }

    /**
     * @throws Exception
     */
    public function trades($pair = 'BTC_USDT')
    {
        $post = [
            'pair' => $pair,
        ];
        $response = $this->request('trades', true, $post);

        return $response['pairs'];
    }


    public function account()
    {
        $res = $this->request([
            'method' => 'account',
        ]);

        return $res;
    }

    public function orderCreate($req = [])
    {
        $res = $this->request([
            'method' => 'order_create',
            'post' => $req,
        ]);

        return $res;
    }

    public function orderStatus($req = [])
    {
        $res = $this->request([
            'method' => 'order_status',
            'post' => $req,
        ]);

        return $res['order'];
    }

    public function myOrders($req = [])
    {
        $res = $this->request([
            'method' => 'my_orders',
            'post' => $req,
        ]);

        return $res['items'];
    }


}
