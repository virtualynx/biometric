<?php
namespace biometric\src\core;

require_once(dirname(__FILE__)."/models/EnvFileModel.php");

use biometric\src\core\models\EnvFileModel;
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
        $env = new EnvFileModel();

        $this->host = $env->get('BIOMETRIC_DB_HOST');
        $this->user = $env->get('BIOMETRIC_DB_USER');
        $this->password = $env->get('BIOMETRIC_DB_PASSWORD');
        $this->database = $env->get('BIOMETRIC_DB_NAME');

        mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);

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

    function execute($query){
        $rs = mysqli_query($this->conn, $query);

        return $rs;
    }

    function beginTransaction(){
        $this->conn->autocommit(FALSE);
    }

    function endTransaction(){
        if($this->conn->commit() == false){
            $this->conn->rollback();
        }
    }
}
