<?php
class Flite
{
    private $metrics;
    private $config;
    private $exceptions = array();
    public $start_session = true;
    public $tweak_server_value = true;

    public function __construct()
    {
        $this->metrics->page_execution_start = microtime(true);
        $this->BootFlite();
    }

    private function Loader($classname)
    {
        $b = $this->GetConfig('site_root') . 'flite/dblib/';
        $classname = strtolower($classname);
        if(file_exists($b . $classname . '.php'))
        {
            include_once($b . $classname . '.php');
        }
        elseif(file_exists($this->GetConfig('site_root') .'flite/lib/' . $classname . '.php'))
        {
            include_once($this->GetConfig('site_root') .'flite/lib/' . $classname . '.php');
        }
    }

    public function LoadFiles($directory,$ext = '.php')
    {
        $handle = opendir($directory);
        if ($handle)
        {
            while (false !== ($file = readdir($handle)))
            {
                $ext_length = strlen($ext);
                if(substr($file, ($ext_length * -1)) == $ext){ include_once($directory . $file); }
            }
            closedir($handle);
            return true;
        }
        else return false;
    }

    public function GetConfig($key,$default=false)
    {
        return isset($this->config->$key) ? $this->config->$key : $default;
    }

    public function SetConfig($key,$value)
    {
        $this->config->$key = $value;
    }

    private function BootFlite()
    {
        if($this->tweak_server_value)
        {
            if(isset($_SERVER['HTTP_HOST']) && substr($_SERVER['HTTP_HOST'],-5) == ':8080') $_SERVER['HTTP_HOST'] = substr($_SERVER['HTTP_HOST'],0,-5);
            if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
            if(isset($_SERVER['HTTP_X_REAL_FORWARDED_FOR'])) $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_FORWARDED_FOR'];
            if(!isset($_SERVER['HOSTNAME'])) $_SERVER['HOSTNAME'] = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
            if(!isset($_SERVER['HOSTNAME'])) $_SERVER['HOSTNAME'] = '';
            if(!isset($_SERVER['SERVER_ADMIN'])) $_SERVER['SERVER_ADMIN'] = '';
            if(!isset($_SERVER['SERVER_NAME']) && isset($_SERVER['HTTP_HOST'])) $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
        }

        if(stristr($_SERVER['PHP_SELF'], '.') !== false) list($this->page,) = explode('.', $_SERVER['PHP_SELF'], 2);
        else $this->page = $_SERVER['PHP_SELF'];
        $this->local_page = $this->page;
        if(isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])) $this->local_page = $_SERVER['REQUEST_URI'];

        if(!include_once(dirname(__FILE__) . '/cache/config.php')) include_once(dirname(__FILE__) . '/config.php');

        error_reporting($this->GetConfig('error_reporting'));
        ini_set('display_errors', $this->GetConfig('display_errors'));

        if(!include_once($this->GetConfig('site_root') . 'flite/cache/core.lib.php')) $this->LoadFiles($this->GetConfig('site_root') . 'flite/core.lib/');

        if($this->GetConfig('zlib_enabled'))
        {
            ini_set('zlib.output_compression','On');
            ini_set('zlib.output_compression_level',$this->GetConfig('zlib_level'));
        }
        else
        {
            ini_set('zlib.output_compression','Off');
            ini_set('zlib.output_compression_level',0);
        }

        if($this->start_session && $this->GetConfig('is_web')) session_start();

        spl_autoload_register(array($this, 'Loader'));

        $this->db         = new DBConnection($this->GetConfig('database_host'), $this->GetConfig('database_user'),$this->GetConfig('database_pass'),$this->GetConfig('database_name'));
        $this->dbslave    = new DBConnection($this->GetConfig('database_slave'),$this->GetConfig('database_user'),$this->GetConfig('database_pass'),$this->GetConfig('database_name'));

        $this->membase = new Membase();
        if($this->GetConfig('membase_servers') != false && FC::count($this->GetConfig('membase_servers')) > 0)
        {
            foreach ($this->GetConfig('membase_servers') as $mserv)
            {
                if(is_array($mserv))
                {
                    $this->membase->addServer($mserv['host'],
                        isset($mserv['port']) ? $mserv['port'] : 11211,
                        isset($mserv['persistent']) ? $mserv['persistent'] : true
                    );
                }
                else $this->membase->addServer($mserv);
            }
        }
        else $this->membase->addServer('localhost');

        if($this->GetConfig('memcache_servers') != false && FC::count($this->GetConfig('memcache_servers')) > 0)
        {
            $this->memcache = new Memcache;
            foreach ($this->GetConfig('memcache_servers') as $mserv)
            {
                if(is_array($mserv))
                {
                    $this->memcache->addServer($mserv['host'],
                        isset($mserv['port']) ? $mserv['port'] : 11211,
                        isset($mserv['persistent']) ? $mserv['persistent'] : true
                    );
                }
                else $this->memcache->addServer($mserv);
            }
        }
        else $this->memcache = new stdClass();

        $cassandra_clustername = $this->GetConfig('cassandra_cluster');
        if($cassandra_clustername)
        {
            require_once($this->GetConfig('site_root') . 'flite/thirdparty/phpcassa/columnfamily.php');
            require_once($this->GetConfig('site_root') . 'flite/thirdparty/phpcassa/sysmanager.php');
            $this->cassandra = new ConnectionPool($cassandra_clustername,$this->GetConfig('cassie_servers',null));
        }
        else $this->cassandra = new stdClass();

        $this->LoadFiles($this->GetConfig('site_root') . 'flite/included/');
    }

    public function EchoLocation($call,$force=false)
    {
        if($this->GetConfig('show_echo'))
        {
            $this->metrics->now = microtime(true);
            $start = $this->metrics->now - $this->metrics->page_execution_start;
            $start = round($start * 1000,3);
            $last = isset($this->metrics->page_execution_last) ? $this->metrics->now - $this->metrics->page_execution_last : 0;
            $last = round($last * 1000,3);
            echo "\n<br /><h2>$call</h2><br>Time Since Start: <strong>$start</strong>ms, Time Since Last Check: <strong>$last</strong>ms\n<br>";
            $this->metrics->page_execution_last = microtime(true);
        }
    }

    public function SendErrorReport($script,$message,$errorno=0)
    {
        return mail($this->GetConfig('debug_report_email'),'Error Report' . ($errorno > 0 ? ' : Err No ('. $errorno .')' : ''),"Script Name: $script\n\nError:\n\n" . $message);
    }

    public function Exception($class,$method,$exception)
    {
        $e= new stdClass();
        $e->class = $class;
        $e->method = $method;
        $e->exception = $exception;
        $this->exceptions[] = $e;
        return true;
    }
}

$_FLITE = new Flite();