<?php
/*

    This is the SOURCE side of a source-client synchronization 
    protocol.

    A sync_client.php script on another machine will call this script.

    You call this endpoint passing the API Bearer token and a greater_than parameter. 
    A page of SOLR documents is returned. The page size is at the discretion of this service but defaults to 500.
    We use the internal _version_ field of SOLR to track changed documents for synchronization.
    You will receive documents with the lowest _version_ numbers that are higher than the number provided.
    Start an empty index with a _version_ of 0.

    The client application must keep track of which its highest _version_ number is. 
    This must not be done by writing the SOLR documents directly to its own index because changed documents will cause an error.
    If the a document (as identified by its ID) is overwritten with new data containing the same _version_ then SOLR will increment the _version_ in the saved document.
    It will then be out of sync for the next update which will fail.

    Allow the client to maintain its own _version_ numbers for submitted documents.
    Copy the _version_ value to the _source_version_ field and delete the _version_ field before committing the document to the client index.
    A simple query can then be used to find highest _source_version for the next synchronization paging call.

*/

require_once('config.php');
require_once('../include/BearerToken.php');
require_once('../include/PlantList.php');

// not authorized you can go away
if(!BearerToken::authorized('read')){
    header("HTTP/1.1 403 Forbidden");
    echo '<h1>403 Forbidden</h1>';
    echo "<p>You must supply a valid API bearer token.";
    exit;
}

// if you haven't provided a good version number you can go away as well
if( !isset($_GET['greater_than']) || !is_numeric($_GET['greater_than']) ){
    header("HTTP/1.1 400 Bad Request");
    echo '<h1>400 Bad Request</h1>';
    echo "<p>You must supply a valid greater_than value.";
    exit;
}

$greater_than = $_GET['greater_than']; 

// build a query of what we want
$query = array(
        'query' => "_version_:[{$greater_than} TO *]",
        'sort' => '_version_ ASC', // empty values will come first
        'limit' => API_SYNC_PAGE_SIZE,
        'offset' => 1 // needed to do a greater than query as start of series is included by default
);

$docs = PlantList::getSolrDocs($query);

// we could zip this maybe?
header('Content-Type: application/json');
echo json_encode($docs);
exit;
