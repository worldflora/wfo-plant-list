<?php
require_once('config.php');
?>
<script src="https://www.gstatic.com/charts/loader.js"></script>
<?php
require_once('../include/PlantList.php');
require_once('header.php');

if(@$_GET['classification']){
    $classification_selected = $_GET['classification'];
}else{
    $classification_selected =  WFO_DEFAULT_VERSION;
}

// the filters are hierachical - we ignore lower ones if higher ones aren't set
$phylum_selected = @$_GET['phylum'];
if($phylum_selected) $order_selected = @$_GET['order'];
else $order_selected = false;
if($order_selected) $family_selected = @$_GET['family'];
else $family_selected = false;


/*

    Common facet definitions used below

*/

$rank_facet = (object)array(
        "type" => "terms",
        "field" => "rank_s",
        "limit" => -1,
        "sort" => 'count',
        "mincount" => 0 // we want them all for the form
);

$role_facet = (object)array(
        "type" => "terms",
        "field" => "role_s",
        "limit" => -1,
        "sort" => 'count',
        "mincount" => 0 // we want them all for the form
);

$classification_facet = (object)array(
        "type" => "terms",
        "field" => "classification_id_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 0 // we want them all for the form
);

$family_facet = (object)array(
        "type" => "terms",
        "field" => "placed_in_family_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 1 // we want them all for the form
);

$order_facet = (object)array(
        "type" => "terms",
        "field" => "placed_in_order_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 1 // we want them all for the form
);

$phylum_facet = (object)array(
        "type" => "terms",
        "field" => "placed_in_phylum_s",
        "limit" => -1,
        "sort" => 'index',
        "mincount" => 1 // we want them all for the form
);


/*

    First query is to get key figures for each classification

*/
$facets = array();

// row for each classification
$facets['classification'] = $classification_facet;

// by role
$facets['classification']->facet = (object)array('role' => $role_facet);

// by rank
$facets['classification']->facet->role->facet = (object)array('rank' => $rank_facet);

// so we can answer "give me the accepted species in classification x
$query = array(
    'query' => "*:*",
    'facet' => $facets,
    'limit' => 0 // we are only interested in the facet data
);

$solr_response = PlantList::getSolrResponse($query);

if(!isset($solr_response->facets)){
    echo "Somethings up.";
    echo '<pre>';
    print_r($solr_response);
    exit;
}

echo "<h2>Overview</h2>";

// build a more useful data structure
$overview = array();


foreach ($solr_response->facets->classification->buckets as $classification) {

    $o = (object)array();
    $o->name = $classification->val;
    $o->total_names = $classification->count;

    if(!$o->total_names) continue;

    // may not be present in early classifications
    $o->accepted_species = 0;
    $o->accepted_taxa = 0;
    $o->unplaced = 0; 
    $o->deprecated = 0;


    foreach($classification->role->buckets as $role){

        if($role->val == 'accepted'){
            $o->accepted_taxa = $role->count;

            foreach($role->rank->buckets as $rank){
                if($rank->val == 'species'){
                    $o->accepted_species = $rank->count;
                }
            }
        }

        if($role->val == 'synonym'){
            $o->synonyms = $role->count;
        }

        if($role->val == 'unplaced'){
            $o->unplaced = $role->count;
        }

        if($role->val == 'deprecated'){
            $o->deprecated = $role->count;
        }

    }



    // add in the %
    $o->accepted_species_proportion = $o->accepted_species / $o->total_names;
    $o->accepted_species_percentage = number_format($o->accepted_species_proportion * 100 , 2) . '%';

    $o->accepted_taxa_proportion = $o->accepted_taxa / $o->total_names;
    $o->accepted_taxa_percentage = number_format($o->accepted_taxa_proportion * 100 , 2) . '%';

    $o->synonyms_proportion = $o->synonyms / $o->total_names;
    $o->synonyms_percentage = number_format($o->synonyms_proportion * 100 , 2) . '%';
    
    $o->unplaced_proportion = $o->unplaced / $o->total_names;
    $o->unplaced_percentage = number_format($o->unplaced_proportion * 100 , 2) . '%';

    $o->deprecated_proportion = $o->deprecated / $o->total_names;
    $o->deprecated_percentage = number_format($o->deprecated_proportion * 100 , 2) . '%';
    
    $overview[] = $o;
    
}


