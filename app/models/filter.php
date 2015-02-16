<?php

namespace App\Model;

class Filter
{
	protected static $lookup = array();
	protected static $fields = array();
	protected static $filters = array();

	public static function prepare ( $property, $format = false )
	{
		$property['_id'] = (int)$property['MLSNUM'];

		if (!empty($property['_id'])) {

			foreach (self::getFields() as $field) {
				if (isset($property[$field['system']])) {
					switch ($field['datatype']) {
						case 'Character':
							if (!is_array($property[$field['system']])) {
								$property[$field['system']] = trim($property[$field['system']]);
								if ($field['length'] == 1) {
									$property[$field['system']] = strtoupper($property[$field['system']]) == 'Y' ? true : false;
								} else {
									if (preg_match('#^Lookup#', $field['interpretation'])) {
										$property[$field['system']] = strtoupper($property[$field['system']]);
										if ($field['interpretation'] == 'LookupMulti') {
											$property[$field['system']] = array_values(
												array_filter(
													explode(",", str_replace(' ', '', $property[$field['system']])), function ( $value ) {
														if (!empty($value)) {
															return $value;
														}
													}
												)
											);
										}
									}
								}
							}
							break;
						case 'DateTime':
							$property[$field['system']] = is_numeric($property[$field['system']]) ? $property[$field['system']] : strtotime($property[$field['system']]);
							break;
						case 'Decimal':
						case 'Int':
							$property[$field['system']] = floatval($property[$field['system']]);
							break;
						default:
							$property[$field['system']] = trim($property[$field['system']]);
					}
				}
			}

			if ($format) {

				$property['TYPE'] = 'Sale';
				$property['ADDRESS'] = array();

				$property['ADDRESS'] = $property['STREETNUMDISPLAY'] . ' ' . $property['STREETNAME'];
				$property['ADDRESS'] = trim(ucwords(strtolower($property['ADDRESS'])));

				if ($property['PROPTYPE'] == 'HIR' && !empty($property['PARKINGSPACES'])) {
					$property['GARAGECAP'] = $property['PARKINGSPACES'];
				}

				if ($property['PROPTYPE'] == 'RNT') {

					$property['TYPE'] = 'Rent';

					switch ($property['PROPCLASS']) {
						case 'LOT':
							$property['PROPTYPE'] = 'LND';
							break;
						case 'MLT':
							$property['PROPTYPE'] = 'MUL';
							break;
						case 'SGL':
							$property['PROPTYPE'] = 'RES';
							break;
						case 'THC':
							$property['PROPTYPE'] = 'CND';
							break;
						default:
							$property['PROPTYPE'] = $property['PROPCLASS'];
					}
				}

				switch ($property['PROPTYPE']) {
					case 'ACR':
					case 'LND':
					case 'MUL':
					case 'RES':
						$property['MONTHLYMAINT'] = !empty($property['ANNUALMAINTFEE']) ? round($property['ANNUALMAINTFEE']/12) : 0;
						break;
					default:
						$property['MAINTFEE'] = !empty($property['MAINTFEE']) ? (int)$property['MAINTFEE'] : 0;
						$property['MONTHLYMAINT'] = $property['MAINTFEE'];
						
						if (!empty($property['MONTHLYMAINT'])) {
							if (!empty($property['MAINTFEEPAYSCHEDULE'])) {
								switch ($property['MAINTFEEPAYSCHEDULE']) {
									case 'ANNUALLY':
										$property['MONTHLYMAINT'] = round($property['MAINTFEE']/12);
										break;
									case 'QUARTERLY':
										$property['MONTHLYMAINT'] = round($property['MAINTFEE']/3);
										break;
									default:
										$property['MONTHLYMAINT'] = round($property['MAINTFEE']);
								}
							}
						}
				}
			}

			ksort($property);
			return $property;
		}
	}

	public static function active ( $liststatus )
	{	
		$liststatus = trim(strtoupper($liststatus));
		return in_array($liststatus, array('ACT', 'PEND', 'PSHO', 'OP'));
	}

	public static function update ()
	{
	}

	public static function getFilters ()
	{
		if(empty(self::$filters)) {
			if ($filters = \Shadow\Mongo::get()->filters->find()) {
				foreach ($filters as $filter) {
					self::$filters[] = $filter;
				}
			}
		}
		return self::$filters;
	}

	public static function getFields ( $system = null )
	{
		if (empty(self::$fields)) {
			$fields = \Shadow\Mongo::get()->metadata->find();
			foreach ($fields as $field) {
				self::$fields[$field['system']] = $field;
			}
		}
		return !empty($system) ? self::$fields[$system] : self::$fields;
	}

