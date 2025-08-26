<?php

namespace Vvveb\Plugins\VvvebMonriPluginMain\Controller;

use function Vvveb\__;
use Vvveb\Controller\Base;
use function Vvveb\getMultiSettingContent;
use function Vvveb\setMultiSetting;
use function Vvveb\setMultiSettingContent;
use Vvveb\Sql\Payment_StatusSQL;
use Vvveb\Sql\Region_GroupSQL;
use Vvveb\Sql\Tax_TypeSQL;
use Vvveb\System\CacheManager;

class Settings extends Base {
	private $namespace = 'monri';

	function save() {
		//$validator = new Validator(["plugins.{$this->namespace}.settings"]);
		$settings  = $this->request->post['settings'] ?? false;
		$errors    = [];

		if ($settings /*&&
			($errors = $validator->validate($settings)) === true*/) {
			//$settings              = $validator->filter($settings);
			$results               =  setMultiSetting($this->namespace, $settings);

			$lang     = $this->request->post['lang'] ?? [];
			$meta     = [];

			foreach ($lang as $key => $values) {
				foreach ($values as $language_id => $value) {
					$meta[] = ['namespace' => $this->namespace, 'key' => $key, 'value' => $value, 'language_id' => $language_id];
				}
			}

			setMultiSettingContent($this->global['site_id'], $meta);
			CacheManager::delete();
			$this->view->success[] = __('Settings saved!');
		} else {
			$this->view->errors = $errors;
		}

		return $this->index();
	}

	function index() {
		$geoRegion = new Region_GroupSQL();
		$regions	  = $geoRegion->getAll($this->global);

		$region_group_id = [];

		foreach ($regions['region_group'] as $region_group) {
			$region_group_id[$region_group['region_group_id']] = $region_group['name'];
		}

		$this->view->region_group = $region_group_id;

		$taxTypes = new Tax_TypeSQL();
		$taxes    = $taxTypes->getAll($this->global);

		$tax_type_id = [];

		foreach ($taxes['tax_type'] as $tax) {
			$tax_type_id[$tax['tax_type_id']] = $tax['name'];
		}

		$paymentStatus  = new Payment_StatusSQL();
		$statuses       = $paymentStatus->getAll($this->global);

		foreach ($statuses['payment_status'] as $status) {
			$payment_status_id[$status['payment_status_id']] = $status['name'];
		}

		$desc = getMultiSettingContent($this->global['site_id'], $this->namespace) ?? [];

		foreach ($desc as $meta) {
			$this->view->lang[$meta['language_id']][$meta['key']] = $meta['value'];
		}

		$this->view->tax_type        = $tax_type_id;
		$this->view->payment_status  = $payment_status_id;
		$settings                    = \Vvveb\getSetting($this->namespace, null,null, $this->global['site_id']);

		$this->view->set($settings);
	}
}
