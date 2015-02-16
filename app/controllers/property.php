<?php

namespace App\Controller;

class Property extends \Shadow\Controller
{
	public function index ()
	{
		\App\Model\Property::sync();
	}

	public function get ()
	{
		$modified = strtotime(date('Y-m-d H:00:00'));
		\App\Model\Property::get($modified);
	}
}