	public static function getLocalFilterColumns ()
	{
		$columns = array();
		foreach(self::getFilters() as $filter) {
			if (self::isLocalFilter($filter['field'])) {
				$columns[] = $filter['field'];
			}
		}
		return $columns;
	}

	/**
	 * Convert filter conditions to PHRETS string
	 * @return array $query
	 */
	public static function getQueryFilters ( $proptype = null, $liststatus = true )
	{
		$query = array();
		foreach(self::getFilters() as $filter) {
			if (!self::isLocalFilter($filter['field'])) {
				$field = self::getFields($filter['field']);

				if ($filter['field'] == 'LISTPRICE') {
					if ($proptype == 'RNT') {
						$filter['min'][0] = $filter['min'][1];
					}
				}

				switch ($filter['operator']) {
					case 'equal':
						if (in_array($field['datatype'], array('DateTime', 'Int', 'Decimal'))) {
							$query[$filter['field']] = '(' . $filter['field'] . '=' . floatval($filter['min'][0]) . ')';
						} else {
							$query[$filter['field']] = '(' . $filter['field'] . '=' . implode(',', $filter['min']) . ')';
						}
						break;

					case 'not_equal':
						if (in_array($field['datatype'], array('DateTime', 'Int', 'Decimal'))) {
							$query[$filter['field']] = '(' . $filter['field'] . '=~' . floatval($filter['min'][0]) . ')';
						} else {
							$query[$filter['field']] = '(' . $filter['field'] . '=~' . implode(',', $filter['min']) . ')';
						}
						break;

					case 'less_than':
						$filter['min'][0] = floatval($filter['min'][0]) - 1;
					case 'less_than_equal':
						$query[$filter['field']] = '(' . $filter['field'] . '=0-' . floatval($filter['min'][0]) . ')';
						break;

					case 'greater_than':
						$filter['min'][0] = floatval($filter['min'][0]) + 1;
					case 'greater_than_equal':
						$query[$filter['field']] = '(' . $filter['field'] . '=' . floatval($filter['min'][0]) . '+)';
						break;

					case 'between':
						$query[$filter['field']] = '(' . $filter['field'] . '=' . floatval($filter['min'][0]) . '-' . floatval($filter['max'][0]) . ')';
						break;
				}
			}

			if (!$liststatus) {
				unset($query['LISTSTATUS']);
			} else {
				if (is_array($liststatus)) {
					$query['LISTSTATUS'] = '(LISTSTATUS=' . $liststatus . ')';
				}
			}
		}

		return $query;
	}

	public static function isLocalFilter ( $system )
	{
		$field = self::getFields($system);
		return (in_array($field['datatype'], array('DateTime', 'Int', 'Decimal')) || ($field['system'] == 'Character' || ($field['length'] == 1 || preg_match('#Lookup#', $field['interpretation'])))) ? false : true;
	}

	public static function valid ( $property )
	{
		if (!empty($property['_id'])) {
			\Shadow\Logger::write("Validating {$property['_id']}");
			foreach(self::getFilters() as $filter) {
				if (self::isLocalFilter($filter['field'])) {
					if (!isset($property[$filter['field']]) && !self::$filter['operator']($filter, $property)) {
						return false;
					}
				}
			}
			\Shadow\Logger::write("TRUE");
			return true;
		}
	}

	public static function less_than ( $filter, $property )
	{
		if ($filter['field'] == 'LISTPRICE') {
			return (($property['TYPE'] != 'Rent' && floatval($property[$filter['field']]) < floatval($filter['min'][0])) || ($property['TYPE'] == 'Rent' && floatval($property[$filter['field']]) < floatval($filter['min'][1])));
		} else {
			return ($property[$filter['field']] < floatval($filter['min']));
		}
		return false;
	}

	public static function less_than_equal ( $filter, $property )
	{
		if ($filter['field'] == 'LISTPRICE') {
			return (($property['TYPE'] != 'Rent' && floatval($property[$filter['field']]) <= floatval($filter['min'][0])) || ($property['TYPE'] == 'Rent' && floatval($property[$filter['field']]) <= floatval($filter['min'][1])));
		} else {
			return ($property[$filter['field']] <= floatval($filter['min']));
		}
		return false;
	}

	public static function greater_than ( $filter, $property )
	{
		if ($filter['field'] == 'LISTPRICE') {
			return (($property['TYPE'] != 'Rent' && floatval($property[$filter['field']]) > floatval($filter['min'][0])) || ($property['TYPE'] == 'Rent' && floatval($property[$filter['field']]) > floatval($filter['min'][1])));
		} else {
			return ($property[$filter['field']] > floatval($filter['min']));
		}
		return false;
	}

