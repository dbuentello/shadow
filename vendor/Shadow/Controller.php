<?php

// @todo: add template engine here

namespace Shadow;

abstract class Controller
{
	public function __construct () {}

	public abstract function index ();
}