<?php

namespace PbxBlowball\Settings;

use Exception;
use PbxBlowball\Client\PbxBlowballClient;
use PbxBlowball\Notifications\Notifier;
use PbxBlowball\PbxBlowball;
use PbxBlowball\PbxHelper;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Manage the Blowball Settings Page
 */
class Settings {
	const OPTIONNAME = 'pbx_blowball';
	const PAGEID = 'pbx-bb-settings';

	/**
	 * @var array<string, mixed>
	 */
	private $options;

	/**
	 * @var Notifier
	 */
	private $notifier;

	/**
	 * @var PbxBlowballClient
	 */
	private $blowballClient;

	public function __construct(Notifier $notifier) {
		$this->blowballClient = new PbxBlowballClient();
		$this->notifier = $notifier;
		ob_start();
		add_action('admin_menu', [$this, 'addPluginPage']);
		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts'], 15 );
	}

	public function addPluginPage():void {
		add_options_page('Settings Admin', 'Probelix Blowball', 'manage_options', self::PAGEID, [$this, 'getSettingsPage']);
	}

	public static function enqueueAdminScripts():void {
		if (!defined('PBX_BLOWBALL_PLUGIN_FILE'))
			return;
		wp_enqueue_style('pbx_blowball_admin_css', plugins_url( '/assets/css/backend.css', PBX_BLOWBALL_PLUGIN_FILE), [], PbxBlowball::PLUGIN_VERSION);
	}

	/**
	 * @param array<int, array<string, mixed>> $items
	 * @param string $pageUrl
	 * @param string $currentTab
	 * @return array<string, mixed>
	 */
	private function getMenuItemData($items, $pageUrl, $currentTab = null){
		$i = 0;
		$result = ['items' => []];
		$current = null;
		foreach ($items as $item ) {
			$i++;
			$slug = esc_attr($item['slug']);
			$title = esc_attr($item['title']);
			$classes = [$slug];
			$itemData = [];

			if(!is_null($currentTab)){
				if ($currentTab==$slug){
					$classes[] = 'current';
					$current = $item;
				}
			} elseif ($i == 1){
				$classes[] = 'current';
				$current = $item;
			}

			if (isset($item['new']) && $item['new'])
				$classes[] = 'new';

			if (isset($item['info']) && $item['info'])
				$classes[] = 'info';

			$classString = implode( ' ', $classes );
			$itemData['callback'] = isset($item['callback']) ? $item['callback'] : null;
			$itemData['sanitize'] = isset($item['sanitize']) ? $item['sanitize'] : null;
			$itemData['class'] = $classString;
			$itemData['link'] = $pageUrl.$slug;
			$itemData['title'] = $title;
			$result['items'][] = $itemData;
		}
		$result['current'] = $current;
		return $result;
	}

	public function getSettingsPage():void {
		$this->options = get_option(self::OPTIONNAME);
		$currentTab = null;
		$pageUrl = get_admin_url().'admin.php?page='.self::PAGEID;
		if(isset($_GET['tab']))
			$currentTab = sanitize_key($_GET['tab']);
		$menuItems = $this->getSidebarMenu();

		$menuItemData = $this->getMenuItemData($menuItems, $pageUrl.'&tab=', $currentTab);
		$current = $menuItemData['current'];

		echo '<div class="wrap"><div class="blowball-settings"><div class="pbxbb-sidebar-menu"><div class="logo"></div><ul>';
		foreach ($menuItemData['items'] as $item){
			echo '<li class="'.esc_attr($item['class']).'"><a href="'.esc_url($item['link']).'" title="'.esc_html($item['title']).'">'.esc_html($item['title']).'</a></li>';
		}
		echo '</ul><div class="pbxbb-footer-menu">'.esc_html(__( sprintf( 'Version %s', PbxBlowball::PLUGIN_VERSION ), PbxBlowball::PLUGIN_NAME )).'</div></div>';
		echo '<div class="pbxbb-main-section">';
		$this->printMenuPageHtml($current);
		echo '</div></div></div>';
	}

