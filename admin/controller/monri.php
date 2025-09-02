<?php

namespace Vvveb\Plugins\VvvebMonriPluginMain\Controller;

use function Vvveb\__;
use Vvveb\Controller\Base;
use function Vvveb\session as sess;
use Vvveb\System\Cart\Cart;

class Monri extends Base
{
    private $namespace = 'monri';

    public function payment()
    {
        $cart = Cart::getInstance();

        $description = '';

        foreach ($cart->getAll() as $product) {
            $description .= $product['name'] . '-' . $product['sku'] . '; ';
        }

        if (! $cart->getGrandTotal()) {
            return json_encode([
                'status' => 'declined',
            ]);
        }

        $payment = $this->monriPayment($settings['url'] ?? 'https://ipgtest.monri.com', $settings['secret'], $settings['merchant_key'], [
            'amount' => intval(round(floatval($cart->getGrandTotal()), 2) * 100),
            'currency' => sess('currency') ?? 'BAM',
            'order_id' => 'new_order' . time(),
        ]);

        if (! empty($payment['client_secret'])) {
            $clientSecret = $payment['client_secret'];

            return json_encode([
                'status' => 'approved',
                'client_secret' => $clientSecret,
                'description' => $description,
            ]);
        }

        return json_encode([
            'status' => 'declined',
        ]);
    }

    protected function monriPayment(string $url, string $authenticity_token, string $key, $data) {
        $data = [
            'amount' => $data['amount'], //minor units = 1EUR
            // unique order identifier
            'order_number' => $data['order_id'],
            'currency' => $data['currency'],
            'transaction_type' => 'purchase',
            'order_info' => 'Create payment session order info',
            'scenario' => 'charge',
            'supported_payment_methods' => ['card']
        ];

        $body_as_string = json_encode($data); // use php's standard library equivalent if Json::encode is not available in your code
        $ch = curl_init($url . '/v2/payment/new');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body_as_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $timestamp = time();
        $digest = hash('sha512', $key . $timestamp .$authenticity_token. $body_as_string);
        $authorization = "WP3-v2 $authenticity_token $timestamp $digest";

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body_as_string),
                'Authorization: ' . $authorization
            )
        );

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return ['client_secret' => null, 'status' => 'declined', 'error' => curl_error($ch)];
        } else {
            curl_close($ch);
            $res = json_decode($result, true);

            if (isset($res['client_secret'])) {
                return ['status' => 'approved', 'client_secret' => json_decode($result, true)['client_secret']];
            }

            return ['client_secret' => null, 'status' => 'declined', 'error' => curl_error($ch)];
        }
    }
}