// graph view
echo '<div style="height: 200px; padding: 0px; width: 80%; margin-bottom: 1em; position:relative;">';

$classification_width = ((1 / count($overview)) * 100) . '%';
$col_width = (1/7 * 100) .'%';

foreach ($overview as $o) {
    echo "<div style=\"float: left; padding: 0px; margin: 0px; height: 100%; width: $classification_width; red; border:none; vertical-align: bottom; position: relative;\">";
    
   
    $h = ($o->accepted_species_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'green');

    $h = ($o->accepted_taxa_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'orange');

    $h = ($o->synonyms_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'blue');

    $h = ($o->unplaced_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'brown');

    $h = ($o->deprecated_proportion * 100) . '%';
    render_histogram_column($col_width, $h, 'gray');

     echo "<div style=\"position: absolute; left: 0px; top: 0px; padding: 0.3em; margin: 0px; width: auto; border:none;\">$o->name</div>";


    echo '</div>';
}

echo '</div>';


// table view
echo '<table style="width: 80%">';

echo "<tr><th style=\"text-align: center;\" >Classification</th>";
echo "<th style=\"text-align: center; color: green; \" >Accepted Species</th>";
echo "<th style=\"text-align: center; color: orange; \" >Accepted Taxa</th>";   
echo "<th style=\"text-align: center; color: blue; \" >Synonyms</th>";
echo "<th style=\"text-align: center; color: brown; \" >Unplaced Names</th>";
echo "<th style=\"text-align: center; color: gray; \" >Deprecated Names</th>";
echo "<th style=\"text-align: center; color: black; \" >Total Names</th>";
echo "</tr>";

foreach ($overview as $classification) {

    if($classification_selected == $classification->name){
        $bg_colour = 'lightyellow';
    }else{
        $bg_colour = 'white';
    }

    echo "<tr style=\"background-color: $bg_colour;\">";
    echo "<th style=\"text-align: right;\" >{$classification->name}</th>";
    
    echo "<td style=\"text-align: right;\" >" . number_format($classification->accepted_species, 0) . "<br/>$classification->accepted_species_percentage</td>";
    echo "<td style=\"text-align: right;\" >" . number_format($classification->accepted_taxa, 0) . "<br/>$classification->accepted_taxa_percentage</td>";
    echo "<td style=\"text-align: right;\" >" . number_format($classification->synonyms, 0) . "<br/>$classification->synonyms_percentage</td>";
    echo "<td style=\"text-align: right;\" >" . number_format($classification->unplaced, 0) . "<br/>$classification->unplaced_percentage</td>";
    echo "<td style=\"text-align: right;\" >" . number_format($classification->deprecated, 0) . "<br/>$classification->deprecated_percentage</td>";
    
    echo "<td style=\"text-align: right;\" >" . number_format($classification->total_names, 0) . "<br/>&nbsp;</td>";
    echo "<tr>";
}

echo '</table>';

//echo "<pre>";
//print_r($solr_response->facets);
//echo "</pre>";


/*

    Now we do a filtered view of a single classification

*/

// clear the facets so we don't get recursion
unset($rank_facet->facet);
unset($role_facet->facet);
unset($classification_facet->facet);

// each rank is divided into roles
$facets = array();
$rank_facet->facet = (object)array('role' => $role_facet);

// the main breakdown is via rank
$facets['rank'] = $rank_facet;

// get a list of the classifications to filter by
$facets['classification'] = $classification_facet;
$facets['family'] = $family_facet;
$facets['order'] = $order_facet;
$facets['phylum'] = $phylum_facet;

// now set up some filters
$filters = array();

// always filter on a classification
$filters[] = 'classification_id_s:' . $classification_selected;

// can filter on some ranks
if($phylum_selected) $filters[] = 'placed_in_phylum_s:"' . $phylum_selected . '"';
if($order_selected) $filters[] = 'placed_in_order_s:"' . $order_selected . '"';
if($family_selected) $filters[] = 'placed_in_family_s:"' . $family_selected . '"';

$query = array(
    'query' => "*:*",
    'facet' => $facets,
    'filter' => $filters,
    'limit' => 0 // we are only interested in the facet data
);

$solr_response = PlantList::getSolrResponse($query);

