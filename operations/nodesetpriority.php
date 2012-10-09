<?php

class nodesetpriorityOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodesetpriority;start=<number>;interval=<number>"

start - Priority value for the first node
interval - Interval value to add to start value for each node
';
    }

    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'start', 'interval' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";

        if ( !isset( $parm_array[ 'start' ] ) )
            return 'Missing starting priority value';
        $this->priority = intval( $parm_array[ 'start' ] );
        $this->interval = intval( $parm_array[ 'interval' ] );
        if ( $this->interval == 0 )
            return 'Missing or illegal interval value';
        return true;
    }

    // Set priority on a node, with the specified start and skip values
    function runOperation( &$object )
    {
        $this->priority -= $this->interval;
        $object->setAttribute( 'priority', $this->priority );
        $object->store();
        return true;
    }

    var $priority;
    var $interval;
}