<?php

namespace Shadow;

class Database
{
	protected static $db = array();

	public static function get ()
	{
		$params = func_get_args();

		if (empty($params)) {
			$config = Config::get('database');
			$host = $config['host'];
			$dbname = $config['dbname'];
			$username = $config['username'];
			$password = $config['password'];
		} else {
			list($host, $dbname, $username, $password) = $params;
			if (count($params) == 1) {
				$dbname = $params[0];
			}
		}

		if (!in_array($dbname, array_keys(self::$db))) {
			self::$db[$dbname] = new \PDO('mysql:host=' . $host . ';dbname=' . $dbname, $username, $password );
		}
		
		return self::$db[$dbname];
	}
}