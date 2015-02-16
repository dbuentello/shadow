<?php

namespace App\Model;

class Api
{
	public static function property ( $method, $params = false )
	{
		return self::search($params);
	}

	public static function photo ( $params )
	{
		if (isset($params['404'])) {
			$data = Schema::get('photos.not_found')->find()->sort(array('created' => -1));
			$data = iterator_to_array($data);
		} else {
			$url = 'http://images.houstonproperties.com';
			$property = array();
			$data = array();

			$property = Schema::get('properties.active')->findOne(array('_id' => (int)$params['MLSNUM'], 'PHOTOCOUNT' => array('$gt' => 0)), array('ADDRESS', 'LISTPRICE', 'PHOTOCOUNT'));
			if (!empty($property) && (int)$property['PHOTOCOUNT'] > 0) {

				$photos = array();
				foreach (Schema::get('photos.photo')->find(array('mlsnum' => (int)$params['MLSNUM'])) as $photo) {
					$photos[$photo['object_id']] = $photo;
				}

				$stream = \Shadow\Config::get('photo.stream');

				foreach (Schema::get('photos.thumbnail')->find(array('mlsnum' => (int)$params['MLSNUM']))->sort(array('object_id' => 1)) as $thumbnail) {
					$set = array();
					if (isset($thumbnail['exists']) && !empty($thumbnail['exists']) && !$stream) {
						$set[0] = $url . '/' . $params['MLSNUM'] . '/' . basename($thumbnail['source']);
					} else {
						$set[0] = $url . '/index/get/Thumbnail/' . $params['MLSNUM'] . '/' . $thumbnail['object_id'] . '.jpg';
					}

					if (isset($photos[$thumbnail['object_id']]) && isset($photos[$thumbnail['object_id']]['exists']) && !$stream) {
						$set[1] = $url . '/' . $params['MLSNUM'] . '/' . basename($photos[$thumbnail['object_id']]['source']);
					} else {
						$set[1] = $url . '/index/get/Photo/' . $params['MLSNUM'] . '/' . $thumbnail['object_id'] . '.jpg';
					}

					$property['IMAGES'][] = $set;
				}

				unset($property['PHOTOCOUNT']);
				$data = $property;
			}	
		}
		
		return !empty($data) ? array('data' => $data) : false;
	}

	public static function user ( $method, $params, $public = false )
	{
		$data = array();

		$collection = 'users';

		// Create separate user collection for developers so we won't affect live data
		if (!empty($public)) {
			$collection = 'users_' . str_replace('.', '_', $public);
			\Shadow\Mongo::get()->createCollection($collection);
		}

		switch ($method) {
			case 'post':
				if (!empty($params['user_id']) && !empty($params['name']) && !empty($params['query'])) {
					$data = array(
						'user_id' => $params['user_id'],
						'created' => microtime(true),
						'modified' => microtime(true),
						'name' => $params['name'],
						'query' => $params['query']
					);
					\Shadow\Mongo::get()->{$collection}->insert($data);
				}
				break;
			case 'put':
				if (!empty($params['_id']) && !empty($params['user_id']) && !empty($params['name'])) {
					$data = \Shadow\Mongo::get()->{$collection}->findOne(array('_id' => new \MongoId($params['_id'])));
					if (!empty($data)) {
						$data = array(
							'_id' => new \MongoId($params['_id']),
							'created' => $data['created'],
							'modified' => microtime(true),
							'user_id' => $params['user_id'],
							'name' => $params['name'],
							'query' => !empty($params['query']) ? $params['query'] : array(),
							'key' => !empty($data['key']) ? $data['key'] : false
						);
						\Shadow\Mongo::get()->{$collection}->update(array('_id' => new \MongoId($params['_id'])), $data);
					}
				}
				break;
			default:
				if (!empty($params['_id']) || $params['user_id']) {
					if (!empty($params['_id'])) {
						$data = \Shadow\Mongo::get()->{$collection}->findOne(array('_id' => new \MongoId($params['_id'])));
					} else {
						foreach (\Shadow\Mongo::get()->{$collection}->find(array('user_id' => $params['user_id']))->sort(array('created' => 1)) as $user) {
							$user['_id'] = $user['_id']->{'$id'};
							$data[] = $user;
						}

					}					
				}
		}
		return !empty($data) ? array('data' => $data) : false;
	}

