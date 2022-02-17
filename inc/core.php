<?php

defined('_APPEXEC') or die;

/** 
 * Class cCore: Loads all configuration variables and makes the application live.
 * In debug mode the public variable $dbg is instance of cDebig class.
 */
class cCore {

    private static $cfg      = null; // StdClass object with configuration data
    private static $input    = null; // StdClass object with all user input parameters
    private static $errors   = null; // StdClass object with error messages 
    private static $messages = null; // StdClass object with messages for the frontend
    public static  $dbg      = null; // Instance of Debug class in debug mode, otherwise null
    
    /** 
     * Initialization of cCore.
     */
    public static function init() {
        self::loadConfig();
	      if (self::$cfg->debug) {
            require PATH_INC . 'debug.php';            
            self::$dbg = new cDebug();
            self::$dbg->startDebugSession();
        }        
        self::$input = (object)[];
        foreach ($_REQUEST as $name => $value) { self::$input->$name = $value; }
        self::$errors = self::loadArray(PATH_INC . 'errors.php');
        if ((self::$errors === false)&&(self::$cfg->debug)) { self::$dbg->Log('Can not load error messages!'); }
        self::$messages = self::loadArray(PATH_INC . 'messages.php');
        if ((self::$messages === false)&&(self::$cfg->debug)) { self::$dbg->Log('Can not load LANG messages!'); }
    }

    /** 
     * Tries to load ( "config.php" ). If file not exists - terminates the script. 
     * NOTE, that if the file is not readable, fatal E_COMPILE_ERROR level error will appear!
     */
    public static function loadConfig() {
        self::$cfg = self::loadArray(PATH_CONFIG . 'config.php');
        if (self::$cfg === false) { self::terminate('Can not load config file!'); }        
    }
    
    /** 
     * This function makes the application live.
     */
    public static function loadModule() {
        if (isset(self::$input->m)) {
            $file = PATH_MODULES . 'mod_' . self::$input->m . '/module.php';
            if (file_exists($file)) {
                require $file;
                $module = new self::$input->m;
            } else { 
                if (self::$cfg->debug) { self::$dbg->Log('FATAL ERROR: Can not load ' . $file . ' , does not exists or not readable!'); }
                self::terminate();             
            }
        } else {
            require PATH_MODULES . 'mod_index/module.php';
            $module = new cApp();
        }
        if (isset(self::$input->a)) {
            if (method_exists($module,self::$input->a)) { call_user_func([$module,self::$input->a]); } 
            else { 
                if (self::$cfg->debug) { self::$dbg->Log('ERROR', 'There is no action ' . self::$input->a . ' in module ' . self::$input->m); }
                $module->index();
            }
        } else { $module->index(); }
        self::terminate();        
    }

    /** 
     * Terminates the script with die('$message')
     * @param $message (string)
     */
    public static function terminate($message = '') {
	      if (self::$cfg->debug) { 
            if ($message!='') { self::$dbg->Log($message); }
            self::$dbg->endDebugSession();
        }
        die($message);            
    }
    
    /** 
     * Returns the value of input parameter by given name
     * @param $name - parameter name
     * @return mixed If the name is empty, returns all entry parameters as object, otherwise returns the value of given name
     */
    public static function getInput($name = false) {
        return (!$name) ? self::$input : ((isset(self::$input->$name)) ? self::$input->$name : null);
    }
    
    /** 
     * Returns the value of configuration parameter by given name
     * @param $name (string) name of parameter
     * @return (mixed) If the name is empty, returns all entry parameters as object, otherwise returns the value of given name
     */
    public static function getCfg($name = false) {
        return (!$name) ? self::$cfg : ((isset(self::$cfg->$name)) ? self::$cfg->$name : null);
    }
    
    /** 
     * Returns the value of message ( "messages.php" ) by given name
     * @param $code (string) name of parameter
     * @return (mixed) If the name is empty, returns all entry parameters as object, otherwise returns the value of given name
     */
    public static function getMessage($code) { 
        return (isset(self::$messages->$code)) ? self::$messages->$code : 'Undefined';
    }
    
    /** 
     * Returns the value of error ( "errors.php" ) by given name
     * @param $code (string) name of parameter
     * @return (mixed) If the name is empty, returns all entry parameters as object, otherwise returns the value of given name
     */
    public static function getError($code) { 
        return (isset(self::$errors->$code)) ? self::$errors->$code : 'Undefined';
    }
    
    /** 
     * Tries to require file with the given name, who contains array $settings = ['name'=>'value', ...]
     * @param $name (string) path and name of the file
     * @return (mixed) If file exists, returns array $settings as object, otherwise returns false. 
     * NOTE, that if the file is not readable, this function will produce a fatal E_COMPILE_ERROR level error!
     */
    public static function loadArray($name) {
        if (file_exists($name)) {
            require $name;
            return (object) $settings;            
        } else { return false; }
    }
    
    /** 
     * Changes the requested action.
     * @param $action (string) action name
     */
    public static function setAction($action) {
        unset(self::$input->m); self::$input->a = $action;
    }
 
