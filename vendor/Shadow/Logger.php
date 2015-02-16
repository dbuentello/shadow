<?php

namespace Shadow;

class Logger
{
	const INFO = 1;

	const NOTICE = 2;
	
	const WARNING = 3;

	public static function write ( $string, $type = 0 )
	{
		if (php_sapi_name() == 'cli' || defined('STDIN')) {
			switch ($type) {
				case self::INFO:
					$string = "\033[0;30m\033[44m " . $string . " \033[0m";
					break;

				case self::NOTICE:
					$string = "\033[0;30m\033[43m " . $string . " \033[0m";
					break;

				case self::WARNING:
					$string = "\033[0;30m\033[41m " . $string . " \033[0m";
					break;
			}
		}
		echo $string . "\n";
	}
}
