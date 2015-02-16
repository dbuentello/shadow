<?php

namespace App\Controller;

class Api extends \Shadow\Controller
{

	private $_start = 0;
	private $_response = array();
	private $_public = true;
	private $_forbidden = false;

	public function __construct ()
	{
		parent::__construct();

		if (in_array($_SERVER['REMOTE_ADDR'], \Shadow\Config::get('api.users'))) {
			$this->_start = microtime(true);
			if ($_SERVER['REMOTE_ADDR'] == '192.168.159.102') {
				$this->_public = false;
			} else {
				$this->_public = $_SERVER['REMOTE_ADDR'];
			}
		} else {
			$this->_forbidden = true;
		}
	}

	public function index ()
	{
	}

	public function property ( $method, $params = false )
	{
		if ($method == 'get') {
			$this->_response = \App\Model\Api::property( $method, \App\Model\Api::decodeParams($params) );
		}
	}

	public function detail ( $method, $params )
	{
		if ($method == 'get') {
			$this->_response = \App\Model\Api::detail( \App\Model\Api::decodeParams($params) );
		}
	}

	public function photo ( $method, $params )
	{
		if ($method == 'get') {
			$this->_response = \App\Model\Api::photo( \App\Model\Api::decodeParams($params) );
		}
	}

	public function user ( $method, $params )
	{
		if (in_array($method, array('post', 'put', 'get'))) {
			$this->_response = \App\Model\Api::user( $method, \App\Model\Api::decodeParams($params), $this->_public );
		}
	}

	public function listing ( $method, $params )
	{
		if ($method == 'get') {
			$this->_response = \App\Model\Api::listing( \App\Model\Api::decodeParams($params) );
		}
	}

	public function lookup ( $method, $params )
	{
		if ($method == 'get') {
			$this->_response = \App\Model\Api::lookup( \App\Model\Api::decodeParams($params) );
		}
	}

	public function metadata ( $method )
	{
		if ($method == 'get') {
			$this->_response = \App\Model\Api::metadata();	
		}
	}

	public function statistic ( $method )
	{
		if ($method == 'get') {
			$this->_response = \App\Model\Api::statistic();
		}
	}

	public function __destruct ()
	{
		ob_end_clean();
	
		if (!$this->_forbidden) {
			$error = empty($this->_response) ? true : false;
			$this->_response['status'] = array(
				'error' => $error,
				'time' => microtime(true) - $this->_start
			);
			header('HTTP/1.1 200 OK');
			header("Content-type: application/json");
			echo json_encode($this->_response);
		} else {
			header('HTTP/1.0 403 Forbidden');
		}
		exit;
		
	}
}
