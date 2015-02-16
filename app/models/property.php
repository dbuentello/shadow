<?php

namespace App\Model;

class Property
{
	protected static $lookup = array();

	protected static $queue;

	protected static $columns = array('_id', 'MLSNUM', 'TYPE', 'PROPTYPE', 'PHOTODATE', 'LISTPRICE', 'LISTSTATUS', 'MODIFIED');

	protected static $statistics = array();

	protected static $photos = array();

	public static function get ( $modified = 0 )
	{
		self::resetQueue();
		foreach (self::getLookupData('proptype') as $proptype) {
			$parameters = _Filter::getQueryFilters($proptype, false);
			if (!empty($modified)) {
				$parameters = array_merge(array('(MODIFIED=' . date('Y-m-d\TH:i:s', $modified) . '+)'), $parameters);
			}

			$search = self::getOnlineProperties($proptype, $parameters);
			while ($property = self::fetchRow($search)) {
				$property['PROPTYPE'] = $proptype;
				self::queue($property);
			}
		}

		self::processQueue();
	}

	public static function sync ()
	{
		self::resetQueue();
		$now = \Shadow\Time::format(date('Y-m-d 00:00:00', time())) - 3600;
		foreach (self::getLookupData('proptype') as $proptype) {

			$properties = self::getLocalProperties($proptype);
			$parameters = _Filter::getQueryFilters($proptype);
			$search = self::getOnlineProperties($proptype, $parameters);

			while ($property = self::fetchRow($search)) {
				$property['PROPTYPE'] = $proptype;
				$local = isset($properties[(int)$property['MLSNUM']]) ? $properties[(int)$property['MLSNUM']] : false;
				if (!empty($local)) {
					unset($properties[(int)$property['MLSNUM']]);
				}
				self::queue($property, $local);
			}

			$parameters = array_merge(array('(LISTSTATUS=CLOSD,TERM,WITH,EXP)', '(MODIFIED=' . date('Y-m-d', $now) . '+)'), _Filter::getQueryFilters($proptype, false));
			$search = self::getOnlineProperties($proptype, $parameters); 

			while ($property = self::fetchRow($search)) {
				$property['PROPTYPE'] = $proptype;
				$local = isset($properties[(int)$property['MLSNUM']]) ? $properties[(int)$property['MLSNUM']] : false;
				if (!empty($local)) {
					unset($properties[(int)$property['MLSNUM']]);
				}
				self::queue($property, $local);
			}

			if (!empty($properties)) {
				foreach ($properties as $property) {
					$local = isset($properties[(int)$property['MLSNUM']]) ? $properties[(int)$property['MLSNUM']] : false;
					self::queue($property, $local, true);
				}
			}
		}
		self::processQueue();
		self::generateStatistics();
		self::getPhotos();
	}

	public static function queue ( $property, $local = false, $force_remove = false )
	{
		$property['_id'] = $property['MLSNUM'] = (int)$property['MLSNUM'];
		$property['MODIFIED'] = is_numeric($property['MODIFIED']) ?: \Shadow\Time::format($property['MODIFIED']);
		$property['LISTSTATUS'] = strtoupper($property['LISTSTATUS']);
		$local = !empty($local) ? $local : self::getLocalProperty($property['_id']);

		if (!isset(self::$queue['active'][$property['PROPTYPE']])) {
			self::$queue['active'][$property['PROPTYPE']] = array();
		}

		if (_Filter::active($property['LISTSTATUS']) && !$force_remove) {
			if (empty($local) || (!empty($local) && ($property['MODIFIED'] != $local['MODIFIED'] || $property['LISTSTATUS'] != $local['LISTSTATUS']))) {
				\Shadow\Logger::write("{$property['PROPTYPE']} Queue Insert: {$property['_id']}", \Shadow\Logger::INFO);
				self::$queue['active'][$property['PROPTYPE']][$property['_id']] = $property;
			}
		} else {
			\Shadow\Logger::write("Queue Remove: {$property['_id']}", \Shadow\Logger::WARNING);
			self::$queue['inactive'][$property['PROPTYPE']][$property['_id']] = $property;
		}

		if(empty($force_remove)) {
			self::statistics($property);
		}
	}