	/**
	 * @param array<string, mixed> $item
	 * @return void
	 */
	private function printMenuPageHtml($item):void{
		$callback = isset($item['callback']) ? $item['callback'] : null;
		$sanitize = isset($item['sanitize']) ? $item['sanitize'] : null;
		$pageUrl = get_admin_url().'admin.php?page='.self::PAGEID.'&tab='.$item[ 'slug' ];
		$current  = $item;

		echo '<h1>'.esc_html($item['title']).'</h1>';
		// submenu
		if (isset($item['submenu'])) {
			$submenu = $item['submenu'];
			$currentTab = null;
			if(isset($_GET['sub_tab']))
				$currentTab = sanitize_key($_GET['sub_tab']);

			$menuItemData = $this->getMenuItemData($submenu,$pageUrl.'&subtab=',$currentTab);
			$current = $menuItemData['current'];

			echo '<ul class="submenu">';
			foreach ($menuItemData['items'] as $item){
				//echo '<li class="'.esc_attr($item['class']).'"><a href="'.esc_url($item['link']).'" title="'.esc_html($item['title']).'">'.esc_html($item['title']).'</a></li>';
				$callback = !is_null($item['callback']) ? $item['callback'] : $callback;
				$sanitize = !is_null($item['sanitize']) ? $item['sanitize'] : $sanitize;
			}
			echo '</ul>';
		}

		$isOptionPage = isset($current['options']);

		// callback
		if (isset($callback)) {
			if( ( is_array( $callback ) && method_exists($callback[0],$callback[1]))||(!(is_array($callback)) && function_exists($callback))) {
				if ( $isOptionPage ) {
					// get Token
					if ( isset( $_POST[ 'submit_get_token'] )) {
						try{
							$this->getAccessToken();
						}catch (\Exception $e){
							$this->notifier->error($e->getMessage());
							wp_redirect($pageUrl);
							return;
						}
					}
					// get Handshake
					if ( isset( $_POST[ 'submit_get_handshake'] )) {
						try{
							$client = PbxBlowball()->getBlowballClient();
							$siteId = $this->getOptionValue('site_id');
							$siteUrl = get_site_url();
							$token = $client->getHandshakeToken($siteId, $siteUrl);
							if ($token !== false){
								$this->options['handshake_token'] = $token;
								update_option ( self::OPTIONNAME, $this->options );
							}
						}catch (\Exception $e){
							$this->notifier->error($e->getMessage());
							wp_redirect($pageUrl);
							return;
						}
					}
					// save
					if ( isset( $_POST[ 'submit_save_plugins'] )) {
						try{
							$this->savePluginSettings();
						}catch (\Exception $e){
							$this->notifier->error($e->getMessage());
							wp_redirect($pageUrl);
							return;
						}
					}
					if (isset ( $_GET['code'] )) {
						$code = sanitize_key($_GET['code']);
						$serverUrl = $this->options['server_url'];
						$clientId = $this->options['client_id'];
						$clientSecret = $this->options['client_secret'];
						$redirectUri = $this->getRedirectUri ();
						try{
							$tokens = $this->blowballClient->getAccessToken($serverUrl, $clientId, $clientSecret, $redirectUri, $code);
						} catch (\Exception $e){
							$this->notifier->error($e->getMessage());
							wp_redirect($pageUrl);
							return;
						}
						$this->options['access_token'] = $tokens['access_token'];
						$this->options['refresh_token'] = $tokens['refresh_token'];
						update_option ( self::OPTIONNAME, $this->options );
						$this->notifier->success(__("Access token created successfully", PbxBlowball::PLUGIN_NAME));
						wp_redirect($pageUrl);
					}

					$options = call_user_func( $callback );

					// save settings
					if ( isset( $_POST[ 'submit_save_pbxbb_options' ] ) ) {
						if ( ! wp_verify_nonce( $_POST[ 'update_pbxbb_settings' ], 'update_pbxbb_settings' ) ) {
							?>
							<div class="notice notice-error">
								<p><?php echo esc_html(__( 'Sorry, but something went wrong while saving your settings. Please, try again.', PbxBlowball::PLUGIN_NAME )); ?></p>
							</div>
							<?php
						} else {
							$sanitizeErrors = [];
							if (isset($sanitize)) {
								$sanitizeErrors = call_user_func($sanitize, $options);
							}
							if (count($sanitizeErrors)>0) {
								foreach($sanitizeErrors as $error)
									echo esc_html('<div class="notice notice-error"><p>'.$error.'</p></div>');
							} else {
								update_option(self::OPTIONNAME, $options);
								echo esc_html('<div class="notice notice-success"><p>'.
										__( 'Your settings have been saved.', PbxBlowball::PLUGIN_NAME ).
										'</p></div>');
							}
						}
					}

					echo '<form method="post">'.wp_nonce_field('update_pbxbb_settings', 'update_pbxbb_settings');
					SettingsHelper::outputFields($options);
					echo '</form>';
				} else {
					call_user_func( $callback );
				}
			}
		}
	}

