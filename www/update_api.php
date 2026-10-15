<?php

/*
    Provides simple calls to update the index
    These are orchestrated by Airflow which 

    n.b. This is always passed the classification to work on so it 
    can be switched to populating a newly released classification 
    whilst the existing one is still driving the portal
    It ignores the local default classification set in the config file.

*/

require_once('config.php');
require_once('../include/BearerToken.php');
require_once('../include/PlantList.php');

// this returns facet and snippet values for taxa that are provided as 'taxon graphs' in json
$post_body = file_get_contents('php://input');

if($post_body || $_GET){

    $out = array();

    // we are serving data so check they have a bearer token that matches
    // the correct token is stored in the config secrets witht he github token and db credentials.
    if(!BearerToken::authorized('write')){
        http_response_code(401);
        echo "Unauthorized: You must provide a bearer token!";
        exit;
    }

    if($post_body){

        // all requests must have a 'kind' property
        $request_data = json_decode($post_body);
        $request_kind = isset($request_data->kind) ?  $request_data->kind : '';

        switch ($request_kind) {
            case 'taxon-values':
                $out = update_taxon_values($request_data);
                break;
            case 'sources-metadata':
            case 'facets-metadata':
            case 'scores-metadata':
            case 'snippets-metadata':
                $out = update_metadata($request_data);
                break;
            default:
                $out = (object)array(
                    'success' => false,
                    'message' => "No function call associated with request kind '$request_kind'.",
                    'docs' => array()
                );
                break;
        }
    }else{

        if(isset($_GET['page_size'])){
            $page_size = (int)$_GET['page_size'];
            $out = get_page_of_taxon_graphs($page_size, $_GET['classification']);
        }elseif(isset($_GET['wfo_id'])){
            $out = get_single_taxon_graph($_GET['wfo_id'], $_GET['classification']);
        }elseif(isset($_GET['last_modified'])){
            $out = get_last_modified($_GET['last_modified']);
        }elseif(isset($_GET['delete'])){
            $out = delete_metadata($_GET['delete']);
        }else{
            $out = (object)array(
                'success' => false,
                'message' => 'You neither provided "page_size" nor "wfo_id" parameters in the GET'
            );
        }
    
    }

    // send it back as json
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;

}else{
    // no data request so just render documentation
    render_documentation_page();
}

/*
  Given an array of taxon value objects
  update the taxa in the index with 
  these fields.
*/
function update_taxon_values($request_data){

  $out = (object)array(
    'success' => true,
    'message' => "Well done!",
    'solr_response' => null,
    'docs' => array()
  );

  $solr_docs = array();
  foreach ($request_data->docs as $taxon) {
    $response = update_single_taxon_values($taxon);
    if($response->success){
        // we found a solr doc and populated it
        $solr_docs[] = $response->solr_doc;
        unset($response->solr_doc); // remove the doc so we don't sent it all back
    }
    $out->docs[] = $response;
  }

  // save the docs to the index back to the index
  $out->solr_response =PlantList::saveSolrDocs($solr_docs, true);

  return $out;

}

