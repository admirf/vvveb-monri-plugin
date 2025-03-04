<?php
/*
Name: Monri plugin
Slug: vvveb-monri-plugin
Category: tools
Url: https://github.com/admirf/vvveb-monri-plugin
Description: Monri Payment Method
Thumb: monri.svg
Author: admirf
Version: 0.1
Author url: https://github.com/admirf
Settings: /admin/?module=plugins/vvveb-monri-plugin/settings
*/

/**
 * Vvveb
 *
 * Copyright (C) 2022  Ziadin Givan
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

use Vvveb\System\Cart\Cart;
use function Vvveb\getSetting;
use Vvveb\Plugins\VvvebMonriPlugin\Payment;
use Vvveb\System\Core\Request;
use Vvveb\System\Core\View;
use Vvveb\System\Event;
use Vvveb\System\Payment as PaymentApi;
use Vvveb\System\Routes;
use Vvveb\System\Cart\Order;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

class VvvebMonriPlugin {
	private $view;

	function charge($checkoutInfo, $order_id, $site) {
		$request = Request::getInstance();
		$token   = $request->post['stripeToken'];
		$card    = $request->post['card'] ?? '';

		if ($token) {
			$domain   = $site['title'] ?? 'vvveb';
			$currency = $checkoutInfo['currency'];
			//stripe amount must be in cents so multiply with 100
			$amount = intval(round(floatval($checkoutInfo['total']), 2) * 100);

			$data = [
				'amount'      => intval($amount),
				'currency'    => $currency,
				'card'        => $token ? $token : $card,
				'description' => "Order #$order_id on $domain",
			];

			$settings = getSetting('monri', []);

			//\Stripe\Stripe::setApiKey($settings['secret_key'] ?? '');
			//$charge = \Stripe\Charge::create($data);

			if ($charge && is_array($charge)) {
				$success = ($charge['status'] == 'succeeded');
				$receipt = $charge['receipt_url'];

				$checkoutInfo['payment_data'] = json_encode($charge);
			}
		}

		return [$checkoutInfo, $order_id, $site];
	}

	function admin() {
	}

	function app() {
	}

	function __construct() {
		if (Vvveb\isEditor()) {
			return;
		}

		$this->view = View::getInstance();
		$payment    = PaymentApi::getInstance();
		$payment->registerMethod('monri', Payment::class);

		if (APP == 'admin') {
			$this->admin();
		} else {
			if (APP == 'app') {
				$this->app();
			}
		}
	}

    function monriPayment(string $authenticity_token, string $key, $data) {
        $data = [
            'amount' => 100, //minor units = 1EUR
            // unique order identifier
            'order_number' => 'random' . time(),
            'currency' => 'EUR',
            'transaction_type' => 'purchase',
            'order_info' => 'Create payment session order info',
            'scenario' => 'charge',
            'supported_payment_methods' => ['card']
        ];

        $body_as_string = json_encode($data); // use php's standard library equivalent if Json::encode is not available in your code
        $base_url = 'https://ipgtest.monri.com'; // parametrize this value
        $ch = curl_init($base_url . '/v2/payment/new');
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
            return ['status' => 'approved', 'client_secret' => Json::decode($result)['client_secret']];
        }
    }
}

$monri = new VvvebMonriPlugin();
