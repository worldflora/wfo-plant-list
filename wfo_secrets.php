<?php

/*

This is a template file for variable that should not be submitted to github

Make a copy of it and move it below the code level where it will be included in the
config.php file.

If you want to place it anywhere else you will need to adjust the path in config.php

*/

$solr_query_uri = "http://localhost:8983/solr/***";
$solr_user = '***';
$solr_password = '***';

$default_classification_version = "***"; // the classification to use (the index may contain many)


// set system message if one is needed
// $plant_list_system_message = "**** Classification id: " . $default_classification_version;
$plant_list_system_message = null;

// set up bearer tokens used for reading or writing the index
// each token is associated with an array of rights (usually just read or write)
// https://randomkeygen.com/
$api_bearer_tokens = array(
	'*****' => array('write', 'read'),
	'*****' => array('read'	)
);
	
?>