<?php

class fetchnodelistFilter extends BatchToolFilter
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--filter="fetchnodelist;parent=<parent>[;classname=<class id list>][;limit=<limit>][;offset=<offset>][;depth=<depth>][;ignore_visibility][;locales=<locale code list>]"

parent - The parent node id of the nodes you want to fetch (required)
classname - A list of class identifiers separated by a colon
limit - Limit the total number of nodes to fetch
offset - The offset value for the node in the list of nodes to fetch
depth - Max level of depth to fetch nodes from (default is current folder only)
ignore_visibility - Fetch also hidden nodes
locales - List of translation locale codes to filter on, separated by a colon (ex."nor-NO:eng-GB")
';
    }

    // Sets required and optional command line parameter fields for this class
    // If any values are wrong or missing, return an error message as string
    // Otherwise return true
    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'parent', 'classname', 'limit', 'offset', 'depth', 'ignore_visibility', 'locales' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in filter";

        // Check for mandatory parameters
        $this->parent_node_id = intval( $parm_array['parent'] );
        if ( $this->parent_node_id == 0 )
            return 'Missing or illegal parent node id';

        // Store values of optional parameters
        $this->limit = isset( $parm_array[ 'limit' ] ) ? intval( $parm_array[ 'limit' ] ) : -1;
        $this->offset = isset( $parm_array[ 'offset' ] ) ? intval( $parm_array[ 'offset' ] ) : 0;
        $this->depth = isset( $parm_array[ 'depth' ] ) ? intval( $parm_array[ 'depth' ] ) : 1;
        if ( $this->depth == 0 )
            $this->depth = 1;
        $this->ignore_visibility = isset( $parm_array[ 'ignore_visibility' ] ) ? true : false;
        $this->class_filter = isset( $parm_array['classname'] ) ? explode( ':', $parm_array['classname'] ) : array();
        $this->locales = isset( $parm_array['locales'] ) ? explode( ':', $parm_array['locales'] ) : array();
        return true;
    }
    
    // Returns an array of objects to do operations on
    // All filters in a job must return the same type of objects,
    // which must correspond to the type of objects operations in a job is made for
    function getObjectList()
    {
        $parameters = array( 'parent_node_id' => $this->parent_node_id, 
                        'class_filter_type' => 'include', 
                        'class_filter_array' => $this->class_filter,
                        'offset' => $this->offset,
                        'depth' => $this->depth,
                        'ignore_visibility' => $this->ignore_visibility
                        );
        if ( $this->limit > 0 )
        {
            $parameters['limit'] = $this->limit;
        }
        if ( !empty( $this->locales ) )
        {
            $parameters['extended_attribute_filter'] = array( 'id' => 'TranslationsFilter',
                                                              'params' => array( 'locales' => $this->locales ) );
        }
        return eZFunctionHandler::execute( 'content', 'list', $parameters );
    }

    // Command line input parameters
    var $parent_node_id;
    var $class_filter;
    var $limit;
    var $offset;
    var $depth;
    var $ignore_visibility;
    var $locales;
}