<?php

require_once('config.php');
require_once('../include/PlantList.php');
require_once('../include/TaxonRecord.php');
require_once('../include/content_negotiate.php'); // handles content negotiation so saves us having .htaccess

require_once('header.php');
?>

<h1>WFO Plant List API</h1>

<p>
    Welcome to the WFO Plant List API. The WFO Plant List is the consensus list of names used as the taxonomic backbone
    of the World Flora Online portal.
    A new version is released every six months.
</p>
<p>
    This site provides techies with access to WFO Plant List data.
    It is intended for those who are comfortable working with simple (not pretty) HTML forms or who want to exploit
    the REST or GraphQL APIs.
</p>

<h2>Are you in the right place?</h2>

<ol>
    <li><a href="http://www.worldfloraonline.org">WFO Portal</a>: The main entry point for the WFO including all the
        description, distribution, image and other data. This is where you go to find out more about a plant.</li>
    <li><a href="http://www.wfoplantlist.org">WFO Plant List</a>: Human friendly access to the WFO Plant List in all its
        versions as soon as they are released. It forms part of 1, the main portal.</li>
    <li><a href="https://zenodo.org/doi/10.5281/zenodo.7460141">WFO Plant List Download
            [doi:10.5281/zenodo.7460141]</a>: Gives
        access to the same data as available from 2 above to download in multiple formats and citable via a DOI.</li>
    <li><strong>WFO Plant List API:</strong> This site. Gives access to the same data as available from 2 above but via
        APIs and specialist tools.</li>
    <li><a href="https://rhakhis-live.rbge.info">The Rhakhis Taxonomic Editor (<strong>LIVE</strong>)</a>: A
        tool for taxonomists preparing the next WFO Plant List data release.</li>
    <li><a href="https://rhakhis-test.rbge.info">Rhakhis Taxonomic Editor (<strong>TEST</strong>)</a>: A test
        copy of the Rhakhis tool used for training. The data on this one is overwritten each night
        with the data from the live copy.
    </li>
</ol>

<p>
    The services here only cover names governed by the International Coded of Nomenclature for Algae, Fungi and Plants
    as curated by the WFO.
    If your data includes names from the Zoological code or you wish to query other sources then you may be better
    served by the <a href="http://gni.globalnames.org/">Global Names Verifier</a>.</p>

<h2>What is here?</h2>

<ul>
    <li><a href="sw_index.php" />Stable URIs</a>: Semantic Web compatible stable HTTP URIs that you can use to link to
        Names and Taxa as well as in triple stores and other technologies.</li>
    <li><a href="gql_index.php" />GraphQL API</a>: A GraphQL API giving access to all the data releases of the WFO Plant
        List through a flexible, widely used, cross platform technology.</li>
    <li><a href="matching.php" />Matching Tool</a>: A form based online tool to match lists of names either cut and
        pasted into a form or uploaded as a CSV file.</li>
    <li><a href="matching_rest.php" />Matching API</a>: A simple REST API to match name strings. This is a subset of
        what is available through the GraphQL interface.</li>
    <li><a href="reconcile_index.php" />Reconciliation API</a>: An implementation of the <a
            href="https://reconciliation-api.github.io/specs/latest/">W3C Reconciliation Service API</a> for use in <a
            href="https://openrefine.org/">OpenRefine</a> and other tools.</li>
    <li><a href="references.php" />Refs Tool</a>: Download the ancillary data (references to people, literature,
        specimens and databases) associated with a list of matched names.</li>
    <li><a href="stats.php" />Statistics</a>: A break down of the numbers of names and taxa in the index.</li>
    <li><a href="ranks.php" />Ranks</a>: A list of the ranks used in the list.</li>
<!--
    <li><a href="browser.php" />Browser</a>: A simple text based tool for exploring the data in the index.</li>
-->

</ul>

<p>
    For our purposes, matching/reconciling is the process or binding your data to a WFO Name record (represented by a
    WFO ID) on the basis of a string of characters you supply.
    This differentiates between a Name (capital 'N') and a string of characters that represent the Name in a particular
    context and thus avoids us getting into a semantic/philosophical tangle.
</p>


<h2>Data Model</h2>

<p>
    Every six months, on the solstices, a snapshot of the data in the Rhakhis editor is taken and added to this service.
    The data available here therefore represents multiple classifications of the
    plant kingdom showing how our understanding has changed through time.
