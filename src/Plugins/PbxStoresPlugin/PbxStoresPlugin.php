<?php

namespace PbxBlowball\Plugins\PbxStoresPlugin;

use PbxBlowball\PbxBlowball;
use PbxBlowball\PbxHelper;
use PbxBlowball\Plugins\PbxStoresPlugin\Integrations\AgileStoreLocatorIntegration;
use PbxBlowball\Plugins\PbxStoresPlugin\Model\StoreModel;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Blowball Stores Plugin
 */
class PbxStoresPlugin {
    /**
     * @var PbxBlowball
     */
    private $core;

    private $wpdb;

	private $storeCache = [];

	/**
     * @var string
     */
    private $table;

	public function __construct(PbxBlowball $pbxBlowball)
	{
        $this->core = $pbxBlowball;
		add_action('init', [$this, 'init'], 100);
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'pbxblowball_stores';
    }

	function createPluginTables() {
		$charset_collate = $this->wpdb->get_charset_collate();
		$sql = "CREATE TABLE " . $this->table . " (
		id int(11) NOT NULL AUTO_INCREMENT,
		`creation_date` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
		`modification_date` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
		`store_id` VARCHAR(255) NULL DEFAULT NULL,
		`store_name` VARCHAR(255) NULL DEFAULT NULL,
		`display_name` VARCHAR(255) NULL DEFAULT NULL,
		`address1` VARCHAR(255) NULL DEFAULT NULL,
		`region` VARCHAR(255) NULL DEFAULT NULL,
		`address2` VARCHAR(255) NULL DEFAULT NULL,
		`state` VARCHAR(255) NULL DEFAULT NULL,
		`country` VARCHAR(255) NULL DEFAULT NULL,
		`city` VARCHAR(255) NULL DEFAULT NULL,
		`zip` VARCHAR(255) NULL DEFAULT NULL,
		`phone` VARCHAR(255) NULL DEFAULT NULL,
		`fax` VARCHAR(255) NULL DEFAULT NULL,
		`email` VARCHAR(255) NULL DEFAULT NULL,
		`url` VARCHAR(255) NULL DEFAULT NULL,
		`opening_hours` LONGTEXT NULL DEFAULT NULL,
		`sync_date` DATETIME NULL DEFAULT NULL,
		`lon` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
		`lat` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
		`status` VARCHAR(255) NULL DEFAULT NULL,
		`metadata` TEXT NULL DEFAULT NULL,
		PRIMARY KEY (`id`) USING BTREE,
		INDEX `STORE_ID` (`store_id`) USING BTREE
		) $charset_collate;";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$this->core->deleteOptionValue('install_stores_plugin');
	}

	public function getStore($storeId){
		if (array_key_exists($storeId, $this->storeCache))
			return $this->storeCache[$storeId];
		$row = $this->wpdb->get_row("SELECT * FROM $this->table WHERE store_id = '$storeId' limit 1");
		if (is_null($row))
			return null;
		$store = new StoreModel();
		$model = get_object_vars($store);
		foreach($model as $key => $value) {
			if (property_exists($row, $key))
				$store->$key = $row->$key;
		}
		$this->storeCache[$storeId] = $store;
		return $store;
	}

	public function getActiveStores(){
		$res = $this->wpdb->get_results("SELECT * FROM $this->table WHERE status=\"active\" order by zip asc");
		$result = [];
		foreach ($res as $row){
			$store = new StoreModel();
			$model = get_object_vars($store);
			foreach($model as $key => $value) {
				if (property_exists($row, $key))
					$store->$key = $row->$key;
			}
			$result[] = $store;
		}
		return $result;
	}

	public function shortcodeStoreMetaConditionContent($atts = [], $content = null, $tag = ''){
		$atts = array_change_key_case( (array) $atts, CASE_LOWER);
		$scAtts = shortcode_atts(['store_id' => '', 'meta_key' => ''], $atts, $tag);
		$storeId = $scAtts['store_id'];
		$metaKey = $scAtts['meta_key'];
		$store = $this->getStore($storeId);
		if ((is_null($store))||(is_null($store->metadata)))
			return;
		$meta = json_decode($store->metadata,true);
		if ((!is_array($meta))||(!array_key_exists($metaKey,$meta)))
			return;
		$value = $meta[$metaKey];
		if (($value===true)||($value=='1')||(strtolower($value)=='true'))
			return $content;
		return;
	}

	public function shortcodeStoreField($atts = [], $content = null, $tag = ''){
		$atts = array_change_key_case( (array) $atts, CASE_LOWER);
		$scAtts = shortcode_atts(['store_id' => '', 'field' => ''], $atts, $tag);
		$storeId = $scAtts['store_id'];
		$field = $scAtts['field'];
		$store = $this->getStore($storeId);
		if (is_null($store))
			return;
		if (!property_exists($store,$field))
			return;
		return $store->$field;
	}

	public function shortcodeStoreMetaField($atts = [], $content = null, $tag = ''){
		$atts = array_change_key_case( (array) $atts, CASE_LOWER);
		$scAtts = shortcode_atts(['store_id' => '', 'meta_key' => ''], $atts, $tag);
		$storeId = $scAtts['store_id'];
		$metaKey = $scAtts['meta_key'];
		$store = $this->getStore($storeId);
		if ((is_null($store))||(is_null($store->metadata)))
			return;
		$meta = json_decode($store->metadata,true);
		if ((!is_array($meta))||(!array_key_exists($metaKey,$meta)))
			return;
		return $meta[$metaKey];
	}

	public function shortcodeStoreName($atts = [], $content = null, $tag = ''){
		$atts = array_change_key_case( (array) $atts, CASE_LOWER);
		$scAtts = shortcode_atts(['store_id' => ''], $atts, $tag);
		$storeId = $scAtts['store_id'];
		$store = $this->getStore($storeId);
		if (is_null($store))
			return $store;
		return $store->display_name;
	}

	public function shortcodeStoreAddress($atts = [], $content = null, $tag = ''){
		$atts = array_change_key_case( (array) $atts, CASE_LOWER);
		$scAtts = shortcode_atts(['store_id' => ''], $atts, $tag);
		$storeId = $scAtts['store_id'];
		$store = $this->getStore($storeId);
		if (is_null($store))
			return $store;
		return $store->address1.'<br>'.$store->zip.' '.$store->city.'<br>';
	}

	private function getDayString($opening, $key, $label){
		if ((is_object($opening))&&(property_exists($opening,$key)))
		{
            $splitted = $opening->$key[0];
			$splitted = explode(",",$splitted);
			if (count($splitted)<2)
				return '';
			return '<tr><td class="pbx-store-opening-hours">'.$label.'</td><td class="layout-table-value">'.$splitted[0].' - '.$splitted[1].'</td></tr>';
		}
        return '';
	}

	public function shortcodeStoreOpening($atts = [], $content = null, $tag = ''){
		$atts = array_change_key_case( (array) $atts, CASE_LOWER);
		$scAtts = shortcode_atts(['store_id' => ''], $atts, $tag);
		$storeId = $scAtts['store_id'];
		$store = $this->getStore($storeId);
		if (is_null($store))
			return $store;
		$opening = json_decode($store->opening_hours);
		$o = '<table class="layout-table">';
		$o .= $this->getDayString($opening,'monday','Montag');
		$o .= $this->getDayString($opening,'tuesday','Dienstag');
		$o .= $this->getDayString($opening,'wednesday','Mittwoch');
		$o .= $this->getDayString($opening,'thursday','Donnerstag');
		$o .= $this->getDayString($opening,'friday','Freitag');
		$o .= $this->getDayString($opening,'saturday','Samstag');
		$o .= $this->getDayString($opening,'sunday','Sonntag');
		$o .= '</table>';
		return $o;
		//return $store->address1.'<br>'.$store->zip.' '.$store->city.'<br>';
	}

	public function init()
	{
		if ($this->core->getOptionValue('install_stores_plugin',false))
			$this->createPluginTables();
		add_shortcode('pbxbb-storename', [$this, 'shortcodeStoreName']);
		add_shortcode('pbxbb-storeaddress', [$this, 'shortcodeStoreAddress']);
		add_shortcode('pbxbb-storeopening', [$this, 'shortcodeStoreOpening']);
		add_shortcode('pbxbb-storemeta-content', [$this, 'shortcodeStoreMetaConditionContent']);
		add_shortcode('pbxbb-store-field', [$this, 'shortcodeStoreField']);
		add_shortcode('pbxbb-storemeta-field', [$this, 'shortcodeStoreMetaField']);
		add_action( 'rest_api_init', function () {
			register_rest_route('pbx-blowball/v1', '/stores/(?P<store_id>[a-zA-Z0-9-]+)', [
				'methods' => 'POST',
				'callback' => [$this, 'updateStore'],
				'permission_callback' => [$this, 'checkPermissions']
			]);
			register_rest_route( 'pbx-blowball/v1', '/stores', [
				'methods' => 'POST',
				'callback' => [$this, 'bulkUpdateStores'],
				'permission_callback' => [$this, 'checkPermissions']
			]);
		});
	}

	function checkPermissions(WP_REST_Request $request){
		$authToken = $request->get_header('Authorization');
		if (is_null($authToken))
			$authToken = $request->get_header('PbxAuthorization');
		$authToken = explode(',',$authToken);
		foreach ($authToken as $token) {
			if (preg_match('/Bearer\s(\S+)/', $token, $matches))
				$bearerToken = $matches[1];
		}
		$handshakeToken = $this->core->getOptionValue('handshake_token','');
		if ((!empty($handshakeToken))&&($handshakeToken == $bearerToken))
			return true;
		return false;
	}

	private function mapStoreToSqlArray(StoreModel $store){
		return array(
			'store_id' => $store->store_id,
			'store_name' => $store->store_name,
			'display_name' => $store->display_name,
			'address1' => $store->address1,
			'region' => $store->region,
			'address2' => $store->address2,
			'state' => $store->state,
			'country' => $store->country,
			'city' => $store->city,
			'zip' => $store->zip,
			'phone' => $store->phone,
			'fax' => $store->fax,
			'email' => $store->email,
			'url' => $store->url,
			'opening_hours' => $store->opening_hours,
			'lon' => $store->lon,
			'lat' => $store->lat,
			'status' => $store->status,
			'metadata' => json_encode($store->metadata),
		);
	}

	function syncAgileStorePlugin(){
		$asl = new AgileStoreLocatorIntegration();
		$asl->loadCountries();
		$dbStores = $this->wpdb->get_results("SELECT * FROM $this->table");
		foreach($dbStores as $dbStore){
			$store = new StoreModel();
			$model = get_object_vars($store);
			foreach($model as $key => $value) {
				if (property_exists($dbStore, $key))
					$store->$key = $dbStore->$key;
			}
			$asl->syncStore($store);
		}
	}

	function bulkUpdateStores(WP_REST_Request $request) {
		$format = array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%s','%s');
		$parameters = $request->get_json_params();
		if (!array_key_exists('list',$parameters)){
			return new WP_Error( 'invalid request', 'Missing list element', array( 'status' => 400 ) );
		}
		$list = $parameters['list'];
		foreach($list as $listItem){
			$store = new StoreModel();
			$model = get_object_vars($store);
			foreach($model as $key => $value) {
				if (array_key_exists($key, $listItem))
					$store->$key = $listItem[$key];
			}
			$id = $this->wpdb->get_var( $this->wpdb->prepare("SELECT id FROM $this->table WHERE store_id = %s",$store->store_id));
			if (is_null($id)){
				$result = $this->wpdb->insert($this->table, $this->mapStoreToSqlArray($store), $format);
			} else {
				$result = $this->wpdb->update($this->table, $this->mapStoreToSqlArray($store), array('store_id' => $store->store_id), $format);
			}
		}

		if (PbxHelper::isAgileStoreFinderActive())
			$this->syncAgileStorePlugin();

		$data = array( 'success' => true );
		$response = new WP_REST_Response( $data );
		$response->set_status( 201 );
		return $response;
	}

	function updateStore(WP_REST_Request $request) {
		$parameters = $request->get_json_params();
		$store = new StoreModel();
		$model = get_object_vars($store);
		foreach($model as $key => $value) {
			if (array_key_exists($key, $parameters))
				$store->$key = $parameters[$key];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'pbxblowball_stores';
		$format = array('%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%d','%s','%s');
		$id = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $table_name WHERE store_id = %s",$store->store_id));
		if (is_null($id)){
			$result = $wpdb->insert($table_name, $this->mapStoreToSqlArray($store), $format);
		} else {
			$result = $wpdb->update($table_name, $this->mapStoreToSqlArray($store), array('store_id' => $store->store_id), $format);
		}

		if (PbxHelper::isAgileStoreFinderActive())
			$this->syncAgileStorePlugin();

		$data = array( 'success' => true );
		$response = new WP_REST_Response( $data );
		$response->set_status( 201 );
		return $response;
	}
}