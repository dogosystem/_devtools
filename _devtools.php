<?php

class DevTools
{
    public static $buffer = [];

    public static function a2e($array, $glue = '<br>')
    {
        echo implode($glue, (array) $array);
    }

    public static function a2b($array, $glue = '<br>')
    {
        static::$buffer[] = implode($glue, (array) $array);
    }

    public static function a2t($array)
    {
        $out = [];
        $out[] = '<table>';
        $out[] = '<tbody>';
        foreach ($array as $k => $v) {
            $out[] = '<tr>';
            $out[] = '<td>' . $k . '</td><td>' . $v . '</td>';
            $out[] = '</tr>';
        }
        $out[] = '</tbody>';
        $out[] = '</table>';

        echo static::a2b($out, '');
    }

    public static function test($params)
    {
        $out = [];
        $out[] = 'test';
        $out[] = 'params: ' . print_r($params, true);
        static::a2b($out);
    }

    public static function getcwd()
    {
        $out = [];
        $out['getcwd'] = getcwd();
        $out['__DIR__'] = __DIR__;
        $out['__FILE__'] = __FILE__;
        static::a2t($out);
    }

    public static function phpinfo()
    {
        phpinfo();
    }

    public static function env()
    {
        static::a2b('$_SERVER');
        static::a2t($_SERVER);
        static::a2b('$_ENV');
        static::a2t($_ENV);
    }

