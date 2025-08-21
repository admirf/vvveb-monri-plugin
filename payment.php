<?php

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

namespace Vvveb\Plugins\VvvebMonriPluginMain;

use function Vvveb\getLanguageId;
use function Vvveb\getMultiSettingContent;
use function Vvveb\getSetting;
use function Vvveb\session as sess;
use Vvveb\System\Core\Request;
use Vvveb\System\PaymentMethod;
use Vvveb\System\Cart\Cart;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

class Payment extends PaymentMethod {
	private $namespace = 'monri';

	public function getMethod($checkoutInfo = []) {
		$method_data = [
			'name'        => $this->namespace,
			'title'       => 'Monri',
			'description' => '',
			'cost'        => 0,
			'tax'         => 1,
			'region_id'   => 1,
		];

		$settings = \Vvveb\getSetting($this->namespace, null, null, SITE_ID);
		$language_id = getLanguageId();
		$lang        = getMultiSettingContent(SITE_ID, $this->namespace, [], $language_id) ?? [];

		foreach ($lang as $meta) {
			$desc[$meta['language_id']][$meta['key']] = $meta['value'];
		}

		$method_data['description'] = $desc[$language_id]['desc']['message'] ?? $method_data['description'];
		$method_data['title']       = $desc[$language_id]['desc']['name'] ?? $method_data['title'];

        if (empty($checkoutInfo)) {
            $method_data['render'] = '';

            return $method_data;
        }

		$template = 'plugins/vvveb-monri-plugin-main/monri.html';

        $cart = Cart::getInstance();

        $description = '';

        foreach ($cart->getAll() as $product) {
            $description .= $product['name'] . '-' . $product['sku'] . '; ';
        }

        if (! $cart->getGrandTotal()) {
            return $method_data;
        }

        $clientSecret = sess('monri_client_secret');

        if (! $clientSecret) {
            $payment = $this->monriPayment($settings['url'] ?? 'https://ipgtest.monri.com', $settings['secret'], $settings['merchant_key'], [
                'amount' => intval(round(floatval($cart->getGrandTotal()), 2) * 100),
                'currency' => sess('currency') ?? 'BAM',
                'order_id' => 'new_order' . time(),
            ]);

            if (! empty($payment['client_secret'])) {
                $clientSecret = $payment['client_secret'];
                sess([
                    'monri_client_secret' => $clientSecret,
                ]);
            }
        }

        if ($clientSecret) {
            $form = file_get_contents(DIR_PUBLIC . $template);

            $form = str_replace('<authenticity-token>', $settings['secret'], $form);
            $form = str_replace('<client-secret>', $clientSecret, $form);
            $form = str_replace('<order-info>', $description, $form);
        } else {
            $form = file_get_contents(DIR_PUBLIC . 'plugins/vvveb-monri-plugin-main/monri-declined.html');
        }

		if (APP == 'app') {
			// $form                       = file_get_contents(DIR_PUBLIC . $template);
			$method_data['render']      = $form;
		}

		return $method_data;
	}

	public function init() {
	}

	public function setMethod() {
	}

    public function authorize()
    {
        sess([
            'monri_client_secret' => null,
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
            return ['status' => 'approved', 'client_secret' => json_decode($result, true)['client_secret']];
        }
    }
}
