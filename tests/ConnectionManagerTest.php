<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require "./../src/Connection.php";
require "./../src/ConnectionManager.php";

final class ConnectionManagerTest extends TestCase {

    const DBHOST = "localhost";
    const DBUSER = "testuser";
    const DBPWD = "testpwd";

    const CONNNAME1 = "testdb";
    const DBNAME1 = "flphpdbtestdbtest";

    const CONNNAME2 = "livedb";
    const DBNAME2 = "flphpdbtestdblive";

    public static function setUpBeforeClass(): void {
        // create the database
        try {
            $dbh = new PDO("mysql:host=" . ConnectionManagerTest::DBHOST, ConnectionManagerTest::DBUSER, ConnectionManagerTest::DBPWD);
            $dbh->exec("CREATE DATABASE `"  . ConnectionManagerTest::DBNAME1 . "`;
                CREATE USER '" . ConnectionManagerTest::DBUSER . "'@'" . ConnectionManagerTest::DBHOST. "' IDENTIFIED BY '" . ConnectionManagerTest::DBPWD . "';
                GRANT ALL ON `" . ConnectionManagerTest::DBNAME1 . "`.* TO '" . ConnectionManagerTest::DBUSER . "'@'" . ConnectionManagerTest::DBHOST . "';
                FLUSH PRIVILEGES;")
                    or die(print_r($dbh->errorInfo(), true));
            $dbh->exec("CREATE DATABASE `"  . ConnectionManagerTest::DBNAME2 . "`;
                CREATE USER '" . ConnectionManagerTest::DBUSER . "'@'" . ConnectionManagerTest::DBHOST. "' IDENTIFIED BY '" . ConnectionManagerTest::DBPWD . "';
                GRANT ALL ON `" . ConnectionManagerTest::DBNAME2 . "`.* TO '" . ConnectionManagerTest::DBUSER . "'@'" . ConnectionManagerTest::DBHOST . "';
                FLUSH PRIVILEGES;")
                    or die(print_r($dbh->errorInfo(), true));
        } catch (PDOException $e) {
            die("DB ERROR: " . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void {
        // drop the database
        try {
            $dbh = new PDO("mysql:host=" . ConnectionManagerTest::DBHOST, ConnectionManagerTest::DBUSER, ConnectionManagerTest::DBPWD);
            $result = $dbh->exec("DROP DATABASE `"  . ConnectionManagerTest::DBNAME1 . "`;");
            if ($result === false) {
                die(print_r($dbh->errorInfo(), true));
            }
            $result = $dbh->exec("DROP DATABASE `"  . ConnectionManagerTest::DBNAME2 . "`;");
            if ($result === false) {
                die(print_r($dbh->errorInfo(), true));
            }
        } catch (PDOException $e) {
            die("DB ERROR: " . $e->getMessage());
        }
    }

    // Testing 
    // -------
    public function testConnectionManager(): void {
        $connmgr = FL\ConnectionManager::getInstance();
        $conn1 = FL\Connection::getInstance(ConnectionManagerTest::CONNNAME1, ConnectionManagerTest::DBHOST, ConnectionManagerTest::DBUSER, ConnectionManagerTest::DBPWD, ConnectionManagerTest::DBNAME1);
        $conn2 = FL\Connection::getInstance(ConnectionManagerTest::CONNNAME2, ConnectionManagerTest::DBHOST, ConnectionManagerTest::DBUSER, ConnectionManagerTest::DBPWD, ConnectionManagerTest::DBNAME2);
        
        $connmgr->addConnection($conn1);
        $connmgr->addConnection($conn2);
        
        $this->assertEquals($connmgr->getConnectionCount(), 2);

        $connmgr->getConnection(ConnectionManagerTest::CONNNAME1)->open();
        $this->assertTrue($connmgr->getConnection(ConnectionManagerTest::CONNNAME1)->isOpen());
        $this->assertFalse($connmgr->getConnection(ConnectionManagerTest::CONNNAME2)->isOpen());
        
        $connmgr->getConnection(ConnectionManagerTest::CONNNAME2)->open();
        $this->assertTrue($connmgr->getConnection(ConnectionManagerTest::CONNNAME1)->isOpen());
        $this->assertTrue($connmgr->getConnection(ConnectionManagerTest::CONNNAME2)->isOpen());
        
        $connmgr->getConnection(ConnectionManagerTest::CONNNAME2)->close();
        $this->assertTrue($connmgr->getConnection(ConnectionManagerTest::CONNNAME1)->isOpen());
        $this->assertFalse($connmgr->getConnection(ConnectionManagerTest::CONNNAME2)->isOpen());

        $connmgr->getConnection(ConnectionManagerTest::CONNNAME1)->close();
        $this->assertFalse($connmgr->getConnection(ConnectionManagerTest::CONNNAME1)->isOpen());
        $this->assertFalse($connmgr->getConnection(ConnectionManagerTest::CONNNAME2)->isOpen());
        
    }

}
