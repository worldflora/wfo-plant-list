<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/NameMatcher.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');

?>
<div style='display: block; float: right; max-width: 16em; margin: 1em;'>
    <a href="https://tettris.eu/" target="tettris"><img src="images/tettris.png" width="100%" /></a>
</div>
<h1>TETTRIs Name Matching API</h1>

<p>The <a href="https://tettris.eu/3rd-party-projects/test-2/">TNLS</a> (Taxonomic Name Linking Services) was a sub project of <a href="https://tettris.eu/">TETTRIs</a> (Transforming European Taxonomy through
    Training, Research and
    Innovations).
    Its purpose was to enable TETTRIs participants to link their services with those of the major, global taxonomic
    datasets thus aligning
    multiple datasets to a common, global taxonomic backbone system. The first work area in TNLS was to develop a
    common name matching design pattern applicable across multiple projects.
</p>
<p>
    <strong>This is an demonstration of that initial common design pattern. Its aim is to explore the concept rather
        than be a finished tool.</strong>
</p>
<p>
    Twenty different name matching services were surveyed.
    Forty input parameters were found across the services.
    There were six parameters that were considered common to all.
    Sixty different output fields were identified. Analysis narrowed these down to
    six common fields.
    These input parameters and output fields have been mapped to the WFO Plant List dataset
    and can be explored below.
</p>


<h2>Common Input Parameters</h2>
<p>This form implements the six common input parameters identified.
    The shared documentation in highlighted in blue. The WFO implemenation is in grey.</p>
<form method="GET" action="matching_tettris.php">
    <table class="table align-middle table-hover ">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Parameter</th>
                <th scope="col">Type</th>
                <th scope="col">Definition</th>
                <th scope="col">Notes</th>
            </tr>
        </thead>
        <tbody>
            <!-- name -->
            <tr class="table-primary">
                <td rowspan="2">1</td>
                <th scope="row" rowspan="2" style="text-align: center;">name</th>
                <td>string</td>
                <td>The full scientific name of the organism as defined by an appropriate nomenclatural code.
                    <span style="color: red;">Required.</span>
                </td>
                <td>This may include the rank part of a name, the authority string and the year of publication where
                    appropriate.</td>
            </tr>
            <tr>
                <td colspan="2">
                    <input
                        class="form-control"
                        type="text"
                        name="name"
                        id="name"
                        onkeyup="nameFieldChanged(this)"
                        value="<?php echo @$_GET['name'] ?>"
                        placeholder="Full plant name" size="60" />
                </td>
                <td>
                    WFO's default is to assume the author string is included in the name.
                </td>
            </tr>

            <!-- authorship -->
            <tr class="table-primary">
                <td rowspan="2">2</td>
                <th scope="row" rowspan="2" style="text-align: center;">authorship</th>
                <td>string</td>
                <td>A string representation of the authors of the name.</td>
                <td>If the client is able to pass this as a separate field.</td>
            </tr>
            <tr>
                <td colspan="2">
                    <input class="form-control" type="text" name="authorship" value="<?php echo @$_GET['authorship'] ?>"
                        placeholder="Authors string" size="60" />
                </td>
                <td>
                    This is implemented as a filter to restrict results to names matching this authors string exactly.
                </td>
            </tr>

            <!-- year -->
            <tr class="table-primary">
                <td rowspan="2">3</td>
                <th scope="row" rowspan="2" style="text-align: center;">year</th>
                <td>integer</td>
                <td>The year of publication of the name as an integer. The match must be published in this year if it is
                    provided.</td>
                <td>More relevant in zoology than botany.</td>
            </tr>
            <tr>
                <td colspan="2">
                    <select name="year" id="year" class="form-select">
                        <option value="">~ not specified ~</option>
                        <?php
    for($i = 1750; $i <= date("Y"); $i++){
        $selected = @$_GET['year'] == $i ? 'selected' : '';
        echo "<option value=\"{$i}\" {$selected} >{$i}</option>";
    }