</p>
<p>
    In order to represent multiple classifications in a single dataset it is necessary to adopt the TaxonConcept model
    which differentiates between taxa (TaxonConcepts)
    which vary between classifications and names (TaxonNames) which do not, but which may play different roles in
    different classifications.
</p>

<p class="aside">
    <strong>Taxon name/concept background: </strong>
    A good analogy for those unfamiliar with the TaxonConcept model is that of polygons and points within a geospatial
    model.
    A classification is like a map of contiguous, nested polygons (like counties, regions, countries, continents).
    These are the taxa.
    The names are like fixed points on the map. They never move.
    Each polygon might contain multiple points.
    The name used for a polygon is based on the oldest point that occurs within it.
    Other names that fall in the polygon are referred to as synonyms.
    Different taxonomic classifications are like different maps of the same terrain with different polygons but with the
    same points.
    Polygons on two maps might have the same calculated name but different boundaries and different synonyms.
    It is therefore necessary to refer to taxa in different classifications using unique identifiers rather than just
    their calculated names.
</p>

<a id="identifiers">
    <h2>Identifiers</h2>
</a>

<p>
    All name records have a single, prescribed ID which is of the form <code>wfo-0000615907</code>. The lowercase
    letters "wfo" followed by a hyphen followed by ten digits.
    A regular expression similar to <code>'/^wfo-[0-9]{10}$/'</code> will match a WFO ID (depending on your precise
    regex implementation).
</p>

<p>
    Once created WFO IDs are never deleted and will always return data.
    However it is possible that two IDs have been created for one real world name. We do our utmost to prevent this
    happening but
    we are still dealing with some historical duplication within the data.
    In the cases where it is decided that multiple WFO IDs apply to a single name then the records are merged and one of
    the IDs prescribed as the one that should be used going forward.
    The other WFO ID becomes a deduplicated ID for that name record. Services will still respond to that ID.
    It will never be deleted but it won't be presented as the WFO ID that should be used for that name again.
</p>

<p>
    With each data release a new set of IDs are created that are of the form <code>wfo-0000615907-2022-12</code>.
    For each name the year and month of the data release are appended.
    A regular expression similar to <code>'/^wfo-[0-9]{10}-[0-9]{10}-[4]{2}$/'</code> will match a versioned WFO ID
    (depending on your precise regex implementation).
</p>

<p>
    Within a data release names play one of four roles and the meaning of the sixteen digit WFO ID depends on role the
    name is playing.
</p>

<ol>
    <li>Placed as the <strong>accepted</strong> name of a taxon. The ID refers to the taxon.</li>
    <li>Placed as a <strong>synonym</strong> within a taxon. The ID refers to the name usage.</li>
    <li><strong>Unplaced</strong> if our experts have yet to express an opinion on placement. The ID refers to the name
        alone.</li>
    <li><strong>Deprecated</strong> if our experts conclude it isn't possible to place this name and it should not be
        used. The ID refers to the name record that shouldn't be used.</p>
</ol>

<p>
    Un-versioned, ten digit WFO IDs will usually be treated as referring to the current usage of that name.
    i.e. if a data release version isn't specified the current release will be presumed.
</p>

<p>
    WFO IDs are used in their ten digit and sixteen digit forms in different services or as the final parts of <a
        href="sw_index.php">Stable HTTP URIs</a>.
</p>

<p>
    To link to a WFO name it is recommended to always use the <a href="sw_index.php">Stable HTTP URI</a> form and not to
    reverse engineer the URL that appears in a browser bar
    which isn't guaranteed to be stable.
</p>

<a name="scale">
    <h2>Scalability and Performance</h2>
</a>

<p>
    Currently no API keys are required for these services. They are open for anyone to use.
    If we find that service is being degraded we may introduce IP based throttling or access tokens to ensure
    availability for all.
</p>

<p>
    This whole application can be installed on a server or personal machine by anyone with appropriate technical skills.
    If you are likely to require heavy use of the service or wish to embed it within a production workflow you are
    encouraged to
    install a local copy of the application.
    The code and instructions are <a href="https://github.com/rogerhyam/wfo-plant-list">available on GitHub</a>.
    The data can be <a href="https://zenodo.org/record/7467360">downloaded from Zenodo</a>.
    Any questions please contact <a href="mailto:rhyam@rbge.org.uk">Roger Hyam</a>.
</p>


<?php
require_once('footer.php');
?>