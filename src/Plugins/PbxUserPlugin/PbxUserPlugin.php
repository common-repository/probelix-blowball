<?php

namespace PbxBlowball\Plugins\PbxUserPlugin;

use Exception;
use PasswordHash;
use PbxBlowball\Client\Model\UserModel;
use PbxBlowball\Client\RestException;
use PbxBlowball\PbxBlowball;
use WP_Error;
use WP_User;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Manage the Blowball Login Handling
 */
class PbxUserPlugin {
    /**
     * @var PbxBlowball
     */
    private $core;

	/**
	 * @var \wpdb
	 */
	private $wpdb;

	public function __construct(PbxBlowball $pbxBlowball)
	{
        $this->core = $pbxBlowball;
		global $wpdb;
		$this->wpdb = $wpdb;
		add_action('init', [$this, 'init'], 100);
    }

	private function removeWPAuthFilters()
	{
		remove_filter('authenticate', 'wp_authenticate_username_password', 20);
		remove_filter('authenticate', 'wp_authenticate_email_password', 20);
	}

	public function init()
	{
		add_filter('authenticate', [$this, 'authenticate'], 10, 3);
		//add_filter('woocommerce_registration_errors',  [$this, 'registerCustomer'], 10, 3 );
		add_action( 'password_reset', [$this, 'onPasswordReset'], 10, 2 );

		add_action('show_user_profile', [$this, 'showExtraProfileFields']);
		add_action('edit_user_profile', [$this, 'showExtraProfileFields']);
		add_action('edit_user_profile_update', [$this, 'saveExtraProfileFields']);

		if ( class_exists('WC_Form_Handler') ) {
			remove_action('wp_loaded', ['WC_Form_Handler', 'process_lost_password'], 20);
			add_action('wp_loaded', [$this, 'processLostPassword'], 20);
		}

		if ( class_exists('WC_Email_Customer_Reset_Password') ) {
			remove_action('woocommerce_reset_password_notification', ['WC_Email_Customer_Reset_Password', 'trigger', 10]);
		}
	}

	function defineUserMeta() {
		$custom_meta_fields = [];
		$custom_meta_fields['pbxbb_user_meta'] = 'User Meta';
		return $custom_meta_fields;
	}

	function showExtraProfileFields($user) {
		print('<h3>Probelix Blowball User Meta</h3>');
		print('<table class="form-table">');
		$meta_number = 0;
		$custom_meta_fields = $this->defineUserMeta();
		foreach ($custom_meta_fields as $meta_field_name => $meta_disp_name) {
			$meta_number++;
			print('<tr>');
			print('<th><label for="' . $meta_field_name . '">' . $meta_disp_name . '</label></th>');
			print('<td>');
			print('<input type="text" name="' . $meta_field_name . '" id="' . $meta_field_name . '" value="' . esc_attr( get_the_author_meta($meta_field_name, $user->ID ) ) . '" class="regular-text" /><br />');
			print('<span class="description"></span>');
			print('</td>');
			print('</tr>');
		}
		print('</table>');
	}

	function saveExtraProfileFields($user_id) {
		if (!current_user_can('edit_user', $user_id))
			return false;

		$meta_number = 0;
		$custom_meta_fields = $this->defineUserMeta();
		foreach ($custom_meta_fields as $meta_field_name => $meta_disp_name) {
			$meta_number++;
			update_user_meta( $user_id, $meta_field_name, $_POST[$meta_field_name] );
		}
	}

	public function onPasswordReset($user, $pass){
		$this->core->getBlowballClient()->updatePassword($user->user_email, $pass);
	}

