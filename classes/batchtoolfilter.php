<?php

abstract class BatchToolFilter {
    // Return help text for this filter
    public abstract function getHelpText();

    // Sets required and optional command line parameter fields for this class
    // If any values are wrong or missing, return an error message as string
    // Otherwise return true
    public abstract function setParameters( $parm_array );

    // Returns name of the ID attribute in the returned objects
    public function getIDField()
    {
        return 'node_id';
    }

    // Returns an array of objects to do operations on
    // All filters in a job must return the same type of objects,
    // which must correspond to the type of objects operations in a job is made for
    public abstract function getObjectList();
}