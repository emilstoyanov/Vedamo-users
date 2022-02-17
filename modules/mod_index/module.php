<?php

defined('_APPEXEC') or die;

class cApp extends baseModule
{

  function __construct()
  {
    parent::__construct();
    $this->path = __DIR__;
    $this->tpl = new cTemplate($this->path . '/templates/');
  }

  function __destruct()
  {
    parent::__destruct();
  }

  public function index()
  {
    $this->tpl->regVar('AppTitle', cCore::getMessage('AppTitle'));
    print $this->tpl->parseTpl('index.tpl');
  }

  public function searchData() {
    $d = cCore::getInput('d');
    $data = cDB::Query('SELECT u.u_id as id,u.first as firstname,u.last as lastname,u.email as emailid,c.name as country FROM `users` u, `countries` c WHERE c.c_id=u.c_id AND match(first,last,email) against ( ? in boolean mode)','s',[$d],true);
    print cDB::formatOutput($data);
  }

  public function getStats() {
    $data = cDB::rawSQL('SELECT count(u.u_id) as count,c.name as country FROM `users` u, `countries` c WHERE c.c_id=u.c_id group by c.name');
    print cDB::formatOutput($data);
  }

}
