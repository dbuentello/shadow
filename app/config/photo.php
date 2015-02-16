<?php

return array(
	'stream' => false,
	'max_simultaneous_connection' => 4,
	'max_property_update_download' => 1000,
	'search' => array(
		array(
			'LISTDATE' => array(
				'$gte' => (\Shadow\Time::format(date('Y-m-d 00:00:00', time())) - 86400)
			)
		),
		array(
			'LISTDATE' => array(
				'$gte' => (\Shadow\Time::format(date('Y-m-d 00:00:00', time())) - (86400 * 14)),
				'$lt' => (\Shadow\Time::format(date('Y-m-d 00:00:00', time())) - 86400)
			)
		),
		array(
			'LISTDATE' => array(
				'$lt' => (\Shadow\Time::format(date('Y-m-d 00:00:00', time())) - (86400 * 14))
			)
		)
	)
);
