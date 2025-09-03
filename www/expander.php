<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/NameMatcher.php');
require_once('../include/Classification.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');

// set up the file store
$file_dir = 'matching_cache/' . session_id() . "/";
if(!file_exists($file_dir)) mkdir($file_dir);
$input_file_path = $file_dir . "expand_input.csv";
$output_file_path = $file_dir . "expand_output.csv";

// what fields are they interested in?
if(isset($_SESSION['selected_fields'])) $selected_fields = $_SESSION['selected_fields'];
else $selected_fields = array();

// we need a list of possible fields to add
$fields = array(
    'wfo_release' => "The data release this data comes from. It is recommended to always include and cite this.",
    'wfo_prescribed_id' => "The WFO ID that should be used for the name. May differ from supplied WFO ID if the supplied one has been deduplicated.",

    'wfo_persistent_uri' => "The URI that you should use in publications to cite this name. We will attempt to maintain this no matter how the portal and other software evolves.",
    'wfo_rhakhis_link' => "Useful for taxonomic editors: This links directly to the name page in the Rhakhis taxonomic editor tool (login required).",

    'wfo_role' => "Whether the name is accepted, a synonym, unplaced or deprecated",
    'wfo_full_name' => "This is the full name including the authors.",
    'wfo_full_name_html' => "The same as wfo_full_name but with HTML mark up of the different name parts.",
    
    'wfo_genus' => "The genus name part if this is a bi or trinomial below the rank of genus",
    'wfo_specific_epithet' => "The species epithet if this is a species or below.",
    'wfo_subspecific_epithet' => "The subspecific epithet (e.g. subspecies or variety) if this name is below the level of the species.",
    'wfo_name_string' => "The single word name part of the name e.g. the epithet if this is a species or name if this is a family.",
    'wfo_trinomial' => "The one, two or three words that make up the name, omitting the rank and authors",

    'wfo_name_no_authors' => "The plain name without the author string",
    'wfo_authors' => "The author string for the name",
    'wfo_rank' => "The rank of the name from our controlled vocabulary",
    'wfo_citation_micro' => "The abbreviated publication string",
    'wfo_accepted_name_id' => "The WFO ID of the accepted taxon name (if this is a synonym)",
    'wfo_accepted_name_full' => "The full name string of the accepted name including authors",
    'wfo_basionym_name_id' => "The WFO ID of the basionym name (if this is a comb nov or nom nov)",
    'wfo_basionym_name_full' => "The full name string of the basionym name including authors",
    'wfo_parent_name_id' => "The WFO ID of the parent taxon name (if this is an accepted taxon name)",
    'wfo_parent_name_full' => "The full name string of the parent name including authors",
    'wfo_placement_name_path' => "A forward slash delimited list of names from the root of the classification to this taxon. You can use this to filter the list by higher taxon in Excel or a database by using includes or starts with queries.",
    'wfo_placement_id_path' => "A forward slash delimited list of WFO IDs from the root of the classification to this taxon."
);

// clear down if called with nothing
if(!$_POST && !@$_GET['offset']){
   @unlink($input_file_path);
   @unlink($output_file_path);
}

// have they uploaded a file?
if($_POST && isset($_FILES["input_file"])){
    
    move_uploaded_file($_FILES["input_file"]["tmp_name"], $input_file_path);

    $selected_fields = array(); // clear down the selected fields
    foreach($fields as $field_name => $field_description){
        if(isset($_POST[$field_name])) $selected_fields[] = $field_name;
    }
    $_SESSION['selected_fields'] = $selected_fields;

}