?>

                    </select>
                </td>
                <td>
                    94% of names in the WFO Plant List have the year of publication specified as a separate field.
                </td>
            </tr>

            <!-- rank -->
            <tr class="table-primary">
                <td rowspan="2">4</td>
                <th scope="row" rowspan="2" style="text-align: center;">rank</th>
                <td>string</td>
                <td>The matching name must be at this rank or ranks if provided.</td>
                <td>It is recommended the service provides a controlled vocabulary for this field.</td>
            </tr>
            <tr>
                <td colspan="2">
                    <select id="rank" name="rank" class="form-select">
                        <option value="">~ not specified ~</option>
                        <?php
    foreach($ranks_table as $rank_name => $rank){
        $selected = @$_GET['rank'] == $rank_name ? 'selected' : '';
        echo "<option value=\"{$rank_name}\" {$selected}>{$rank_name}</option>";
    }

?>

                    </select>
                </td>
                <td>
                    WFO has a controlled vocabulary of <?php echo count($ranks_table) ?> ranks.
                </td>
            </tr>

            <!-- kingdom -->
            <tr class="table-primary">
                <td rowspan="2">5</td>
                <th scope="row" rowspan="2" style="text-align: center;">kingdom</th>
                <td>string</td>
                <td>The matching name must be within this kingdom (e.g. Animalia, Plantae, Fungi, Protista, Archaea and
                    Bacteria) </td>
                <td>Useful for data aggregators covering all forms of life. Limits matching to a single nomenclatural
                    code and prevents cross code homonyms.</td>
            </tr>
            <tr>
                <td colspan="2">
                    Defaults to <strong>Plantae</strong>
                </td>
                <td>
                    The WFO Plant List only contains names from the plant kingdom.
                </td>
            </tr>

            <!-- include_accepted -->
            <tr class="table-primary">
                <td rowspan="2">6</td>
                <th scope="row" rowspan="2" style="text-align: center;">include_accepted</th>
                <td>boolean</td>
                <td>If the name matched is considered a synonym by the data source then also return the accepted name.
                </td>
                <td>Differentiates between pure nomenclators that are only providing name matching services and
                    taxonomic services. </td>
            </tr>
            <tr>
                <td colspan="2">
                    <?php
                        $checked = @$_GET['include_accepted'] ? 'checked' : '';
                    ?>

                    <input type="checkbox" <?php echo $checked ?> class="form-check-input" id="include_accepted" name="include_accepted">
                    &nbsp;<label class="form-check-label" for="include_accepted">Include Accepted</label>
                </td>
                <td>
                    WFO includes general taxonomic placement data in response to this parameter.
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align: right;">
                    <a href="matching_tettris.php" role="button" id="cancelButton" class="btn btn-outline-secondary">Clear</a>
                    &nbsp;
                    <button type="submit" id="submitButton" class="btn btn-primary">Submit</button>
                </td>
            </tr>
        </tfoot>
    </table>
</form>

<?php

// handle the submission
if(@$_GET['name']){


    $matcher = new NameMatcher((object)array(
        'method' => 'full',
        'year' => @$_GET['year'],
        'authors' => @$_GET['authorship'],
        'rank' => @$_GET['rank']
    ));
    $response = $matcher->match($_GET['name']);


?>

<h2>Results</h2>

<table class="table align-middle table-hover">
        <tbody>
<?php
    // write out the names with the appropriate fields with 
    if($response->match) write_name_row($response->match);

    if($response->candidates){
        foreach($response->candidates as $c){
            write_name_row($c);
        }
    }
    
    if(!$response->match && !$response->candidates){
        echo '<tr><td style="white-space: nowrap;">No names found.</td></tr>';
    }
?>
        </tbody>
    </table>
</form>

<h3>Matching Narrative</h3>
<p>This is a narrative description of the steps taken to arrive at these results.</p>
<?php

echo '<ol>';
foreach ($response->narrative as $step) {
    echo "<li>$step</li>";
}
echo '</ol>';

} // we have a name string submitted

