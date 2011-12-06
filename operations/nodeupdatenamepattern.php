<?php

class nodeupdatenamepatternOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodeupdatenamepattern"

Refreshes the name of the specified content object, based on the class name pattern.
Will update the name only if it has changed.
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
    // This function is not required, will return 'eZContentObjectTreeNode' by default
    function getClassName()
    {
        return 'eZContentObjectTreeNode';
    }

    // $node - The content object node to do operation on
    function runOperation( &$node )
    {
        $content_object = $node->attribute( 'object' );
        $class_id = $content_object->attribute( 'contentclass_id' );
        // Find class pattern, if not cached
        if ( !isset( $this->pattern_array[$class_id] ) )
        {
            $class = $content_object->attribute( 'content_class' );
            if ( $class )
            {
                $this->pattern_array[$class_id] = $class->attribute( 'contentobject_name' );
            }
            else
            {
                echo "Error: Class with id $class_id not found.\n";
                return false;
            }
        }
        // Generate new name, and update if changed
        $name_object = new eZNamePatternResolver( $this->pattern_array[$class_id], $content_object );
        $old_name = $content_object->attribute( 'name' );
        $new_name = $name_object->resolveNamePattern();
        if ( $old_name != $new_name )
        {
            echo "Updating object name from '$old_name' to '$new_name'\n";
            $content_object->setName( $new_name );
        }
        return true;
    }

    var $pattern_array = array();
}

?>
