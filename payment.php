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

        if (! $cart->getGrandTotal()) {
            return $method_data;
        }

        $form = file_get_contents(DIR_PUBLIC . $template);
        $form = str_replace('<authenticity-token>', $settings['secret'], $form);

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

    }
}
