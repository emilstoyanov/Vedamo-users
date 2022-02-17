<?php

defined('_APPEXEC') or die;

class cDebug {

    private $data    = [];
    private $logfile = null;
            
    function __construct() {
        $this->logfile = fopen(cCore::getCfg('logdir').'debug_log.txt', 'a');
    }

    function __destruct() {
        fclose($this->logfile);
    }
    
    public function startDebugSession() {
        array_push($this->data,['time'=>_APPSTARTTIME, 'memory'=>_APPSTARTMEM]);
        fwrite($this->logfile, cCore::getCfg('eol') . '===========================================' . cCore::getCfg('eol'));	
        fwrite($this->logfile, '['.date("Y-m-d H:i:s",_APPSTARTTIME).']: Application Started, memory allocated to PHP: ' . _APPSTARTMEM . cCore::getCfg('eol'));
    }

    public function endDebugSession() {
        $debug = array_pop($this->data);
        if (is_null($debug)) {
            fwrite($this->logfile, 'No debug info left! There is a session who is closed, but not opened before!' . cCore::getCfg('eol'));
        } else {
            if (count($this->data)!=0) {
                fwrite($this->logfile, 'Wrong debug info! There is a session who is opened, but not closed!' . cCore::getCfg('eol'));
            } else {
                $end = microtime(1); $mem = memory_get_usage(); $totalmem = memory_get_usage(true);
                fwrite($this->logfile, '['.date("Y-m-d H:i:s",$end).']: Application terminated, Execution time: ' . number_format($end - $debug['time'],4) . '  , Memory Usage: ' . number_format($mem - $debug['memory'],0) . '  , Total Alocated memory: ' . number_format($totalmem - $debug['memory'],0) );
            }
        }        
    }

    public function startBlock($message) {
        $start = microtime(1); $mem = memory_get_usage();
        array_push($this->data,['time'=>$start, 'memory'=>$mem]);
        fwrite($this->logfile, '['.date("Y-m-d H:i:s",$start).']: Start Block, memory allocated to PHP: ' . $mem . ',  Message: ' . $message . cCore::getCfg('eol'));
    }

    public function endBlock($message = '') {
        $debug = array_pop($this->data);
        $end = microtime(1); $mem = memory_get_usage();
        fwrite($this->logfile, '['.date("Y-m-d H:i:s",$end).']: End Block, Execution time: ' . number_format($end - $debug['time'],4) . '  , Memory Usage: ' . number_format($mem - $debug['memory'],0) . ',  Message: ' . $message . cCore::getCfg('eol'));
    }
    
    public function Log($message) {
        if ($message!='') {
            fwrite($this->logfile, '['.date("Y-m-d H:i:s").']: ' . $message . cCore::getCfg('eol'));
        }
    }   
    
    public function varDump() {
        foreach (func_get_args() as $var) {
            fwrite($this->logfile, '['.date("Y-m-d H:i:s").']: ' . preg_replace('/,\\r/',','.cCore::getCfg('eol'),var_export($var,true)) . cCore::getCfg('eol'));
        }    
    }
    
}    