<?php

namespace App\Model;

class Request
{
	protected static $ticket;

	/**
	 * Get connection to the API object and assign a ticket so we can monitor simultaneous connections
	 * @return object
	 */
	public static function get ( $log = true )
	{
		self::removeIdle();
		if ($log) {
			Statistic::request();
			self::$ticket = false;
			if ((int)\Shadow\Mongo::get()->request->count() < \Shadow\Config::get('rets.request.max_simultaneous_connection')) {
				$request = array('start' => microtime(true));
				\Shadow\Mongo::get()->request->insert($request);
				self::$ticket = $request['_id'];
			}
		}
		return \Shadow\Rets::get();	
	}

	/**
	 * Remove tickets which has been idle for 3 minutes
	 * @return null
	 */
	public static function removeIdle ()
	{
		$start = microtime(true) - (60 * 3);
		\Shadow\Mongo::get()->request->remove(array('start' => array('$lte' => $start)));
	}

	/**
	 * Remove ticket
	 * @return null
	 */
	public static function end ()
	{
		if (self::$ticket) {
			\Shadow\Mongo::get()->request->remove(array('_id' => self::$ticket));
		}
	}

	/**
	 * Disconnect from the API
	 * @return null
	 */
	public static function disconnect ()
	{
		\Shadow\Rets::disconnect();
	}
}