	public function savePluginSettings(){
		$activate = false;
		$useBlowballLogin	= ($_POST['pbx_bb_plugin_login']);
		$enableStoresPlugin	= ($_POST['pbx_bb_plugin_stores']);
		$enableWooPlugin	= ($_POST['pbx_bb_plugin_woo']);

		if ((!$this->options['enable_blowball_stores'])&&($enableStoresPlugin)){
			$this->options['install_stores_plugin'] = true;
			$activate = true;
		}

		if ((!$this->options['enable_blowball_products'])&&($enableWooPlugin)){
			$this->options['install_woo_plugin'] = true;
			$activate = true;
		}

		$this->options['use_blowball_login'] = $useBlowballLogin == '1';
		$this->options['enable_blowball_stores'] = $enableStoresPlugin == '1';
		$this->options['enable_blowball_products'] = $enableWooPlugin == '1';
		update_option ( self::OPTIONNAME, $this->options );
		if ($activate){
			$pageUrl = get_admin_url().'admin.php?page='.self::PAGEID.'&tab=plugins';
			wp_redirect($pageUrl);
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function getSidebarMenu() {

		$generalItems = [
			'title'		=> __( 'General', PbxBlowball::PLUGIN_NAME ),
			'slug'		=> 'general',
			'submenu'	=> [
				[
					'title'		=> __( 'Blowball Settings', PbxBlowball::PLUGIN_NAME ),
					'slug'		=> 'blowball-settings',
					'callback'	=> [$this, 'pageBlowballApi'],
					'sanitize'	=> [$this, 'sanitizeBlowballApi'],
					'options'	=> 'yes'
				]
			]
		];

		$pluginPage = [
			'title'		=> __( 'Plugins', PbxBlowball::PLUGIN_NAME ),
			'slug'		=> 'plugins',
			'submenu'	=> [
				[
					'title'		=> __( 'Plugins', PbxBlowball::PLUGIN_NAME ),
					'slug'		=> 'blowball-plugins',
					'callback'	=> [$this, 'pageBlowballPlugins'],
					'sanitize'	=> [$this, 'sanitizeBlowballPlugins'],
					'options'	=> 'yes'
				]
			]
		];

		$items[] = $generalItems;
		$items[] = $pluginPage;
		$items = apply_filters( 'pbx_blowball_menu_items', $items );
		ksort( $items );
		return $items;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	private function getOptionValue(string $key){
		if ((is_array($this->options)) && (array_key_exists($key, $this->options)))
			return $this->options[$key];
		return null;
	}

	private function getCheckboxOptionValue(string $key){
		if ((is_array($this->options)) && (array_key_exists($key, $this->options))&&($this->options[$key]==true))
			return 'yes';
		return null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function pageBlowballPlugins() {
		$sectionDesc = __('Here you can activate different plugins to customize your blowball integration', PbxBlowball::PLUGIN_NAME );

		$options = [];

		/* Title Server Settings */
		$options[] = [
			'type' 		=> 'title',
			'name' 		=> __( 'Plugins', PbxBlowball::PLUGIN_NAME ),
			'desc' 		=> sprintf(__($sectionDesc, PbxBlowball::PLUGIN_NAME ), $this->getRedirectUri()),
			'id'   		=> 'pbx_bb_plugin_settings',
		];

		/* Blowball Login*/
		if (PbxHelper::isWoocommerceActive()){
			$options[] = [
				'type'     	=> 'checkbox',
				'name'     	=> __( 'Blowball Login', PbxBlowball::PLUGIN_NAME ),
				'desc_tip' 	=> '',
				'id'       	=> 'pbx_bb_plugin_login',
				'value'		=> $this->getCheckboxOptionValue('use_blowball_login'),
				'class'    	=> '',
			];
		}

		/* Stores */
		$options[] = [
			'type'     	=> 'checkbox',
			'name'     	=> __( 'Blowball Stores', PbxBlowball::PLUGIN_NAME ),
			'desc_tip' 	=> '',
			'id'       	=> 'pbx_bb_plugin_stores',
			'value'		=> $this->getCheckboxOptionValue('enable_blowball_stores'),
			'class'    	=> '',
		];

		/* Woo */
		if (PbxHelper::isWoocommerceActive()){
			$options[] = [
				'type'     	=> 'checkbox',
				'name'     	=> __( 'Blowball WooCommerce', PbxBlowball::PLUGIN_NAME ),
				'desc_tip' 	=> '',
				'id'       	=> 'pbx_bb_plugin_woo',
				'value'		=> $this->getCheckboxOptionValue('enable_blowball_products'),
				'class'    	=> '',
			];
		}

		$options[] = [
			'type' 		=> 'sectionend',
			'id' 		=> 'pbx_bb_plugin_section_end'
		];

		$options[] = [
			'type'     	=> 'action',
			'label'    	=> __( 'Save', PbxBlowball::PLUGIN_NAME ),
			'action'   	=> 'save_plugins',
			'class'    	=> '',
		];



		return $options;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function pageBlowballApi() {
		$sectionDesc1 = __('Here you can generate and store the access token for communication with the REST API of Blowball', PbxBlowball::PLUGIN_NAME ).'.<br>';
		$sectionDesc2 = __('The RedirectUri is <strong>%s</strong>', PbxBlowball::PLUGIN_NAME);
		$sectionDesc = $sectionDesc1.$sectionDesc2;

		$options = [];

		/* Title Server Settings */
		$options[] = [
			'type' 		=> 'title',
			'name' 		=> __( 'Blowball Settings', PbxBlowball::PLUGIN_NAME ),
			'desc' 		=> sprintf(__($sectionDesc, PbxBlowball::PLUGIN_NAME ), $this->getRedirectUri()),
			'id'   		=> 'pbx_bb_api_settings',
		];

		/* Server URL */
		$options[] = [
			'type'     		=> 'text',
			'name'     		=> __( 'Server URL', PbxBlowball::PLUGIN_NAME ),
			'desc_tip' 		=> '',
			'id'       		=> 'pbx_bb_api_server_url',
			'value'			=> $this->getOptionValue('server_url'),
			'class'    		=> '',
			'placeholder'  	=> 'https://[instance].blowball.io',
		];

		/* Client ID */
		$options[] = [
			'type'     	=> 'text',
			'name'     	=> __( 'Client ID', PbxBlowball::PLUGIN_NAME ),
			'desc_tip' 	=> '',
			'id'       	=> 'pbx_bb_api_client_id',
			'value'		=> $this->getOptionValue('client_id'),
			'values'	=> $this->options,
			'class'    	=> '',
		];

		/* Client Secret */
		$options[] = [
			'type'     	=> 'password',
			'name'     	=> __( 'Client Secret', PbxBlowball::PLUGIN_NAME ),
			'desc_tip' 	=> '',
			'id'       	=> 'pbx_bb_api_client_secret',
			'value'		=> $this->getOptionValue('client_secret'),
			'values'	=> $this->options,
			'class'    	=> '',
		];

		$options[] = [
			'type'		=> 'sectionend',
			'id' 		=> 'pbx_bb_api_settings_end'
		];

		$options[] = [
			'type'		=> 'title',
			'desc' 		=> '',
			'id'   		=> 'pbx_bb_api_token',
		];

		$options[] = [
			'type'		=> 'text',
			'name'     	=> __( 'Access Token', PbxBlowball::PLUGIN_NAME ),
			'desc_tip' 	=> '',
			'readonly'	=> true,
			'value'		=> $this->getOptionValue('access_token'),
			'id'       	=> 'pbx_bb_api_access_token',
			'class'    	=> '',
		];

		$options[] = [
			'type'		=> 'text',
			'name'     	=> __( 'Handshake Token', PbxBlowball::PLUGIN_NAME ),
			'desc_tip' 	=> '',
			'readonly'	=> true,
			'value'		=> $this->getOptionValue('handshake_token'),
			'id'       	=> 'pbx_bb_api_handshake_token',
			'class'    	=> '',
		];

		$options[] = [
			'type' 		=> 'sectionend',
			'id' 		=> 'pbx_bb_api_token_end'
		];

		$options[] = [
			'type'     	=> 'action',
			'label'    	=> __( 'Get Access token', PbxBlowball::PLUGIN_NAME ),
			'action'   	=> 'get_token',
			'class'    	=> '',
		];

		$options[] = [
			'type'     	=> 'action',
			'label'    	=> __( 'Handshake', PbxBlowball::PLUGIN_NAME ),
			'action'   	=> 'get_handshake',
			'class'    	=> '',
			'enabled'	=> $this->getOptionValue('access_token') !== null,
		];


		return $options;
	}

	private function getRedirectUri():string {
		return admin_url('options-general.php?page='.self::PAGEID);
	}

	private function sanitizeServerUrl(string $serverUrl):string {
		$urlParts = parse_url ( $serverUrl );
		if ($urlParts == false)
			throw new \Exception ( 'Invalid Server URL' );
		if ((array_key_exists ( 'host', $urlParts ) == false) || ($urlParts ['host'] == ''))
			throw new \Exception ( 'Invalid Server URL' );

		if ((array_key_exists ( 'scheme', $urlParts ) == false) || ($urlParts ['scheme'] == ''))
			$urlParts ['scheme'] = 'https';

		if (($urlParts ['scheme'] !== 'http') && ($urlParts ['scheme'] !== 'https'))
			throw new \Exception ( 'Invalid Server URL' );

		$serverUrl = $urlParts ['scheme'] . '://' . $urlParts ['host'];

		if ((array_key_exists ( 'port', $urlParts )) && ($urlParts ['port'] !== 80) && ($urlParts ['port'] !== 443) && (is_numeric ( $urlParts ['port'] ))) {
			$serverUrl .= ':' . $urlParts ['port'];
		}

		if (array_key_exists('path',$urlParts))
		{
			$path = $urlParts['path'];
			$pos = strpos ( $path, '/api/v' );
			if ($pos === false)
				$path = rtrim ( $path, '/' ) . '/';
			else
				$path = rtrim ( substr ( $path, 0, $pos ), '/' ) . '/';
		} else {
			throw new Exception('Invalid Path');
		}

		$serverUrl .= $path;
		return $serverUrl;
	}

	/**
	 * @param array<int,array<string, mixed>> $options
	 * @return array<string>
	 */
	public function sanitizeBlowballApi($options) {
		$errors = [];
		foreach($options as $option) {
			if ($option['id'] == 'pbx_bb_api_server_url')
			{
				$serverUrl = filter_var($_POST['pbx_bb_api_server_url'], FILTER_SANITIZE_URL);
				if ($serverUrl===false){
					$errors[] = 'invalid server url';
				} else {
					try {
						$_POST['pbx_bb_api_server_url'] = $this->sanitizeServerUrl($serverUrl);
					} catch (\Exception $e) {
						$errors[] = $e->getMessage();
					}
				}
			}
		}
		return $errors;
	}

	/**
	 * @param array<int,array<string, mixed>> $options
	 * @return array<string>
	 */
	public function sanitizeBlowballPlugins($options) {
		return [];
	}

	public function getAccessToken():void
	{
		$clientId 		= sanitize_text_field($_POST['pbx_bb_api_client_id']);
		$clientSecret	= sanitize_text_field($_POST['pbx_bb_api_client_secret']);
		$serverUrl 		= filter_var($_POST['pbx_bb_api_server_url'], FILTER_SANITIZE_URL);
		if ($serverUrl===false)
			throw new \Exception('Invalid server url');
		$prefix			= '/app';

		if (strpos($serverUrl, 'https://', 0)===false)
			$serverUrl = 'https://'.$serverUrl;
		$serverUrl = rtrim($serverUrl,'/');
		if (substr($serverUrl, -4) != $prefix)
			$serverUrl .= $prefix;
		$serverUrl .= '/';
		try{
			$res = $this->blowballClient->checkConnection($serverUrl);
		}catch (\Exception $e){
			throw new \Exception(__("Failed to connect to server", PbxBlowball::PLUGIN_NAME ).':'. $serverUrl);
		}
		if ($res !== true)
			throw new \Exception(__("Failed to connect to server", PbxBlowball::PLUGIN_NAME ).' : '. $serverUrl);
		$this->options ['server_url'] = $serverUrl;
		$this->options ['client_id'] = $clientId;
		$this->options ['client_secret'] = $clientSecret;
		update_option ( self::OPTIONNAME, $this->options );

		$redirectUri = $this->getRedirectUri();
		$this->blowballClient->loginRedirect($serverUrl, $clientId, $clientSecret, $redirectUri);
	}
}