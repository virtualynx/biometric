<?php
namespace biometric\src\core\models;

class EnvFileModel {
    private $env = [];

    function __construct(){
        $env = parse_ini_file(dirname(__FILE__).'/../../../.env');
        if(empty($env)){
            http_response_code(500);
            echo 'Missing .env file';
            exit;
        }

        $this->env = $env;
    }

    function get($key){
        if(!isset($this->env[$key])){
            throw new \Exception("Env property $key not found", 500);
        }

        return $this->env[$key];
    }
}
