<?php
namespace PbxBlowball\Client;

use Exception;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Class for interacting with the blowball api
 */
final class PbxBlowballClient {
	/**
	 * @var string|null
	 */
	private $serverUrl;

	/**
	 * @var string|null
	 */
	private $accessToken;

	/**
	 * @var int
	 */
	private $apiVersion = 1;

	/**
	 * Returns the proper Rest URL with api version
	 */
	private function getRestBaseUrl():string {
		if (is_null($this->serverUrl))
			throw new \Exception('server url is not set');
		$path = $this->serverUrl;
		$pos = strpos ($path, '/api/v' );
		if ($pos !== false)
			$path = rtrim ( substr ( $path, 0, $pos ), '/' ) . '/';
		$path = rtrim($path, '/' ). '/api/v' . $this->apiVersion . '/';
		return $path;
	}

	public function setServerUrl(string $serverUrl):void {
		$this->serverUrl = $serverUrl;
	}

	public function setAccessToken(string $accessToken):void {
		$this->accessToken = $accessToken;
	}

	/**
	 * Check a response for any api error messages and throw a RestException
	 *
	 * @param array<string,mixed> $res
	 */
	public function checkRestError($res):void {
		if ((is_array($res)) && (isset($res['error']))) {
			$message = 'unknown';
			if (isset ($res['error']['message']))
				$message = $res ['error']['message'];
			else if (isset ($res['error']))
				$message = $res ['error'];
			$faultCode = 0;
			if (isset ($res['error']['faultCode']))
				$faultCode = $res ['error'] ['faultCode'];
			throw new RestException($message, $faultCode );
		}
	}