	public static function statistics ($property)
	{
		if ($property['LISTSTATUS'] == 'ACT') {
			if (!isset(self::$statistics['total_active_properties'])) {
				self::$statistics['total_active_properties'] = 0;
			}
			self::$statistics['total_active_properties']++;
		}

		if (!is_numeric($property['MODIFIED'])) {
			$property['MODIFIED'] = \Shadow\Time::format($property['MODIFIED']);
		}

		$start = \Shadow\Time::format(date('Y-m-d 00:00:00', time()));
		$end = $start + 86400;
		if ($property['MODIFIED'] >= $start && $property['MODIFIED'] < $end) {
			if (!isset(self::$statistics[$property['LISTSTATUS']])) {
				self::$statistics[$property['LISTSTATUS']] = 0;
			}
			self::$statistics[$property['LISTSTATUS']]++;
		}
	}

	public static function generateStatistics ()
	{	
		$start = \Shadow\Time::format(date('Y-m-d'));
		$end = $start + 86400;
		$pre_statistics = self::$statistics;
		$post_statistics = array('total_active_properties' => Schema::get('properties.active')->count(array('LISTSTATUS' => 'ACT')));

		foreach (self::$statistics as $liststatus => $value) {
			if ($liststatus != 'total_active_properties') {
				if (isset($post_statistics[$liststatus])) {
					$post_statistics[$liststatus] = 0;
				}
				$search = array('LISTSTATUS' => $liststatus, 'MODIFIED' => array('$gte' => $start, '$lt' => $end));
				if (_Filter::active($liststatus)) {
					$post_statistics[$liststatus] = Schema::get('properties.active')->count($search);
				} else {
					$post_statistics[$liststatus] = Schema::get('properties.inactive')->count($search);
					if ($post_statistics[$liststatus] != $pre_statistics[$liststatus]) {
						$post_statistics[$liststatus] = $pre_statistics[$liststatus];
					}
				}
			}
		}

		$pre_statistics['date'] = $post_statistics['date'] = date('Y-m-d', $start);
		$pre_statistics['modified'] = $post_statistics['modified'] = \Shadow\Time::format(date('Y-m-d H:i:s', time()));
		$pre_statistics['api'] = true;
		$post_statistics['system'] = true;

		Schema::get('properties.statistics')->update(array('date' => $pre_statistics['date'], 'api' => true), $pre_statistics, array('upsert' => true));
		Schema::get('properties.statistics')->update(array('date' => $post_statistics['date'], 'system' => true), $post_statistics, array('upsert' => true));
		// Old Statistics Table
		\Shadow\Mongo::get()->statistics_properties->update(array('date' => $pre_statistics['date'], 'api' => true), $pre_statistics, array('upsert' => true));
		\Shadow\Mongo::get()->statistics_properties->update(array('date' => $post_statistics['date'], 'system' => true), $post_statistics, array('upsert' => true));

		$request = \Shadow\Mongo::get()->statistics_request->findOne(array('date' => date('Y-m-d', $start)));
		if (!empty($request)) {
			$request['properties_added'] = Schema::get('properties.active')->count(array('LISTDATE' => array('$gte' => $start, '$lt' => $end)));
			$request['properties_updated'] = Schema::get('properties.active')->count(array('MODIFIED' => array('$gte' => $start, '$lt' => $end)));
			\Shadow\Mongo::get()->statistics_request->update(array('_id' => $request['_id']), $request);
		}

		$now = \Shadow\Time::format(date('Y-m-d'));
		$start = $now - 86400;
		$end = $now + 86400;

		Schema::get('properties.log.modified.details')->remove(array('date' => date('Y-m-d', $start)));
		$properties = Schema::get('properties.active')->find(array('SYSTEM_MODIFIED' => array('$gte' => $start, '$lt' => $end)))->sort(array('MODIFIED' => -1));
		if (!empty($properties)) {
			$summary = array('date' => date('Y-m-d', $now), 'new' => 0, 'status' => 0, 'price' => 0);
			foreach ($properties as $property) {
				$data = array(
					'date' => date('Y-m-d', $now),
					'MLSNUM' => $property['_id'],
					'LISTPRICE' => $property['LISTPRICE']
				);
				if (isset($property['OLD_LISTSTATUS']) && isset($property['OLD_LISTPRICE'])) {
					$status = Schema::get('properties.log.liststatus')->find(array('MLSNUM' => $property['_id']))->sort(array('MODIFIED' => -1))->limit(1);
					$status = array_values(iterator_to_array($status));
					$price = Schema::get('properties.log.listprice')->find(array('MLSNUM' => $property['_id']))->sort(array('MODIFIED' => -1))->limit(1);
					$price = array_values(iterator_to_array($price));
					$data['tag'] = 'STATUS';
					if ($price[0]['MODIFIED'] > $status[0]['MODIFIED']) {
						$data['tag'] = 'PRICE';
						$data['OLD_LISTPRICE'] = $property['OLD_LISTPRICE'];
					}
				} else {
					if (isset($property['OLD_LISTSTATUS']) || isset($property['OLD_LISTPRICE'])) {
						if (isset($property['OLD_LISTSTATUS'])) {
							$data['tag'] = 'STATUS';
						} else {
							$data['tag'] = 'PRICE';
						}
					}
				}

				if (!isset($data['tag'])) {
					$summary['new']++;
				} else {
					if ($data['tag'] == 'STATUS') {
						$summary['status']++;
					} else {
						$summary['price']++;
					}
				}

				Schema::get('properties.log.modified.details')->update(array('date' => $data['date'], 'MLSNUM' => $data['MLSNUM']), $data, array('upsert' => true));
			}
			Schema::get('properties.log.modified.summary')->update(array('date' => date('Y-m-d', $now)), $summary, array('upsert' => true));
		}
	}

