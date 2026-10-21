<?php

/*

This is a template file for variable that should not be submitted to github

Make a copy of it and move it below the code level where it will be included in the
config.php file.

If you want to place it anywhere else you will need to adjust the path in config.php

*/

$solr_query_uri = "http://localhost:8983/solr/wfo-api";
$solr_user = '***';
$solr_password = '***';

$default_classification_version = "9999-01"; // zero indexed is used if not overridden.

$plant_list_system_message = "Roger's dev server. Classification id: " . $default_classification_version;
// set up bearer tokens used for reading or writing the index
// each token is associated with an array of rights (usually just read or write)
// https://randomkeygen.com/
$api_bearer_tokens = array(
	'NCS2nPKRwP9v9FIZs02AKB4pZuXKow1Bc2mXTehh8QPKZBE5HGBB3DBa' => array('write', 'read'),
	'roger_local_read_NA65YBPzYCyOZbZUUXFmE5LOtJmrERGL' => array('read'	)
);
	
?>