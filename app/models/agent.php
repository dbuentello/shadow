<?php

namespace App\Model;

class Agent
{
	public static function get ( $modified = 0 )
	{
		$search = Request::get()->SearchQuery($resource, $resource);
		while ($agent = Request::get(false)->FetchRow($search)) {
		}
	}
}