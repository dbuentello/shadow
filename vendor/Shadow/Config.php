<?php

namespace Shadow;

class Config
{
	protected static $configs = array();

	public static function get ( $config, $index = true )
	{
		$config = explode('.', $config);
		if (isset($config[0])) {
			if (!in_array($config[0], array_keys(self::$configs))) {
				self::$configs[$config[0]] = require_once SHADOW_CONFIG . DIRECTORY_SEPARATOR . $config[0] . '.php';
			}
			$data = self::$configs[$config[0]];
			array_shift($config);
			foreach ($config as $key) {
				if ( $data ) {
					if (isset($data[$key])) {
						$data = $data[$key];
					} else {
						$data = false;
					}
				}
			}
		}
		return isset($data) ? $data : false;
	}

	public static function set ( $config, $value )
	{
		if (preg_match('#(\w+)\.*(\w*)#', $config, $match)) {
			if (!empty($match[1])) {
				if (isset(self::$configs[$match[1]])) {
					self::get($match[1], false);
				}

				if (!empty($match[2]) && isset(self::$configs[$match[1]][$match[2]])) {
					self::$configs[$match[1]][$match[2]] = $value;
				}
			}
		}
	}
}