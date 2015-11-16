<?php

class fetchnodeidlistFilter extends BatchToolFilter
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--filter="fetchnodeidlist;node_ids=<node id list>[;use_main_node]"

node_ids - A list of node id numbers separated by a colon
use_main_node - Returns main node instead of the selected node, if they are different
';
    }

    // Sets required and optional command line parameter fields for this class
    // If any values are wrong or missing, return an error message as string
    // Otherwise return true
    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'node_ids', 'use_main_node' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in filter";

        // Check for mandatory parameters
        $this->node_id_list = explode( ':', $parm_array['node_ids'] );
        foreach ( $this->node_id_list as $node_id )
        {
            if ( intval( $node_id ) == 0 )
                return "Node id '$node_id' is invalid";
        }
        if ( count( $this->node_id_list ) == 0 )
        {
            return 'No node ids specified';
        }

        $this->use_main_node = isset( $parm_array['use_main_node'] );
        return true;
    }
    
    // Returns an array of objects to do operations on
    // All filters in a job must return the same type of objects,
    // which must correspond to the type of objects operations in a job is made for
    function getObjectList()
    {
        $result = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $this->node_id_list ) );
        if ( $result && !is_array( $result ) )
        {
            $result = array( $result );
        }
        if ( $this->use_main_node )
        {
            foreach ( $result as $key => $node )
            {
                if ( $node->attribute( 'node_id' ) != $node->attribute( 'main_node_id' ) )
                {
                    $result[$key] = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $node->attribute( 'main_node_id' ) ) );
                }
            }
        }
        return $result;
    }

    // Command line input parameters
    var $node_id_list;
    var $use_main_node;
}