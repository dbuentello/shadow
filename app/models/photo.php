<?php

namespace App\Model;

class Photo
{
	const LOG_PATH = '/home/shadow/app/logs/lock_debug.log';

	protected static $ticket = false;
	private static $_lockTimeStart;

	public static function init ( $mlsnum )
	{
		Schema::get('photos.thumbnail')->remove(array('mlsnum' => $mlsnum));
		Schema::get('photos.photo')->remove(array('mlsnum' => $mlsnum));

		$images = Request::get(false)->GetObject('Property', 'Thumbnail', $mlsnum, '*', 1);

		if (!empty($images)) {
			$destination = \Shadow\Config::	get('app.path.object') . DIRECTORY_SEPARATOR . $mlsnum;
			$mkdir = self::createDirectory($destination);
			foreach ($images as $image) {
				$data = array(
					'mlsnum' => $mlsnum,
					'object_id' => (int)$image['Object-ID'],
					'source' => trim($image['Location']),
					'created' => microtime(true)
				);
				$image = $destination . DIRECTORY_SEPARATOR . basename($data['source']);

				if (file_exists($image) && filesize($image)) {
					$data['exists'] = true;
				}

				Schema::get('photos.thumbnail')->insert($data);
				if ($data['object_id'] == 1) {
					self::getPrimaryPhoto($data);
				}
				print_r($data);

				$data['source'] = str_replace('lr', 'hr', $data['source']);
				Schema::get('photos.photo')->insert($data);
			}
		}
	}

	public static function get ( &$data, $rets = false )
	{
		if (!$rets) {
			$data['source'] = parse_url($data['source']);
			$data['source'] = str_replace('/RETS/', 'http://harpictures.marketlinx.com/', $data['source']['path']);
		}

		// self::acquireLock($lock);
		$session = curl_init();
		curl_setopt($session, CURLOPT_URL, $data['source']);
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		$content = curl_exec($session);
		// self::freeLock($lock);
		$code = curl_getinfo($session, CURLINFO_HTTP_CODE);

		if ($code != 404) {
			$image = \Shadow\Config::get('app.path.object') . DIRECTORY_SEPARATOR . $data['mlsnum'] . DIRECTORY_SEPARATOR . basename($data['source']);
			if (!empty($content) && file_put_contents($image, $content)) {
				$data['exists'] = true;
			}
		} else {
			$image = false;
			Schema::get('photos.not_found')->update(
				array(
					'mlsnum' => $data['mlsnum'],
					'source' => $data['source']
				),
				array(
					'created' => microtime(true),
					'mlsnum' => $data['mlsnum'],
					'source' => $data['source']
				),
				array('upsert' => true)
			);
		}

		return \Shadow\Config::get('photo.stream') ? $data['source'] : $image;
	}

	public static function failed ()
	{
		// Schema::get('photos.thumbnail')->update(array('_id' => $data['_id']), $data);
		$cursor = Schema::get('photos.not_found')->find();
		foreach ($cursor as $data) {
			if (preg_match('#lr#', basename($data['source']))) {
				$schema = Schema::get('photos.thumbnail');
			} else {
				$schema = Schema::get('photos.photo');
			}
			Schema::get('photos.not_found')->remove(array('_id' => $data['_id']));
			$schema->remove(array('mlsnum' => $data['mlsnum'], 'source' => array('$regex' =>basename($data['source']))));
		}
	}