	public static function listing ( $params )
	{
		if (isset($params['get_key']) && !empty($params['get_key'])) {
			unset($params['key']);
			$data = array('data' => User::getKey($params['user_id']));
		} else if (isset($params['get_active_user_fields']) && !empty($params['get_active_user_fields'])) {
			$data = array('data' => User::getActiveSearchFields($params['key']));
		} else {
			$params['query'] = User::getQuery($params['key']);
			$data = self::search($params);
		}
		return isset($data) ? $data : false;
	}

	public static function lookup ( $params )
	{
		$data = \Shadow\Cache::get('lookup_' . $params['LOOKUP']);
		if (empty($cache)) {
			$data = array();
			foreach (\Shadow\Mongo::get()->lookup->find(array('lookup' => $params['LOOKUP']), array('short', 'long'))->sort(array('long' => 1)) as $row) {
				$data[$row['short']] = $row['long'];
			}
			\Shadow\Cache::set('lookup_' . $params['LOOKUP'], $data);
		}
		return isset($data) ? array('data' => $data) : false;
	}

	public static function metadata ()
	{
		$data = \Shadow\Cache::get('metadata');
		if (empty($data)) {
			foreach (\Shadow\Mongo::get()->metadata->find()->sort(array('system' => 1)) as $field) {
				if ($field['datatype'] == 'Character') {
					if ($field['length'] == 1) {
						$field['datatype'] = 'Boolean';
					} else {
						if (preg_match('#Lookup#', $field['interpretation'])) {
							$field['datatype'] = $field['interpretation'];
						}
					}
				}
				$data[] = array(
					'value' => $field['system'],
					'label' => strtolower($field['system']),
					'datatype' => $field['datatype'],
					'desc' => $field['long']
				);
			}
			\Shadow\Cache::set('metadata', $data);
		}
		return isset($data) ? array('data' => $data) : false;
	}

	public static function statistic ()
	{
		$data = array(
			'properties' => Statistic::get('properties'),
			'connections' => Statistic::get('connections'),
			'request' => Statistic::get('request')
		);
		return isset($data) ? array('data' => $data) : false;
	}

