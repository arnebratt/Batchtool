<?php

include_once( 'extension/batchtool/classes/lib.php' );

try
{
    $batchtool = new Batchtool();
    $batchtool->runOperations();
    $batchtool->summary();
}
catch( Exception $e )
{
    $cli->output( 'Error: ' . $e->getMessage() );
}

?>
