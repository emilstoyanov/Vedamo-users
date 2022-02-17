<?php
/**
 * @license Emil Stoyanov v2.1.0
 * (c) 2018 Emil Stoyanov
 * License: MIT
 */

 
//error_reporting(0);
error_reporting(E_ALL);
ini_set('display_errors',1);
ini_set('display_startup_errors',1);

define('_APPEXEC', true);
define('_APPSTARTTIME', microtime(1));
define('_APPSTARTMEM' , memory_get_usage());

define('PATH_BASE'    , __DIR__);
define('PATH_CONFIG'  , PATH_BASE . '/config/');
define('PATH_INC'     , PATH_BASE . '/inc/');
define('PATH_MODULES' , PATH_BASE . '/modules/');

include PATH_INC . 'loader.php';