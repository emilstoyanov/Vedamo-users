<?php

defined('_APPEXEC') or die;

class cLogger {

  function __construct($path) {
  }

  function __destruct() {
  }
    
  public static function logAction($id,$newdata,$document = '',$table_name = '', $id_name = '', $id_value = 0) {
    if (cCore::getCfg('log_actions') == 1) {
      $ndata = preg_replace('/\"/','#',$newdata);
      if (cDB::Insert('user_actions_log', [ 
        'user_id' => cSession::getMemberData('user_id'), 
        'master_id' => cSession::getMemberData('master_id'),
        'action_id' => $id, 
        'document' => $document, 
        'table_name' => $table_name, 
        'id_name' => $id_name, 
        'id_value' => $id_value,
        'data' => $ndata
      ],false,true,time(),false)) {
        // we need to commit this transaction by hand because autocommit is turned off from cDB::addDoc()
        cDB::commitTransaction();
      };
    }
  }

}