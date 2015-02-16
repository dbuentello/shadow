<?php

return array(
	'base' => __DIR__ . '/..',
	'cache' => __DIR__ . '/../cache',
	'log' => __DIR__ . '/../logs',
	'timezone' => 'America/Chicago',
	'default' => array(
		'controller' => 'Index',
		'function' => ''
	),
	'path' => array(
		'object' => '/home/shadow/public/properties'
	),
	'salt' => sha1('shadow')
);
