<?php

namespace PbxBlowball;

use PbxBlowball\CF7\CF7Integration;
use PbxBlowball\Client\PbxBlowballClient;
use PbxBlowball\Notifications\Notifier;
use PbxBlowball\Plugins\PbxStoresPlugin\PbxStoresPlugin;
use PbxBlowball\Plugins\PbxWooPlugin\PbxWooPlugin;
use PbxBlowball\Plugins\PbxUserPlugin\PbxUserPlugin;
use PbxBlowball\Settings\Settings;
use PbxVendor\Monolog\Handler\StreamHandler;
use PbxVendor\Monolog\Logger;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Main Plugin Class
 */
final class PbxBlowball {

	const PLUGIN_NAME = 'pbx-blowball';

	const PLUGIN_VERSION = '1.3.10';

	/**
	 * @var PbxBlowballClient|null
	 */
	private $blowballClient;

	/**
	 * @var string
	 */
	public static $pluginPath;

	/**
	 * @var string
	 */
	public static $pluginUrl;

	/**
	 * @var array<string,mixed>
	 */
	private $options;

	/**
	 * @var CF7Integration;
	 */
	private $cfIntegration;

	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * @var PbxStoresPlugin
	 */
	private $pbxStoresPlugin;

	/**
	 * @var PbxUserPlugin
	 */
	private $pbxUserPlugin;

	/**
	 * @var PbxWooPlugin
	 */
	private $pbxWooPlugin;

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @var Notifier
	 */
	private $notifier;

	/**
	 * @var PbxBlowball|null
	 */
	protected static $instance = null;

	private function isOptionSet($name){
		if ((array_key_exists($name, $this->options))&&($this->options[$name]))
			return true;
		return false;
	}

	public function getOptionValue($name, $default){
		if (array_key_exists($name, $this->options))
			return $this->options[$name];
		return $default;
	}

	public function getWooPlugin(){
		return $this->pbxWooPlugin;
	}

	public function getStorePlugin(){
		return $this->pbxStoresPlugin;
	}

	public function deleteOptionValue($name){
		if (array_key_exists($name, $this->options)){
			unset($this->options[$name]);
			update_option(Settings::OPTIONNAME, $this->options);
		}
	}

	public function __construct() {
		$this->pluginPath = dirname( __FILE__ ) . '/../';
		$this->pluginUrl = plugin_dir_url( __FILE__ );

		$this->notifier = new Notifier();
		$this->options 	= get_option(Settings::OPTIONNAME,[]);

		if ($this->isOptionSet('enable_blowball_stores'))
			$this->pbxStoresPlugin = new PbxStoresPlugin($this);

		if (($this->isOptionSet('enable_blowball_products'))&&(PbxHelper::isWoocommerceActive()))
			$this->pbxWooPlugin = new PbxWooPlugin($this);

		if (($this->isOptionSet('use_blowball_login'))&&(PbxHelper::isWoocommerceActive()))
			$this->pbxUserPlugin = new PbxUserPlugin($this);

		$this->checkSiteId();

		add_action('plugins_loaded', [$this, 'onPluginsLoaded'] );
		add_action('wp_enqueue_scripts', [$this, 'onLoadScripts'] );

		if (is_admin())
			$this->settings = new Settings($this->notifier);

		if (PbxHelper::isCF7Active())
			$this->cfIntegration = new CF7Integration($this);
	}

    public function getLogger():Logger
    {
		if (defined('NONCE_KEY'))
			$prefix = md5(NONCE_KEY);
		else
			$prefix = 'D7CAD3394125417EB50851910CF23095';
        if ($this->logger === null) {
            $this->logger = new Logger('pbx-blowball');
			$uploads  = wp_upload_dir( null, false );
			$logDir = $uploads['basedir'] . '/pbx-logs';
            $this->logger->pushHandler(
                new StreamHandler($logDir.'/' . $prefix . '-pbx-blowball')
            );
        }
        return $this->logger;
    }

    public function getNotifier():Notifier
    {
        return $this->notifier;
    }

	function onLoadScripts():void {
		wp_register_style('pbx-content-styles', plugins_url('../assets/css/content.css',__FILE__ ));
		wp_enqueue_style('pbx-content-styles');
	}

	function onPluginsLoaded():void {
		load_plugin_textdomain( self::PLUGIN_NAME, false, dirname( plugin_basename( __FILE__ ) ) . '/../languages' );
	}

	/**
	 * Disable the __clone method
	 */
	public function __clone() {
	    _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', self::PLUGIN_NAME), '1.0');
	}

	/**
	 * Disable the __wakeup method
	 */
	public function __wakeup() {
	    _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', self::PLUGIN_NAME), '1.0');
	}

	/**
	 * Check site id. Generate on if it is not set.
	 */
	public function checkSiteId(){
		if (!$this->isOptionSet('site_id')){
			$siteId = wp_generate_uuid4();
			$this->options['site_id'] = $siteId;
			update_option(Settings::OPTIONNAME, $this->options);
		}
	}

	/**
	 * Returns the Pbx_Blowball instance as singleton.
	 *
	 * @return PbxBlowball
	 */
	public static function instance() {
		if (is_null ( self::$instance ))
			self::$instance = new self();
		return self::$instance;
	}

	public function getBlowballClient():PbxBlowballClient {
		if (!is_object($this->blowballClient))
			$this->blowballClient = new PbxBlowballClient();

		$this->options 	= get_option(Settings::OPTIONNAME);
		$accessToken 	= $this->options['access_token'];
		$serverUrl 		= $this->options['server_url'];

		$this->blowballClient->setServerUrl($serverUrl);
		$this->blowballClient->setAccessToken($accessToken);
		return $this->blowballClient;
	}
}