    public static function mysql($params)
    {
        $host = $params[0];
        $u = $params[1];
        $p = $params[2];
        $db = $params[3];

        try{
            $dbh = new pdo( "mysql:host=$host;dbname=$db;charset=utf8", $u, $p, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            var_dump($dbh);
        }
        catch(PDOException $ex){
            echo(json_encode(array('outcome' => false, 'message' => 'Unable to connect')));
        }
    }

    public static function unzip($params)
    {
        $zip = new ZipArchive();

        $file = __DIR__ . DIRECTORY_SEPARATOR . $params['file'];
        $destination = __DIR__ . DIRECTORY_SEPARATOR . $params['destination'];

        $out = [];
        if (!is_file($file) || !file_exists($file)) {
            $out[] = "file: $file (does not exist)";
            static::a2b($out);
            return false;
        }

        $out[] = 'extracting to: ' . $destination;

        if ($zip->open($file) === true) {
            $zip->extractTo($destination);
            $zip->close();
            $out[] = "file: $file (uzipped)";
            static::a2b($out);
            return true;
        } else {
            $out[] = "file: $file (could not be unzipped)";
            static::a2b($out);
            return false;
        }
    }

    public static function pharDataExtract($params)
    {
        $file = __DIR__ . DIRECTORY_SEPARATOR . $params['file'];
        $destination = __DIR__ . DIRECTORY_SEPARATOR . $params['destination'];

        $out = [];
        if (!is_file($file) || !file_exists($file)) {
            $out[] = "file: $file (does not exist)";
            static::a2b($out);
            return false;
        }

        $out[] = 'extracting to: ' . $destination;

        $phar = new PharData($file);
        $phar->extractTo($destination, null, true);

        $out[] = 'done';
        static::a2b($out);
        return true;
    }

    public static function dir($params)
    {
        $params['folders'] = explode(',', $params['folders']);

        $logFile = __DIR__ . '/' . '_devtools.log';
        $email = 'developerdogo@gmail.com';

        $delete = !empty($params['delete']) ? true : false;
        $echo = !empty($params['echo']) ? true : false;
        $log = !empty($params['log']) ? true : false;
        $mail = !empty($params['mail']) ? true : false;

        $folders = !empty($params['folders']) ? $params['folders'] : [];

        $lines = [];

        $lines[] = $line = date("Y-m-d H:i:s") . "\r\n";

        if ($echo) {
            echo nl2br($line);
        }
        if ($log) {
            $fp = fopen($logFile, 'w');
            flock($fp, LOCK_EX);
            fwrite($fp, $line . "\n\r");
        }

        foreach ($folders as $folder) {
            //
            $path = __DIR__ . '/' . $folder;

            if (!is_dir($path)) {
                $lines[] = $line = $path . ' [is not a directory]' . "\r\n";
                if ($echo) {
                    echo nl2br($line);
                }
                if ($log) {
                    fwrite($fp, $line);
                }
                continue;
            }

            $di = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
            $ri = new RecursiveIteratorIterator($di, RecursiveIteratorIterator::CHILD_FIRST);

            $lines[] = $line = 'start' . "\r\n";
            if ($echo) {
                echo nl2br($line);
            }
            if ($log) {
                fwrite($fp, $line);
            }
            foreach ($ri as $file) {
                if ($file->isDir()) {
                    $res = '[dir]';
                    if ($delete) {
                        $res = @rmdir($file) ? '[dir removed]' : '[dir skipped]';
                    }
                } elseif ($file->isFile()) {
                    $res = '[file]';
                    if ($delete) {
                        $res = @unlink($file) ? '[file removed]' : '[file skipped]';
                    }
                }
                $lines[] = $line = $file . ' : ' . $res . "\r\n";
                if ($echo) {
                    echo nl2br($line);
                }
                if ($log) {
                    fwrite($fp, $line);
                }
            }
            $lines[] = $line = 'stop' . "\r\n";
            if ($echo) {
                echo nl2br($line);
            }
            if ($log) {
                fwrite($fp, $line);
            }
        }

        if ($log) {
            fwrite($fp, "\n\r\n\r\n\r");
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        if ($mail) {
            mail($email, 'cachedel: ' . __FILE__, implode("", $lines));
        }
    }

    public static function dirModx($params)
    {
        $params['folders'] = implode(',', [
            'core/cache',
            'assets/image-cache',
            'assets/components/gallery/cache',
            'assets/components/phpthumbof/cache',
        ]);
        DevTools::dir($params);
    }

    public static function dirPresta($params)
    {
        $params['folders'] = implode(',', [
            'app/cache',
            'var/cache',
        ]);
        DevTools::dir($params);
    }
}

// ====================================================================================================================

class Processor
{
    public $menu;
    public $command;
    public $parameters;

    public function process()
    {
        $this->bind();
        return $this->execute();
    }

    public function bind()
    {
        $this->menu = isset($_GET['m']) ? $_GET['m'] : true;
        $this->command = isset($_GET['c']) ? $_GET['c'] : false;
        $this->parameters = isset($_GET['p']) ? $_GET['p'] : false;
    }

    public function parameters()
    {
        $ep = [];

        if (!empty($this->parameters)) {
            $ep = explode('|', $this->parameters);
            foreach ($ep as $k => $v) {
                if (strpos($v, ':')) {
                    $ev = explode(':', $v);
                    $ep[$ev[0]] = $ev[1];
                    unset($ep[$k]);
                }
            }
        }
        return $ep;
    }

    public function execute()
    {
        if (!empty($this->command)) {
            // call a command
            return call_user_func_array(['DevTools', $this->command], [$this->parameters()]);
        }
    }
}

// ====================================================================================================================

$processor = new Processor();

$processor->process();

// exit showing no menu
if (!$processor->menu) {
    exit;
}

// ====================================================================================================================

?>
<html>
<head>
    <meta charset="utf-8">
    <title>DevTools</title>
    <style>
        .app .section {
            background-color: #F3F3F3;
            padding: 40px 20px;
            margin: 20px 0 20px 0;
        }
        .app table td {
            border: 1px solid lightgray;
            padding: 10px;
        }
        .app .menu {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }
        .app .menu > a {
            margin: 10px;
        }
        .app a {
            color: #0072C9;
            text-decoration: none;
            border: 1px solid lightgray;
            padding: 5px;
        }
        .app a:hover {
            background-color: #D2B8CF;
        }
        .app .spacer {
            display: block;
            width: 100%;
            flex: 0 0 100%;
        }
    </style>
</head>
<body>
    <div class="app">
        <?php if (!empty(DevTools::$buffer)): ?>
        <div class="section display">
            <?php echo implode('<br>', DevTools::$buffer); ?>
        </div>
        <?php endif; ?>

        <div class="section menu">
            <hr class="spacer">
            <a href="_devtools.php?c=test&p=param1:value1|param2:value2">test</a>
            <a href="_devtools.php?c=getcwd">getcwd</a>
            <a href="_devtools.php?c=phpinfo">phpinfo</a>
            <a href="_devtools.php?c=env">env</a>
            <a href="_devtools.php?c=mysql&p=localhost|user|pass|database">mysql</a>
            <hr class="spacer">
            <a href="_devtools.php?c=unzip&p=file:relative_filename|destination:relative_destination">unzip</a>
            <a href="_devtools.php?c=pharDataExtract&p=file:relative_filename|destination:relative_destination">pharDataExtract</a>
            <hr class="spacer">
            <a href="_devtools.php?c=dir&p=folders:'folder1','folder2'|delete:0|echo:0|log:0|mail:0">dir</a>
            <hr class="spacer">
            <a href="_devtools.php?c=dirPresta&p=delete:0|echo:1|log:0|mail:0">dirPresta</a>
            <a href="_devtools.php?c=dirModx&p=delete:0|echo:1|log:0|mail:0">dirModx</a>
            <div class="spacer"></div>
            <a href="_devtools.php?c=dirPresta&p=delete:1|echo:0|log:1|mail:0">clearPresta</a>
            <a href="_devtools.php?c=dirModx&p=delete:1|echo:0|log:1|mail:0">clearModx</a>
            <hr class="spacer">
        </div>
    </div>
</body>
</html>

