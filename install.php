<?php

set_time_limit(180);
define('_APPEXEC', true);
define("PATH_BASE"    , __DIR__);
$start = microtime(1);

if ($_POST!=[]) {

    $host = htmlspecialchars($_POST['host'],ENT_QUOTES);
    $user = htmlspecialchars($_POST['user'],ENT_QUOTES);
    $passwd = htmlspecialchars($_POST['passwd'],ENT_QUOTES);
    $database = htmlspecialchars($_POST['database'],ENT_QUOTES);
    if (($host!='')&&($user!='')&&($passwd!='')&&($database!='')) {
        $settings = '<?php
            defined("_APPEXEC") or die; 
            $settings = ["lang" => "BG", "logdir" => PATH_BASE . "/log/", "debug" => true, "logsql" => true, "eol" => "\r\n", "key" => "", "cipher" => "AES-128-CBC",
                "dbhost" => "'.$host.'", "dbuser" => "'.$user.'", "dbpass" => "'.$passwd.'", "dbname" => "'.$database.'", "dbcharset" => "utf8", "dbprefix" => "",
                "tplstart" => "<<", "tplend" => ">>", "usetoken" => false, "log_actions" => false, "ses_log" => false, "ses_expire" => 200000000000, "ses_cookie" => "ip2k_erp"        
            ];
        ?>';
        $file = __DIR__ . '/config/config.php';
        echo 'Creating file '.$file.'...<br>';
        $res = fopen($file,'w+');
        fwrite($res,$settings);
        fclose($res);

        if (is_readable($file)) {
            require $file;
            if (!is_array($settings)) { die('File '.$file.' is corrupted!'); }
        } else { die('File '.$file.' does not exists or is not readable!'); }
        echo 'File '.$file.' loaded successful!<br>';
        echo 'Connecting to MySQL server...<br>';

        $dblink = new mysqli($settings['dbhost'],$settings['dbuser'],$settings['dbpass']);
        if ($dblink->connect_error) {
            die('Could not connect: '.$dblink->errno.': '.$dblink->error);
        }
        echo 'Connected, creating database...<br>';


//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!    
        $dblink->query('DROP DATABASE IF EXISTS '.$settings['dbname'], MYSQLI_USE_RESULT);
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!    

        $result = $dblink->query('CREATE DATABASE `'.$settings['dbname'].'` CHARACTER SET utf8 COLLATE utf8_general_ci', MYSQLI_USE_RESULT);
        if ($result === false) {
            die('Could not create database: '.$dblink->errno.': '.$dblink->error);
        }
        if (!$dblink->select_db($settings['dbname'])) {
            die('Could not open database: '.$dblink->errno.': '.$dblink->error);
        }
        if (!$dblink->set_charset($settings['dbcharset'])) {
            die('Could not set Character Set: '.$dblink->errno.': '.$dblink->error);
        }
        echo 'Creating tables...<br>';

        $file = __DIR__ . '/database.sql';
        $sql = file_get_contents($file);
        if ($sql===false) {
            die('File '.$file.' does not exists or is not readable!'); 
        }
        
        $commands = explode(';',$sql);
        foreach ($commands as $command) {
            if (trim($command)!='') {
                $result = $dblink->query(trim($command), MYSQLI_USE_RESULT);
                if ($result === false) {
                    die('Could not execute SQL script: '.$dblink->errno.': '.$dblink->error.'<br>SQL: '.trim($command));
                }
            }
        }

        echo 'Generating 100k random users...<br>';


        $firstname = ['Johnathon','Anthony','Erasmo','Raleigh','Nancie','Tama','Camellia','Augustine','Christeen','Luz','Diego','Lyndia','Thomas','Georgianna','Leigha',
            'Alejandro','Marquis','Joan','Stephania','Elroy','Zonia','Buffy','Sharie','Blythe','Gaylene','Elida','Randy','Margarete','Margarett','Dion','Tomi','Arden',
            'Clora','Laine','Becki','Margherita','Bong','Jeanice','Qiana','Lawanda','Rebecka','Maribel','Tami','Yuri','Michele','Rubi','Larisa','Lloyd','Tyisha','Samatha'];
    
        $lastname = ['Mischke','Serna','Pingree','Mcnaught','Pepper','Schildgen','Mongold','Wrona','Geddes','Lanz','Fetzer','Schroeder','Block','Mayoral','Fleishman','Roberie',
            'Latson','Lupo','Motsinger','Drews','Coby','Redner','Culton','Howe','Stoval','Michaud','Mote','Menjivar','Wiers','Paris','Grisby','Noren','Damron','Kazmierczak','Haslett',
            'Guillemette','Buresh','Center','Kucera','Catt','Badon','Grumbles','Antes','Byron','Volkman','Klemp','Pekar','Pecora','Schewe','Ramage'];

        for ($i=0; $i<100; $i++) {
            $command = 'insert into `users` (first,last,email,c_id) values ';
            for ($j=0; $j<1000; $j++) {
                $fn = $firstname[rand(0,count($firstname)-1)];
                $command.= ($j>0) ? ',' : '';
                $command.= '("'.$fn.'","'.$lastname[rand(0,count($lastname)-1)].'","'.strtolower($fn) .'@example.com",'.rand(1,20).')';
            };
            $command.= ';';
            $result = $dblink->query(trim($command), MYSQLI_USE_RESULT);
            if ($result === false) {
                die('Could not execute SQL script: '.$dblink->errno.': '.$dblink->error.'<br>SQL: '.trim($command));
            }
        }
    
        $dblink->commit();
        $end = microtime(1);
        echo('Time for execution: '.number_format($end - $start,4).'<br>');
        die('Installation was successful! You can continue with <a href="/">index.php</a>.');
    }
}
    
