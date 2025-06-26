<?php

/**
 * This is the root class from which
 * all the other classes in the application 
 * inherit.
 */
class PlantList{


    public static function getCurlHandle($uri){
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WFO Plant List');
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, SOLR_USER . ":" . SOLR_PASSWORD);
        return $ch;
    }

    /**
     * Run the cURL requests in one place 
     * so we can catch errors etc
     */
    public static function runCurlRequest($curl){
    
        $out['response'] = curl_exec($curl);  
        $out['error'] = curl_errno($curl);
        
        if(!$out['error']){
            // no error
            $out['info'] = curl_getinfo($curl);
            $out['headers'] = PlantList::getHeadersFromCurlResponse($out);
            $out['body'] = trim(substr($out['response'], $out['info']["header_size"]));

        }else{
            // we are in error
            $out['error_message'] = curl_error($curl);
        }
        
        // we close it down after it has been run
        curl_close($curl);
        
        return (object)$out;
        
    }

    public static function curlPostJson($uri, $json){
        $ch = PlantList::getCurlHandle($uri);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $response = PlantList::runCurlRequest($ch);
        return $response;
    }

    /**
     * cURL returns headers as sting so we need to chop them into
     * a useable array - even though the info is in the 
     */
    public static function getHeadersFromCurlResponse($out){
        
        $headers = array();
        
        // may be multiple header blocks - we want the last
        $headers_block = substr($out['response'], 0, $out['info']["header_size"]-1);
        $blocks = explode("\r\n\r\n", $headers_block);
        $header_text = trim($blocks[count($blocks) -1]);

        foreach (explode("\r\n", $header_text) as $i => $line){
            if ($i === 0){
                $headers['http_code'] = $line;
            }else{
                list ($key, $value) = explode(': ', $line);
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    function getBodyFromCurlResponse($response){
        return trim(substr($response, strpos($response, "\r\n\r\n")));
    }

    /**
     * 
     * 
     */
    public static function getSolrDocs($query){
        $data = PlantList::getSolrResponse($query);
        if(isset($data->response->docs)) return $data->response->docs;
        else return null;
    }

    public static function getSolrResponse($query){

        $solr_query_uri = SOLR_QUERY_URI . '/query';
        $response = PlantList::curlPostJson($solr_query_uri, json_encode($query));
        if(isset($response->body)){
            $data = json_decode($response->body);
            return $data;
        }else{
            return null;
        }

    }

    
    public static function isRankWord($word){

        global $ranks_table;

        $word = strtolower($word);
        foreach($ranks_table as $rank => $rankInfo){

            // does it match the rank name
            if(strtolower($word) == $rank) return $rank;

            // does it match the official abbreviation
            if($word == strtolower($rankInfo['abbreviation'])) return $rank;

            // does it match one of the known alternatives
            foreach($rankInfo['aka'] as $aka){
                if($word == strtolower($aka)) return $rank;
            }

        }

        // no luck so it isn't a rank word we know of
        return false;

    }

    public static function getLatestClassificationId(){

            $query = array(
                'query' => '*:*',
                'facet' => array(
                    'classifications' => array(
                        "type" => "terms",
                        "field" => "classification_id_s",
                        'limit' => 1000
                )
                ),
                'limit' => '0'

            );
            
            $response = PlantList::getSolrResponse($query);

            return $response->facets->classifications->buckets[0]->val;

    }

}