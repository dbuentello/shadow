<?php

namespace App\Model;

class Legacy
{
	public function lookup ()
	{
		$lookups = Request::get(Request::REQUEST_DATA)->getAllLookupValues('Property');
		\Shadow\Mongo::get()->createCollection('lookup');
		foreach ( $lookups as $lookup ) {
			foreach ( $lookup['Values'] as $value ) {

				$lookup['Lookup'] = preg_replace('#\_\w+#', '', $lookup['Lookup']);
				$data = array(
                    'lookup' => $lookup['Lookup'],
                    'short' => $value['Value'],
                    'long' => $value['LongValue']
				);
				if ( \Shadow\Mongo::get()->lookup->count($data) == 0 ) {
					\Shadow\Mongo::get()->lookup->insert($data);
				}
			}
		}

	}

	public function property ()
	{
		$proptype = \Shadow\Mongo::get()->lookup->find(array('lookup' => 'PROPTYPE'), array('short'));
		foreach ( $proptype as $type ) {
		
			$search = Request::get(Request::REQUEST_DATA)->SearchQuery('Property', $type['short'], '(ListDate=2014-01-10+)', array('Format' => 'COMPACT'));
			while ( $listing = Request::get()->FetchRow($search) ) {
				if (!empty($listing)) {
					if ( in_array(strtolower(trim($listing['LISTSTATUS'])), array('act', 'pend', 'psho', 'op'))) {
						if ( \Shadow\Mongo::get()->properties->count(array('_id' => $listing['MLSNUM'])) > 0 ) {
							\Shadow\Mongo::get()->properties->update(array('_id' => $listing['MLSNUM']), $listing);
						} else {
							$listing['_id'] = $listing['MLSNUM'];
							\Shadow\Mongo::get()->properties->insert($listing);
						}
					} else {
						\Shadow\Mongo::get()->properties->remove(array('_id' => $listing['MLSNUM']));
					}
				}
			}
			
			Request::end();
		}
	}

	public function metatable ()
	{
		$proptype = \Shadow\Mongo::get()->lookup->find(array('lookup' => 'PROPTYPE'), array('short'));
		\Shadow\Mongo::get()->createCollection('metadata');
		foreach ( $proptype as $type ) {
			$fields = Request::get(Request::REQUEST_DATA)->GetMetadataTable('Property', $type['short']);
			foreach ( $fields as $field ) {
				$data = array(
					'db' => $field['DBName'],
					'system' => $field['SystemName'],
					'long' => $field['LongName'],
					'short' => $field['ShortName'],
					'datatype' => $field['DataType'],
					'interpretation' => $field['Interpretation']
				);
				if ( \Shadow\Mongo::get()->metadata->count($data) == 0 ) {
					\Shadow\Mongo::get()->metadata->insert($data);
					print_r($data);	
				}
			}
		}
	}

	public function compact ()
	{
		$properties = \Shadow\Mongo::get()->properties_x->find(array(), array('proptype', 'PROPTYPE'));
		\Shadow\Mongo::get()->createCollection('properties');

		$proptype = array(
			"Lots" => "LND",
	        "Rental" => "RNT",
	        "Single-Family" => "RES",
	        "Townhouse/Condo" => "CND",
	        "Country Homes/Acreage" => "ACR",
	        "Multi-Family" => "MUL",
	        "Mid/Hi-Rise Condo" => "HIR"
	    );

		foreach ( $properties as $property ) {

			if ( isset($property['proptype']) ) {
				$type = $proptype[$property['proptype']];
			} else {
				$type = $property['PROPTYPE'];
			}

			$property = Property::getItem($property['_id'], $type);

			if (Filter::active($property['LISTSTATUS'])) {
				Property::add($property);
			} else {
				Property::remove($property['MLSNUM'], $property['LISTSTATUS'], strtotime($property['MODIFIED']));
			}
		}
	}

	public static function populate ()
	{
		foreach ( \Shadow\Mongo::get()->lookup->find(array('lookup'=>'PROPTYPE'),array('short')) as $proptype ) {
			$search = Request::get(Request::REQUEST_DATA)->SearchQuery('Property', $proptype['short'], '(ListStatus=act,op,pend,psho)', array('Select' => 'MLSNum,ListStatus,ListPrice,Area,ZipCode,GeoMarketArea,Photocount,PropClass,ListDate,Modified,PhotoDate', 'Format' => 'COMPACT'));
			while ($property = Request::get()->FetchRow($search)) {
				
				$property['_id'] = $property['MLSNUM'];
				$property['PROPTYPE'] = $proptype['short'];

				print_r($property);
				$property = Filter::prepare($property);
				\Shadow\Mongo::get()->property_details->insert($property);
				/*
				if ((int)\Shadow\Mongo::get()->property_details->count(array('_id'=>$property['MLSNUM'])) == 0) {
					Property::add($property);
				} else {
					$data = \Shadow\Mongo::get()->properties->findOne(array('_id'=>$property['_id']));
					$update = false;
					foreach ($property as $key => $value) {
						if (!$update) {
							if ($property[$key] != $data[$key]) {
								echo "Update {$property['_id']}\n";
								Property::add($property);
							}
						}
					}
				}
				*/
			}
			Request::end();
		}
	}

