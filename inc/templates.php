<?php

defined('_APPEXEC') or die;

class cTemplate {

    private $path = null;
    private $vars = [];

    function __construct($path) {
        $this->path = $path;
    }

    function __destruct() {
    }
    
    public function clear() { 
        $this->vars = []; 
    }
    
    private function loadTpl($name) {
        $html = file_get_contents($this->path . $name);
        if ($html === false) {
            cCore::$dbg->Log('Can not load template ' . $this->path . $name . ', not found or not readable!');
            return false;
        } return $html; 
    }

    private function parser($input, $vars) {
        $res = [];
        $count = preg_match_all('/'.cCore::getCfg('tplstart').'([a-zA-Z0-9_]+)'.cCore::getCfg('tplend').'/', $input, $res);
        if ($count === false) {
            cCore::$dbg->Log('cTemplate->parcer: preg_match_all returns FALSE!');
            return false;
        }
        if ($count == 0) {
            cCore::$dbg->Log('cTemplate->parcer: preg_match_all matches 0 results!');
            return false;
        }
        for ($i = 0; $i < $count; $i++) {
            $replace = $vars[$res[1][$i]];
            $a[] = $res[0][$i];
            $b[] = $replace;
        }
        return str_replace($a, $b, $input);
    }

    public function parseTpl($tpl) {
        $html = $this->loadTpl($tpl);
        if ($html !== false) { $html = $this->parser($html, $this->vars); }
        if ($html !== false) { return $html; } else { return ''; }
    }

    private function htmlEnt(&$val) {
        if (is_array($val)) {
            foreach ($val as $key => $value) { $val[$key] = htmlentities($value, ENT_QUOTES); }
        } else { $val = htmlentities($val, ENT_QUOTES); }
    }
    
    private function parseLoop($array, $tpl, $htmlent = false) {
        $html_loop = '';
        foreach ($array as $row) {
            if ($htmlent === true) { $this->htmlEnt($array); }
            $html_loop .= $this->parser($tpl, $row);
        }
        return $html_loop;
    }

    public function parseLoopTpl($array, $tpl, $htmlent = false) {
        $html = $this->loadTpl($tpl);
        if ($html !== false) { 
            $html = $this->parseLoop($array,$html,$htmlent); 
        }
        return $html;
    }

    public function parseSingleTpl($array, $tpl, $htmlent = false) {
        $html = $this->loadTpl($tpl);
        if ($html !== false) { 
            if ($htmlent === true) { $this->htmlEnt($array); }
            $html = $this->parser($html, $array);
        }
        return $html;
    }
    
    public function regVar($name, $value, $htmlent = false) {
        $this->vars[$name] = ($htmlent) ? htmlentities($value) : $value;
    }

}