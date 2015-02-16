<?php

return array(
	'property' => array(
		'default' => array(
			'columns' => array(
				'MLSNUM',
				'LISTSTATUS',
				'TYPE',
				'PROPTYPE',
				'ADDRESS',
				'GEOMARKETAREA',
				'ZIPCODE',
				'AREA',
				'LISTPRICE',
				'MONTHLYMAINT',
				'LOTSIZE',
				'BEDS',
				'BATHSFULL',
				'LISTDATE',
				'MODIFIED',
				'DAYSONMARKET'
			),
			'sort' => array(
				'LISTDATE' => -1
			),
			'limit' => 25,
			'page' => 0
		)
	),
	'users' => array(
		'112.209.22.124',	// Pao
		'125.212.125.21',		// Ryan
		'192.168.159.102'	// Local my.houstonproperties.com
	)
);