function update_single_taxon_values($values){

    // get the current solr document
    $doc_id = $values->taxon . '-' . $values->classification;
    $solr_doc = PlantList::getSolrDoc($doc_id);

    // no find doc then return error
    if(!$solr_doc){
        return (object)array(
            "taxon" => $values->taxon,
            "success" => false,
            "message" => "Could not get SOLR doc for $doc_id"
        );
    }

    // absolutely refuse to index something that isn't accepted
    if($solr_doc->role_s != 'accepted'){
        return (object)array(
            "taxon" => $values->taxon,
            "success" => false,
            "message" => "This document ($doc_id) says it is '$solr_doc->role_s'. We only index accepted taxa here."
        );
    }

    // remove the existing facet properties
    foreach($solr_doc as $prop => $val){
        if(preg_match('/^wfo-f-.+_ss$/', $prop)) unset($solr_doc->{$prop}); // facet field
        if(preg_match('/^wfo-fv-.+_provenance_ss$/', $prop)) unset($solr_doc->{$prop}); // facet provenance field
        if(preg_match('/^wfo-f-.+_t$/', $prop)) unset($solr_doc->{$prop}); // text version
    }

    // remove the existing snippet properties
    $solr_doc->snippet_text_categories_ss = array(); // the category the snippet is
    $solr_doc->snippet_text_languages_ss = array(); // the language the snippet is in
    $solr_doc->snippet_text_name_ids_ss = array(); // the WFO ID of the name the snippet is attached to
    $solr_doc->snippet_text_ids_ss = array(); // the id of this snippet - used to recover the metadata for this snippet
    $solr_doc->snippet_text_sources_ss = array(); // the id of this snippet source so we can facet on it
    $solr_doc->snippet_text_bodies_txt = array(); // actual blocks of text 

    // document should now be clear of any data previously added from Fyllo,
    // as pure as when it came from Rhakhis!

    // add the fields back in - just as they are found in the values doc
    foreach($values as $prop => $val){
       $solr_doc->{$prop} = $val;
    }

    // flag when we indexed it
    $solr_doc->fyllo_last_indexed_d = time(); // as a double 
    $solr_doc->fyllo_last_indexed_dt = (new DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'); // as a date object string

    // return ok + the completed document for saving
    return (object)array(
        "taxon" => $values->taxon,
        "success" => true,
        "message" => "{$solr_doc->full_name_string_plain_s}",
        "solr_doc" => $solr_doc
    );

}

function get_page_of_taxon_graphs($page_size, $classification){

    // get a list of wfo_ids to work on

    // start with any queue jumpers that have been put in a 
    // file using the UI
    $queue_jumpers = array();
    if(file_exists(INDEX_QUEUE_FILE_PATH)){
        $lines = file(INDEX_QUEUE_FILE_PATH);
        foreach($lines as $line){
            $wfo = trim($line);
            if(preg_match('/^wfo-[0-9]{10}$/', $wfo)) $queue_jumpers[] = $wfo;
        }
        // empty the list
        file_put_contents(INDEX_QUEUE_FILE_PATH, '');
    }

    $graphs = array(); 
    foreach($queue_jumpers as $jumper){
        $graphs[] = get_taxon_graph($jumper, $classification);
    }

    // call solr for the rest of them
    $new_page_size = $page_size - count($queue_jumpers);
    if($new_page_size > 0){

        // get the taxon
        $query = array(
            'query' => "role_s:accepted",
            'filter' => ['classification_id_s:' . $classification ],
            'fields' => ['wfo_id_s'],
            'sort' => 'fyllo_last_indexed_d ASC, id ASC', // empty values will come first
            'limit' => $new_page_size
        );

        $docs = PlantList::getSolrDocs($query);

        foreach($docs as $doc){
            $graphs[] = get_taxon_graph($doc->wfo_id_s, $classification);
        }

    }

    return (object)array(
      'success' => true,
      'message' => 'Got them',
      'page_size' => $page_size,
      'docs' => $graphs
    );

}

function get_single_taxon_graph($wfo_id, $classification){

    return (object)array(
      'success' => true,
      'message' => 'Got it',
      'docs' => array(
        get_taxon_graph($wfo_id, $classification)
      )
    );

}

function get_taxon_graph($wfo_id, $classification){

    $graph = array();
    $graph['classification'] = $classification;

    // get the taxon
    $query = array(
        'query' => "wfo_id_s:$wfo_id OR wfo_id_deduplicated_ss:$wfo_id",
        'filter' => ['classification_id_s:' . $classification],
        'fields' => ['wfo_id_s', 'full_name_string_plain_s', 'wfo_id_deduplicated_ss', 'classification_id_s', 'role_s', 'accepted_id_s', 'name_ancestor_path']
    );
    
    $docs = PlantList::getSolrDocs($query);

    if($docs[0]->role_s == 'accepted'){
        // we've got a taxon
        if(isset($docs[0]->wfo_id_deduplicated_ss)){
            $graph['taxon'] = array_merge(array($docs[0]->wfo_id_s), $docs[0]->wfo_id_deduplicated_ss);
        }else{
            $graph['taxon'] = array($docs[0]->wfo_id_s);
        }
        $path = $docs[0]->name_ancestor_path;
    }elseif($docs[0]->role_s == 'synonym'){
        // this is a synonym so we need to retrieve the accepted name
        $query = array(
            'query' => "id:{$docs[0]->accepted_id_s}",
            'filter' => ['classification_id_s:' . $classification ],
            'fields' => ['wfo_id_s', 'full_name_string_plain_s', 'wfo_id_deduplicated_ss', 'classification_id_s', 'name_ancestor_path']
        );
        $docs = PlantList::getSolrDocs($query);
        if(isset($docs[0]->wfo_id_deduplicated_ss)){
            $graph['taxon'] = array_merge(array($docs[0]->wfo_id_s), $docs[0]->wfo_id_deduplicated_ss);
        }else{
            $graph['taxon'] = array($docs[0]->wfo_id_s);
        }
        $path = $docs[0]->name_ancestor_path;
    }else{
        // this is unplaced or deprecated so there is no tree
        return (object)$graph;
    }

    // get the path
    $graph['path'] = array();
    $query = array(
        'query' => "name_ancestor_path:{$path}",
        'filter' => ['classification_id_s:' . $classification, 'role_s:accepted'],
        'fields' => ['wfo_id_s', 'full_name_string_plain_s', 'wfo_id_deduplicated_ss', 'classification_id_s', 'name_ancestor_path'],
        'sort' => 'name_path_s ASC'
    );

    $docs = PlantList::getSolrDocs($query);
    foreach ($docs as $doc) {
    
        // we mustn't include the taxon in the path or it will be in the graph twice
        if(in_array($doc->wfo_id_s, $graph['taxon'])) continue;

        // all the wfo_ids the name is known by
        if(isset($doc->wfo_id_deduplicated_ss)){
            $graph['path'][] = array_merge(array($doc->wfo_id_s), $doc->wfo_id_deduplicated_ss);
        }else{
            $graph['path'][] = array($doc->wfo_id_s);
        }

        //$graph['path'][] = $doc->name_ancestor_path;
    }

    // get the synonyms
    $graph['synonyms'] = array();
    $taxon_id = $graph['taxon'][0] .'-'. $classification;
    $query = array(
        'query' => "accepted_id_s:$taxon_id",
        'filter' => ['classification_id_s:' . $classification ],
        'fields' => ['wfo_id_s', 'full_name_string_plain_s', 'wfo_id_deduplicated_ss', 'classification_id_s']
    );

    $docs = PlantList::getSolrDocs($query);

    foreach ($docs as $doc) {
        if(isset($doc->wfo_id_deduplicated_ss)){
            $graph['synonyms'][] = array_merge(array($doc->wfo_id_s), $doc->wfo_id_deduplicated_ss);
        }else{
            $graph['synonyms'][] = array($doc->wfo_id_s);
        }
    }

    return (object)$graph;
    
}

/**
 * Simple function to get the timestamp on the last
 * modified facet metadata document so we can do 
 * updates only when things have changed
 */
function get_last_modified($kind){

    // get the taxon
    $query = array(
        'query' => "*:*",
        'filter' => ["kind_s:$kind"],
        'fields' => ['last_modified_d'],
        'sort' => 'last_modified_d DESC',
        'limit' => 1
    );
    $docs = PlantList::getSolrDocs($query);
    if(count($docs) == 0) $last_mod = 0.0;
    else $last_mod = (float)$docs[0]->last_modified_d;

    return (object)array( 
        'success' => true,
        'message' => 'Got it',
        'value' => $last_mod
    );

}


function update_metadata($request_data){

    $out = (object)array(
        'success' => true,
        'message' => "Well done!",
        'solr_response' => null,
        'docs' => array()
    );

    // each doc supplied is simply written to the index
    // it will overwrite any existing doc
    $solr_docs = array();
    foreach ($request_data->docs as $new_doc) {
        $solr_docs[] = $new_doc;
    }

    // save the docs to the index to the index and commit
    $out->solr_response = PlantList::saveSolrDocs($solr_docs, true);

    return $out; 

}

function delete_metadata($kind){

    // '{"delete":{"query":"kind_s:' . $kind . '"} }';
    $query = (object)array(
        'delete' => (object)array(
            'query' => "kind_s:{$kind}"
        )
    );

    $response = PlantList::deleteSolrDocs($query, true);

    if($response->error){
        $out = (object)array(
            'success' => false,
            'message' => "Something went wrong",
            'solr_response' => $response,
            'docs' => array()
        );
    }else{
        $out = (object)array(
            'success' => true,
            'message' => "All gone!",
            'solr_response' => $response,
            'docs' => array()
        );
    }

    return $out; 

}

function render_documentation_page(){

    global $plant_list_system_message;

    require_once('header.php');
?>
<main class="container">
    <h2>API for updating the portal index</h2>
    <p class="lead">
        This is how content from <a href="https://fyllo.rbge.info">Fyllo</a> is updated in the portal index.
        It is not a public API. You need to pass a Bearer Token to access more than this page.
        It is described here as a convenience.
        There is a <a href="sync_api.php">complementary API</a> that is used to sync another portal index with this portals index.
    </p>
    <p>
        Fyllo doesn't know anything about the classification of the names it tracks.
        The portal knows about the taxonomy because it contains the latest classification.
        Airflow orchestrates calls between the <a href="https://fyllo.rbge.info/api.php">Fyllo API</a> and this API to
        keep the taxon
        records in the portal up to date.
    </p>
    <p>
        The different calls are listed below.
    </p>

    <h3>Get taxon graph</h3>
    <p>
        A get call passes as <strong>wfo_id</strong> parameter and a single taxon graph is returned as a JSON object.
        Taxon graphs as described on the <a href="https://fyllo.rbge.info/api.php">Fyllo API page</a>.
    </p>

    <h3>Get taxon graphs</h3>
    <p>
        A GET call passes <strong>page_size</strong> and a page full of the most stale taxon graphs is returned
        as an array of JSON objects.
        The output is suitable for sending to the "Fetch values for taxon trees" on the Fyllo API.
        This is likely used to crawl the whole classification when re-index.
    </p>

    <h3>Update taxa</h3>
    <p>
        A POST call sends an array of JSON objects that were generated by the Fyllo "Fetch values for taxon trees"
        API call. This updates the associated taxa in the index.
    </p>

    <h3>Update facets metadata</h3>
    <p>
        A POST call sends an array of JSON objects that were generated by the Fyllo facets metadata
        API call. This overwrites all the facet metadata in the index.
    </p>

    <h3>Update sources metadata</h3>
    <p>
        A POST call sends an array of JSON objects that were generated by the Fyllo sources metadata
        API call. This overwrites all the sources metadata in the index.
    </p>

    <h3>Update scores metadata</h3>
    <p>
        A POST call sends an array of JSON objects (max 1,000) that were generated by the Fyllo scores metadata
        API call. This overwrites scores metadata that is sent metadata in the index.
    </p>

    <h3>Update snippets metadata</h3>
    <p>
        A POST call sends an array of JSON objects (max 1,000) that were generated by the Fyllo snippets metadata
        API call. This overwrites snippets metadata that is sent metadata in the index.
    </p>

    <h3>Return and error handling</h3>
    <p>All calls return a JSON object with a boolean property <strong>success</strong> and a <strong>message</strong>
        property.</p>
</main>
<?php
    require_once('footer.php');
}