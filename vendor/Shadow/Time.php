<?php

namespace Shadow;

class Time
{
	public static function set ()
	{
		date_default_timezone_set(Config::get('app.timezone'));
	}

	public static function format ( $date )
	{
		$date = new \DateTime($date);
		return (int)$date->format('U');
	}
}
