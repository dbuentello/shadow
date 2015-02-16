<?php

namespace Shadow;

class Mongo
{
	protected static $db = array();

	public static function get ()
	{
		$params = func_get_args();

		if (empty($params)) {
			$config = Config::get('mongo');
			$host = $config['host'];
			$dbname = $config['dbname'];
		} else {
			list($host, $dbname) = $params;
			if (count($params) == 1) {
				$dbname = $params[0];
			}
		}

		if (!in_array($dbname, array_keys(self::$db))) {
			self::$db[$dbname] = new \Mongo('mongodb://' . $host);
		}
		
		return self::$db[$dbname]->$dbname;
	}
}