	public function getUserIp(){
		if (!empty($_SERVER['HTTP_CLIENT_IP'])){
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return $ip;
	}

	public function authenticateUser(string $username, string $password){
		$serviceUrl = $this->getRestBaseUrl().'myaccount/authenticate';
		$data = [];
		$data['username'] = $username;
		$data['password'] = $password;
		return $this->restPost( $serviceUrl, $data, $this->accessToken );
	}

	public function getAccount($userName) {
		$serviceUrl = $this->getRestBaseUrl().'myaccount/account/'.urlencode($userName);
		$res = $this->restGet($serviceUrl, $this->accessToken );
		if (isset ( $res ['error'] )) {
			if ($res ['error'] ['faultCode'] == 404)
				return FALSE;
		}
		return $res;
	}

	public function createAccount($user) {
		$serviceUrl = $this->getRestBaseUrl().'myaccount/create';
		$res = $this->restPost($serviceUrl, $user, $this->accessToken);
		return $res;
	}

	public function confirmAccount(string $confirmCode){
		$serviceUrl = $this->getRestBaseUrl().'myaccount/doiconfirm';
		$res = $this->restPost($serviceUrl, ['confirm_code'=>$confirmCode], $this->accessToken);
		return $res;
	}

	public function confirmWithEmailAndCode(string $email, string $confirmCode){
		$serviceUrl = $this->getRestBaseUrl().'myaccount/doicodeconfirm';
		$res = $this->restPost($serviceUrl, ['email' => $email, 'confirm_code'=>$confirmCode], $this->accessToken);
		return $res;
	}	

	/**
	 * @param array<string,mixed> $fields
	 * @return mixed
	 */
	public function createLead($fields){
		$serviceUrl = $this->getRestBaseUrl() . 'crm/leads/register';
		return $this->restPost( $serviceUrl, $fields, $this->accessToken );
	}

	/**
	 * @param string $url
	 * @return mixed
	 */
	public function performAuthorizedGetRequest(string $url){
		$serviceUrl = $this->getRestBaseUrl() . $url;
		return $this->restGet( $serviceUrl, $this->accessToken );
	}

	/**
	 * @param string $url
	 * @param array<mixed> $values
	 * @return mixed
	 */
	public function performAuthorizedPostRequest(string $url, $values){
		$serviceUrl = $this->getRestBaseUrl() . $url;
		return $this->restPost( $serviceUrl, $values, $this->accessToken );
	}

	/**
	 * Check if the server is available by using the "check"-endpoint
	 */
	public function checkConnection(string $serverUrl):bool {
		$res = $this->restGet( $serverUrl . 'check' );
		if ((is_array ( $res ) == false) || (array_key_exists ( 'success', $res ) == false) || ($res ['success'] != true))
			return false;
		return true;
	}

	/**
	 * Redirect to the OAuth Endpoint to get a token
	 */
	public function loginRedirect(string $serverUrl, string $clientId, string $clientSecret, string $redirectUri):void {
		$params = [];
		$serverUrl .= 'oauth2/auth';
		$params ['response_type'] = 'code';
		$params ['client_id'] = $clientId;
		$params ['client_secret'] = $clientSecret;
		$params ['redirect_uri'] = $redirectUri;
		$params ['scope'] = "";
		$qryStr = '?';

		foreach ( $params as $key => $value ) {
			if ($qryStr !== '?')
				$qryStr .= '&';
			$qryStr .= urlencode ( $key ) . '=' . urlencode ( $value );
		}

		$serverUrl .= $qryStr;
		wp_redirect($serverUrl);
	}

	/**
	 * Redirect to the OAuth Endpoint to get a token
	 * @return array<string, string>
	 */
	public function getAccessToken(string $serverUrl, string $clientId, string $clientSecret, string $redirectUri, string $code) {
		$serverUrl .= 'oauth2/token';

		$data = [
			'code' => urlencode ( $code ),
			'client_id' => urlencode ( $clientId ),
			'client_secret' => urlencode ( $clientSecret ),
			'redirect_uri' => urlencode ( $redirectUri ),
			'grant_type' => urlencode ( 'authorization_code' ),
		];

		$fields_string = '';
		foreach ( $data as $key => $value ) {
			$fields_string .= $key . '=' . $value . '&';
		}
		$data = rtrim ( $fields_string, '&' );

		$header = [];
		$header [] = 'Content-Type: application/x-www-form-urlencoded';
		$header [] = 'Content-Length:' . strlen ( $data );

		$result = $this->restRequest($serverUrl,'POST',$header,$data);

		if (array_key_exists('access_token', $result ) == false)
			throw new \Exception ( 'Invalid Response');

		return $result;
	}

	public function getHandshakeToken($siteId, $siteUrl){
		$data = ['site_id' => $siteId, 'site_url' => $siteUrl];
		$serviceUrl = $this->getRestBaseUrl().'/integrations/wordpress/handshake';
		$res = $this->restPost($serviceUrl, $data, $this->accessToken );
		if (isset($res['error'])) {
			if($res['error']['faultCode'] == 404)
				return FALSE;
		}
		if (isset($res['token']))
			return $res['token'];
		return false;
	}

	public function getPasswordKey($username) {
		$username = urlencode($username);
		$serviceUrl = $this->getRestBaseUrl() . 'myaccount/getpasswordkey/' . $username;
		$res = $this->restGet($serviceUrl, $this->accessToken);
		return $res;
	}

	public function updatePassword($username, $password, $currentpass = null){
        $serviceUrl = $this->getRestBaseUrl() . 'myaccount/changepassword/'.$username;
	    $res = $this->restPost($serviceUrl, ["password" => $password, "current_password" => $currentpass], $this->accessToken);
	    return $res;
	}

	/**
	 * @param string $serverUrl
	 * @param string|null $accessToken
	 * @return mixed
	 */
	private function restGet(string $serverUrl, ?string $accessToken = null) {
		$header = [];

		if (isset ( $accessToken ))
			$header['Authorization'] = ': Bearer ' . $accessToken;

		$header['Pbx-Client-Ip'] = $this->getUserIp();

		return $this->restRequest($serverUrl,'GET',$header);
	}

	/**
	 * @param string $serverUrl
	 * @param mixed $values
	 * @param string|null $accessToken
	 * @return mixed
	 */
	private function restPost(string $serverUrl, $values, ?string $accessToken = null) {
		$data = json_encode($values);
		if ($data===false)
			throw new Exception('Invalid data for request');

		$data = str_replace("+", "%2B", $data );

		$header = [];
		$header['Content-Type'] = 'application/json';
		$header['Content-Length'] = (string)strlen($data);
		$header['Pbx-Client-Ip'] = $this->getUserIp();

		if (isset ( $accessToken ))
			$header['Authorization'] = ': Bearer ' . $accessToken;

		return $this->restRequest($serverUrl,'POST',$header, $data);
	}

	/**
	 * Performing a request to a server and validates the response
	 *
	 * @param string $serverUrl
	 * @param string $method
	 * @param array<string> $header
	 * @param string|null $data
	 * @return array<mixed>
	 */
	private function restRequest(string $serverUrl, string $method, $header, $data = null){
		if ($method=='POST'){
			$args = [
				'body'        => $data,
				'blocking'    => true,
				'headers'     => $header
			];
			$response = wp_remote_post($serverUrl, $args);
		} else if ($method=='GET'){
			$args = [
				'blocking'    => true,
				'headers'     => $header
			];
			$response = wp_remote_get($serverUrl, $args);
		} else {
			throw new \Exception('unsupported request in blowball client');
		}

		if (is_wp_error($response)){
			$errors = $response->errors;
			$error = reset($errors);
			$errMsg = $error[0];
			throw new \Exception ( 'Connection Error:' . $errMsg . ' - ' . $serverUrl );
		}

		$result = wp_remote_retrieve_body( $response );

		$data = json_decode($result, true);
		if ($data === null)
			throw new \Exception ( 'Invalid Response:' . $result );

		$this->checkRestError($data);
		return $data;
	}
}
