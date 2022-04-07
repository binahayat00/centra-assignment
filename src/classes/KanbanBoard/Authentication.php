<?php

namespace App\Classes\KanbanBoard;

use App\Classes\Utilities;
use Exception;
use Lib\Config;

class Authentication
{

	private $client_id = NULL;
	private $client_secret = NULL;
	private $authorize_link = NULL;
	private $access_token_link = NULL;
	private $state = NULL;
	private $scope = NULL;

	public function __construct()
	{
		$this->client_id = Utilities::env('GH_CLIENT_ID');
		$this->client_secret = Utilities::env('GH_CLIENT_SECRET');
		$this->authorize_link = Config::get('AUTHORIZE_LINK');
		$this->access_token_link = Config::get('ACCESS_TOKEN_LINK');
		$this->state = Config::get('STATE');
		$this->scope = Config::get('SCOPE');
	}

	public function login()
	{
		if( empty(session_id()) && !headers_sent())
			session_start();	
		$token = $this->_setTokenForLogin();
		$this->logout();
		$_SESSION['gh-token'] = $token;
		return $token;
	}

	private function logout()
	{
		unset($_SESSION['gh-token']);
	}

	private function _redirectToGithub($client_id = null)
	{
		$client_id = ($client_id) ? $client_id : $this->client_id;
		$url = 'Location: '.$this->authorize_link;
		$url .= '?client_id=' . $client_id;
		$url .= '&scope=' . $this->scope;
		$url .= '&state=' . $this->state;
		header($url);
		try {
			throw new Exception($url);
		}
		catch (Exception $e) {
			echo $e->getMessage().'  ';
			echo $e->getCode();
			return;
		}
	}

	private function _returnsFromGithub($code)
	{
		$url = $this->access_token_link;
		$options = $this->_buildParamsForGithubAccessToken($code);
		$context = stream_context_create($options);
		$result = file_get_contents($url, false, $context);
		return $this->_buildResultForGithubAccessToken($result);
	}

	private function _buildParamsForGithubAccessToken($code)
	{
		$data = [
			'code' => $code,
			'state' => $this->state,
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret
		];

		$options = [
			'http' => [
				'method' => 'POST',
				'header' => "Content-type: application/x-www-form-urlencoded\r\n",
				'content' => http_build_query($data),
			],
		];
		return $options;
	}

	private function _buildResultForGithubAccessToken($result)
	{
		if ($result === FALSE)
			die('Error: can not get data from access token.');
		$result = explode('=', explode('&', $result)[0]);
		array_shift($result);
		return array_shift($result);
	}

	private function _setTokenForLogin()
	{	
		if (array_key_exists('gh-token', $_SESSION) && ($_SESSION['gh-token'])) {
			$token = $_SESSION['gh-token'];
		} else if (
			Utilities::hasValue($_GET, 'code') && 
			Utilities::hasValue($_GET, 'state') && 
			$_SESSION['redirected']
		) {
			$_SESSION['redirected'] = false;
			$token = $this->_returnsFromGithub($_GET['code']);
		} else {
			$_SESSION['redirected'] = true;
			$this->_redirectToGithub();
		}
		return (isset($token)) ? $token : NULL;
	}
}

?>