	public static function grab ()
	{
		$limit = \Shadow\Config::get('photo.max_property_update_download');
		foreach (\Shadow\Config::get('photo.search') as $search) {
			$search['PHOTOCOUNT'] = array('$gt' => 0);
			$properties = Schema::get('properties.active')->find($search, array('_id'))->sort(array('LISTDATE' => -1));
			if (!empty($properties)) {
				foreach ($properties as $property) {
					$cursor = Schema::get('photos.thumbnail')->find(array('mlsnum' => $property['_id']))->limit($limit);
					if (!$cursor->count()) {
						self::init($property['_id']);
						\Shadow\Logger::write("Initialize: {$property['_id']}", \Shadow\Logger::INFO);
						$cursor = Schema::get('photos.thumbnail')->find(array('mlsnum' => $property['_id']))->limit($limit);
					}
					foreach ($cursor as $data) {
						$image = \Shadow\Config::get('app.path.object') . DIRECTORY_SEPARATOR . $data['mlsnum'] . DIRECTORY_SEPARATOR . basename($data['source']);
						if (isset($data['exists'])) {
							if (!file_exists($image) || file_exists($image) && !filesize($image)) {
								unset($data['exists']);
							}
						} else {
							\Shadow\Logger::write("{$limit}. Downloading: {$property['_id']} - {$data['object_id']}", \Shadow\Logger::NOTICE);
							self::get($data);
							$limit--;
						}
						Schema::get('photos.thumbnail')->update(array('_id' => $data['_id']), $data);
					}
					if (empty($limit)) {
						break;
					}
				}
			}
			if (empty($limit)) {
				break;
			}
		}
		self::clean();
	}

	public static function clean ()
	{
		foreach (Schema::get('properties.active')->find(array('PHOTOCOUNT' => array('$gt' => 0)))->sort(array('LISTDATE' => -1)) as $property) {

			if (isset($property['PHOTOPRIMARY'])) {
				$unset = true;
				if (!empty($property['PHOTOPRIMARY'])) {
					$image = \Shadow\Config::get('app.path.object') . DIRECTORY_SEPARATOR . $property['_id'] . DIRECTORY_SEPARATOR . $property['PHOTOPRIMARY'];
					if (file_exists($image) && filesize($image)) {
						$unset = false;
					}
				}

				if ($unset) {
					unset($property['PHOTOPRIMARY']);
					Schema::get('properties.active')->update(array('_id' => $property['_id']), $property);
					$property = Schema::get('properties.decoded')->findOne(array('_id' => $property['_id']));
					unset($property['PHOTOPRIMARY']);
					Schema::get('properties.decoded')->update(array('_id' => $property['_id']), $property);
					\Shadow\Logger::write("Unset Primary: {$property['_id']}", \Shadow\Logger::NOTICE);
				}
			}

			if ((int)Schema::get('photos.thumbnail')->count(array('mlsnum' => $property['_id'])) < $property['PHOTOCOUNT']) {
				\Shadow\Logger::write("Clean Initialize: {$property['_id']}", \Shadow\Logger::INFO);
				self::init($property['_id']);
			}
		}

		foreach (array('thumbnail', 'photo') as $schema) {
			$schema = Schema::get('photos.' . $schema);
			foreach ($schema->find(array('exists' => true)) as $image) {
				$photo = \Shadow\Config::get('app.path.object') . DIRECTORY_SEPARATOR . $image['mlsnum'] . DIRECTORY_SEPARATOR . basename($image['source']);
				if (!file_exists($photo) || (file_exists($photo) && !filesize($photo))) {
					\Shadow\Logger::write("Remove: {$image['mlsnum']} - {$image['object_id']}", \Shadow\Logger::WARNING);
					unset($image['exists']);
					$schema->update(array('_id' => $image['_id']), $image);
				}
			}
		}
	}

	public static function createDirectory ( $destination )
	{
		if(!is_dir($destination)) {
			mkdir($destination, 0777);
			chown($destination, 'emweb');
			chgrp($destination, 'emweb');
			$mkdir = true;
		}
		return isset($mkdir) ? $mkdir : false;
	}

	public static function remove ( $mlsnum )
	{
		Schema::get('photos.thumbnail')->remove(array('mlsnum' => $mlsnum));
		Schema::get('photos.photo')->remove(array('mlsnum' => $mlsnum));
		$destination = \Shadow\Config::get('app.path.object') . DIRECTORY_SEPARATOR . $mlsnum;
		if (is_dir($destination)) {
			exec('rm -rf ' . $destination);
		}
	}

