<?php

namespace App\Controller;

class Statistic extends \Shadow\Controller
{
	public function index ()
	{
		\App\Model\Statistic::generate();
	}
}