if(!isset($solr_response->facets)){
    echo "Somethings up.";
    echo '<pre>';
    print_r($solr_response);
    exit;
}


// a form to filter by

echo "<p/>";
echo "<h2>Summary for {$classification_selected}</h2>";


echo '<form method="GET" action="stats.php">';

// pick a classification
echo '<strong>Data Release:&nbsp;</strong><select name="classification" onchange="this.form.submit()">';
foreach ($solr_response->facets->classification->buckets as $classification) {
    if( $classification->val == '9999-99') continue;
    $selected = $classification_selected == $classification->val ? 'selected' : '';
    echo "<option $selected value=\"{$classification->val}\">{$classification->val}</option>";
}
echo '</select>';


// pick a phylum
echo '&nbsp;<select name="phylum" onchange="this.form.submit()">';
echo "<option value=\"\">Placed, Unplaced and Deprecated</option>";
foreach ($solr_response->facets->phylum->buckets as $phylum) {
    $c = number_format($phylum->count, 0);
    $selected = $phylum_selected == $phylum->val ? 'selected' : '';
    echo "<option $selected  value=\"{$phylum->val}\">Placed in: {$phylum->val} ($c)</option>";
}
echo '</select>';

// pick an order
if($phylum_selected){
    echo '&nbsp;<select name="order" onchange="this.form.submit()">';
    echo "<option value=\"\">~ Any Order ~</option>";
    foreach ($solr_response->facets->order->buckets as $order) {
        $c = number_format($order->count, 0);
        $selected = $order_selected == $order->val ? 'selected' : '';
        echo "<option $selected  value=\"{$order->val}\">{$order->val} ($c)</option>";
    }
    echo '</select>';
}


// pick a family
if($order_selected){
    echo '&nbsp;<select name="family" onchange="this.form.submit()">';
    echo "<option value=\"\">~ Any Family ~</option>";
    foreach ($solr_response->facets->family->buckets as $family) {
        $c = number_format($family->count, 0);
        $selected = $family_selected == $family->val ? 'selected' : '';
        echo "<option $selected  value=\"{$family->val}\">{$family->val} ($c)</option>";
    }
    echo '</select>';
}

echo '<form/>';

echo "<table  style=\"width: 80%; margin-top: 1em; margin-bottom: 1em;\">";

// next row breaks classifications into accepted/synonym/unplaced/deprecated
// we can put the classifications headings in based on the first as it will be biggest
echo "<tr><th style=\"text-align: center;\" >Ranks</th>";
   echo "<th style=\"text-align: center;\" >Total</th>";
    echo "<th style=\"text-align: center;\" >Accepted</th>";
    echo "<th style=\"text-align: center;\" >Synonyms</th>";
    echo "<th style=\"text-align: center;\" >Unplaced</th>";
    echo "<th style=\"text-align: center;\" >Deprecated</th>";
echo "</tr>";


foreach ($solr_response->facets->rank->buckets as $rank) {
   echo "<tr>";
   echo "<th style=\"text-align: right;\" >{$rank->val}</th>";
   
        // the total
        $total = number_format($rank->count, 0);
        echo "<td style=\"text-align: right;\" >{$total}</td>";
        

        $cols = array('accepted', 'synonym', 'unplaced', 'deprecated');

        foreach ($cols as $col) {
            if(isset($rank->role->buckets)){
                foreach($rank->role->buckets as $role){
                    if($role->val == $col){
                        $count = number_format($role->count);
                        echo "<td style=\"text-align: right;\" >{$count}</td>";
                        break;
                    }
                }
            }else{
                echo "<td style=\"text-align: right;\" >-</td>";
            }

        }

   }


   echo "</tr>";

echo "</table>";


/*

    Now we get the speciation rate and synonymisation rate

*/


// we facet on the years - all of them
$facets = array();
$facets['years'] = (object)array(
      "type" => "terms",
      "field" => "publication_year_i",
      "sort" => "index",
      "allBuckets" => true,
      "limit" => -1
);

// now set up some filters
$filters = array();

// always filter on a classification
$filters[] = 'classification_id_s:' . $classification_selected;

// can filter on some ranks
if($phylum_selected) $filters[] = 'placed_in_phylum_s:"' . $phylum_selected . '"';
if($order_selected) $filters[] = 'placed_in_order_s:"' . $order_selected . '"';
if($family_selected) $filters[] = 'placed_in_family_s:"' . $family_selected . '"';

