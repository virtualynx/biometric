<?php
namespace biometric\src\core\utils;

class Helper{
    public static function removesTrailingSlash(string $path){
        if(substr_compare($path, '/', -strlen('/')) === 0){
            $path = substr($path, 0, strlen($path)-1);
        }

        return $path;
    }

    public static function startsWith($haystack, $needle){
        $length = strlen( $needle );

        return substr( $haystack, 0, $length ) === $needle;
    }

    function endsWith($haystack, $needle){
        $length = strlen( $needle );
        if( !$length ) {
            return true;
        }

        return substr( $haystack, -$length ) === $needle;
    }
}