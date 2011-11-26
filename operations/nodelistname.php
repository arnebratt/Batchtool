<?php

class nodelistnameOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodelistname"

Prints out the name of the selected nodes, no changes are done on them.
';
    }

    // Sets required and optional command line parameter fields for this class
    // If any values are wrong, missing or unsupported, return an error message as string
    // If error is returned, the program and all operations are aborted
    // Otherwise return true
    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        foreach ( $parm_array as $name=>$value )
        {
            return "Unsupported parameter '$name' in operation";
        }

        return true;
    }
    
    // Returns class name of objects that this operation is made for
    // This function is not required, will return 'eZContentObjectTreeNode' if not available
    function getClassName()
    {
        return 'eZContentObjectTreeNode';
    }

    // Specifies an operation to be done on the filtered objects
    // $object - The object to do operation on
    // Returns true if successfull, false otherwise
    // If false is returned, any following operations on this object will be aborted
    // and the program will continue on the next object
    function runOperation( &$object )
    {
        echo $object->attribute( 'name' ).': ';
        return true;
    }
}

?>
