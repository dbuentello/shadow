<?php

namespace App\Model;

class User
{
	public static function hash ( $user_id )
	{
		$hash = new \Hashids\Hashids(\Shadow\Config::get('app.salt'));
		return $hash->encrypt($user_id, time());
	}

	public static function getKey ( $user_id )
	{
		$data = \Shadow\Mongo::get()->users->find(array('user_id' => (int)$user_id))->sort(array('_id' => 1))->limit(1);
		$data = array_values(iterator_to_array($data));
		$data = isset($data[0]) ? $data[0] : false;
		if (!empty($data)) {
			if (!isset($data['key']) || empty($data['key'])) {
				$data['key'] = self::hash($user_id);
				\Shadow\Mongo::get()->users->update(array('_id' => $data['_id']), $data);
			}
		} else {
			$data = array(
				'created' => microtime(true),
				'user_id' => $user_id,
				'name' => 'Property Search #1',
				'query' => array('basic' => array('LISTSTATUS' => array('min' => array('ACT'))))
			);
			\Shadow\Mongo::get()->users->insert($data);
				
		}
		return isset($data['key']) ? $data['key'] : false;
	}

	public static function getQuery ( $key )
	{
		$data = \Shadow\Mongo::get()->users->findOne(array('key' => $key));
		return isset($data['query']) ? $data['query'] : false;
	}

	public static function getActiveSearchFields ( $key )
	{
		$count = 0;
		$data = \Shadow\Mongo::get()->users->findOne(array('key' => $key));

		if (!empty($data)) {
			if (isset($data['query']) && !empty($data['query'])) {

				// Basic
				if (isset($data['query']['basic']) && !empty($data['query']['basic'])) {
					foreach ($data['query']['basic'] as $items) {
						foreach ($items as $value) {
							if (!empty($value)) {
								$count++;
							}
						}
					}
				}

				// Logic
				if (isset($data['query']['logic']) && !empty($data['query']['logic'])) {
					foreach ($data['query']['logic'] as $logic_items) {
						foreach ($logic_items as $items) {
							foreach ($items as $value) {
								if (!empty($value)) {
									$count++;
								}
							}
						}
					}
				}
			}
		}
		return $count;
	}
}
