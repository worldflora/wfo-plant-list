<?php
if($_SERVER['SERVER_NAME'] && $_SERVER['SERVER_NAME'] == 'list.worldfloraonline.org'){
?>
User-agent: *
Allow: /wfo-*
Disallow: /browser.php
Crawl-delay: 10
<?php
}else{
?>
User-agent: *
Disallow: /
<?php
}