	public function processLostPassword(){
		if (isset($_POST['wc_reset_password'])) {
			$login = sanitize_email($_POST['user_login']);
			if ((empty($login))||(is_email($login)==FALSE)){
				if (!isset($_POST['reset_key']))
					wc_add_notice(__('Bitte geben Sie eine gültige E-Mail-Adresse an.', 'woocommerce'), 'error');
				return;
			};

			try{
				$response = $this->core->getBlowballClient()->getPasswordKey($login);
			}catch (Exception $e){
				if ($e->getCode()==404){
					$this->core->getLogger()->warning("Password reset: not found: ".$login);
					wc_add_notice(__('Falls zu Ihrer Email Adresse ein Account vorliegt wurde eine Email mit weiteren Anweisungen versendet.', 'woocommerce'));
				}else{
					$this->core->getLogger()->warning("Password Key retrieval: " . $e->getMessage());
					wc_add_notice(__('Ein Fehler ist aufgetreten', 'woocommerce'), 'error');
					return;
				}
			}
			if ((isset($response)) && (array_key_exists('success', $response)) && ($response['success'] == 1))
			{
				if (isset($response['key'])){
					$key = sanitize_text_field($response['key']);

					require_once ABSPATH . WPINC . '/class-phpass.php';
					$wpHasher = new PasswordHash(8, true);

					$hashed = time() . ":" . $wpHasher->HashPassword($key);

					if (array_key_exists('userdata', $response))
						$userdata = $response['userdata'];

					$userobj = new WP_User();
					$user = $userobj->get_data_by('email', $login); // Does not return a WP_User object 🙁
					$user = new WP_User($user->ID); // Attempt to load up the user with that ID

					if ($user->ID == 0) {
						// The user does not currently exist in the WordPress user table.
						// You have arrived at a fork in the road, choose your destiny wisely

						// If you do not want to add new users to WordPress if they do not
						// already exist uncomment the following line and remove the user creation code
						// $user = new WP_Error( 'denied', __("ERROR: Not a valid user for this system") );

						// Setup the minimum required user information for this example
						$tmpData = array(
							'user_email' => $login,
							'user_login' => $login,
							'first_name' => $userdata['first_name'],
							'last_name' => $userdata['last_name'],
							'display_name' => trim($userdata['first_name'].' '.$userdata['last_name'])
						);
						$newUserId = wp_insert_user($tmpData); // A new user has been created
						update_user_meta($newUserId, 'billing_last_name', $userdata['last_name']);
						update_user_meta($newUserId, 'billing_first_name', $userdata['first_name']);
					}
					$this->wpdb->update($this->wpdb->users,	['user_activation_key' => $hashed], ['user_email' => $login]);
				}
				wc_add_notice(__('Falls zu Ihrer Email Adresse ein Account vorliegt wurde eine Email mit weiteren Anweisungen versendet.', 'woocommerce'));
				do_action( 'woocommerce_reset_password_notification', $login, $key );
				return;
			}
		}
	}

