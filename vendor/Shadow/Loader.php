<?php

namespace Shadow;

class Loader
{
	public static function register ()
	{
		spl_autoload_register( array(
				__NAMESPACE__ . '\Loader',
				'load'
			)
		);
	}

	public static function load ( $class )
	{
		if ($config = Config::get('autoload')) {
			$file = $config['vendor'] . '/' . $class . '.php';
			if (preg_match('#^App\\\(Controller|Model+)\\\(\w+)#', $class, $match)) {
				$match[2] = strtolower($match[2]);
				$file = $config[strtolower($match[1])] . '/' . strtolower($match[2]) . '.php';
			}
			$file = str_replace(array('/','\\'), DIRECTORY_SEPARATOR, $file);
			file_exists($file) ? require $file : false;
		}
	}

	public static function setDirectories ( $directories )
	{
		self::$directories = array_merge(self::$directories, $directories);
		self::$directories = array_unique(self::$directories);
	}

}