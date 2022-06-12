<?php

namespace sd\Inc\Tests;

use Exception;
use sd\Inc\Classes\PayeerTradeApi;
use PHPUnit\Framework\TestCase;

class PayeerTradeApiTest extends TestCase
{

    /**
     * @throws Exception
     */
    public function testCheckConnection()
    {
        $payeerTradeApi = new PayeerTradeApi();
        $checkConnection = $payeerTradeApi->checkConnection();

        $this->assertArrayHasKey('success', $checkConnection);
        $this->assertArrayHasKey('time', $checkConnection);
        $this->assertIsInt($checkConnection['time']);
        $this->assertIsBool($checkConnection['success']);
        $this->assertEquals(true, $checkConnection['success']);
    }
}
