<?php

namespace Shadow;

class Cache
{
	const CACHE_PRE = 'shadow_';

	protected static $expiry = false;

	public static function get ( $key )
	{
		$cache = Config::get('app.cache') . DIRECTORY_SEPARATOR . $key . '.cache';
		if (file_exists($cache)) {
			$cache = unserialize(base64_decode(file_get_contents($cache)));
			self::$expiry = $cache['expiry'];
			return $cache['expiry'] > microtime(true) ? $cache['data'] : false;
		}
	}

	public static function set ( $key, $value, $time = 86400 )
	{
		if (!empty($value)) {
			$cache = Config::get('app.cache') . DIRECTORY_SEPARATOR . $key . '.cache';
			$data = array(
				'expiry' => microtime(true) + $time,
				'data' => $value
			);
			return file_put_contents($cache, base64_encode(serialize($data)));
		}
	}

	public static function remove ( $key )
	{
		$cache = Config::get('app.cache') . DIRECTORY_SEPARATOR . $key . '.cache';
		if (file_exists($cache)) {
			return unlink($cache);
		}
	}

	public static function setIndex ( $key, $index, $value )
	{
		if ($cache = self::get($key)) {
			$cache[$index] = $value;
			self::set($key, $cache, self::$expiry);
		}
	}

}