// if there is an input file we must be on a processing run
if(file_exists($input_file_path)){

    // how far into the run are we
    $offset = @$_GET['offset'];

    // we are on a new run
    if(!$offset){
        $offset = 0;
        // remove the existing file
        @unlink($output_file_path);
    }

    // get our in/out handles
    $in = fopen($input_file_path, 'r'); // reading only
    $out = fopen($output_file_path, 'a'); // appending

    // if we are starting an new output file then put in a header row
    if($offset == 0){
        $header = fgetcsv($in);
        $header = array_merge($header, $selected_fields);
        fputcsv($out, $header, escape: "\\");
    }else{
        echo "<p>Offset: $offset</p>";
    }

    // work through the input file
    $counter = 0;
    while($line = fgetcsv($in)){

        $counter++;
        
        // skip to the offset position
        if($counter < $offset) continue;

        // the out line is a copy of the inline with appendages
        $out_line = $line;

        $wfo = trim($line[0]);
        
        // skip non compliant WFO IDs or ones that don't load the name

        $name = new TaxonRecord($wfo . '-' . WFO_DEFAULT_VERSION);
        if(!preg_match('/^wfo-[0-9]{10}$/', $wfo) || !$name || !$name->getWfoId()){
            // fill the gaps with empty values.
            $out_line = array_merge($out_line, array_fill(0,count($selected_fields), null));
            fputcsv($out, $out_line, escape: "\\");
        }else{

            // no other way to do this but manually map the fields to calls on the object
            foreach($selected_fields as $field_name){
                switch ($field_name) {
                    case 'wfo_release':
                        $out_line[] = WFO_DEFAULT_VERSION;
                        break;
                    case 'wfo_prescribed_id':
                        $out_line[] = $name->getWfoId();
                        break;
                    case 'wfo_persistent_uri':
                        $out_line[] = 'https://list.worldfloraonline.org/' . $name->getWfoId();
                        break;
                    
                    case 'wfo_rhakhis_link':
                        $out_line[] = 'https://list.worldfloraonline.org/rhakhis/ui/index.html#' . $name->getWfoId();
                        break;

                    case 'wfo_role':
                        $out_line[] = $name->getRole();
                        break;
                    case 'wfo_full_name':
                        $out_line[] = $name->getFullNameStringPlain();
                        break;
                    case 'wfo_full_name_html':
                        $out_line[] = $name->getFullNameStringHtml();
                        break;

                    case 'wfo_genus':
                        if($name->getRank() == 'genus'){
                            $out_line[] = $name->getNameString();
                        }else{
                            $out_line[] = $name->getGenusString();
                        }
                        break;
                    case 'wfo_specific_epithet':
                        if($name->getRank() == 'species'){
                            $out_line[] = $name->getNameString();
                        }else{
                            $out_line[] = $name->getSpeciesString();
                        }
                        break;

                    case 'wfo_subspecific_epithet':
                        if($name->getSpeciesString()){
                            $out_line[] = $name->getNameString();
                        }else{
                            $out_line[] = "";
                        }
                        break;

                    case 'wfo_name_string':
                        $out_line[] = $name->getNameString();
                        break;

                    case 'wfo_trinomial':
                        $parts = array();
                        $parts[] = $name->getGenusString();
                        $parts[] = $name->getSpeciesString();
                        $parts[] = $name->getNameString();
                        $parts = array_filter($parts,'strlen');
                        $out_line[] = implode(' ', $parts);
                        break;

                    case 'wfo_name_no_authors':
                        $out_line[] = $name->getFullNameStringNoAuthorsPlain();
                        break;
                    case 'wfo_authors':
                        $out_line[] = $name->getAuthorsString();
                        break;
                    case 'wfo_rank':
                        $out_line[] = $name->getRank();
                        break;
                    case 'wfo_citation_micro':
                        $out_line[] = $name->getCitationMicro();
                        break;
                    case 'wfo_accepted_name_id':
                        $out_line[] = substr($name->getAcceptedId(), 0, 14);
                        break;
                    case 'wfo_accepted_name_full':
                        if($name->getAcceptedId()){
                            $accepted = new TaxonRecord($name->getAcceptedId());
                            $out_line[] = $accepted->getFullNameStringPlain();
                        }else{
                            $out_line[] = "";
                        }
                        break;
                    case 'wfo_basionym_name_id':
                        $out_line[] = substr($name->getBasionymId(), 0, 14);
                        break;
                    case 'wfo_basionym_name_full':
                        if($name->getBasionymId()){
                            $basionym = new TaxonRecord($name->getBasionymId());
                            $out_line[] = $basionym->getFullNameStringPlain();
                        }else{
                            $out_line[] = "";
                        }
                        break;
                    case 'wfo_parent_name_id':
                        if($name->getParent()){
                            $out_line[] = $name->getParent()->getWfoId();
                        }else{
                             $out_line[] = "";
                        }
                        break;
                    case 'wfo_parent_name_full':
                        if($name->getParent()){
                            $out_line[] = $name->getParent()->getFullNameStringPlain();
                        }else{
                             $out_line[] = "";
                        }
                        break;
                    case 'wfo_placement_name_path':
                        $path = array();

                        if($name->getRole() == 'accepted'){
                            $ancestors = $name->getPath(); 
                            foreach($ancestors as $a){
                                $path[] = $a->getNameString();
                            }
                            $out_line[] = implode("/", array_reverse($path));
                        }elseif($name->getRole() == 'synonym'){
                            $accepted = new TaxonRecord($name->getAcceptedId());
                            $ancestors = $accepted->getPath(); 
                            foreach($ancestors as $a){
                                $path[] = $a->getNameString();
                            }
                            $path = array_reverse($path);
                            $path[] = $name->getNameString();
                            $out_line[] = implode("/", $path);
                        }else{
                            $out_line[] = "";
                        }
                        
                        break;

                    case 'wfo_placement_id_path':
                        $path = array();

                        if($name->getRole() == 'accepted'){
                            $ancestors = $name->getPath(); 
                            foreach($ancestors as $a){
                                $path[] = $a->getWfoId();
                            }
                            $out_line[] = implode("/", array_reverse($path));
                        }elseif($name->getRole() == 'synonym'){
                            $accepted = new TaxonRecord($name->getAcceptedId());
                            $ancestors = $accepted->getPath(); 
                            foreach($ancestors as $a){
                                $path[] = $a->getWfoId();
                            }
                            $path = array_reverse($path);
                            $path[] = $name->getWfoId();
                            $out_line[] = implode("/", $path);
                        }else{
                            $out_line[] = "";
                        }
                        
                        break;

                
                    default:
                        $out_line[] = "";
                        break;
                }
            }
            // put the line out
            fputcsv($out, $out_line, escape: "\\");

        }

        // page at 1000 ids
        if($counter > $offset + 1000){
            fclose($in);
            fclose($out);
            $uri = "expander.php?offset={$counter}";
            echo "<script>window.location = \"$uri\"</script>";
            exit;
        }

    }

    // if we get to here then we have complete the loop
    // and therefore done the input file and can delete it
    @unlink($input_file_path);
    
}


