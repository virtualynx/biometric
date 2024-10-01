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

    public static function endsWith($haystack, $needle){
        $length = strlen( $needle );
        if( !$length ) {
            return true;
        }

        return substr( $haystack, -$length ) === $needle;
    }

    public static function base64_to_image($base64_string, $output_file) {
        // open the output file for writing
        $ifp = fopen( $output_file, 'wb' ); 
    
        // split the string on commas
        // $data[ 0 ] == "data:image/png;base64"
        // $data[ 1 ] == <actual base64 string>
        $data = explode( ',', $base64_string );
    
        // we could add validation here with ensuring count( $data ) > 1
        fwrite( $ifp, base64_decode( $data[ 1 ] ) );
    
        // clean up the file resource
        fclose( $ifp ); 
    
        return $output_file; 
    } 
}