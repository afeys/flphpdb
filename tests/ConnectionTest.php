<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require "./../src/Connection.php";

final class ConnectionTest extends TestCase {

    const CONNNAME = "livedb";
    const DBHOST = "localhost";
    const DBUSER = "testuser";
    const DBPWD = "testpwd";
    const DBNAME = "flphpdbtestdb";

    public static function setUpBeforeClass(): void {
        // create the database
        try {
            $dbh = new PDO("mysql:host=" . ConnectionTest::DBHOST, ConnectionTest::DBUSER, ConnectionTest::DBPWD);
            $dbh->exec("CREATE DATABASE `"  . ConnectionTest::DBNAME . "`;
                CREATE USER '" . ConnectionTest::DBUSER . "'@'" . ConnectionTest::DBHOST. "' IDENTIFIED BY '" . ConnectionTest::DBPWD . "';
                GRANT ALL ON `" . ConnectionTest::DBNAME . "`.* TO '" . ConnectionTest::DBUSER . "'@'" . ConnectionTest::DBHOST . "';
                FLUSH PRIVILEGES;")
                    or die(print_r($dbh->errorInfo(), true));
        } catch (PDOException $e) {
            die("DB ERROR: " . $e->getMessage());
        }
    }

    public static function tearDownAfterClass(): void {
        // drop the database
        try {
            $dbh = new PDO("mysql:host=" . ConnectionTest::DBHOST, ConnectionTest::DBUSER, ConnectionTest::DBPWD);
            $result = $dbh->exec("DROP DATABASE `"  . ConnectionTest::DBNAME . "`;");
            if ($result === false) {
                die(print_r($dbh->errorInfo(), true));
            }
        } catch (PDOException $e) {
            die("DB ERROR: " . $e->getMessage());
        }
    }

    // Testing the GetInstance variations
    // ----------------------------------
    public function testGetInstance(): void {
        $conn = FL\Connection::getInstance(ConnectionTest::CONNNAME, ConnectionTest::DBHOST, ConnectionTest::DBUSER, ConnectionTest::DBPWD, ConnectionTest::DBNAME);
        $this->assertEquals($conn->getName(), ConnectionTest::CONNNAME);
        $this->assertEquals($conn->getHost(), ConnectionTest::DBHOST);
        $this->assertEquals($conn->getDatabase(), ConnectionTest::DBNAME);
        $this->assertEquals($conn->getUser(), ConnectionTest::DBUSER);
    }

    public function testOpenConnection(): void {
        $conn = FL\Connection::getInstance(ConnectionTest::CONNNAME, ConnectionTest::DBHOST, ConnectionTest::DBUSER, ConnectionTest::DBPWD, ConnectionTest::DBNAME);
        $this->assertFalse($conn->isOpen());
        $conn->open();
        $this->assertTrue($conn->isOpen());
    }
    
    public function testCloseConnection(): void {
        $conn = FL\Connection::getInstance(ConnectionTest::CONNNAME, ConnectionTest::DBHOST, ConnectionTest::DBUSER, ConnectionTest::DBPWD, ConnectionTest::DBNAME);
        $conn->open();
        $this->assertTrue($conn->isOpen());
        $conn->close();
        $this->assertFalse($conn->isOpen());
    }
}