	//Filter is called first. If no user can be determined, the default WordPress mechanisms are called
	function authenticate($user, $username, $password)
	{
		if ($username == '' || $password == '')
			return;

		if (is_email($username) == FALSE)
			return;

		$client = $this->core->getBlowballClient();

		try {
			$response = $client->authenticateUser($username, $password);
		} catch (Exception $e) {
			$this->removeWPAuthFilters();
			if ($e instanceof RestException) {
				$code = $e->getCode();
				if (($code == 301) || ($code == 404)) {
					return new \WP_Error('authentication_failed', __('Ungültiger Benutzername, ungültige E-Mail-Adresse oder falsches Passwort.','pbx_blowball'));
				} else {
					$this->core->getLogger()->warning('Authenticate Error for ' . $username . ' : ' . $e->getCode() . '-' . $e->getMessage());
					return new \WP_Error('denied2', __("Es ist ein unbekannter Fehler aufgetreten. Bitte versuchen Sie es später erneut."));
				}
			} else {
				$this->core->getLogger()->warning('Authenticate Error for ' . $username . ' : ' . $e->getCode() . '-' . $e->getMessage());
				return new \WP_Error('denied2', __("Es ist ein unbekannter Fehler aufgetreten. Bitte versuchen Sie es später erneut."));
			}
		}


		if ((array_key_exists('success',$response))&&($response['success']==1)&&(array_key_exists('userdata', $response))) {
			$userdata = $response['userdata'];

			$userobj = new WP_User();

			$user = $userobj->get_data_by('email', $username ); // Does not return a WP_User object 🙁
			$user = new WP_User($user->ID); // Attempt to load up the user with that ID

			if ($user->ID == 0) {
				// The user does not currently exist in the WordPress user table.
				// You have arrived at a fork in the road, choose your destiny wisely

				// If you do not want to add new users to WordPress if they do not
				// already exist uncomment the following line and remove the user creation code
				// $user = new WP_Error( 'denied', __("ERROR: Not a valid user for this system") );

				// Setup the minimum required user information for this example
				$tmpData = array (
					'user_email' => $username,
					'user_login' => $username,
					'first_name' => $userdata ['first_name'],
					'last_name'  => $userdata ['last_name'],
					'display_name' => trim($userdata['first_name'].' '.$userdata['last_name'])
				);
				$new_user_id = wp_insert_user($tmpData); // A new user has been created
				// Load the new user info
				$user = new WP_User($new_user_id);
			} else {
				$user->first_name = $userdata['first_name'];
				$user->last_name = $userdata['last_name'];
				$user->display_name = trim($user->first_name.' '.$user->last_name);
				wp_update_user($user);
			}
			update_user_meta($user->ID, 'pbxbb_user_meta', json_encode($userdata));
			if (class_exists('WC_Customer')){
				$customer = new \WC_Customer($user->ID);
				$customer->set_first_name($userdata['first_name']);
				$customer->set_last_name($userdata['last_name']);
				$customer->set_billing_city($userdata['city']);
				$customer->set_billing_company($userdata['company']);
				$customer->set_billing_postcode($userdata['zip']);
				$customer->set_billing_address_1($userdata['street']);
				$customer->set_billing_phone($userdata['phone']);
				$customer->save();
			}
		} else {
			$user = new WP_Error ('denied', __( "ERROR: User/pass bad" ));
		}

		// Comment this line if you wish to fall back on WordPress authentication
		// Useful for times when the external service is offline
		remove_action ( 'authenticate', 'wp_authenticate_username_password', 20 );
		return $user;
	}

	public function registerCustomer($validation_errors, $username, $user_email) {
		// abort if there are already errors
		if (count($validation_errors->errors) > 0)
			return $validation_errors;

		// abort if there is no passowrd
		if (isset($_POST['password']))

		// fill user model
		$user = new UserModel;
		$user->username 	= $user_email;
		$user->bill_email 	= $user_email;
		$user->password		= sanitize_text_field($_POST['password']);
		if (isset($_POST['first_name']))
			$user->fname = sanitize_text_field($_POST['first_name']);
		if (isset($_POST['last_name'] ))
			$user->lname = sanitize_text_field($_POST['last_name']);

		//try to create account
		try {
			$res = $this->core->getBlowballClient()->createAccount($user);
		} catch ( Exception $e ) {
			if ($e instanceof RestException){
				if ($e->getCode()==16010){
					$validation_errors->add('middleware_error', __( 'Kein Passwort übergeben oder Passwort zu kurz!', 'pbx_blowball' ) );
					return $validation_errors;
				} else if ($e->getCode()==16019){
					$validation_errors->add('middleware_error', __( 'Es exitstiert bereits ein Konto mit dieser E-Mail-Adresse. Bitte melden Sie sich an.', 'pbx_blowball' ) );
					return $validation_errors;
				}
			}
			$this->core->getLogger()->warning('Error creating account for ' . $username . ' : ' . $e->getCode() . '-' . $e->getMessage());
			$validation_errors->add('middleware_error', __( '<strong>Error</strong>: Ein unbekannter Fehler ist aufgetreten!', 'pbx_blowball' ) );
			return $validation_errors;
		}
		return $validation_errors;
	}
}