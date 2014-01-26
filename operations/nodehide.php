<?php
 
class nodehideOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodehide;hidden=<true|false>"
 
hidden - specify if the node (and sub nodes) should be hidden or revealed
';
    }
 
    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'hidden' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
        {
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";
        }

        switch( $parm_array[ 'available' ] )
        {
            case 'true':
                $this->hidden = true;
                break;

            case 'false':
                $this->hidden = false;
                break;

            default:
                return 'Illegal "hidden" value';
        }

        return true;
    }
 
    // Hide or unhide the given node
    function runOperation( &$node )
    {
        if ( !$node->canHide() )
        {
            return false;
        }

        if ( $this->hidden )
        {
            eZContentObjectTreeNode::hideSubTree( $node );
        }
        else
        {
            eZContentObjectTreeNode::unhideSubTree( $node );
        }
        // We have no result value, so assume the operation worked
        return true;
    }
 
    var $hidden = true;
}