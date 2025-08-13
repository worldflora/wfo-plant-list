<?php

/*

Calls are 303 redirected here by the semantic web content negotiation in index.php

Responds to calls of the form

sw_data.php?wfo=wfo-0000813432&format=json

and returns data in the correct format for Semantic Web calls.

*/

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');

// are the looking for a name or a taxon?
$wfo = @$_GET['wfo'];
$format_string = @$_GET['format'];

// check they have given params
if(!$wfo || !$format_string){
    http_response_code(400);
    echo "HTTP 400 Bad Request: You must specify a WFO ID and format.";
    exit;
}

// check wfo id is of right form
if(
    !preg_match('/^wfo-[0-9]{10}$/', $wfo) // not a name wfo
    &&
    !preg_match('/^wfo-[0-9]{10}-[0-9]{4}-[0-9]{2}$/', $wfo) // not a taxon wfo
    &&
    !preg_match('/^[0-9]{4}-[0-9]{2}$/', $wfo) // not a classification wfo
 ){

    http_response_code(400);
    echo "HTTP 400 Bad Request: '$wfo' does not resemble a WFO ID or classification version.";
    exit;
 }

// check format is recognized
$format = null;
$formats = \EasyRdf\Format::getFormats();
$formats_supported = array();

// also check we have a class loadable for that format
foreach($formats as $key => $value){
    $serialiserClass  = $value->getSerialiserClass();
    if($serialiserClass){
        $formats_supported[$key] = $value;
    }
}

foreach($formats_supported as $key => $value){
    if($key == $format_string){
        $format = $value;
    } 
}

if(!$format){
    http_response_code(400);
    echo "HTTP 400 Bad Request: '$format_string' is not supported.";
    echo " Available formats are: " . implode(', ', array_keys($formats_supported));
    exit;
}

// got the basics right. Now let's generate a graph and return it
$graph = null; // the RDF Graph we will return

// we set up a namespace for the graph
\EasyRdf\RdfNamespace::set('wfo', 'https://list.worldfloraonline.org/terms/');

if(preg_match('/^[0-9]{4}-[0-9]{2}$/', $wfo)){

    // they are after the classification so we create a really simple graph of it.
    // not much to say
    $graph = new \EasyRdf\Graph();
    $classification = $graph->resource(get_uri($wfo), 'wfo:Classification');
    $parts = explode('-', $wfo);
    $classification->set('wfo:month', $parts[0]);
    $classification->set('wfo:year', $parts[1]);

}else{

    // they are looking for a name or taxon
    // in either case the SOLR ID is a qualified wfo id

    if(preg_match('/^wfo-[0-9]{10}$/', $wfo)){
        // we have an unqualified wfo id we need to convert it to the latest classification
        $wfo_qualified_id = $wfo . '-' . WFO_DEFAULT_VERSION;
    }else{
        // wfo is already qualified
        $wfo_qualified_id = $wfo;
    }

    $record = new TaxonRecord($wfo_qualified_id);

    // if we can't load a record - probably doesn't exist
    if(!$record->exists()){
        http_response_code(404);
        echo "Not found: {$wfo}";
        exit;
    }

    $graph = new \EasyRdf\Graph();

    // fields depending on if it is a name or a taxon
    if($record->getIsName()){
       $root_resource = get_name_resource($graph, $record);
    }else{
       $root_resource = get_taxon_resource($graph, $record);
    }

}

// no graph no go
if(!$graph){
    http_response_code(500);
    echo "Internal Server Error: Unable to generate graph for $wfo";
    exit;
}

$serialiserClass  = $formats[$format_string]->getSerialiserClass();
$serialiser = new $serialiserClass();
    
// if we are using GraphViz then we add some parameters 
// to make the images nicer
if(preg_match('/GraphViz/', $serialiserClass)){
    $serialiser->setAttribute('rankdir', 'LR');
}
$data = $serialiser->serialise($graph, $format_string);
header('Content-Type: ' . $format->getDefaultMimeType());
echo $data;

// ----- FUNCTIONS ------