	public static function resetQueue ()
	{
		if (!isset(self::$queue['insert'])) {
			self::$queue = array(
				'active' => array(),
				'inactive' => array()
			);
		}
	}

	public static function processQueue ()
	{
		$system_modified = array('liststatus' => array(), 'listprice' => array(), 'new' => array());

		// Active
		if (!empty(self::$queue['active'])) {
			foreach (self::$queue['active'] as $proptype => $properties) {
				$queue = array_chunk($properties, \Shadow\Config::get('rets.request.max_property_detail_per_batch'), true);
				foreach ($queue as $properties) {
					$i = count($properties);
					while (!empty($i)) {
						$search = self::getOnlinePropertiesByBatch($proptype, $properties);
						while ($property = self::fetchRow($search)) {
							if (!empty($property)) {
								if (_Filter::active($property['LISTSTATUS'])) {
									$local = self::getLocalProperty((int)$property['MLSNUM']);
									$property = _Filter::prepare($property, true);
									if (!empty($local)) {

										if (isset($local['LISTPRICE']) && !empty($local['LISTPRICE']) && $local['LISTPRICE'] != $property['LISTPRICE']) {
											$property['OLD_LISTPRICE'] = $local['LISTPRICE'];
											Schema::get('properties.log.listprice')->update(
												array(
													'MLSNUM' => $property['_id'],
													'MODIFIED' => $property['MODIFIED']
												),
												array(
													'MLSNUM' => $property['_id'],
													'MODIFIED' => $property['MODIFIED'],
													'OLD_LISTPRICE' => $property['OLD_LISTPRICE'],
													'NEW_LISTPRICE' => $property['LISTPRICE']
												),
												array('upsert' => true)
											);
											$property['SYSTEM_MODIFIED'] = \Shadow\Time::format(date('Y-m-d H:i:s', time()));
											$system_modified['listprice'][] = $property['_id'];

										}

										if ($local['LISTSTATUS'] != $property['LISTSTATUS']) {
											$property['OLD_LISTSTATUS'] = $local['LISTSTATUS'];
											Schema::get('properties.log.liststatus')->update(
												array(
													'MLSNUM' => $property['_id'],
													'MODIFIED' => $property['MODIFIED']
												),
												array(
													'MLSNUM' => $property['_id'],
													'MODIFIED' => $property['MODIFIED'],
													'OLD_LISTSTATUS' => $property['OLD_LISTSTATUS'],
													'NEW_LISTSTATUS' => $property['LISTSTATUS']
												),
												array('upsert' => true)
											);

											if ($local['LISTSTATUS'] != 'ACT' && $property['LISTSTATUS'] == 'ACT') {
												$property['SYSTEM_MODIFIED'] = \Shadow\Time::format(date('Y-m-d H:i:s', time()));
												$system_modified['liststatus'][] = $property['_id'];
											}
										}

										Schema::get('properties.active')->update(array('_id' => $property['_id']), $property);
										\Shadow\Logger::write("DB Update: {$property['_id']}", \Shadow\Logger::NOTICE);

									} else {

										$property['SYSTEM_MODIFIED'] = \Shadow\Time::format(date('Y-m-d H:i:s', time()));
										$system_modified['new'][] = $property['_id'];
										Schema::get('properties.active')->insert($property);
										\Shadow\Logger::write("DB Insert: {$property['_id']}", \Shadow\Logger::INFO);

									}

									if ((!empty($local) && $property['PHOTODATE'] != $local['PHOTODATE']) || (empty($local) && $property['PHOTOCOUNT'] > 0)) {
										self::$photos[] = $property['_id'];
									}

									Schema::get('properties.decoded')->update(array('_id' => $property['_id']), self::decode($property), array('upsert' => true));
									self::processLog($property);
								} else {
									self::$queue['inactive'][$proptype][(int)$property['MLSNUM']] = $properties[(int)$property['MLSNUM']];
								}
								$i--;
							}
						}
					}
				}
			}
		}

		// Inactive
		foreach (self::$queue['inactive'] as $proptype => $properties) {
			foreach ($properties as $property) {
				self::remove($property);
				self::processLog($property);
			}
		}

		foreach ($system_modified as $key => $items) {
			if (!empty($items)) {
				foreach ($items as $property) {
					$modified = \Shadow\Time::format(date('Y-m-d H:i:s', time()));
					$property = Schema::get('properties.active')->findOne(array('_id' => $property));
					$property['SYSTEM_MODIFIED'] = $modified;
					Schema::get('properties.active')->update(array('_id' => $property['_id']), $property);
					$property = Schema::get('properties.decoded')->findOne(array('_id' => $property['_id']));
					$property['SYSTEM_MODIFIED'] = $modified;
					Schema::get('properties.decoded')->update(array('_id' => $property['_id']), $property);
					\Shadow\Logger::write("DB Modified: {$key} {$property['_id']}", \Shadow\Logger::INFO);
				}
			}
		}
	}

