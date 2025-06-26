<?php

require_once('../vendor/autoload.php'); // composer libraries
require_once('../../wfo_secrets.php'); // outside the github root
require_once('../include/PlantList.php'); 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);
//error_reporting(E_ALL);
session_start();

define('WFO_SERVICE_VERSION','2.0.1');

// whether to expose facet browsing
// should be defined in wfo_secrets but set default just incase.
if(!defined("WFO_FACET_BROWSE_ON")){
  define('WFO_FACET_BROWSE_ON', false);
}

// Location of the solr server
define('SOLR_QUERY_URI', $solr_query_uri); // from wfo_secrets.php

// used for lookups and other services that don't want to 
// trouble themselves with many versions of backbone
// will normally be set to the most recent.
if(isset($over_ride_classification) && isset($default_classification_versions[$over_ride_classification])){
  define('WFO_DEFAULT_VERSION',$default_classification_versions[$over_ride_classification]);
}else{
  define('WFO_DEFAULT_VERSION',$default_classification_versions[0]);
}

define('SOLR_USER', $solr_user); // from wfo_secrets.php
define('SOLR_PASSWORD', $solr_password); // from wfo_secrets.php

// facets to used
// these are a list of the field names IDs to use
// in the order provided
$facet_ids = array(
  "wfo-f-5_ss", // Life form
  "wfo-f-2_ss", // Countries ISO
  "wfo-f-8_ss", // TDWG Countries
  "wfo-f-10_ss", // IUCN Statuses
  "role_s",
  "rank_s",
  "placed_in_phylum_s",
  "placed_in_family_s",
  "placed_in_genus_s",
  "nomenclatural_status_s"
);

// this ensures that we have 
// the names of the facets and facet values
// cached in the session and refreshed every
// now and then.

// we only do it if faceting is on
if(WFO_FACET_BROWSE_ON){

    // the facets cache
    $facets_cache = @$_SESSION['facets_cache'];

    if(!$facets_cache || @$_GET['facet_cache_refresh'] == 'true'){

        $facets_cache = array();

        $query = array(
            'query' => "kind_s:wfo-facet",
            'limit' => 10000
        );
      
        $docs  = PlantList::getSolrDocs($query);
        foreach($docs as $doc){
            $facets_cache[$doc->id] = json_decode($doc->json_t);
        }

        $_SESSION['facets_cache'] = $facets_cache;


    }


    // we do the same for sources of info
    $sources_cache = @$_SESSION['sources_cache'];

    if(!$sources_cache || @$_GET['sources_cache_refresh'] == 'true'){
        
        $sources_cache = array();

        $query = array(
            'query' => "kind_s:wfo-facet-source",
            'limit' => 10000
        );
      
        $docs  = PlantList::getSolrDocs($query);
      
          foreach($docs as $doc){
              $sources_cache[$doc->id] = json_decode($doc->json_t);
          }

          $_SESSION['sources_cache'] = $sources_cache;

    }

} // end of facet browsing test


// used all over to generate guids
function get_uri($taxon_id){
  if(php_sapi_name() === 'cli'){
    return "https://list.worldfloraonline.org/" . $taxon_id;
  }else{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $taxon_id;
  }
}


