<?php

abstract class BatchToolOperation {
    // Return help text for this filter
    public abstract function getHelpText();

    // Sets required and optional command line parameter fields for this class
    // If any values are wrong, missing or unsupported, return an error message as string
    // If error is returned, the program and all operations are aborted
    // Otherwise return true
    public abstract function setParameters( $param_array );

    // Returns class name of objects that this operation is made for
    // This function is not required, will return 'eZContentObjectTreeNode' by default
    public function getClassName()
    {
        return 'eZContentObjectTreeNode';
    }

    // $node - The content object node to do operation on
    public abstract function runOperation( &$node );
}