    public static function num2bgtext($number, $stotinki = false) {
        $_num0 = array(0 => "нула", 1 => "един", 2 => "две", 3 => "три", 4 => "четири",
            5 => "пет", 6 => "шест", 7 => "седем", 8 => "осем", 9 => "девет",
            10 => "десет", 11 => "единадесет", 12 => "дванадесет");
        $_num100 = array(1 => "сто", 2 => "двеста", 3 => "триста");

        $number = (int) $number;

        $_div10 = ($number - $number % 10) / 10;
        $_mod10 = $number % 10;
        $_div100 = ($number - $number % 100) / 100;
        $_mod100 = $number % 100;
        $_div1000 = ($number - $number % 1000) / 1000;
        $_mod1000 = $number % 1000;
        $_div1000000 = ($number - $number % 1000000) / 1000000;
        $_mod1000000 = $number % 1000000;
        $_div1000000000 = ($number - $number % 1000000000) / 1000000000;
        $_mod1000000000 = $number % 1000000000;

        if ($number == 0) {
            return $_num0[$number];
        }
        /* До двайсет */
        if ($number > 0 && $number < 20) {
            if ($stotinki && $number == 1)
                return "една";
            if ($stotinki && $number == 2)
                return "две";
            if ($number == 2)
                return "два";
            return isset($_num0[$number]) ? $_num0[$number] : $_num0[$_mod10] . "надесет";
        }
        /* До сто */
        if ($number > 19 && $number < 100) {
            $tmp = ($_div10 == 2) ? "двадесет" : $_num0[$_div10] . "десет";
            $tmp = $_mod10 ? $tmp . " и " . self::num2bgtext($_mod10, $stotinki) : $tmp;
            return $tmp;
        }
        /* До хиляда */
        if ($number > 99 && $number < 1000) {
            $tmp = isset($_num100[$_div100]) ? $_num100[$_div100] : $_num0[$_div100] . "стотин";
            if (($_mod100 % 10 == 0 || $_mod100 < 20) && $_mod100 != 0) {
                $tmp .= " и";
            }
            if ($_mod100) {
                $tmp .= " " . self::num2bgtext($_mod100);
            }
            return $tmp;
        }
        /* До милион */
        if ($number > 999 && $number < 1000000) {
            /* Damn bulgarian @#$%@#$% два хиляди is wrong :) */
            $_num0[2] = "две";
            $tmp = ($_div1000 == 1) ? "хиляда" : self::num2bgtext($_div1000) . " хиляди";
            $_num0[2] = "два";
            if (($_mod1000 % 10 == 0 || $_mod1000 < 20) && $_mod1000 != 0) {
                if (!(($_mod100 % 10 == 0 || $_mod100 < 20) && $_mod100 != 0)) {
                    $tmp .= " и";
                }
            }
            if (($_mod1000 % 10 == 0 || $_mod1000 < 20) && $_mod1000 != 0 && $_mod1000 < 100) {
                $tmp .= " и";
            }
            if ($_mod1000) {
                $tmp .= " " . self::num2bgtext($_mod1000);
            }
            return $tmp;
        }
        /* Над милион */
        if ($number > 999999 && $number < 1000000000) {
            $tmp = ($_div1000000 == 1) ? "един милион" : self::num2bgtext($_div1000000) . " милиона";
            if (($_mod1000000 % 10 == 0 || $_mod1000000 < 20) && $_mod1000000 != 0) {
                if (!(($_mod1000 % 10 == 0 || $_mod1000 < 20) && $_mod1000 != 0)) {
                    if (!(($_mod100 % 10 == 0 || $_mod100 < 20) && $_mod100 != 0)) {
                        $tmp .= " и";
                    }
                }
            }
            $and = ", ";
            if (($_mod1000000 % 10 == 0 || $_mod1000000 < 20) && $_mod1000000 != 0 && $_mod1000000 < 1000) {
                if (($_mod1000 % 10 == 0 || $_mod1000 < 20) && $_mod1000 != 0 && $_mod1000 < 100) {
                    $tmp .= " и";
                }
            }
            if ($_mod1000000) {
                $tmp .= " " . self::num2bgtext($_mod1000000);
            }
            return $tmp;
        }
        /* Над милиард */
        if ($number > 99999999 && $number <= 2000000000) {
            $tmp = ($_div1000000000 == 1) ? "един милиард" : "";
            $tmp = ($_div1000000000 == 2) ? "два милиарда" : $tmp;
            if ($_mod1000000000) {
                $tmp .= " " . self::num2bgtext($_mod1000000000);
            }
            return $tmp;
        }
        /* Bye ... */
        return "";
    }

    public static function number2lv($number) {
        list($lv, $st) = explode(".", number_format($number, 2, ".", ""));
        $lv = (int) $lv;
        if ($lv >= 2000000000)
            return "Твърде голямо число";
        $text = self::num2bgtext($lv);
        $text .= $lv == 1 ? " лев" : " лева";
        if ($st <> 0)
            $text = preg_replace("/^един /", "", $text);
        if ($st && $st != 0) {
            $sttext = self::num2bgtext($st, true);
            $text .= " и " . self::num2bgtext($st, true);
            $text .= $st == 1 ? " стотинка" : " стотинки";
        }
        return $text;
    }


}