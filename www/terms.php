<?php
    require_once('config.php');
    
    $graph = new \EasyRdf\Graph();

    $classificationClass = $graph->resource(\EasyRdf\RdfNamespace::get('wfo') . 'Classification', 'rdfs:Class');

    $year = $graph->resource('wfo:year');
    $classificationClass->add('rdfs:Property', $year);
    $year->add('rdfs:range', $graph->resource('xsd:int'));

    $month = $graph->resource('wfo:month');
    $classificationClass->add('rdfs:Property', $month);
    $month->add('rdfs:range', $graph->resource('xsd:int'));


    // - T A X O N - C O N C E P T -

    $taxon_concept = $graph->resource(\EasyRdf\RdfNamespace::get('wfo') . 'TaxonConcept', 'rdfs:Class');

    // name
    $hasName = $graph->resource('wfo:hasName');
    $taxon_concept->add('rdfs:Property', $hasName);
    $hasName->add('rdfs:range', $graph->resource('wfo:TaxonName'));

    $editorialStatus = $graph->resource('wfo:editorialStatus');
    $taxon_concept->add('rdfs:Property', $editorialStatus);
    $editorialStatus->add('rdfs:range', $graph->resource('xsd:string'));

    // taxonomic
    $taxon_concept->add('rdfs:Property', $graph->resource('dc:hasPart'));
    $taxon_concept->add('rdfs:Property', $graph->resource('dc:isPartOf'));

    // versioning
    $taxon_concept->add('rdfs:Property', $graph->resource('dc:replaces'));
    $taxon_concept->add('rdfs:Property', $graph->resource('dc:isReplacedBy'));

    // synonymy
    $hasSynonym = $graph->resource('wfo:hasSynonym');
    $taxon_concept->add('rdfs:Property', $hasSynonym);
    $hasSynonym->add('rdfs:range', $graph->resource('wfo:TaxonName'));

    $classification = $graph->resource('wfo:classification');
    $taxon_concept->add('rdfs:Property', $classification);
    $classification->add('rdfs:range', $graph->resource('wfo:Classification'));


    // - T A X O N - N A M E -

    $taxon_name = $graph->resource(\EasyRdf\RdfNamespace::get('wfo') . 'TaxonName', 'rdfs:Class');

    // accepted Name for
    $acceptedNameFor = $graph->resource('wfo:acceptedNameFor');
    $taxon_name->add('rdfs:Property', $acceptedNameFor);
    $acceptedNameFor->add('rdfs:range', $graph->resource('wfo:TaxonConcept'));

    // current preferred usage
    $currentPreferredUsage = $graph->resource('wfo:currentPreferredUsage');
    $taxon_name->add('rdfs:Property', $currentPreferredUsage);
    $currentPreferredUsage->add('rdfs:range', $graph->resource('wfo:TaxonConcept'));

    // is synonymy of
    $isSynonymOf = $graph->resource('wfo:isSynonymOf');
    $taxon_name->add('rdfs:Property', $isSynonymOf);
    $isSynonymOf->add('rdfs:range', $graph->resource('wfo:TaxonConcept'));

    // hasBasionym
    $hasBasionym = $graph->resource('wfo:hasBasionym');
    $taxon_name->add('rdfs:Property', $hasBasionym);
    $hasBasionym->add('rdfs:range', $graph->resource('wfo:TaxonName'));

    // full name literal
    $fullName = $graph->resource('wfo:fullName');
    $taxon_name->add('rdfs:Property', $fullName);
    $fullName->add('rdfs:range', $graph->resource('xsd:string'));

    // authorship
    $authorship = $graph->resource('wfo:authorship');
    $taxon_name->add('rdfs:Property', $authorship);
    $authorship->add('rdfs:range', $graph->resource('xsd:string'));


    // familyName
    $familyName = $graph->resource('wfo:familyName');
    $taxon_name->add('rdfs:Property', $familyName);
    $familyName->add('rdfs:range', $graph->resource('xsd:string'));

    // genusName
    $genusName = $graph->resource('wfo:genusName');
    $taxon_name->add('rdfs:Property', $genusName);
    $genusName->add('rdfs:range', $graph->resource('xsd:string'));

    // specificEpithet
    $specificEpithet = $graph->resource('wfo:specificEpithet');
    $taxon_name->add('rdfs:Property', $specificEpithet);
    $specificEpithet->add('rdfs:range', $graph->resource('xsd:string'));


    //publicationCitation
    $publicationCitation = $graph->resource('wfo:publicationCitation');
    $taxon_name->add('rdfs:Property', $publicationCitation);
    $publicationCitation->add('rdfs:range', $graph->resource('xsd:string'));

    //publicationID
    $publicationID = $graph->resource('wfo:publicationID');
    $taxon_name->add('rdfs:Property', $publicationID);
    $publicationID->add('rdfs:range', $graph->resource('xsd:string'));

    //nameID
    $nameID = $graph->resource('wfo:nameID');
    $taxon_name->add('rdfs:Property', $nameID);
    $nameID->add('rdfs:range', $graph->resource('xsd:string'));

    // rank
    $ofTaxonomicRank = $graph->resource('wfo:rank');
    $taxon_name->add('rdfs:Property', $ofTaxonomicRank);
    $ofTaxonomicRank->add('rdfs:range', $graph->resource('wfo:TaxonomicRank'));

    //publicationID
    $deduplicatedID = $graph->resource('wfo:deduplicatedID');
    $taxon_name->add('rdfs:Property', $deduplicatedID);
    $deduplicatedID->add('rdfs:range', $graph->resource('xsd:string'));

    $graph->resource('wfo:genus')->add('rdf:type', $graph->resource('wfo:TaxonomicRank'));
    $graph->resource('wfo:family')->add('rdf:type', $graph->resource('wfo:TaxonomicRank'));
    $graph->resource('wfo:species')->add('rdf:type', $graph->resource('wfo:TaxonomicRank'));
    $graph->resource('wfo:subspecies')->add('rdf:type', $graph->resource('wfo:TaxonomicRank'));
    

/* 
rank. [Not defined] – used for the relative position of a taxon in the taxonomic hierarchy (Art. 2.1). For suprageneric names published on or after 1 January 1887, the rank is indicated by the termination of the name (see Art. 37.2 and footnote). For names published on or after 1 January 1953, a clear indication of the rank is required for valid publication (Art. 37.1).
*/

    // no graph no go
    if(!$graph){
        http_response_code(500);
        echo "Internal Server Error: Unable to generate graph for $wfo";
        exit;
    }

    // what are they asking for?
    $format_string = @$_GET['format'];
    if(!$format_string) $format_string = 'svg';


    // actually return the graph as requested
    $format = \EasyRdf\Format::getFormat($format_string);
    $serialiserClass  = $format->getSerialiserClass();
    $serialiser = new $serialiserClass();
        
    // if we are using GraphViz then we add some parameters 
    // to make the images nicer
    if(preg_match('/GraphViz/', $serialiserClass)){
        $serialiser->setAttribute('rankdir', 'LR');
    }
    $data = $serialiser->serialise($graph, $format_string);
    header('Content-Type: ' . $format->getDefaultMimeType());
    echo $data;

