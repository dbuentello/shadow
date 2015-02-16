<?php

namespace App\Controller;

class Index extends \Shadow\Controller
{
	public function index ()
	{
	}

	public function get ( $resource, $mlsnum, $object_id )
	{
		header('HTTP/1.1 302 Found');
		header('Access-Control-Allow-Origin: *');
		header('Content-Type: image/jpeg');
		header('Cache-Control: max-age=28800');
		readfile(\App\Model\Photo::display((int)$mlsnum, $object_id, $resource));
		exit;
	}
}