	public static function remove ( $property )
	{
		Photo::remove($property['_id']);
		Schema::get('properties.active')->remove(array('_id' => $property['_id']));
		Schema::get('properties.decoded')->remove(array('_id' => $property['_id']));
		if ((int)Schema::get('properties.inactive')->count(array('_id' => $property['_id'])) > 0) {
			Schema::get('properties.inactive')->update(array('_id' => $property['_id']), $property);
		} else {
			Schema::get('properties.inactive')->insert($property);
		}
	}

	public static function processLog ( $property )
	{
		$start = \Shadow\Time::format(date('Y-m-d 00:00:00', time()));
		$end = $start + 86400;
		$data = Schema::get('properties.log.history')->findOne(array('MLSNUM' => $property['MLSNUM'], 'MODIFIED' => array('$gte' => $start, '$lt' => $end)));
		if (!empty($data)) {
			if ($property['MODIFIED'] > $data['MODIFIED']) {
				if ($property['LISTSTATUS'] != $data['LISTSTATUS']) {
					$property['LISTSTATUS_OLD'] = $data['LISTSTATUS'];
				}
				$property['_id'] = $data['_id'];
				unset($data);
				Schema::get('properties.log.history')->update(array('_id' => $property['_id']), $property);
			}
		} else {
			Schema::get('properties.log.history')->insert($property);
		}
	}

	public static function getLookupData ( $lookup )
	{
		$lookup = strtoupper($lookup);
		if (!isset(self::$lookup[$lookup])) {
			foreach (Schema::get('metadata.lookup')->find(array('lookup' => $lookup), array('short')) as $item) {
				self::$lookup[$lookup][] = strtoupper($item['short']);
			}
		}
		return self::$lookup[$lookup];
	}

	public static function getBasicColumns ( $local = true )
	{
		$columns = self::$columns;
		if (!$local) {
			unset($columns[0], $columns[2], $columns[3], $columns[4], $columns[5]);
			$columns = array_values($columns);
		}
		return $columns;
	}

	public static function getLocalProperties ( $proptype = null )
	{
		$search = array();
		$properties = array();
		if (!empty($proptype)) {
			$search = $proptype == 'RNT' ? array('TYPE' => 'Rent') : array('TYPE' => 'Sale', 'PROPTYPE' => $proptype);
		}
		foreach (Schema::get('properties.active')->find($search, self::getBasicColumns()) as $property) {
			$property['PROPTYPE'] = $proptype;
			$properties[$property['_id']] = $property;
		}
		return $properties;
	}

