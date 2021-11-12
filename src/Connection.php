<?php

namespace FL;

class Connection {


    /**
     * @name string The name use to reference this connection
     */
    private $name = null;

    /**
     * @host string The host where the database resides
     */
    private $host = null;

    /**
     * @user string username to use to connect to the database
     */
    private $user = null;

    /**
     * @pwd string The password to use to connect to the database
     */
    private $pwd = null;

    /**
     * @database string The name of the database
     */
    private $database = null;

    /**
     * @connection object Reference to the open connection
     */
    private $connection = null;

    /**
     * Helper function to the constructor.
     * getInstance takes the exact same parameters as the __construct method.
     *
     * @param string $dbhost hostname of the databaseserver
     * @param string $dbuser username to use to connect to the databaseserver
     * @param string $dbpassword password to use to connect to the databaseserver
     * @param string $dbname name of the database to connect to
     * 
     * @return object the Connection instance
     */
    public static function getInstance($name, $dbhost, $dbuser, $dbpassword, $dbname) {
        $class = __CLASS__;
        return new $class($name, $dbhost, $dbuser, $dbpassword, $dbname);
    }

    // --------------------------------------------------------------------------------------//
    // __ FUNCTIONS                                                                      //
    // --------------------------------------------------------------------------------------//

    /**
     * Initializes a Connection instance. 
     * @param string $dbhost hostname of the databaseserver
     * @param string $dbuser username to use to connect to the databaseserver
     * @param string $dbpassword password to use to connect to the databaseserver
     * @param string $dbname name of the database to connect to
     * 
     * @return object the Connection instance
     */
    function __construct($name, $dbhost, $dbuser, $dbpassword, $dbname) {
        $this->name = $name;
        $this->host = $dbhost;
        $this->user = $dbuser;
        $this->pwd = $dbpassword;
        $this->database = $dbname;
    }

    // --------------------------------------------------------------------------------------//
    // GETTER FUNCTIONS                                                                      //
    // --------------------------------------------------------------------------------------//

    /**
     * Returns the name of the connection
     * @return string the name of the connection
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Returns the name of the host. 
     * 
     * @return string The name of the host
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * Returns the name of the user. 
     * 
     * @return string The name of the user
     */
    public function getUser() {
        return $this->user;
    }

    /**
     * Returns the name of the database. 
     * 
     * @return string The name of the database
     */
    public function getDatabase() {
        return $this->database;
    }

    /**
     * Returns a reference to the current PDO connection
     * If the connection is not opened yet, this function will open it
     * 
     * @return object PDO Connection object
     */
    public function getConnection() {
        if ($this->connection === null) {
            $this->open();
        }
        return $this->connection;
    }

    /**
     * Returns the id of the last inserted record
     * 
     * @return integer The id of the last inserted record
     */
    public function lastInsertId() {
        return $this->getConnection()->lastInsertId();
    }

    // --------------------------------------------------------------------------------------//
    // ACTION FUNCTIONS                                                                      //
    // --------------------------------------------------------------------------------------//

    /**
     * Opens the database connection and stores it in the $connection private variable
     * 
     * @return object The connection instance
     */
    public function open() {
        $this->connection = new \PDO('mysql:host=' . $this->host . ';dbname=' . $this->database, $this->user, $this->pwd);
        return $this;
    }

    /**
     * Performs a query on the open connection
     * @param string $query the query string to execute
     * @return object returns a PDOStatement object
     */
    public function query($query) {
        return $this->getConnection()->query($query);
    }

    /**
     * Closes the database connection and resets the $connection private variable to null
     * 
     * @return object The connection instance
     */
    public function close() {
        $this->connection = null;
        return $this;
    }

    // --------------------------------------------------------------------------------------//
    // CHECKER FUNCTIONS                                                                     //
    // --------------------------------------------------------------------------------------//

    /**
     * Checks whether the connection is opened
     * 
     * @return boolean true or false
     */
    public function isOpen() {
        if ($this->connection === null) {
            return false;
        }
        return true;
    }

}
