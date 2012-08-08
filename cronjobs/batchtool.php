<?php

include_once( "lib/ezdb/classes/ezdb.php" );
include_once( "lib/ezutils/classes/ezini.php" );
include_once( 'lib/ezutils/classes/ezfunctionhandler.php' );
include_once( 'kernel/classes/ezcontentobjecttreenodeoperations.php' );
include_once( 'kernel/classes/ezcontentobjectoperations.php' );
include_once( 'extension/batchtool/classes/lib.php' );

$standard_options = "[h|help][q|quiet][d;*|debug;*][c|colors][no-colors][logfiles][no-logfiles][s:|siteaccess:][l:|login:][p:|password:][v*|verbose*]";
$script_options = "[f:|filter:*][o:|operation:*][and][or]";

$db = eZDB::instance();
$ini = eZINI::instance( 'site.ini' );
$iniBT = eZINI::instance( 'batchtool.ini' );

// User to run the specified command with (set to admin pr.default)
$user_id = $iniBT->variable( 'BatchToolSettings', 'UserID' );
$user = eZUser::fetch( $user_id );
eZUser::setCurrentlyLoggedInUser( $user, $user_id );

// Object list to work on. Must be fetched and returned by the filter file
$object_list = array();

// Fetch all command line options
$options = $cli->getOptions( $standard_options.$script_options, '' );

// Find name of include file that will fetch nodes and specify command to run
$filter_list = $iniBT->variable( 'BatchToolSettings', 'FilterList' );
$filter_objects = array();
$op_list = $iniBT->variable( 'BatchToolSettings', 'OperationList' );
$op_objects = array();

// Create filter objects and make basic checks
foreach( $options['filter'] as $filter )
{
    $parm_array = explode( ';', $filter );
    $filter_name = $parm_array[0];
    unset( $parm_array[0] );
    if ( !in_array( $filter_name, $filter_list ) )
    {
        echo "Error: Filter '$filter_name' not registered in batchtool.ini.\n";
        return;
    }
    if ( !is_readable( "extension/batchtool/filters/$filter_name.php" ) )
    {
        echo "Error: Filter '$filter_name' not found in extension/batchtool/filters/$filter_name.php.\n";
        return;
    }
    require_once( "extension/batchtool/filters/$filter_name.php" );
    $classname = "{$filter_name}Filter";
    $filter_obj = new $classname();
    $filter_objects[] = &$filter_obj;
    if ( !method_exists( $filter_obj, 'getObjectList' ) )
    {
        echo "Error: Filter '$filter_name' missing method getObjectList().\n";
        return;
    }
    if ( method_exists( $filter_obj, 'setParameters' ) )
    {
        $result = $filter_obj->setParameters( getParameters( $parm_array ) );
        if ( $result !== true )
        {
            echo "Error: Filter '$filter_name' have faulty parameters: '$result'.\n";
            if ( method_exists( $filter_obj, 'getHelpText' ) )
                echo $filter_obj->getHelpText();
            return;
        }
    }
    else
    {
        echo "Error: Filter '$filter_name' missing method setParameters().\n";
        return;
    }
    if ( isset( $options['help'] ) )
    {
        if ( method_exists( $filter_obj, 'getHelpText' ) )
            echo $filter_obj->getHelpText();
    }
    unset( $filter_obj );
}
// Create operation objects and make basic checks
foreach( $options['operation'] as $operation )
{
    $parm_array = explode( ';', $operation );
    $operation_name = $parm_array[0];
    unset( $parm_array[0] );
    if ( !in_array( $operation_name, $op_list ) )
    {
        echo "Error: Operation '$operation_name' not registered in batchtool.ini.\n";
        return;
    }
    if ( !is_readable( "extension/batchtool/operations/$operation_name.php" ) )
    {
        echo "Error: Operation '$operation_name' not found in extension/batchtool/operations/.\n";
        return;
    }
    require_once( "extension/batchtool/operations/$operation_name.php" );
    $classname = "{$operation_name}Operation";
    $op_obj = new $classname();
    $op_objects[] = &$op_obj;
    if ( !method_exists( $op_obj, 'runOperation' ) )
    {
        echo "Error: Operation '$operation_name' missing method 'runOperation'.\n";
        return;
    }
    if ( method_exists( $op_obj, 'setParameters' ) )
    {
        $result = $op_obj->setParameters( getParameters( $parm_array ) );
        if ( $result !== true )
        {
            echo "Error: Operation '$operation_name' have faulty parameters: $result.\n";
            if ( method_exists( $op_obj, 'getHelpText' ) )
                echo $op_obj->getHelpText();
            return;
        }
    }
    else
    {
        echo "Error: Operation '$operation_name' missing method setParameters().\n";
        return;
    }
    if ( isset( $options['help'] ) )
    {
        if ( method_exists( $op_obj, 'getHelpText' ) )
            echo $op_obj->getHelpText();
    }
    unset( $op_obj );
}

