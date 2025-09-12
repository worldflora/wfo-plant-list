
<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');
?>

<h1>Stable URIs</h1>

<p>
TaxonConcepts and TaxonNames can be identified over the internet with HTTP URIs which resolve according to semantic web best practices (see below).
They are intended to be persistent links and can be stored and used in web pages. The URIs are based on the WFO IDs used elsewhere.</p>
<p>

<p>
<strong>
A WFO stable URI is created by prepending "https://list.worldfloraonline.org/" to a ten or sixteen digit WFO ID described in the <a href="index.php#identifiers">identifiers section</a>.
</strong>
</p>

<p>
    Once created the HTTP URI can be used a link in a webpage or social media post and will always redirect the user to an appropriate location.
</p>

<?php
    if(!preg_match('/^https:\/\/list.worldfloraonline.org\//', get_uri('wfo-0000615907'))){
        echo '<p style="color: red; font-weight: bold;">This instance of the application is not running on the correct domain so the examples here will be based on URIs starting with '
        . get_uri('') 
        .' rather than https://list.worldfloraonline.org/</a>';
    }
?>

<p>
    The behaviour of the URIs differs depending on how they are resolved, by a human (with a web browser) or by a machine requesting data in a particular format.
    This is called content negotiation. 
    Below is an example.
    It might seem complicated but it is transparent to a user of the data. Just refer to things by their URIs and the system routes calls to the right place!
</p>

<ul>
    <li>The name <a href="<?php echo get_uri('wfo-0000615907') ?>" ><?php echo get_uri('wfo-0000615907') ?></a> (<i>Comandra elliptica</i> Raf.)
    is a synonym in the classification 2022-12. Clicking on it in a web browser you will be redirect to the taxon it is currently accepted in and the name will be highlighted.</li>
    
    <li>If you were to ask for <strong>data</strong> for this name using, for example, the cURL command: <code>curl -I -H "Accept: application/json" <?php echo get_uri('wfo-0000615907') ?></code>
        You would be returned a JSON object for that name which would include references to its placements in different data releases. 
    </li>

    <li>A versioned URI of that same name is this: <a href="<?php echo get_uri('wfo-0000615907-2022-06') ?>" ><?php echo get_uri('wfo-0000615907-2022-06') ?></a>
    If you click on it in a web browser you will be redirected to the taxon page in the WFO Plant List for the accepted name <strong>in the classification in that data release</strong> and the synonym will be highlighted.</li>
    
    <li>If you were to call for <strong>data</strong> for a version of that name like this <code>curl -I -H "Accept: application/json" <?php echo get_uri('wfo-0000615907-2022-06') ?></code>
    then you would get a 301 redirect to the non-versioned name <a href="<?php echo get_uri('wfo-0000615907') ?>" ><?php echo get_uri('wfo-0000615907') ?></a> and on to the data for the bare name.</li>

    <li>On the other hand calling for <strong>data</strong> for a versioned name that is the accepted name of a taxon will return the taxon JSON object with the name embedded in it.
</ul>


<h3>Supported formats</h3>

<?php
    $formats = \EasyRdf\Format::getFormats();
    $formats_supported = array();

    // also check we have a class loadable for that format
    foreach($formats as $key => $value){
        $serialiserClass  = $value->getSerialiserClass();
        if($serialiserClass){
            $formats_supported[$key] = $value;
        }
    }

?>

<p>
    Data can be returned in the <?php echo count($formats_supported) ?> formats listed in the table below.
    These include graphical representations of the data.
</p>
<table class="table table-hover">
<tr>
    <th>Name</th>
    <th>Recommended Mime Type</th>
    <th>Recognized Mime Types</th>
    <th>Example</th>
</tr>
<?php



foreach($formats_supported as $format_name => $format){
    echo "<tr>";
    echo "<td>$format_name</td>";
    echo "<td>". $format->getDefaultMimeType() . "</td>";
    echo "<td>" . implode( ', ', array_keys($format->getMimeTypes()) ) . "</td>";
    echo "<td><a href=\"/sw_data.php?wfo=wfo-4000000718&format=$format_name\">$format_name</a></td>"; 
    echo "</tr>";
}

?>
</table>

<p>An example graph for a TaxonConcept</p>
<a href="/sw_data.php?wfo=wfo-4000000718-2022-12&format=svg"><img src="/sw_data.php?wfo=wfo-4000000718-2022-12&format=svg" style="width: 50em"/></a>

<p>An example graph for a TaxonName</p>
<a href="/sw_data.php?wfo=wfo-4000000718&format=svg"><img src="/sw_data.php?wfo=wfo-4000000718&format=svg" style="width: 50em"/></a>



<h3>Properties</h3>
<p>
The diagram below shows the property relationships in the semantic web data model. Further documentation on these can be found either by dereferencing the URIs of the terms in the RDF responses. 
The GraphQL API uses a very similar data model and you can access its documentation using an IDE.
</p>

<a href="terms.php?format=svg"><img src="terms.php?format=svg" style="width: 50em"/></a>

<p></p>


<?php
require_once('footer.php');
?>