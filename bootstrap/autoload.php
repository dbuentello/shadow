<?php

define('SHADOW_START', microtime(true));
define('SHADOW_CONFIG', __DIR__ . '/../app/config');

require __DIR__ . '/../vendor/Shadow/Config.php';
require __DIR__ . '/../vendor/Shadow/Loader.php';

Shadow\Loader::register();

Shadow\Time::set();
Shadow\Error::init();

register_shutdown_function('App\Model\Request::disconnect');