	public static function validate ()
	{
		foreach (\Shadow\Mongo::get()->property_details->find() as $property) {
			if (Filter::active($property['LISTSTATUS'])) {
				$property = Filter::prepare($property, true);
				if (Filter::valid($property)) {
					if ((int)\Shadow\Mongo::get()->properties->count(array('_id' => $property['_id'])) == 0) {
						\Shadow\Mongo::get()->properties->insert($property);
						echo "### Added {$property['_id']}\n";
					} else {
						$data = \Shadow\Mongo::get()->properties->findOne(array('_id' => $property['_id']));
						foreach ($property as $key => $value) {
							$data[$key] = $value;
						}
						\Shadow\Mongo::get()->properties->update(array('_id' => $property['_id']), $data);
						echo "### Updated {$property['_id']}\n";
					}
				}
			}
		}
	}



	public static function filter ()
	{
		$properties = \Shadow\Mongo::get()->property_details->find(array('PROPTYPE'=>strtoupper($proptype)));

		$i = 1;
		foreach ($properties as $property) {
			echo "{$i}.\n";
			if (Filter::active($property['LISTSTATUS'])) {
				Property::add($property);
			} else {
				Property::remove($property['MLSNUM']);
			}
			$i++;
		}
	}

	public function compare ( $proptype )
	{
		$proptype = strtoupper($proptype);
		$search = Request::get(Request::REQUEST_DATA)->SearchQuery('Property', $proptype, '(ListStatus=act,op,pend,psho)', array('Select' => 'MLSNum,ListStatus,ListPrice,Area,ZipCode,GeoMarketArea', 'Format' => 'COMPACT'));

		\Shadow\Mongo::get()->createCollection('property_details');
		$i = 0;
		while ($property = Request::get()->FetchRow($search)) {
			$property['_id'] = $property['MLSNUM'];
			$property['PROPTYPE'] = $proptype;
			print_r($property);
			\Shadow\Mongo::get()->property_details->insert($property);
			$i++;
		}

		echo $i;

		Request::end();
	}

	public static function detail ()
	{
		foreach (\Shadow\Mongo::get()->properties->find(array('DAYSONMARKET' => array('$exists' => false)))->limit(100) as $property)
		{
			$proptype = $property['TYPE'] == 'Rent' ? 'RNT' : $property['PROPTYPE'];
			$property = Property::getItem($property['MLSNUM'], $proptype);
			Filter::active($property['LISTSTATUS']) ? Property::add($property) : Property::remove($property);
		}
	}

	public static function photo ()
	{
		$config = \Shadow\Config::get('app.path');
		foreach (scandir($config['object']) as $property) {
			$property = (int)$property;
			if (!in_array($property, array('.', '..'))) {
				$photos = array();
				foreach (scandir($config['object'] . DIRECTORY_SEPARATOR . $property) as $photo) {
					if (!in_array($photo, array('.', '..'))) {
						$photos[$photo] = array(
							'mlsnum' => $property,
							'created_at' => filemtime($config['object'] . DIRECTORY_SEPARATOR . $property),
							'name' => $photo,
							'size' => filesize($config['object'] . DIRECTORY_SEPARATOR . $property . DIRECTORY_SEPARATOR . $photo)
						);
					}
				}

				$data = \Shadow\Mongo::get()->properties->findOne(array('_id' => $property), array('PHOTOCOUNT', 'TYPE', 'PROPTYPE'));
				if (empty($data)) {
					Photo::remove($property);
				} else {
					if (count($photos) == $data['PHOTOCOUNT']) {
						foreach($photos as $photo) {
							\Shadow\Mongo::get()->photo_logs->insert($photo);
						}
					} else {
						$images = Request::get(Request::REQUEST_OBJECT)->GetObject('Property', 'Photo', $property, '*', 1);
						if (count($images) != $data['PHOTOCOUNT']) {
							$data = Property::getItem($property, $data['TYPE'] == 'Rent' ? 'RNT' : $data['PROPTYPE']);
							Property::add($data);
							$data = \Shadow\Mongo::get()->properties->findOne(array('_id' => $property), array('PHOTOCOUNT', 'TYPE', 'PROPTYPE'));
							if (!empty($data)) {
								if ($data['PHOTOCOUNT'] > 0) {
									if (count($images) == $data['PHOTOCOUNT']) {
										foreach($photos as $photo) {
											\Shadow\Mongo::get()->photo_logs->insert($photo);
										}
									} else {
										// get unique from arrays and delete existing images from disk and db

									}
								} else {
									Photo::remove($property);
								}
							}
						}
					}
				}
			}
		}
	}
}