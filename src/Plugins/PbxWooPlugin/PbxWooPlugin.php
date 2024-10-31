<?php

namespace PbxBlowball\Plugins\PbxWooPlugin;

use PbxBlowball\PbxBlowball;
use wpdb;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Blowball WooCommerce Plugin
 */
class PbxWooPlugin {
    /**
     * @var PbxBlowball
     */
    private $core;

    /**
     * @var wpdb
     */
    private $wpdb;

	private $productData;

	/**
     * @var string
     */
    private $table;

	public function __construct(PbxBlowball $pbxBlowball)
	{
        $this->core = $pbxBlowball;
		global $wpdb;
		$this->wpdb = $wpdb;
		$this->table = $wpdb->prefix . 'pbxblowball_product';
		add_action('init', [$this, 'init'], 100);
    }

	function createPluginTables() {
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . $this->table;
		
		$sql = "CREATE TABLE $table_name (
		  post_id bigint(20) NOT NULL AUTO_INCREMENT,
		  manufacturer varchar(255) DEFAULT NULL,
		  deposit decimal(10,2) DEFAULT NULL,
		  origin_country varchar(255) DEFAULT NULL,
		  filling_qty decimal(10,2) DEFAULT NULL,
		  filling_unit varchar(255) DEFAULT NULL,
		  drained_weight decimal(10,2) DEFAULT NULL,
		  stat01 varchar(255) DEFAULT NULL,
		  stat02 varchar(255) DEFAULT NULL,
		  stat03 varchar(255) DEFAULT NULL,
		  stat04 varchar(255) DEFAULT NULL,
		  stat05 varchar(255) DEFAULT NULL,
		  stat06 varchar(255) DEFAULT NULL,
		  stat07 varchar(255) DEFAULT NULL,
		  stat08 varchar(255) DEFAULT NULL,
		  stat09 varchar(255) DEFAULT NULL,
		  stat10 varchar(255) DEFAULT NULL,
		  stat11 varchar(255) DEFAULT NULL,
		  stat12 varchar(255) DEFAULT NULL,
		  stat13 varchar(255) DEFAULT NULL,
		  stat14 varchar(255) DEFAULT NULL,
		  stat15 varchar(255) DEFAULT NULL,
		  stat16 varchar(255) DEFAULT NULL,
		  stat17 varchar(255) DEFAULT NULL,
		  stat18 varchar(255) DEFAULT NULL,
		  stat19 varchar(255) DEFAULT NULL,
		  stat20 varchar(255) DEFAULT NULL,
		  PRIMARY KEY  (post_id)
		) $charset_collate;";				
		dbDelta($sql);
		$this->core->deleteOptionValue('install_woo_plugin');
	}

    public function display($fieldName, $default = null)
    {
    	if ((!is_array($this->productData))||(!array_key_exists($fieldName, $this->productData)))
    		return $default;
    	return $this->productData[$fieldName];
    }

    public function loadProductData($postId)
    {
		$this->productData = null;
		$query = "SELECT * from $this->table WHERE post_id = %d ";
    	$query = $this->wpdb->prepare( $query, $postId);
    	$res = $this->wpdb->get_results($query, ARRAY_A);
    	if(count($res)==1)
    		$this->productData = $res[0];
    }

	public function init()
	{
		if ($this->core->getOptionValue('install_woo_plugin',false))
			$this->createPluginTables();
		add_action('woocommerce_rest_insert_product_object', [$this, 'updateCustomMeta'], 10, 2);
	}

    public function updateCustomMeta($product, $data)
    {
		if ((!is_object($data))||(!property_exists($data,'params')))
			return;
		$data = $data->get_json_params();
    	if (!(array_key_exists('pbx_art_ext', $data)))
    		return;
    	$sku  = $data['sku'];
    	$data = $data['pbx_art_ext'];

		if (array_key_exists('subject',$data)){
			$subject = $data['subject'];
			wp_set_post_terms($product->get_id(), $subject, 'product_pbx_subject',true);
		};

		$unit = null;
		$unitProd = null;

		if ($data['filling_unit']=='g'){
			$unit = 'kg';
			$unitProd = $data['filling_qty']/1000;
		} else if ($data['filling_unit']=='ml'){
			$unit = 'l';
			$unitProd = $data['filling_qty']/1000;
		} else if ($data['filling_unit']=='kg'){
			$unit = 'kg';
			$unitProd = $data['filling_qty'];
		} else if ($data['filling_unit']=='l'){
			$unit = 'l';
			$unitProd = $data['filling_qty'];
		}
		if (!is_null($unit)&&(is_null($unitProd)))
		{
			update_post_meta($product->get_id(),'_unit',$unit);
			update_post_meta($product->get_id(),'_unit_product',$unitProd);
		}

		update_post_meta($product->get_id(),'_unit',str_replace('1 ','',$data['baseprice_unit']));
		update_post_meta($product->get_id(),'_unit_base',1);
		update_post_meta($product->get_id(),'_unit_price',$data['baseprice']);


		//$data['baseprice']
		//$data['baseprice_unit']
		//$data['filling_qty']
		//$data['filling_unit']

    	$status = $this->wpdb->replace($this->table,
			[
				'post_id' 			=> $product->get_id(),
				'manufacturer' 		=> $data['manufacturer'],
				'deposit'	 		=> $data['deposit'],
				'origin_country' 	=> $data['origin_country'],
				'filling_qty'		=> $data['filling_qty'],				
				'filling_unit'		=> $data['filling_unit'],
				'drained_weight'	=> $data['drained_weight'],
				'stat01' 			=> $data['stat01'],
				'stat02' 			=> $data['stat02'],
				'stat03' 			=> $data['stat03'],
				'stat04' 			=> $data['stat04'],
				'stat05' 			=> $data['stat05'],
				'stat06' 			=> $data['stat06'],
				'stat07' 			=> $data['stat07'],
				'stat08' 			=> $data['stat08'],
				'stat09' 			=> $data['stat09'],
				'stat10' 			=> $data['stat10'],
				'stat11' 			=> $data['stat11'],
				'stat12' 			=> $data['stat12'],
				'stat13' 			=> $data['stat13'],
				'stat14' 			=> $data['stat14'],
				'stat15' 			=> $data['stat15'],
				'stat16' 			=> $data['stat16'],
				'stat17' 			=> $data['stat17'],
				'stat18' 			=> $data['stat18'],
				'stat19' 			=> $data['stat19'],
				'stat20' 			=> $data['stat20'],
			],
   			[
				'%d',
				'%s',
				'%f',
				'%s',
				'%f',
				'%s',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
    	);
    }
}