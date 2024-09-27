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
    private const host = "localhost";
    private const user = "root";
    private const password = "";
    private const database = "biometric";
    private $conn;

    function __construct(){
        $this->conn = new mysqli(self::host, self::user, self::password, self::database);
        if (mysqli_connect_errno()) {
            printf("Connection Failed: %s\n",  mysqli_connect_errno());
            exit();
        }
    }

    function __destruct() {
        $this->conn->close();
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
