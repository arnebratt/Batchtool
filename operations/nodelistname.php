<?php

class nodelistnameOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodelistname[;fields=<field identifier list][;delimiter=<delimiter characters>][;newline]"

Prints out the name of the selected nodes, no changes are done on them.
fields - If included, will also print the content of these attributes
delimiter - One or more characters to separate multiple fields (";" by default)
newline - Specifies if newline should be added to each line
';
    }

    // Sets required and optional command line parameter fields for this class
    // If any values are wrong, missing or unsupported, return an error message as string
    // If error is returned, the program and all operations are aborted
    // Otherwise return true
    function setParameters( $parm_array )
    {
        $supported_parameters = array( 'fields', 'delimiter', 'newline' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
        {
            return "Unsupported parameter '{$unsupported_list[0]}' in filter";
        }

        $this->fields = isset( $parm_array[ 'fields' ] ) ? explode( ':', $parm_array[ 'fields' ] ) : array();
        $this->delimiter = isset( $parm_array[ 'delimiter' ] ) ? $parm_array[ 'delimiter' ] : ';';
        $this->newline = isset( $parm_array[ 'newline' ] );

        return true;
    }

    // Specifies an operation to be done on the filtered objects
    // $object - The object to do operation on
    // Returns true if successfull, false otherwise
    // If false is returned, any following operations on this object will be aborted
    // and the program will continue on the next object
    function runOperation( &$node )
    {
        if ( empty( $this->fields ) )
        {
            $data = array( $node->attribute( 'name' ) );
        }
        else
        {
            $object = $node->attribute( 'object' );
            $datamap = $object->datamap();
            foreach ( $this->fields as $field )
            {
                if ( isset( $datamap[$field] ) )
                {
                    $data[] = $datamap[$field]->attribute( 'content' );
                }
                else
                {
                    if ( $object->hasAttribute( $field ) )
                    {
                        // Found field name as an attribute of the content object php class
                        $data[] = $object->attribute( $field );
                    }
                    elseif ( $node->hasAttribute( $field ) )
                    {
                        // Found field name as an attribute of the node php class
                        $data[] = $node->attribute( $field );
                    }
                    else
                    {
                        // Support sub fields by using / as a delimiter between field and subfield name
                        $subfield = explode( '/', $field );
                        if ( count( $subfield ) == 2 )
                        {
                            $content = $datamap[$subfield[0]]->content();
                            if ( $content->hasAttribute( $subfield[1] ) )
                            {
                                $data[] = $content->attribute( $subfield[1] );
                            }
                        }
                    }
                }
            }
        }
        echo implode( $this->delimiter, $data );
        if ( $this->newline )
        {
            echo "\n";
        }
        return true;
    }

    // Command line input parameters
    var $fields;
    var $delimiter;
    var $newline;
}