// copy of ranks table from management app
// FIXME need a way of sharing this efficiently between 
// repositories!
// also in wfo-backbone-management
$ranks_table = array(

  "code" => array(
    "children" => array("kingdom", "phylum"), // permissible ranks for child taxa
    "abbreviation" => "ICN", // official abbreviation
    "plural" => "Code",
    "aka" => array() // alternative representations for import
  ),

  "kingdom" => array(
    "children" => array("subkingdom", "phylum"), // permissible ranks for child taxa
    "abbreviation" => "King.", // official abbreviation
    "plural" => "Kingdoms",
    "aka" => array() // alternative representations for import
  ),

  "subkingdom" => array(
    "children" => array("phylum", "class", "order","family", "superorder"), // permissible ranks for child taxa
    "abbreviation" => "subking.", // official abbreviation
    "plural" => "Subkingdoms",
    "aka" => array() // alternative representations for import
  ),

  "phylum" => array(
    "children" => array("class", "order", "family", "superorder"), // permissible ranks for child taxa
    "abbreviation" => "phylum", // official abbreviation
    "plural" => "Phyla",
    "aka" => array() // alternative representations for import
  ),

  "class" => array(
    "children" => array("subclass", "order", "family","superorder"), // permissible ranks for child taxa
    "abbreviation" => "class", // official abbreviation
    "plural" => "Classes",
    "aka" => array() // alternative representations for import
  ),

  "subclass" => array(
    "children" => array("order", "family", "superorder"), // permissible ranks for child taxa
    "abbreviation" => "subclass.", // official abbreviation
    "plural" => "Subclasses",
    "aka" => array() // alternative representations for import
  ),

  "superorder" => array(
    "children" => array("order"), // permissible ranks for child taxa
    "abbreviation" => "superord.", // official abbreviation
    "plural" => "Superorders",
    "aka" => array() // alternative representations for import
  ),

  "order" => array(
    "children" => array("suborder", "family"), // permissible ranks for child taxa
    "abbreviation" => "ord.", // official abbreviation
    "plural" => "Orders",
    "aka" => array() // alternative representations for import
  ),

  "suborder" => array(
    "children" => array("family"), // permissible ranks for child taxa
    "abbreviation" => "subord.", // official abbreviation
    "plural" => "Suborders",
    "aka" => array() // alternative representations for import
  ),

  "family" => array(
    "children" => array("supertribe", "subfamily", "tribe", "genus"), // permissible ranks for child taxa
    "abbreviation" => "fam.", // official abbreviation
    "plural" => "Families",
    "aka" => array() // alternative representations for import
  ),

  "subfamily" => array(
    "children" => array("supertribe", "tribe", "genus"), // permissible ranks for child taxa
    "abbreviation" => "subfam.", // official abbreviation
    "plural" => "Subfamilies",
    "aka" => array() // alternative representations for import
  ),

  "supertribe" => array(
    "children" => array("tribe"), // permissible ranks for child taxa
    "abbreviation" => "suptr.", // official abbreviation
    "plural" => "Supertribes",
    "aka" => array('supertrib.') // alternative representations for import
  ),

  "tribe" => array(
    "children" => array("subtribe", "genus"), // permissible ranks for child taxa
    "abbreviation" => "tr.", // official abbreviation
    "plural" => "Tribes",
    "aka" => array('trib.') // alternative representations for import
  ),

  "subtribe" => array(
    "children" => array("genus"), // permissible ranks for child taxa
    "abbreviation" => "subtr.", // official abbreviation
    "plural" => "Subtribes",
    "aka" => array('subtrib.', 'subtrib') // alternative representations for import
  ),

  "genus" => array(
    "children" => array("subgenus", "section", "series", "species"), // permissible ranks for child taxa
    "abbreviation" => "gen.", // official abbreviation
    "plural" => "Genera",
    "aka" => array() // alternative representations for import
  ),

  "subgenus" => array(
    "children" => array("section", "series", "species"), // permissible ranks for child taxa
    "abbreviation" => "subg.", // official abbreviation
    "plural" => "Subgenera",
    "aka" => array("subgen.") // alternative representations for import
  ),

  "section" => array(
    "children" => array("subsection", "series", "species"), // permissible ranks for child taxa
    "abbreviation" => "sect.", // official abbreviation
    "plural" => "Sections",
    "aka" => array("sect",  "nothosect.") // alternative representations for import
  ),
  
  "subsection" => array(
    "children" => array("series", "species"), // permissible ranks for child taxa
    "abbreviation" => "subsect.", // official abbreviation
    "plural" => "Subsections",
    "aka" => array() // alternative representations for import
  ),

  "series" => array(
    "children" => array("subseries", "species"), // permissible ranks for child taxa
    "abbreviation" => "ser.", // official abbreviation
    "plural" => "Series",
    "aka" => array() // alternative representations for import
  ),

  "subseries" => array(
    "children" => array("species"), // permissible ranks for child taxa
    "abbreviation" => "subser.", // official abbreviation
    "plural" => "Subseries",
    "aka" => array() // alternative representations for import
  ),

  "species" => array(
    "children" => array("subspecies", "variety", "form", "prole", "lusus"), // permissible ranks for child taxa
    "abbreviation" => "sp.", // official abbreviation
    "plural" => "Species",
    "aka" => array("nothospecies", "spec.") // alternative representations for import
  ),

  "subspecies" => array(
    "children" => array("variety", "form", "prole", "lusus"), // permissible ranks for child taxa
    "abbreviation" => "subsp.", // official abbreviation
    "plural" => "Subspecies",
    "aka" => array("nothosubspecies", "nothosubsp.", "subsp.", "subsp", "ssp", "ssp.", "subspec.") // alternative representations for import
  ),

  "prole" => array(
    "children" => array(), // permissible ranks for child taxa
    "abbreviation" => "prol.", // official abbreviation
    "plural" => "Proles",
    "aka" => array("race", "proles") // alternative representations for import
  ),

  "variety" => array(
    "children" => array("subvariety", "form", "prole", "lusus"), // permissible ranks for child taxa
    "abbreviation" => "var.", // official abbreviation
    "plural" => "Varieties",
    "aka" => array("nothovar.", "var.", "var") // alternative representations for import
  ),

  "subvariety" => array(
    "children" => array("form"), // permissible ranks for child taxa
    "abbreviation" => "subvar.", // official abbreviation
    "plural" => "Subvarieties",
    "aka" => array("subvar") // alternative representations for import
  ),

  "form" => array(
    "children" => array("subform"), // permissible ranks for child taxa
    "abbreviation" => "f.", // official abbreviation
    "plural" => "Forms",
    "aka" => array("forma", "f") // alternative representations for import
  ),

  "subform" => array(
    "children" => array(), // permissible ranks for child taxa
    "abbreviation" => "subf.", // official abbreviation  
    "plural" => "Subforms",
    "aka" => array("subforma") // alternative representations for import
  ),

  "lusus" => array(
    "children" => array(), // permissible ranks for child taxa
    "abbreviation" => "lus.", // official abbreviation
    "plural" => "Lusus",
    "aka" => array("lus", "lusus naturae") // alternative representations for import
  ),

  "unranked" => array(
    "children" => array(), // permissible ranks for child taxa = none
    "abbreviation" => "unranked", // official abbreviation
    "plural" => "Unranked",
    "aka" => array("unr.", "infraspec.", "infrasec.", "infragen." ) // alternative representations for import
  )

);


function render_name_link($record, $classification_id){
    $link_id = $record->getWfoId() . "-" . $classification_id;
    echo "<a href=\"browser.php?id={$link_id}\">{$record->getFullNameStringHtml()}</a>";
}