<?php

return array(
	'host' => 'http://retsiq.harmls.com/rets/login',
	'username' => 'RETSKWPT',
	'password' => 'T2vP03h7',
	'request' => array(
		'max_simultaneous_connection' => 10,
		'max_property_detail_per_batch' => 100
	),
	'search_query_callback' => array(
		'object' => '\\App\\Model\\Request',
		'method' => 'end'
	)
);