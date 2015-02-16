<?php

namespace App\Model;

class Statistic
{

	public static function get ( $activity )
	{
		$data = array();
		$activity = strtolower($activity);
		if ($activity == 'connections') {
			$connections = \Shadow\Mongo::get()->request->find()->sort(array('start' => -1));
			if (!empty($connections)) {
				foreach ($connections as $connection) {
					$id = implode("-", str_split($connection['_id']->{'$id'}, 6));
					$data[$id] = array(
						'id' => $id,
						'date' => date('Y-m-d H:i:s', $connection['start'])
					);
				}
			}
		} else {
			$collection = 'statistics_' . $activity;
			$start = strtotime(date('Y-m-d 00:00:00', microtime(true)));
				if ($activity == 'properties') {
					for ($i = 0; $i < 10; $i++) {
					$activities = \Shadow\Mongo::get()->{$collection}->find(array('date' => date('Y-m-d', $start)))->limit(2);
					if (!empty($activities)) {
						# $data[date('Y-m-d', $start)] = array();
						foreach ($activities as $activity) {
							unset($activity['_id']);
							if (isset($activity['api'])) {
								unset($activity['api']);
								$data[date('Y-m-d', $start)]['api'] = (array)$activity;
							} else {
								$data[date('Y-m-d', $start)]['system'] = (array)$activity;
							}
						}
					}
					$start = $start - 86400;
					}
				} else {
					for($i = 0; $i < 10; $i++) {
					$activities = \Shadow\Mongo::get()->{$collection}->findOne(array('date' => date('Y-m-d', $start)));
					if (!empty($activities)) {
						unset($activities['_id']);
						$data[date('Y-m-d', $start)] = (array)$activities;
					}
					$start = $start - 86400;
					}
				}
		}
		# print_r($data);
		return $data;
	}

	/**
	 * Log Request Activity
	 * @return null
	 */
	public static function request ()
	{
		$now = date('Y-m-d', microtime(true));
		$start = strtotime(date('Y-m-d 00:00:00', strtotime($now) - 86400));
		
		$simultaneous_requests = (int)\Shadow\Mongo::get()->request->count(array('start' => array('$gt' => $start)));
		if ($simultaneous_requests == 0) {
			$simultaneous_requests = 1;
		}

		$data = \Shadow\Mongo::get()->statistics_request->findOne(array('date' => $now));
		if (!empty($data)) {
			$data['sent'] = $data['sent'] + 1;
			if ($simultaneous_requests > $data['simultaneous_requests']) {
				$data['simultaneous_requests'] = $simultaneous_requests;
			}
			\Shadow\Mongo::get()->statistics_request->update(array('date' => $data['date']), $data);
		} else {
			\Shadow\Mongo::get()->statistics_request->insert(array(
					'date' => $now,
					'simultaneous_requests' => $simultaneous_requests,
					'sent' => 1
				)
			);
		}
	}

	/**
	 * Log Property Activity
	 * @return null
	 */
	public static function properties ( $new_data, $old_data )
	{
		if (!empty($new_data) && (int)\Shadow\Mongo::get()->statistics_properties_log->count(array('MLSNUM' => $new_data['MLSNUM'], 'MODIFIED' => $new_data['MODIFIED'])) == 0) {

			$log = array(
				'MLSNUM' => $new_data['_id'],
				'LISTDATE' => $new_data['LISTDATE'],
				'MODIFIED' => $new_data['MODIFIED'],
				'LISTSTATUS_0' => isset($old_data['LISTSTATUS']) ? $old_data['LISTSTATUS'] : '',
				'LISTSTATUS_1' => $new_data['LISTSTATUS']
			);
			\Shadow\Mongo::get()->statistics_properties_log->insert($log);

			$insert = false;
			$now = date('Y-m-d', \Shadow\Rets::now());
			$data = \Shadow\Mongo::get()->statistics_properties->findOne(array('date' => $now));

			if (empty($data)) {
				$insert = true;
				$data = array(
					'date' => $now,
					'ACT' => 0,
					'OP' => 0,
					'PEND' => 0,
					'PSHO' => 0,
					'CLOSD' => 0,
					'TERM' => 0,
					'WITH' => 0,
					'EXP' => 0
				);
			}

			if (empty($old_data) || (!empty($old_data) && $new_data['LISTSTATUS'] != $old_data['LISTSTATUS'])) {
				$data[$new_data['LISTSTATUS']]++;
			}

			if ($insert) {
				// \Shadow\Mongo::get()->statistics_properties->insert($data);
			} else {
				// \Shadow\Mongo::get()->statistics_properties->update(array('date' => $now), $data);
			}
		}
	}

	public static function generate ()
	{
		$modified = strtotime(date('Y-m-d 00:00:00', \Shadow\Rets::now()));
		$data = array('date' => date('Y-m-d', $modified), 'api' => true, 'total_active_properties' => 0);

		$proptypes = array();

		// Get total active properties
		foreach (\Shadow\Mongo::get()->lookup->find(array('lookup' => 'PROPTYPE'), array('short')) as $proptype) {
			$proptype = strtoupper($proptype['short']);
			$proptypes[] = $proptype;
			$data['total_active_properties'] += _Property::getRows($proptype, array('ACT'));
		}
		print_r($data);

		// Get total properties by liststatus to date
		foreach (\Shadow\Mongo::get()->lookup->find(array('lookup' => 'LISTSTATUS')) as $liststatus) {
			$liststatus = $liststatus['short'];
			if (!isset($data[$liststatus])) {
				$data[$liststatus] = 0;
			}
			foreach ($proptypes as $proptype) {
				$data[$liststatus] += _Property::getRows($proptype, $liststatus, $modified);
			}
			print_r($data);
		}
	print_r($data);

		if (\Shadow\Mongo::get()->statistics_properties->count(array('date' => date('Y-m-d', $modified), 'api' => true)) > 0) {
			\Shadow\Mongo::get()->statistics_properties->update(array('date' => $data['date'], 'api' => $data['api']), $data);
		} else {
			\Shadow\Mongo::get()->statistics_properties->insert($data);
		}
	}

	/**
	 * Get active connections
	 * @return array
	 */
	public static function connections ()
	{
		$data = array();
		$requests = \Shadow\Mongo::get()->requests->find();

		if (!empty($requests)) {
			foreach ($requests as $request) {
				$data[] = $request;
			}
		}

		return $data;
	}

}
