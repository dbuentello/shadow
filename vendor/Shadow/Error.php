<?php

namespace Shadow;

class Error
{
	public static function init ()
	{
		ini_set('error_log', Config::get('app.log') . DIRECTORY_SEPARATOR . 'error_' . date('Y-m-d') . '.log');
		ini_set('log_errors', 'On');
		ini_set('error_reporting', E_ALL);
		ini_set('display_errors', 'Off');
	}
}