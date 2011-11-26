<?php

class nodealwaysavailableOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodealwaysavailable;available=<true/false>"

available - Set node as available for all languages or not
';
    }

    function setParameters( $parm_array )
    {
        $this->available = ( $parm_array[ 'available' ] == 'true' ) ? 1 : -1;
        if ( $parm_array[ 'available' ] == 'false' )
            $this->available = 0;
        if ( $this->available == -1 )
            return 'Missing or illegal availability value';
        return true;
    }

    // Set always available language on a node, with the specified start and skip values
    function runOperation( &$node )
    {
        if ( eZOperationHandler::operationIsAvailable( 'content_updatealwaysavailable' ) )
        {
            $operationResult = eZOperationHandler::execute( 'content', 'updatealwaysavailable',
                                                            array( 'object_id'            => $node->attribute( 'contentobject_id' ),
                                                                   'new_always_available' => $this->available,
                                                                   // note : the $nodeID parameter is ignored here but is
                                                                   // provided for events that need it
                                                                   'node_id'              => $node->attribute( 'node_id' ) ) );
        }
        else
        {
            eZContentOperationCollection::updateAlwaysAvailable( $node->attribute( 'contentobject_id' ), $this->available );
        }
        return true;
    }

    var $available;
}

?>
