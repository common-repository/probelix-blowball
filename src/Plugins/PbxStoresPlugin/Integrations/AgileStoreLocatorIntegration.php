<?php

namespace PbxBlowball\Plugins\PbxStoresPlugin\Integrations;

use Exception;
use PbxBlowball\Plugins\PbxStoresPlugin\Model\StoreModel;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Blowball Stores Plugin
 */
class AgileStoreLocatorIntegration {

	private $countries = [];

    private $wpdb;

	public function __construct()
	{
		global $wpdb;
		$this->wpdb = $wpdb;
    }

	public function loadCountries(){
		$countries = $this->wpdb->get_results("SELECT id,iso_code_2 FROM ".ASL_PREFIX."countries");
		foreach ($countries as $country){
			$this->countries[$country->iso_code_2] = $country->id;
		}
	}

	private function slugify(StoreModel $store){
		$slug = $store->display_name.'-'.$store->city;
		$slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));
		$slug = preg_replace('/-+/', '-', $slug);
		return $slug;
	}

	public function mapKey($key){
		if ($key == 'monday')
			return 'mon';
		if ($key == 'tuesday')
			return 'tue';
		if ($key == 'wednesday')
			return 'wed';
		if ($key == 'thursday')
			return 'thu';
		if ($key == 'friday')
			return 'fri';
		if ($key == 'saturday')
			return 'sat';
		if ($key == 'sunday')
			return 'sun';
	}

	public function mapValue($value){
		$result = [];
		foreach ($value as $item){
			$slot = explode(',',$item);
			if (count($slot) == 2)
				$result[] = $slot[0].' - '.$slot[1];
		}
		return $result;
	}

	private function mapStoreToSqlArray(StoreModel $store){
		if (array_key_exists(strtoupper($store->country),$this->countries))
			$countryId = $this->countries[strtoupper($store->country)];
		else
			$countryId = 82;

		$hours = json_decode($store->opening_hours,true);
		$hoursMapped = [];
		foreach($hours as $key => $value){
			$hoursMapped[$this->mapKey($key)] = $this->mapValue($value);
		}
		$hoursMapped = json_encode($hoursMapped);

		return [
			'title' => $store->display_name,
			'description' => '',
			'street' => $store->address1,
			'city' => $store->city,
			'state' => $store->state,
			'postal_code' => $store->zip,
			'country' => $countryId,
			'lat' => $store->lat/1000000,
			'lng' => $store->lon/1000000,
			'phone' => $store->phone,
			'fax' => $store->fax,
			'email' => $store->email,
			'website' => $store->url,
			'is_disabled' => $store->status == 'closed',
			'logo_id' => null,
			'marker_id' => null,
			'open_hours' => $hoursMapped,
			'description_2' => null,
			'ordr' => null,
			'brand' => null,
          	'slug' => $this->slugify($store),
			'custom' => null,
		];
	}

	public function syncStore(StoreModel $store){
		$slug = $this->slugify($store);

		$id = $this->wpdb->get_var( $this->wpdb->prepare("SELECT id FROM ".ASL_PREFIX."stores WHERE slug = %s",$slug));
		if (is_null($id)){
			$result = $this->wpdb->insert(ASL_PREFIX.'stores', $this->mapStoreToSqlArray($store));
		} else {
			$result = $this->wpdb->update(ASL_PREFIX.'stores', $this->mapStoreToSqlArray($store), array('id' => $id));
		}
		$this->wpdb->print_error();
	}
}