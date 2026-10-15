<?php


/**
 * A utility to check that they have supplied a
 * bearer token to access pages
 * 
 * This mechanism is used when the ORCID linked API keys are inappropriate
 * - the db might not exist for example
 * 
 */
class BearerToken{


    static function authorized($rite){

        $request_token = BearerToken::getRequestBearerToken();

        //echo $request_token;

        return (
                array_key_exists($request_token, API_BEARER_TOKENS) 
                &&
                in_array($rite, API_BEARER_TOKENS[$request_token])
                );

    }

    /**
     * get access token from header
     * */
    static function getRequestBearerToken() {
        $headers = BearerToken::getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }

        // for testing purposes we allow the token to be passed as a request parameter
        // this shouldn't be done in production
        if(isset($_REQUEST['bearer_token'])) return $_REQUEST['bearer_token'];

        // not found one so return null
        return null;
    }

    /** 
     * Get header Authorization
     * https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens
     * */
    static function getAuthorizationHeader(){
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        }
        else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

}