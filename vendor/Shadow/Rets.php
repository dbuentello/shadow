<?php

namespace Shadow;

class Rets
{
	protected static $rets = false;

	public static function get ()
	{
		if (empty(self::$rets)) {
			self::connect();
		}
		return self::$rets;
	}

	public static function connect ()
	{
		$config = Config::get('rets');
		self::$rets = new \Rets\Phrets;
		$connect = self::$rets->Connect( $config['host'], $config['username'], $config['password'] );
		return $connect ? true : self::$rets->Error();
	}

	public static function disconnect ()
	{
		!empty(self::$rets) ? self::$rets->Disconnect() : false;
		self::$rets = false;
	}
}