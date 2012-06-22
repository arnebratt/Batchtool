<?php

class nodesetstateOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodesetstate;state_group=<state group identifier>;state=<state identifier>"

state group identifier - Specify the identifier of the state group
state identifier -  Specify the iddentifier of the state to set on the content object
';
    }

    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'state_group', 'state' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";

        // Fetch state id's from the identifiers
        $state_group = eZContentObjectStateGroup::fetchByIdentifier( $parm_array[ 'state_group' ] );
        if ( $state_group === false )
            return 'Missing or illegal state group identifier';
        $state_group_id = $state_group->attribute( 'id' );
        $state = eZContentObjectState::fetchByIdentifier( $parm_array[ 'state' ], $state_group_id );
        if ( $state === false )
            return 'Missing or illegal state identifier';
        $this->state_id = $state->attribute( 'id' );
        return true;
    }

    function runOperation( &$node )
    {
        eZContentOperationCollection::updateObjectState( $node->attribute( 'contentobject_id' ), array( $this->state_id ) );
        return true;
    }

    var $state_id;
}

?>
