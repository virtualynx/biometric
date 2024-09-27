<?php
/*
 * Author: Dahir Muhammad Dahir
 * Date: 26-April-2020 12:05 AM
 * About: I will tell you later
 */


namespace biometric\src\core;

use mysqli;

date_default_timezone_set("Asia/Jakarta");

class Database {
    // private const host = "localhost";
    // private const user = "root";
    // private const password = "";
    // private const database = "biometric";

    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $database = "biometric";

    private $conn;

    function __construct(){
        // $this->conn = new mysqli(self::host, self::user, self::password, self::database);

        $env = parse_ini_file(dirname(__FILE__).'/../../.env');

        if(empty($env)){
            http_response_code(500);
            echo 'Missing .env file';
            exit;
        }

        $this->host = $env['BIOMETRIC_DB_HOST'];
        $this->user = $env['BIOMETRIC_DB_USER'];
        $this->password = $env['BIOMETRIC_DB_PASSWORD'];
        $this->database = $env['BIOMETRIC_DB_NAME'];
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->database);
        if (mysqli_connect_errno()) {
            printf("Connection Failed: %s\n",  mysqli_connect_errno());
            exit();
        }
    }

    function __destruct() {
        if(!empty($this->conn)){
            $this->conn->close();
        }
    }

    function query($query){
        $results = [];

        $rs = mysqli_query($this->conn, $query);
        while($row = mysqli_fetch_assoc($rs)) {
            $results []= $row;
        }

        return json_decode(json_encode($results));
    }
}
