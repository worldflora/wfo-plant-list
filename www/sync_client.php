<?php

/*

    Mirroring of another SOLR index. 

    This script will call a sync_api.php on another server for 
    a page of index documents that it then writes to the local index

    More documentation in the sync_source.php file

*/

require_once('config.php');
require_once('../include/BearerToken.php');
require_once('../include/PlantList.php');


/*

    This can be triggered in two ways.

    1) It can be called by a cron job on the local machine.

    2) It can be called via HTTP by an orchestration service (such as an Airflow DAG)
    but in this case a valid API token with 'write' authorization should be 
    passed in the header or 

*/
if (php_sapi_name() != "cli" && !BearerToken::authorized('write')) {
    header("HTTP/1.1 403 Forbidden");
    echo '<h1>403 Forbidden</h1>';
    echo "<p>You must supply a valid API bearer token.";
    exit;
} 

// we return json object to make it easier for orchestration
$out = (object)array();
log_progress('start', time());

// get the last updated version from our own index
$query = array(
    'query' => "_source_version_:[* TO *]",
    'fields' => '_source_version_',
    'sort' => '_source_version_ DESC', // empty values will come last
    'limit' => 1
);

$docs = PlantList::getSolrDocs($query);

if($docs){
    $greater_than = $docs[0]->_source_version_;
}else{
    // we didn't find any with _source_version_ so we are a clean index
    log_progress('message', "No previously sync'd docs found.");
    $greater_than = 0;
}

log_progress('greater_than', $greater_than);

// call the source for the next page of results

log_progress('source_url', API_SYNC_SOURCE_URL);

$ch = curl_init(API_SYNC_SOURCE_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'WFO Plant List');
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer " . API_SYNC_SOURCE_TOKEN
));

log_progress('calling_source', time());
$response = curl_exec($ch);
if(curl_errno($ch)){
    log_progress('curl_error', curl_error($curl));
}else{
    print_r($response);

}

// remove the _version_fields

// store them in ourselves

log_progress('finish', time());

if (php_sapi_name() != "cli"){
    header('Content-Type: application/json');
    echo json_encode($out);
}

exit;

function log_progress($name, $value){

    global $out;

    // if we are on the commandline write it out
    if (php_sapi_name() == "cli"){
        echo "\n{$name}:\t\t{$value}";
    }else{
        // add it to the object
        $out->{$name} = $value;
    }

}