// If help requested, exit program here
if ( isset( $options['help'] ) )
{
    return;
}

// Check that filters and operations exist
if ( empty( $filter_objects ) OR empty( $op_objects ) )
{
    echo "Error: No filters specified or no operations specified.\n";
    return;
}

// Fetch the name of the ID field for our objects
$idFieldName = 'node_id';
if ( method_exists( $filter_objects[0], 'getIDField' ) )
{
    $idFieldName = $filter_objects[0]->getIDField();
}

// Run through all filters for this job
$first_filter = true;
foreach( $filter_objects as $filter )
{
    $tmp_list = $filter->getObjectList();
    if ( isset( $object_list[0] ) AND isset( $tmp_list[0] ) AND get_class( $object_list[0] ) != get_class( $tmp_list[0] ) )
    {
        echo "Error: Illegal mixing of different filter objects.\n";
        return;
    }
    if ( $options['and'] === true AND !$first_filter )
    {
        $object_id_array = array();
        foreach ( $object_list as $object )
        {
            $object_id_array[] = $object->attribute( $idFieldName );
        }
        foreach ( $tmp_list as $object )
        {
            $id = $object->attribute( $idFieldName );
            $key = array_search( $id, $object_id_array );
            if ( $key !== false )
            {
                unset( $object_id_array[$key] );
            }
        }
        foreach ( $object_list as $key=>$object )
        {
            if ( in_array( $object->attribute( $idFieldName ), $object_id_array ) )
            {
                unset( $object_list[$key] );
            }
        }
        unset( $object_id_array );
    }
    else if( is_array( $tmp_list ) )
    {
        $object_list = array_merge( $object_list, $tmp_list );
    }
    $first_filter = false;
    unset( $tmp_list );
}
unset( $filter_objects );

// Make sure all operations accept the object classes that have been fetched
foreach( $op_objects as $operation )
{
    $class_name = ( method_exists( $operation, 'getClassName' ) ) ? $operation->getClassName() : 'eZContentObjectTreeNode';
    if ( isset( $object_list[0] ) AND strcasecmp( get_class( $object_list[0] ), $class_name ) != 0 )
    {
        echo "Error: Operation not created for these object types.\n";
        return;
    }
}

// Nodes fetched, lets run a loop through all the nodes
$total_count = count( $object_list );
echo "Running operations on $total_count objects.\n";
$changed_count = 0;
$duplicate_count = 0;
$object_id_array = array();
foreach( $object_list as $object )
{
    $object_id = $object->attribute( $idFieldName );
    if ( in_array( $object_id, $object_id_array ) )
    {
        $duplicate_count++;
        continue;
    }
    $object_id_array[] = $object_id;
    // Run through all operations for this job
    foreach( $op_objects as $operation )
    {
        $result = $operation->runOperation( $object );
        if ( !$result )
        {
            break;
        }
    }

    if ( $result )
    {
        $changed_count++;
    }
    if ( empty( $options['quiet'] ) )
    {
        echo ( $result ) ? "Done operations on node $object_id\n" : "Operations failed on node $object_id\n";
    }
}

echo "$total_count objects processed, $changed_count objects successfull, $duplicate_count duplicates.\n";

?>
