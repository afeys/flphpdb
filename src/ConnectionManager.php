<?php
namespace FL;

class ConnectionManager {

    private $connections = array();
    
    public function __construct() {
    }

    public static function getInstance($configfileuri = "") {
        // this implements the 'singleton' design pattern
        static $instance;

        if (!isset($instance)) {
            $c = __CLASS__;
            $instance = new $c;
            $configuredconnections = Config::getInstance()->get(Config::DBCONNECTIONS);
            if (is_array($configuredconnections)) {
                foreach($configuredconnections as $connectionname => $params) {
                    $instance->addConnection($connectionname, $params);
                }
            }
        }
        return $instance;
    }

    public function addConnection($connectionname, $params) {
        if (is_array($params)) {
            $dbhost = "localhost";
            $dbuser = "";
            $dbpassword = "";
            $dbdatabase = "";
            if (array_key_exists(Config::DBHOST, $params)) {
                $dbhost = $params[Config::DBHOST];
            }
            if (array_key_exists(Config::DBUSER, $params)) {
                $dbuser = $params[Config::DBUSER];
            }
            if (array_key_exists(Config::DBPASSWORD, $params)) {
                $dbpassword = $params[Config::DBPASSWORD];
            }
            if (array_key_exists(Config::DBDATABASE, $params)) {
                $dbdatabase = $params[Config::DBDATABASE];
            }
            $this->connections[$connectionname] = new Connection($dbhost, $dbuser, $dbpassword, $dbdatabase);
        }
    }
    
    public function get($connectionname) {
        if (is_array($this->connections)) {
            if (array_key_exists($connectionname, $this->connections)) {
                return $this->connections[$connectionname];
            }
        }
        return null;
    }
    

}