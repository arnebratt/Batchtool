<?php
 
class nodeassignlocationsOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodeassignlocations;locations=<node_id,node_id...>"

Add new locations under the specified parent node id\'s
locations - a colon separated list of new parent node id\'s to assign to this node
';
    }
 
    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'locations' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";

        $this->locations = isset( $parm_array[ 'locations' ] ) ? explode( ':', $parm_array[ 'locations' ] ) : array();
 
        if ( empty( $this->locations ) )
            return 'Missing or illegal locations list';
        foreach ( $this->locations as $node_id )
        {
            if ( intval( $node_id ) == 0 )
                return "Node id '$node_id' is invalid";
        }
        return true;
    }
 
    // Assign locations to the given node
    // locations - an array of node IDs for new parent nodes
    function runOperation( &$node )
    {
        $db = eZDB::instance();
        $db->begin();

        foreach ($this->locations as $location)
        {
            $contentobject = $node->object();
            $id = $contentobject->addLocation( $location, true );
            if ( empty( $id ) )
            {
                $db->rollback();
                return false;
            }
        }

        $db->commit();
        return true;
    }
 
    var $locations = array();
}