<?php

namespace Shadow;

class Request
{
	protected static $command = false;

	protected static $request = array();

	public static function init ( $request = false )
	{
		if (!empty($request)) {
			array_walk_recursive($request, function (&$value) {
				$value = trim($value);
			});
			self::$request = $request;
		}
	}

	public static function get ( $index )
	{
		return isset(self::$request[$index]) ? self::$request[$index] : false;
	}

	public static function set ( $index, $value )
	{
		self::$request[$index] = trim($value);
	}

	public static function all ()
	{
		return self::$request;
	}

	public static function isPost ()
	{
		return strtolower($_SERVER['REQUEST_METHOD']) == 'post' ? true : false;
	}
}