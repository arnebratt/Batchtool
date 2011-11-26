<?php

class nodedeleteOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodedelete[;use_trash]"

Will delete the selected nodes.
Also deletes the corresponding content object, if it\'s the only node on the object.
Any deleted content objects will be put in trash only if use_trash parameter is specified.
';
    }

    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'use_trash' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";

        $this->use_trash = isset( $parm_array['use_trash'] );
        return true;
    }

    // Delete the given node and, if it's the last node, it's object also
    function runOperation( &$object )
    {
        return $object->removeNodeFromTree( $this->use_trash );
    }

    var $use_trash;
}

?>
