<?php

defined('_APPEXEC') or die;

class baseModule {

  public $path      = '';
  public $tpl       = null;
  public $firm      = null;
  public $unres     = null;
  public $res       = null;
  public $as        = null;
  public $limit     = 0;
  public $direction = 'ASC';

  public function __construct()  {
    $this->firm = cCore::getInput('f');
    $this->unres = cCore::getInput('u');
    $this->res = cCore::getInput('r');
    $this->as = cCore::getInput('s');
    $b = cCore::getInput('b'); if ($b !== null) $this->limit = $b;
    $c = cCore::getInput('c'); if ($c !== null) $this->direction = $c;
  }

  public function __destruct() {
    unset($this->firm);
    unset($this->path);
    unset($this->tpl);
    unset($this->unres);
    unset($this->res);
    unset($this->as);
    unset($this->limit);
    unset($this->direction);
  }

  public function checkFirm() {
    if ($this->firm === null) {
      cDB::setState('false', '', 'internal: 000006');
      print cDB::formatOutput();
      cCore::terminate();
    }
  }

  public function checkToken($token) {
    return true;
  }

  public function loadTpl() {
    header('Content-Type: text/html; charset=UTF-8');
    $tplname = cCore::getInput('n');
    $tpldata = '';
    if ($tplname === null) {
      cCore::$dbg->Log('LoadTpl: File is required, but no name in input!');
      readfile(PATH_BASE . '/app/templates/notplname.tpl');
      return;
    }
    $name = $this->path . '/templates/' . $tplname . '.tpl';
    if (file_exists($name)) {
      $tpldata = file_get_contents($name); 
    } else {
      $name = PATH_BASE . '/app/templates/' . $tplname . '.tpl';
      if (file_exists($name)) {
        $tpldata = file_get_contents($name);
      } else {
        cCore::$dbg->Log('LoadTpl: Required file ' . $name . ' does not exists!');
        $tplname = PATH_BASE . '/app/templates/notfound.tpl';
        $tpldata = file_get_contents($tplname);
      }
    }
    if (strpos($tplname, '_edit') !== false) {
      $token = ''; //cSession::createToken();
      $tpldata = str_replace(cCore::getCfg('tplstart') . 'formtoken' . cCore::getCfg('tplend'), $token, $tpldata);
    }
    print $tpldata;
  }

  public function loadController() {
    header('Content-Type: application/javascript');
    if (cCore::getInput('n') !== null) {
      $name = $this->path . '/' . cCore::getInput('n') . 'Controller.js';
      if (file_exists($name)) {readfile($name);} else {
        $name = PATH_BASE . '/app/controllers/' . cCore::getInput('n') . 'Controller.js';
        if (file_exists($name)) {readfile($name);} else {
          cCore::$dbg->Log('loadController: Required controller ' . $name . ' does not exists!');
        }
      }
    } else {
      cCore::$dbg->Log('loadController: Controller is required, but no name in input!');
    }
  }

  public function createPaterns($data) {
    $result = [];
    foreach ($data as $name) {
      $result[] = '/\b'.$name.'\b/';
    }
    return $result;
  }

  public function getData() {
  }

  public function editData() {
  }

  public function delData() {
  }

  public function getMax() {
  }

  public function unReserve() {
  }

  /* u: oldval, r: newval
  *
  */
  public function reReserve() {
  }

  public function getDetail() {
  }

  public function delDetail() {
  }

  protected function removeSysCols($append) {
  }

  protected function addTableInfo(&$data, $append) {
  }

  protected function mergeFieldNames($master,$type) {
  }

  public function addDoc() {
  }

  public function editDoc() {
  }

  protected function mergeTableNames($master) {
  }

  public function delDoc() {
  }

  public function activateDoc() {
  }

  public function printList() {
  }

}