function write_name_row($name){

    echo '<tr>';
    $rowspan = @$_GET['include_accepted'] ? '5' : '4';
    echo "<td rowspan=\"{$rowspan}\"  style=\"white-space: nowrap; width: 10em;\">";
    echo "<a href=\"https://list.worldfloraonline.org/{$name->getWfoId()}\">";
    echo $name->getWfoId();
    echo "</a>";
    echo"</td>";

    // name
    echo "<td style=\"text-align: right; width: 10em;\"><strong>name: </strong></td>";
    echo "<td>";
    echo $name->getFullNameStringPlain();
    echo "</td>";
    echo "</tr>";

    // name_html
    echo "<tr>";
    echo "<td style=\"text-align: right;\"><strong>name_html: </strong></td>";
    echo "<td>";
    echo $name->getFullNameStringHtml();
    echo "</td>";
    echo "</tr>";

    // authorship
    echo "<tr>";
    echo "<td style=\"text-align: right;\"><strong>authorship: </strong></td>";
    echo "<td>";
    echo $name->getAuthorsString();
    echo "</td>";
    echo "</tr>";

    // accepted
    echo "<tr>";
    echo "<td style=\"text-align: right;\"><strong>accepted: </strong></td>";
    echo "<td>";
    echo $name->getRole() == 'accepted' ? '<span style="color: green">TRUE</span>' : '<span style="color: red">FALSE</span>';
    echo "</td>";
    echo "</tr>";

    if(@$_GET['include_accepted']){

             // placement
            echo "<tr>";
            echo "<td style=\"text-align: right;\"><strong>placement: </strong></td>";
            echo "<td>";

            if($name->getRole() == 'accepted' || $name->getRole() == 'synonym'){
                // we need a taxon record when name match returns only names
                if($name->getRole() == 'synonym'){
                    $taxon = new TaxonRecord( $name->getAcceptedId());
                }else{
                    $taxon = new TaxonRecord( $name->getWfoId() . '-' . WFO_DEFAULT_VERSION);
                }
                
                $ancestors = array_reverse($taxon->getPath());
                $first = true;
                foreach ($ancestors as $anc) {
                    if(!$first) echo ' &gt; ';
                    echo "<a href=\"https://list.worldfloraonline.org/{$anc->getWfoId()}\">";
                    echo $anc->getFullNameStringHtml();
                    echo "</a>";
                    $first = false;
                }

                // if we are a synonym then we add them to the end of the chain
                if($name->getRole() == 'synonym'){
                    echo " &gt; <strong>Syn:</strong> ". $name->getFullNameStringHtml();
                }else{
                    echo " &gt; ". $name->getFullNameStringHtml();
                }

            }else{
                echo $name->getRole();
            }

    }
    echo "</td>";
    echo "</tr>";


}

?>

<h2>Common Output Fields</h2>

The table below are suggestions of a minimum set of common fields. Services are likely to provide many more
than these core fields depending on their user requirements.

<table class="table align-middle table-hover ">
    <thead>
        <tr>
            <th scope="col">Field&nbsp;Name</th>
            <th scope="col">Type</th>
            <th scope="col">Definition</th>
            <th scope="col">Notes</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>ID</td>
            <td>string</td>
            <td>A persistent, unique (within the scope of the database or globally) identifier for this name object and or taxon.</td>
            <td>Services may provide standard ways to convert local IDs to global (e.g. prepend URL). Should not change when name
spelling changes.</td>
        </tr>
        <tr>
            <td>name</td>
            <td>string</td>
            <td>The full scientific name of the organism as defined in by an appropriate nomenclatural code.</td>
            <td>The correct way to cite the name according to the service provider.</td>
        </tr>
        <tr>
            <td>name_html (optional)</td>
            <td>string</td>
            <td>The same as name but with the code mandated italics parts of the name delimited with &lt;i&gt; tags.</td>
            <td>This is to allow client software to render the names correctly without needing to re-parse them.</td>
        </tr>
        <tr>
            <td>authorship (optional)</td>
            <td>string</td>
            <td>A string representation of the authors of the name.</td>
            <td>The correct author string for the name ideally following any domain standards such as IPNI authors.</td>
        </tr>
        <tr>
            <td>accepted (optional)</td>
            <td>boolean</td>
            <td>Whether the name is an accepted name of a taxon or not.</td>
            <td>Can be ignored by pure nomenclator services.</td>
        </tr>
        <tr>
            <td>placement (optional)</td>
            <td>array</td>
            <td>Where the name is placed in the classification./td>
            <td>How this is presented will depend on the service. It could be a single string path or a more complex structure.</td>
        </tr>
    </tbody>
</table>

<script>
     function nameFieldChanged(field) {
        submitButton = document.getElementById("submitButton");
        if(field.value.length > 0){
            submitButton.disabled = false;
        }else{
            submitButton.disabled = true;
        }
      }
      // call it on page load to set the button correctly
      nameFieldChanged(document.getElementById("name"));
</script>

    <?php
require_once('footer.php');
?>