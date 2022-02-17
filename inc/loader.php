<?php

defined('_APPEXEC') or die;

include PATH_INC . 'core.php';
include PATH_INC . 'database.php';
include PATH_INC . 'session.php';
include PATH_INC . 'templates.php';
include PATH_INC . 'baseModule.php';
include PATH_INC . 'logger.php';

cCore::init();
cDB::init();
cSession::init();

cCore::loadModule();