	public static function greater_than_equal ( $filter, $property )
	{
		if ($filter['field'] == 'LISTPRICE') {
			return (($property['TYPE'] != 'Rent' && floatval($property[$filter['field']]) >= floatval($filter['min'][0])) || ($property['TYPE'] == 'Rent' && floatval($property[$filter['field']]) >= floatval($filter['min'][1])));
		} else {
			return ($property[$filter['field']] >= floatval($filter['min']));
		}
		return false;
	}

	public static function equal ( $filter, $property )
	{
		if ($property[$filter['field']] == 'LISTPRICE') {
			return (($property['TYPE'] != 'Rent' && floatval($property[$filter['field']]) == floatval($filter['min'][0])) || ($property['TYPE'] == 'Rent' && floatval($property[$filter['field']]) == floatval($filter['min'][1])));
		} else {
			if (is_bool($property[$filter['field']])) {
				return $property[$filter['field']];
			} else {
				switch (self::$fields[$filter['field']]['datatype']) {
					case 'DateTime':
					case 'Decimal':
					case 'Int':
						return (floatval($property[$filter['field']]) == floatval($filter['min']));
						break;
					default:
						if (is_array($filter['min'])) {
							$property[$filter['field']] = !is_array($property[$filter['field']]) ? array($property[$filter['field']]) : $property[$filter['field']];
							$intersect = array_intersect($property[$filter['field']], $filter['min']);
							return !empty($intersect);
						} else {
							return (strcmp($property[$filter['field']], $filter['min']) == 0);
						}
				}
			}
		}
		return false;
	}

	public static function not_equal ( $filter, $property )
	{
		if ($property[$filter['field']] == 'LISTPRICE') {
			return (($property['TYPE'] != 'Rent' && floatval($property[$filter['field']]) != floatval($filter['min'][0])) || ($property['TYPE'] == 'Rent' && floatval($property[$filter['field']]) != floatval($filter['min'][1])));
		} else {
			if (is_bool($property[$filter['field']])) {
				return !$property[$filter['field']];
			} else {
				switch (self::$fields[$filter['field']]['datatype']) {
					case 'DateTime':
					case 'Decimal':
					case 'Int':
						return (floatval($property[$filter['field']]) != floatval($filter['min']));
						break;
					default:
						if (is_array($filter['min'])) {
							$property[$filter['field']] = !is_array($property[$filter['field']]) ? array($property[$filter['field']]) : $property[$filter['field']];
							$intersect = array_intersect($property[$filter['field']], $filter['min']);
							return empty($intersect);
						} else {
							return (strcmp($property[$filter['field']], $filter['min']) != 0);
						}
				}
			}
		}
		return false;
	}

	public static function between ( $filter, $property )
	{
		if ($property[$filter['field']] == 'LISTPRICE') {
			return (($property['TYPE'] != 'Rent' && floatval($property[$filter['field']]) >= floatval($filter['min'][0]) && floatval($property[$filter['field']]) <= floatval($filter['max'][0])) || ($property['TYPE'] == 'Rent' && floatval($property[$filter['field']]) >= floatval($filter['min'][1]) && floatval($property[$filter['field']]) <= floatval($filter['max'][1])));
		} else {
			return (floatval($property[$filter['field']]) >= floatval($filter['min']) && floatval($property[$filter['field']]) <= floatval($filter['max']));
		}
		return false;
	}

	public static function getDataType ( $field )
	{
		if (!in_array($field, self::$fields)) {
			$data = \Shadow\Mongo::get()->metadata->findOne(array('system' => $field));
			if(!empty($data)) {
				if ($data['datatype'] == 'Character') {
					if ($data['length'] == 1) {
						$data['datatype'] = 'Boolean';
					} else {
						if (preg_match('#Lookup#', $data['interpretation'])) {
							$data['datatype'] = $data['interpretation'];
						}
					}
				}
				self::$fields[$data['system']] = $data['datatype'];
			}
		}
		return !empty(self::$fields[$field]) ? self::$fields[$field] : false;
	}

	public static function getLookupValue ( $field, $lookup )
	{
		if (!in_array($field, self::$lookup)) {
			if (!isset(self::$lookup[$field])) {
				self::$lookup[$field] = array();
			}
			if (!in_array($lookup, array_keys(self::$lookup[$field]))) {
				$data = \Shadow\Mongo::get()->lookup->findOne(array('lookup' => $field, 'short' => $lookup));
				if (!empty($data)) {
					self::$lookup[$field] = array(
						$lookup => $data['long']
					);
				}
			}
		}
		return !empty(self::$lookup[$field][$lookup]) ? self::$lookup[$field][$lookup] : false;
	}
}
