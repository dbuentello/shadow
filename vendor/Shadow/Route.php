<?php

namespace Shadow;

class Route
{
	protected static $controller;

	protected static $function;

	protected static $params;

	public static function start ()
	{
		if ( php_sapi_name() == 'cli' || defined('STDIN') )
		{
			if (isset($_SERVER['argv'][1])) {
				self::$controller = $_SERVER['argv'][1];
				if (isset($_SERVER['argv'][2])) {
					self::$function = $_SERVER['argv'][2];
				}
			}

			array_splice($_SERVER['argv'], 0, 3);
			$request = $_SERVER['argv'];
		} else {
			$uri = preg_replace('#[^\.]+\.php[^\/]+\/#', '', $_SERVER['REQUEST_URI']);;
			
			$script = $_SERVER['SCRIPT_NAME'];
			foreach (explode('/', $script) as $segment) {
				if (preg_match('#\.php#', $segment)) {
					$script = preg_replace('#\/' . $segment . '#', '', $script);
					break;
				}
			}

			$script = explode('/', str_replace($script, '', $uri));
			self::$params = array_values(array_filter($script, 'strlen'));

			if (!empty(self::$params) && isset(self::$params[0])) {
				self::$controller = self::$params[0];
				if (isset(self::$params[1])) {
					self::$function = self::$params[1];
				}
			}

			array_splice(self::$params, 0, 2);

			$request = $_POST;
		}
		Request::init( $request );
		self::handle();
	}

	public static function handle ()
	{
		if (empty(self::$controller)) {
			$config = Config::get('app.default');
			$controller = '\App\Controller\\' . $config['controller'];
			$function = !empty($config['function']) ? $config['function'] : 'index';
		} else {
			$controller = '\App\Controller\\' . ucfirst(strtolower(self::$controller));
			$function = !empty(self::$function) ? strtolower(self::$function) : 'index';
		}

		$controller = new $controller;
		if (!empty(self::$params)) {
			call_user_func_array(array($controller, $function), self::$params);
		} else {
			call_user_func(array($controller, $function));
		}
	}
}