	public static function getLocalProperty ( $mlsnum )
	{
		$property = Schema::get('properties.active')->findOne(array('_id' => (int)$mlsnum), self::getBasicColumns());
		if (!empty($property)) {
			$property['PROPTYPE'] = $property['TYPE'] == 'Rent' ? 'RNT' : $property['PROPTYPE'];
		} else {
			$property = false;
		}
		return $property;
	}

	public static function getOnlineProperties ( $proptype, $filters )
	{
		return Request::get()->SearchQuery('Property', $proptype, implode(',', $filters), array('Select' => implode(',', self::getBasicColumns(false)), 'Format' => 'COMPACT'));
	}

	public static function getOnlinePropertiesByBatch ( $proptype, $properties )
	{
		return Request::get()->SearchQuery('Property', $proptype, '(MLSNUM=' . implode(',', array_keys($properties)) . ')', array('Format' => 'COMPACT'));
	}

	public static function getOnlineProperty ( $property, $columns = array(), $retry = 3 )
	{
		$data = array();
		$options = array('Format' => 'COMPACT');
		if (!empty($columns)) {
			$options['Select'] = implode(',', self::getBasicColumns(false));
		}

		while (empty($data) || !empty($retry)) {
			$search = Request::get()->SearchQuery('Property', $property['PROPTYPE'], '(MLSNUM=' . $property['_id'] . ')', $options);
	        	$data = self::fetchRow($search);
        		if (empty($data)) {
	        		\Shadow\Logger::write("Retrying: {$property['_id']}");
			}
			$retry--;
		}

		if (empty($data)) {
			if ((int)Schema::get('properties.failed_request')->count(array('_id' => $property['_id'])) > 0) {
				Schema::get('properties.failed_request')->update(array('_id' => $property['_id']), $property);
			} else {
				Schema::get('properties.failed_request')->insert($property);
			}
		}

		return !empty($data) ? $data : false;
	}

	public static function fetchRow ( $search )
	{
		return Request::get(false)->FetchRow($search);
	}

	public static function getPhotos ()
	{
		if (!empty(self::$photos)) {
			foreach (self::$photos as $mlsnum) {
				Photo::init($mlsnum);
			}
		}
		Photo::grab();
	}

	public static function decode ( $property )
	{
		$merge = array('AREA', 'LOCATION', 'SCHOOLDISTRICT');

		foreach($property as $key => $value) {
			$property[$key] = $value;
			$datatype = Filter::getDataType($key);
			if (preg_match('#Lookup#', $datatype)) {
				$long = array();
				foreach ((array)$value as $lookup) {
					$long[] = trim(Filter::getLookupValue($key, $lookup));
				}
				$pre = false;
				if (in_array($key, $merge)) {
					$pre = $property[$key];
				}
				$property[$key] = !empty($pre) ? "{$pre} - " . implode(", ", $long) : implode(", ", $long);
			} else {
				if ($datatype == 'Boolean') {
					$property[$key] = $property[$key] ? 'Yes' : 'No';
				} else if ($datatype == 'DateTime') {
					$property[$key] = date('Y-m-d H:i:s', $property[$key]);
				}
			}
		}

		if ($office = Schema::get('properties.office')->findOne(array('_id' => $property['OFFICELIST']))) {
			$property['OFFICELIST'] = $office['_id'];
            $property['OFFICELIST_OFFICENAME'] = trim($office['OFFICENAME']);
            $property['OFFICELISTADDRESS'] = trim($office['ADDRESS1']) . ', ' . trim($office['CITY']) . ', ' . trim($office['STATE']);
            $property['OFFICELIST_PHONE'] = trim($office['PHONE']);
            $property['OFFICELISTADDRESS'] = trim(ucwords(strtolower($property['OFFICELISTADDRESS'])));
            $property['FAXNUMBER'] = trim($office['FAX']);
            $property['WEBADDRESS'] = trim($office['WEB']);
		}

		if ($agent = Schema::get('properties.active_agent')->findOne(array('_id' => $property['AGENTLIST']))) {
                $property['AGENTLIST'] = $agent['_id'];
                $property['AGENTLIST_FULLNAME'] = $agent['FULLNAME'];
                $property['AGENTWEB'] = $agent['WEB'];
        }

        return $property;
	}
}
