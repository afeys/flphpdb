<?php

namespace FL;

class ConnectionManager {

    /**
     * 
     * @connections array Array containing the list of connections used by the application.
     */
    private $connections = array();

    /**
     * Helper function to the constructor.
     * 
     * @return object the ConnectionManager instance
     */
    public static function getInstance() {
        // this implements the 'singleton' design pattern
        static $instance;

        if (!isset($instance)) {
            $c = __CLASS__;
            $instance = new $c;
        }
        return $instance;
    }

    // --------------------------------------------------------------------------------------//
    // __ FUNCTIONS                                                                      //
    // --------------------------------------------------------------------------------------//

    /**
     * Initializes a Connection instance. 
     * should not be called directly, use getInstance() instead.
     */
    function __construct() {
        
    }

    // --------------------------------------------------------------------------------------//
    // SETTER FUNCTIONS                                                                      //
    // --------------------------------------------------------------------------------------//

    public function addConnection($connection) {
        if ($connection instanceof Connection) {
            $this->connections[$connection->getName()] = $connection;
        }
    }
    
     
    // --------------------------------------------------------------------------------------//
    // GETTER FUNCTIONS                                                                      //
    // --------------------------------------------------------------------------------------//

    /**
     * 
     * @param string $connectionname
     * @return object Connection object for the given $connectioname, or null if none found
     */
    public function getConnection($connectionname) {
        if (is_array($this->connections)) {
            if (array_key_exists($connectionname, $this->connections)) {
                return $this->connections[$connectionname];
            }
        }
        return null;
    }

    public function getConnectionCount() {
        return count($this->connections);
    }

}
