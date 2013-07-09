<?php

include_once( 'extension/batchtool/classes/lib.php' );

$batchtool = new Batchtool();
$batchtool->runOperations();
$batchtool->summary();

?>