?>

<!doctype html>
<html lang="bg">
<head>
    <title>Vedamo - install</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <link rel="stylesheet" href="css/bootstrap.min.css"> 
    <script src="lib/bootstrap.min.js"></script> 
    <style>
        .colorgraph {
            height: 7px;
            border-top: 0;
            background: #c4e17f;
            border-radius: 5px;
            background-image: -webkit-linear-gradient(left, #c4e17f, #c4e17f 12.5%, #f7fdca 12.5%, #f7fdca 25%, #fecf71 25%, #fecf71 37.5%, #f0776c 37.5%, #f0776c 50%, #db9dbe 50%, #db9dbe 62.5%, #c49cde 62.5%, #c49cde 75%, #669ae1 75%, #669ae1 87.5%, #62c2e4 87.5%, #62c2e4);
            background-image: -moz-linear-gradient(left, #c4e17f, #c4e17f 12.5%, #f7fdca 12.5%, #f7fdca 25%, #fecf71 25%, #fecf71 37.5%, #f0776c 37.5%, #f0776c 50%, #db9dbe 50%, #db9dbe 62.5%, #c49cde 62.5%, #c49cde 75%, #669ae1 75%, #669ae1 87.5%, #62c2e4 87.5%, #62c2e4);
            background-image: -o-linear-gradient(left, #c4e17f, #c4e17f 12.5%, #f7fdca 12.5%, #f7fdca 25%, #fecf71 25%, #fecf71 37.5%, #f0776c 37.5%, #f0776c 50%, #db9dbe 50%, #db9dbe 62.5%, #c49cde 62.5%, #c49cde 75%, #669ae1 75%, #669ae1 87.5%, #62c2e4 87.5%, #62c2e4);
            background-image: linear-gradient(to right, #c4e17f, #c4e17f 12.5%, #f7fdca 12.5%, #f7fdca 25%, #fecf71 25%, #fecf71 37.5%, #f0776c 37.5%, #f0776c 50%, #db9dbe 50%, #db9dbe 62.5%, #c49cde 62.5%, #c49cde 75%, #669ae1 75%, #669ae1 87.5%, #62c2e4 87.5%, #62c2e4);
        }
        .wrapper { margin-top:80px; }
        .form-install { 
            width: 400px;
            padding: 30px 38px 66px;
            margin: 0 auto;
            background-color: #eee;
            border: 3px dotted #FFFFFF;
        }
        .form-install-heading { text-align:center; margin-bottom: 30px; }
        .btnholder {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <form class="needs-validation form-install" action="/install.php" method="post" name="Install_Form" novalidate>
            <h4 class="form-install-heading">Setup Database</h4>
            <hr class="colorgraph">            
            <div class="form-row">
                <label for="host">Host / IP:</label>
                <input type="text" class="form-control" name="host" placeholder="" value="" required>
            </div>
            <div class="form-row">
                <label for="user">User Name:</label>
                <input type="text" class="form-control" name="user" placeholder="" value="" required>
            </div>
            <div class="form-row">
                <label for="passwd">Password:</label>
                <input type="password" class="form-control" name="passwd" placeholder="" value="" required>
            </div>
            <div class="form-row">
                <label for="database">Database Name:</label>
                <input type="text" class="form-control" name="database" placeholder="" value="" required>
            </div>
            <hr class="colorgraph">
            <div class="btnholder"><button class="btn btn-lg btn-primary btn-block" name="Submit" value="Login" type="Submit">Start</button></div>
        </form>
    </div>
</body>
</html>