	public static function search ( $params )
	{
		// Check params and set default values if empty
		foreach (\Shadow\Config::get('api.property.default') as $key => $value) {
			if (empty($params[$key])) {
				$params[$key] = $value;
			}
		}

		// Params to Query
		$search = array();

		// Basic
		if (!empty($params['query'])) {

			if (!empty($params['query']['basic'])) {

				foreach ($params['query']['basic'] as $key => $value) {
					$datatype = Filter::getDataType($key);
					if ($key == 'MONTHLYMAINT') {
						$datatype = 'Int';
					}
					if (isset($params['query']['basic'][$key]['operator'])) {
						switch ($params['query']['basic'][$key]['operator']) {
							case 'equal':
								$search[$key] = $params['query']['basic'][$key]['min'];
								break;

							case 'not_equal':
								$search[$key] = array('$ne' => $params['query']['basic'][$key]['min']);
								break;

							case 'like':
								$search[$key] = new \MongoRegex('/' . $params['query']['basic'][$key]['min'] . '/');
								break;

							case 'not_like':
								$search[$key] = array('$not' => new \MongoRegex('/' . $params['query']['basic'][$key]['min']) . '/');
								break;
						}
					} else {
						if (isset($params['query']['basic'][$key]['max']) && isset($params['query']['basic'][$key]['min'])) {

							if ($key != 'LISTPRICE') {
								if (!isset($search['$and'])) {
									$search['$and'] = array();
								}
								$search['$and'][] = array(
									'$or' => array(
										array($key => array('$exists' => false)),
										array('$and' => array(
												array($key => array('$exists' => true)),
												array($key => 0)
											)
										),
										array('$and' => array(
												array($key => array('$exists' => true)),
												array($key => array(
														'$gte' => $params['query']['basic'][$key]['min'],
														'$lte' => $params['query']['basic'][$key]['max']
													)
												)
											)
										)
									)
								);
							} else {
								$search[$key] = array(
							        '$gte' => $params['query']['basic'][$key]['min'],
							        '$lte' => $params['query']['basic'][$key]['max']
								);
							}

						} else {
							$index = isset($params['query']['basic'][$key]['min']) ? 'min' : 'max';
							if (is_array($params['query']['basic'][$key][$index])) {
								if (count($params['query']['basic'][$key][$index]) == 1) {
									$search[$key] = $params['query']['basic'][$key][$index][0];
									if ($key == 'MLSNUM') {
										$search[$key] = (int)$search[$key];
									}
								} else {
									$search[$key] = array('$in' => $params['query']['basic'][$key][$index]);
									if ($key == 'MLSNUM') {
										$integers = array();
										foreach ($params['query']['basic'][$key][$index] as $integer) {
											$integers[] = (int)$integer;
										}
										$search[$key] = array('$in' => $integers);
									}
								}
							} else {
								if (preg_match('#DateTime|Decimal|Int#', $datatype)) {
									$operator = $index == 'min' ? '$gte' : '$lte';
									if ($key != 'LISTPRICE') {
	                                    if (!isset($search['$and'])) {
                                            $search['$and'] = array();
                                        }
                                        $search['$and'][] = array(
                                	        '$or' => array(
												array($key => array('$exists' => false)),
    	                	                        array('$and' => array(
		                                                array($key => array('$exists' => true)),
    	                                                array($key => 0)
                	                                )
                            	                ),
                                    	        array('$and' => array(
                                    	                array($key => array('$exists' => true)),
                                            	        array($key => array($operator => $params['query']['basic'][$key][$index]))
                        	                        )
                    	                        )
                	                        )
	        	                        );
    		                        } else {
                	                    $search[$key] = array($operator => $params['query']['basic'][$key][$index]);
                            		}
						
								} else {
									if (is_bool($params['query']['basic'][$key][$index]) && empty($params['query']['basic'][$key][$index])) {
                                        if (!isset($search['$and'])) {
											$search['$and'] = array();
                                        }
										$search['$and'][] = array(
       										'$or' => array(
									            array(
									        		'$and' => array(
									                	array($key => array('$exists' => true)),
								        	            array($key => false)
									                )
									            ),
									            array($key => array('$exists' => false))
										    )
										);
									} else {
                                    	$search[$key] = $params['query']['basic'][$key][$index];
                                    }
								}
							}
						}
					}
				}
			}
			
			// Logic
			if (!empty($params['query']['logic'])) {
				foreach ($params['query']['logic'] as $logic => $data) {
					if (preg_match('#AND|OR#', $logic)) {
						foreach ($params['query']['logic'][$logic] as $key => $item) {
							if (!is_array($params['query']['logic'][$logic][$key]['min'])) {
								$params['query']['logic'][$logic][$key]['min'] = (array)$params['query']['logic'][$logic][$key]['min'];
							}
							
							if ($logic == 'OR') {
								$or = array();
								foreach ($params['query']['logic'][$logic][$key]['min'] as $value) {
									$or[] = array($key => $value);
								}
								$params['query']['logic'][$logic][$key]['min'] = array('$or' => $or);
							}

							foreach ($params['query']['logic'][$logic][$key]['min'] as $index => $value) {
								if (!isset($search['$and'])) {
									$search['$and'] = array();
								}
								$search['$and'][] = ($logic == 'OR' && $index == '$or') ? array('$or' => $value) : array($key => $value);
							}
						}	
					} else {
						foreach ($params['query']['logic'][$logic] as $key => $value) {
							if (!is_array($params['query']['logic'][$logic][$key]['min'])) {
								$params['query']['logic'][$logic][$key]['min'] = (array)$params['query']['logic'][$logic][$key]['min'];
							}
							$search[$key] = array('$nin' => $params['query']['logic'][$logic][$key]['min']);
						}
					}
			
				}
			}
		}

		if (isset($params['scope']) && !empty($params['scope']) && $params['scope'] != 'all') {
			$start = \Shadow\Time::format(date('Y-m-d')) - 86400;
			$end = \Shadow\Time::format(date('Y-m-d')) + 86400;
			switch ($params['scope') {
				case 'daily':
					$search['SYSTEM_MODIFIED'] = array('$gte' => $start, '$lt' => $end);
					break;
				case 'weekly':
					$start = $start - (86400 * 5);
					$search['SYSTEM_MODIFIED'] = array('$gte' => $start, '$lt' => $end);
					break;
			}
			unset($params['scope']);
		}

		$response = array();	
	
		if (isset($params['count']) && !empty($params['count'])) {
			$response['data'] = \Shadow\Mongo::get()->x_properties->count($search);
		} else {

			if ($params['page'] > 1) {
				$params['page'] = ($params['page']-1) * $params['limit'];
			} else {
				$params['page'] = 0;
			}

			if (!empty($params['report'])) {
				$params['columns'] = array('_id');
				$params['sort']['SYSTEM_MODIFIED'] = -1;
			}

			if (!empty($params['statistics'])) {
				$params['columns'] = array('LISTDATE', 'OLD_LISTSTATUS', 'OLD_LISTPRICE');
				$cursor =  \Shadow\Mongo::get()->x_properties->find($search, $params['columns']);
				$statistics = array('new' => 0, 'listprice' => 0, 'liststatus' => 0);
				foreach ($cursor as $key => $property) {
					if (!isset($property['OLD_LISTSTATUS']) && $property['OLD_LISTPRICE']) {
						$statistics['new']++;
					} else {
						if (isset($property['OLD_LISTSTATUS'])) {
							$statistics['liststatus']++;
						} else {
							$statistics['listprice']++;
						}
					}
				}
			} else {
				$cursor = \Shadow\Mongo::get()->x_properties->find($search, $params['columns'])->skip($params['page'])->limit($params['limit'])->sort($params['sort']);
			}

			$response['count'] = 0;
			$response['data'] = array();
			
			foreach ($cursor as $key => $property) {
				if (empty($params['statistics'])) {
					$response['data'][$key] = $property;
				}
				$response['count']++;
			}
		
			if (!empty($params['statistics'])) {
				$response['data'] = $statistics;
			}

			if (!empty($params['rows'])) {
				$response['rows'] = $cursor->count();
			}

			if (empty($params['statistics']) && !empty($params['report'])) {
				$cursor = \Shadow\Mongo::get()->x_properties_decoded->find(array('_id' => array('$in' => array_keys($response['data']))))->sort(array('SYSTEM_MODIFIED' => -1));
				$response['data'] = array();
				foreach ($cursor as $key => $property) {
					$response['data'][$key] = $property;
				}
			}

		}
		return $response;		
	}

	public static function decodeParams ( $params )
	{
		if ($params = base64_decode($params, true)) {
			$params = json_decode($params, true);
			foreach ($params as $key => $value) {
				if (empty($value)) {
					unset($params[$key]);
				}
			}

			if (!empty($params['sort'])) {
				foreach ($params['sort'] as $key => $value) {
					$params['sort'][$key] = preg_match('#desc#i', $params['sort'][$key]) ? -1 : 1;
				}
			}

		} else {
			unset($params);
		}
		return isset($params) ? $params : false;
	}
}