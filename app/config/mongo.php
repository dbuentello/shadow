<?php

return array(
	'host' => '127.0.0.1',
	'dbname' => 'shadow',
	'schema' => array(
		'properties' => array(
			'active' => 'x_properties',
			'decoded' => 'x_properties_decoded',
			'inactive' => 'x_properties_inactive',
			'active_agent' => 'x_properties_active_agent',
			'office' => 'x_properties_office',
			'failed_requests' => 'x_properties_failed_requests',
			'log' => array(
				'history' => 'x_properties_history_log',
				'listprice' => 'x_properties_log_listprice',
				'liststatus' => 'x_properties_log_liststatus',
				'modified' => array(
					'details' => 'x_properties_log_modified',
					'summary' => 'x_properties_log_modified_summary'
				)
			),
			'statistics' => 'x_properties_statistics'
		),
		'photos' => array(
			'thumbnail' => 'x_thumbnails',
			'photo' => 'x_photos',
			'not_found' => 'x_photos_404'
		),
		'metadata' => array(
			'lookup' => 'lookup'
		)
	)
);