?>
<h1>WFO ID Expander</h1>

<p>
    Once you have matched name strings to WFO IDs this tool allows you to download single value fields associated with that ID from 
    the current data release. If you want the multivalue, ancillary data related to a name use the <a href="references.php">Reference Download</a> tool.
</p>
<p>
    You upload a CSV file the first column of which must include the WFO IDs (wfo-0123456789). Any values that don't
    match 10 digit WFO IDs will be ignored. If the WFO ID is repeated in the input file it will be repeated in the output file.
</p>
<p>
    The results file will contain the additional columns you specify below.
</p>
<p>
    The columns in the results file are as follows:
</p>

<?php 

    // if there is an output file let them download it
    if(file_exists($output_file_path)){

        date_default_timezone_set('UTC');
        $modified = date(DATE_RFC2822, filemtime($output_file_path), );

        echo "<p><a href=\"$output_file_path\">Download Results</a> (Created: $modified)</p>";


        ?>
<p><strong>Note on Encoding:</strong>
    UTF-8 encoding is assumed throughout.
    This should work seemlessly apart from in one situation.
    <br />If you download a file and open it with Microsoft Excel by double clicking
    on the file itself Excel may assume the wrong encoding.
    <br />To preserve the encoding import the file via File > Import > CSV and choose Unicode (UTF-8) from the
    "File origin" dropdown.
    <br />Files saved as CSV from Excel are UTF-8 encoded by default.
</p>
<?php

    } // end output file exists

    // no input file give them the ability to upload one
    if(!file_exists($input_file_path)){
?>
<hr/>
<h3>Choose fields</h3>
<form action="expander.php" method="POST" enctype="multipart/form-data">

<?php

foreach($fields as $field_name => $field_description){
    if(in_array($field_name, $selected_fields)) $checked = 'checked';
    else $checked = '';
?>
<div class="form-check">
  <input class="form-check-input" type="checkbox" value="1" id="<?php echo $field_name ?>" name="<?php echo $field_name ?>" <?php echo $checked ?> >
  <label class="form-check-label" for="<?php echo $field_name ?>">
    <strong><?php echo $field_name ?></strong> - <?php echo $field_description ?>
  </label>
</div>
<?php
}// for fields
?>

<hr/>
<h3>Upload a CSV file</h3>
    Select file to upload:
    <input type="file" name="input_file" id="input_file">
    <input type="submit" value="Upload CSV File" name="submit">
</form>
</p>
<p>Be patient. For large files this may take a while. Please don't use this for scraping all the data. The complete
    dataset can always be downloaded from <a href="https://zenodo.org/doi/10.5281/zenodo.7460141">Zenodo</a>.</p>
<hr/>
<?php
    } // end no input file

    require_once('footer.php');
?>