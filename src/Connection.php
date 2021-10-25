<?php

namespace FL;

class Connection {

    private $host = null;
    private $user = null;
    private $pwd = null;
    private $database = null;
    private $connection = null;

    function __construct($dbhost, $dbuser, $dbpassword, $dbdatabase) {
        $this->host = $dbhost;
        $this->user = $dbuser;
        $this->pwd = $dbpassword;
        $this->database = $dbdatabase;
    }

    public function open() {
        $this->connection = new \PDO('mysql:host=' . $this->host . ';dbname=' . $this->database, $this->user, $this->pwd);
        return $this;
    }
    public function getDatabase() {
        return $this->database;
    }
    public function getConnection() {
        if ($this->connection === null) {
            $this->open();
        }
        return $this->connection;
    }
    public function lastInsertId() {
        return $this->getConnection()->lastInsertId();
    }
    public function query($query) {
        return $this->getConnection()->query($query);
    }

    public function close() {
        $this->connection = null;
    }

}
