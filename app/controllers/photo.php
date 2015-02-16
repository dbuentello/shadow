<?php

namespace App\Controller;

class Photo extends \Shadow\Controller
{
	public function index ()
	{
		\App\Model\Legacy::photo();
	}
}