// we are only interested in accepted species.
//$filters[] = "rank_s:species";
$filters[] = "-role_s:deprecated";

$filters_basionyms = $filters; 
$filters_basionyms[] = "-authors_string_s:\(*";

$filters_synonyms = $filters;
$filters_synonyms[] = "authors_string_s:\(*";


// first query is for basionyms
$query = array(
    'query' => "*:*",
    'facet' => $facets,
    'filter' => $filters_basionyms,
    'limit' => 0 // we are only interested in the facet data
);

$solr_response = PlantList::getSolrResponse($query);

if(!isset($solr_response->facets)){
    echo "Somethings up.";
    echo '<pre>';
    print_r($solr_response);
    exit;
}

$basionym_years = $solr_response->facets->years->buckets;

// we shouldn't carry on if this is not here
if($solr_response->facets->years->allBuckets->count != 0){

        // second query for synonyms
        $query = array(
            'query' => "*:*",
            'facet' => $facets,
            'filter' => $filters_synonyms,
            'limit' => 0 // we are only interested in the facet data
        );

        $solr_response = PlantList::getSolrResponse($query);

        if(!isset($solr_response->facets)){
            echo "Somethings up.";
            echo '<pre>';
            print_r($solr_response);
            exit;
        }

        $synonym_years = $solr_response->facets->years->buckets;

        $scores = array();
        $scores[] = array('Year', 'New Names', 'Com. Nov.');

        // we are not guaranteed to have a bucket for every year because mincount doesn't work on this kind of facet
        $year_range = range(1750, 2024, 1);

        // initialise the years
        $out = array();
        foreach($year_range as $year) $out[$year] = array($year, 0, 0);

        // populate them
        foreach ($basionym_years as $year) {
            if(!isset($out[$year->val])) continue;
            $out[$year->val][1] = $year->count;
        }
        foreach ($synonym_years as $year) {
            if(!isset($out[$year->val])) continue;
            $out[$year->val][2] = $year->count;
        }

        // add it to the score
        foreach ($out as $year => $vals){
            $scores[] = $vals;
        }



        echo '<h3>Taxon Name Publication Rate</h3>';
        echo '<p style="width:100%; max-width:80%;">Name publication dates can be an indication of species discovery.
        Date of publication of new combinations (with paranthetical authors) can be an indication of revisionary activity. 
        This graph gives an indication of the number of new taxon names and new taxon name combinations published by year (all ranks) for the selection above.</p>';
        echo '<div id="description_rate" style="width:100%; max-width:80%; height:500px;"></div>';

        // write in some javascript to render the chart
        echo "\n<script>\n";

        // load appropriate google package
        echo "google.charts.load('current',{packages:['corechart']});\n";

        // register function to be called when page has loaded
        echo "google.charts.setOnLoadCallback(drawRateChart);";

        // function to draw chart
        echo "function drawRateChart(){\n";

            // options for the chart
            $options = (object)array(
            // "title" => 'Rate of Publication',
                "hAxis" => (object)array( "title" => 'Year', 'format' => '####'),
                "vAxis" => (object)array("title" => 'Names Published'),
                //"legend" => 'Some legend text'
            );
            $options_json = json_encode($options);
            echo "const options = $options_json\n";

            // data for the chart
            $data_json = json_encode($scores);
            echo "const data = google.visualization.arrayToDataTable( $data_json );\n";

            // get the chart in the DOM
            echo "const chart = new google.visualization.LineChart(document.getElementById('description_rate'));\n";

            // actually draw it
            echo "chart.draw(data, options);";

        echo "\n}\n"; // end drawRateChart function

        echo "</script>\n";



}



echo "<pre>";
//echo print_r($data_json);
echo "</pre>";



?>


<?php

//print_r($solr_response);


require_once('footer.php');

function render_histogram_column($width, $height, $colour){

    echo "<div style=\"height: 100%; width: $width; position: relative; float: left; background-color: white;  padding: 0px; margin: 0px; border:none; \">";
    echo "<div style=\" position: absolute; bottom: 0; left: 0;  width:100%; height: $height; background-color: $colour; border:none; padding: 0px; margin: 0px;\" ></div>";
    echo "</div>";


}

?>