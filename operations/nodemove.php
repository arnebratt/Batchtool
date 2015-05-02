<?php

class nodemoveOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodemove;target=<node id>;move_to_depth=<depth>"

target - Node ID of the target node to move selected nodes under
move_to_depth - Depth of content tree to move the node up to. Must always be above current depth!
';
    }

    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'target', 'move_to_depth' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";

        if ( isset( $parm_array[ 'target' ] ) )
        {
            $this->target_id = intval( $parm_array[ 'target' ] );
        }
        if ( isset( $parm_array[ 'move_to_depth' ] ) )
        {
            $this->move_to_depth = intval( $parm_array[ 'move_to_depth' ] );
        }
        if ( $this->target_id == 0 AND $this->move_to_depth == 0 )
            return 'Missing target node id and target depth to move to';
        if ( $this->target_id > 0 AND $this->move_to_depth > 0 )
            return 'Target node id and target depth can not be used simultaniously';
        return true;
    }

    // Move the given node to the specified target
    // target_id - Node id of the node to move to
    // move_to_depth - Depth of content tree to move up to
    function runOperation( &$object )
    {
        $target_id = $this->target_id;

        if ( empty( $target_id ) )
        {
            if ( $this->move_to_depth >= $object->attribute( 'depth' ) )
            {
                return false;
            }
            // Find the correct target node for the specified depth
            $path_array = $object->attribute( 'path_array' );
            $target_id = $path_array[$this->move_to_depth - 1];
        }

        return moveNode( $object, $target_id );
    }

    var $target_id;
    var $move_to_depth;
}