	public static function display ( $mlsnum, $object_id, $resource = 'Thumbnail' )
	{
		$schema = Schema::get('photos.' . strtolower($resource));
		$object_id = str_replace('.jpg', '', $object_id);
		$data = $schema->findOne(array('mlsnum' => $mlsnum, 'object_id' => (int)$object_id));
		
		if (empty($data)) {
			$image = Request::get(false)->GetObject('Property', 'Thumbnail', $mlsnum, $object_id, 1);
			$image = $image[0];
			$data = array(
				'mlsnum' => $mlsnum,
                'object_id' => (int)$image['Object-ID'],
                'source' => trim($image['Location']),
                'created' => microtime(true)
            );
		}

		if (!empty($data)) {
			$image = \Shadow\Config::get('app.path.object') . DIRECTORY_SEPARATOR . $mlsnum . DIRECTORY_SEPARATOR . basename($data['source']);

			if (\Shadow\Config::get('photo.stream') || (!file_exists($image) || (file_exists($image) && !filesize($image)))) {
				$image = self::get($data);
				if (isset($data['exists'])) {
					if (isset($data['_id'])) {
						$schema->update(array('_id' => $data['_id']), $data);
					} else {
						$schema->insert($data);
					}
				}
			}

			if ($resource == 'Thumbnail' && $object_id == 1) {
				self::setPrimaryPhoto($mlsnum, basename($data['source']));
			}

		}
		return $image;
	}

	public static function getPrimaryPhoto ( $data = array() )
	{
		$destination = \Shadow\Config::	get('app.path.object') . DIRECTORY_SEPARATOR . $data['mlsnum'];
		$image = $destination . DIRECTORY_SEPARATOR . basename($data['source']);
		if (file_exists($image) && filesize($image)) {
			$data['exists'] = true;
		} else {
			self::get($data);
		}
		if (isset($data['exists'])) {
			Schema::get('photos.thumbnail')->update(array('_id' => $data['_id']), $data);
			self::setPrimaryPhoto($data['mlsnum'], basename($data['source']));
		}
	}

	public static function setPrimaryPhoto ( $mlsnum, $primary )
	{
		$data = Schema::get('properties.active')->findOne(array('_id' => $mlsnum));
		if (!empty($data)) {
			$data['PHOTOPRIMARY'] = $primary;
			Schema::get('properties.active')->update(array('_id' => $data['_id']), $data);
		}
		$data = Schema::get('properties.decoded')->findOne(array('_id' => $mlsnum));
		if (!empty($data)) {
			$data['PHOTOPRIMARY'] = $primary;
			Schema::get('properties.decoded')->update(array('_id' => $data['_id']), $data);
		}
	}

	public static function acquireLock ( $lock )
	{
		if ($lock) {
			self::$_lockTimeStart = microtime(true);
	
			$cache_dir = \Shadow\Config::get('app.cache');
			$locks = array();
			for ($i = 0; $i < \Shadow\Config::get('photo.max_simultaneous_connection'); $i++) {
				$locks[] = fopen($cache_dir . DIRECTORY_SEPARATOR . 'photo_' . $i . '.lock', 'w+');
			}
			while (self::$ticket === false) {
				foreach ($locks as $lock) {
					if (flock($lock, LOCK_EX | LOCK_NB)) {
						self::$ticket = $lock;
						break;
					}
				}
				usleep(20000);
			}
		}

		file_put_contents(self::LOG_PATH, sprintf('Lock acquired in %f seconds' . PHP_EOL, microtime(true) - self::$_lockTimeStart), FILE_APPEND);
	}

	public static function freeLock ( $lock )
	{
		if ($lock && self::$ticket) {
			flock(self::$ticket, LOCK_UN);
			file_put_contents(self::LOG_PATH, sprintf('Lock held for %f seconds' . PHP_EOL, microtime(true) - self::$_lockTimeStart), FILE_APPEND);
		}
	}
}