function get_name_resource($graph, $record){

    $name_resource = $graph->resource(get_uri($record->getWfoId()), 'wfo:TaxonName');

    // 'rank_s' => 'wfo:rank',
    $name_resource->add('wfo:rank', $graph->resource('wfo:' . $record->getRank()));

    // 'full_name_string_plain_s' => 'wfo:fullName'
    $name_resource->add('wfo:fullName', $record->getFullNameStringPlain());

    // 'authors_string_s' => 'wfo:authorship',
    $name_resource->add('wfo:authorship', $record->getAuthorsString());

    // 'authors_string_s' => 'dc:creator',
    $name_resource->add('dc:creator', $record->getAuthorsString());

    // 'placed_in_genus_s' => 'wfo:genusName',
    $name_resource->add('wfo:genusName', $record->getGenusString());

    // 'species_string_s' => 'wfo:specificEpithet'
    $name_resource->add('wfo:specificEpithet', $record->getSpeciesString());

    // 'citation_micro_s' => 'wfo:publicationCitation'
    if($record->getCitationMicro()) $name_resource->add('wfo:publicationCitation', $record->getCitationMicro());

    // 'basionym_id_s' => 'wfo:hasBasionym'
    if($record->getBasionymId()) $name_resource->add('wfo:hasBasionym', $graph->resource(get_uri($record->getBasionymId())));

    // add in all the placements of this name
    $usages = $record->getUsages();

    foreach($usages as $use){
        if($use->getRole() == 'accepted'){
            $name_resource->add('wfo:acceptedNameFor', $graph->resource(get_uri($use->getId())));
        }

        if($use->getRole() == 'synonym'){
            $name_resource->add('wfo:isSynonymOf', $graph->resource(get_uri($use->getAcceptedId())));
        }
    }

    // add the current usage of the name
    if($record->getCurrentUsage()){
        $name_resource->add('wfo:currentPreferredUsage', $graph->resource(get_uri($record->getCurrentUsage()->getId())));
    }

    // are there any deduplication IDs associated with it?
    // deduplicatedID
    $dedupes = $record->getWfoIdsDeduplicated();
    foreach ($dedupes as $value) {
       $name_resource->add('wfo:deduplicatedID', $value);
    }
    
    $references = $record->getNomenclaturalReferences();
    add_references($references, $name_resource, $graph);


    return $name_resource;

} // name resource

function get_taxon_resource($graph, $record){
    
    $taxon_resource = $graph->resource(get_uri($record->getId()), 'wfo:TaxonConcept');

    // link it to the classification
    $taxon_resource->add('wfo:classification', $graph->resource(get_uri($record->getClassificationId())) );

    //'dc:replaces'
    $replaces = $record->getReplaces();
    if($replaces) $taxon_resource->add('dc:replaces', $graph->resource(get_uri($replaces->getId())));

    // 'dc:isReplacedBy'
    $replaced_by = $record->getReplacedBy();
    if($replaced_by) $taxon_resource->add('dc:isReplacedBy', $graph->resource(get_uri($replaced_by->getId())));

    // the taxon has an accepted name
    $taxon_resource->add('wfo:hasName', get_name_resource($graph, $record));

    // parent
    $parent = $record->getParent();
    if($parent) $taxon_resource->add('dc:isPartOf', $graph->resource(get_uri($parent->getId())));

    // children
    $children = $record->getChildren();
    if($children){
        foreach($children as $child){
            $taxon_resource->add('dc:hasPart', $graph->resource(get_uri($child->getId())));
        }
    }

    // synonyms
    $synonyms = $record->getSynonyms();
    if($synonyms){
        foreach($synonyms as $syn){
            $taxon_resource->add('dc:hasSynonym', $graph->resource(get_uri($syn->getWfoId())));
        }
    }

    $references = $record->getTaxonomicReferences();
    add_references($references, $taxon_resource, $graph);

    return $taxon_resource;

} // taxon resource


function add_references($references, $resource, $graph){
    foreach($references as $ref){

        $ref_resource = $graph->resource($ref->uri);
        $resource->add('dc:references',  $ref_resource);
        $ref_resource->add('dc:title', $ref->label);
        $ref_resource->add('dc:type', $ref->kind);
        if($ref->comment) $ref_resource->add('dc:description', $ref->comment);
        if($ref->thumbnailUri) $ref_resource->add('og:image', $graph->resource($ref->thumbnailUri));
    }
}
