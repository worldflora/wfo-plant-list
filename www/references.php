<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/NameMatcher.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');

// set up the file store
$file_dir = 'matching_cache/' . session_id() . "/";
if(!file_exists($file_dir)) mkdir($file_dir);
$input_file_path = $file_dir . "refs_input.csv";
$output_file_path = $file_dir . "refs_output.csv";

// clear down if called with nothing
if(!$_POST && !@$_GET['offset']){
   @unlink($input_file_path);
   @unlink($output_file_path);
}

// have they uploaded a file?
if($_POST && isset($_FILES["input_file"])){
    move_uploaded_file($_FILES["input_file"]["tmp_name"], $input_file_path);
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
        fputcsv($out, array('wfo_id', 'full_name', 'reference_type', 'reference_context', 'citation', 'comment', 'url', 'thumbnail_url'), escape: "\\");
    }else{
        echo "<p>Offset: $offset</p>";
    }

    // work through the input file
    $counter = 0;
    while($line = fgetcsv($in, escape: "\\")){

        $counter++;
        
        // skip to the offset position
        if($counter < $offset) continue;

        $wfo = trim($line[0]);
        
        // skip non compliant WFO IDs
        if(!preg_match('/^wfo-[0-9]{10}$/', $wfo)) continue;

        // get the Name
        $name = new TaxonRecord($wfo);

        // if we can't load a name for the wfo we continue
        if(!$name) continue;

        // We finally start an output

        // firstly do the micro-citation
        $out_line = array();
        $out_line[] = $wfo;
        $out_line[] = $name->getFullNameStringPlain();
        $out_line[] = 'micro-citation';
        $out_line[] = 'nomenclatural';
        $out_line[] = $name->getCitationMicro();
        $out_line[] = ''; // comment
        $out_line[] = ''; // url
        $out_line[] = ''; // thumbnail
        fputcsv($out, $out_line, escape: "\\");


        // next iterate through the references
        $refs = $name->getReferences();
        foreach ($refs as $ref) {
            $out_line = array();
            $out_line[] = $wfo;
            $out_line[] = $name->getFullNameStringPlain();
            $out_line[] = $ref->kind;
            $out_line[] = $ref->context;
            $out_line[] = $ref->label;
            $out_line[] = $ref->comment; // comment
            $out_line[] = $ref->uri; // url
            $out_line[] = $ref->thumbnailUri; // thumbnail
            fputcsv($out, $out_line, escape: "\\"); 
        }

        // page at 1000 ids
        if($counter > $offset + 1000){
            fclose($in);
            fclose($out);
            $uri = "references.php?offset={$counter}";
            echo "<script>window.location = \"$uri\"</script>";
            exit;
        }
    }

}

?>
<h1>Reference Download</h1>
<p>
    Once you have matched name strings to WFO IDs this tool allows you to download ancillary data associated with those
    names. If you want the core data related to a name use the <a href="expander.php">WFO ID Expander</a>.
</p>
<p>
    You upload a CSV file the first column of which must include the WFO IDs (wfo-0123456789). Any values that don't
    match 10 digit
    WFO IDs will be ignored. If the WFO ID is repeated in the input file it will be repeated in the output file.
</p>
<p>
    The results file will contain multiple lines per WFO ID. Each line will be for a reference associated with that
    name.
    The first will be the micro-citation for name and will always be present. Subsequent lines will be for people,
    literature, specimens and database references associated with the name.
</p>
<p>
    The columns in the results file are as follows:
</p>
<ul>
    <li><strong>wfo_id:</strong> The 10 digit WFO ID this name is known by.</li>
    <li><strong>full_name:</strong> The full name string including the author string.</li>
    <li><strong>nomenclatural_status:</strong> The nomenclatural status of the name.</li>
    <li><strong>reference_type:</strong> One of:
        <ul>
            <li><strong>micro_citation</strong> The abbreviated citation of the place of publication typically used in
                floras and monographs.</li>
            <li><strong>person</strong> A person associated with the name. Typically this is one of the authors of the
                name but may be the person the plant was named for. Details will be in the comment field.</li>
            <li><strong>literature</strong> Typically the place of publication of the name as a link to BHL or a DOI.
            </li>
            <li><strong>database</strong> A nomenclatural database that may have more information about the name.</li>
            <li><strong>specimen</strong> One of the type specimens of the name.</li>
        </ul>
    </li>
    <li><strong>citation</strong> The string representation of the reference. May vary but normalized to APA format
        where possible.</li>
    <li><strong>comment</strong> The comment as to why this reference is associated with this name.</li>
    <li><strong>url</strong> The URL of this reference.</li>
    <li><strong>thumbnail_url</strong> The URL of a small image of this resource, e.g. the page, a specimen or a
        portrait of the person.</li>
</ul>

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

<h2>Upload a CSV File</h2>
<form action="references.php" method="POST" enctype="multipart/form-data">
    Select file to upload:
    <input type="file" name="input_file" id="input_file">
    <input type="submit" value="Upload CSV File" name="submit">
</form>
</p>
<p>Be patient. For large files this may take a while. Please don't use this for scraping all the data. The complete
    dataset can always be downloaded from <a href="https://zenodo.org/doi/10.5281/zenodo.7460141">Zenodo</a>.</p>

<?php
    } // end no input file

    require_once('footer.php');
?>