<?php

class nodecopyOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodecopy;target=<node id>"

target - Node ID of the target node to copy selected nodes under
';
    }

    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'target' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";

        $this->target_id = intval( $parm_array[ 'target' ] );
        if ( $this->target_id == 0 )
            return 'Missing or illegal target node id';
        return true;
    }

    // Copy the given node to the specified target
    // target_id - Node id of the node to move to
    function runOperation( &$object )
    {
        return copyObject( $object->attribute( 'object' ), false, $this->target_id );
    }

    var $target_id;
}

?>
