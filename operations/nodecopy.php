<?php

class nodecopyOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodecopy;target=<node id>[;copysubtree]"

target - Node ID of the target node to copy selected nodes under
';
    }

    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'target', 'copysubtree' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";

        $this->target_id = intval( $parm_array[ 'target' ] );
        if ( $this->target_id == 0 )
            return 'Missing or illegal target node id';

        $this->copysubtree = isset( $parm_array[ 'copysubtree' ] ) ? true : false;
        return true;
    }

    // Copy the given node to the specified target
    // target_id - Node id of the node to move to
    function runOperation( &$node )
    {
        if ( $this->copysubtree )
        {
            // Using command line script ezsubtreecopy.php to copy subtrees
            $php = $_SERVER['_'];
            $siteaccess = $GLOBALS['eZCurrentAccess']['name'];
            $source = '--src-node-id=' . $node->attribute( 'node_id' );
            $destination = ' --dst-node-id=' . $this->target_id;
            $command = "$php bin/php/ezsubtreecopy.php -s $siteaccess $source $destination --all-versions --keep-creator --keep-time";
            exec( $command, $output, $return_var );
            return $return_var == 0;
        }
        else
        {
            return copyObject( $node->attribute( 'object' ), false, $this->target_id );
        }
    }

    var $target_id;
    var $copysubtree;
}