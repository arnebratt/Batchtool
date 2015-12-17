<?php

class nodechangeattributeOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodechangeattribute;attribute=<attribute>[;locale=<locale code>][;phpfunc=<function>][;userfunc=<function>][;arguments=<arguments>]"

attribute - ID name of the attribute to do changes on
locale - Locale code for the translation to update
phpfunc - Name of the PHP function to use for changing the attribute
userfunc - Name of the user function to use for changing the attribute
(either phpfunc or userfunc must be included, but not both)
arguments - Arguments sent to the selected function, separated by a colon.
            Arguments should match the parameters that the function requires.
            Any attribute identifier encased in {} in any argument will be 
            replaced by the actual string value from the specified attribute.
';
    }

    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'attribute', 'locale', 'phpfunc', 'userfunc', 'arguments' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";

        // Check for mandatory parameters
        $iniBT = eZINI::instance( 'batchtool.ini' );
        $this->attribute = $parm_array[ 'attribute' ];
        if ( empty( $this->attribute ) )
            return 'No node attribute specified';

        $this->locale = isset( $parm_array['locale'] ) ? $parm_array['locale'] : false;

        $this->arguments = isset( $parm_array['arguments'] ) ? explode( ':', $parm_array['arguments'] ) : array();

        // Find the function to call on each content object attribute
        if ( empty( $parm_array[ 'phpfunc' ] ) AND empty( $parm_array[ 'userfunc' ] ) )
            return 'No function specified';
        if ( !empty( $parm_array[ 'phpfunc' ] ) AND !empty( $parm_array[ 'userfunc' ] ) )
            return 'More than one function specified';
        if ( empty( $parm_array[ 'phpfunc' ] ) )
        {
            $function_list = $iniBT->variable( 'NodeChangeAttribute', 'UserFunctions' );
            $this->change_function = $parm_array[ 'userfunc' ];
            $this->function_type = 'userfunc';
        }
        else
        {
            $function_list = $iniBT->variable( 'NodeChangeAttribute', 'PHPFunctions' );
            $this->change_function = $parm_array[ 'phpfunc' ];
            $this->function_type = 'phpfunc';
        }
        if ( !in_array( $this->change_function, $function_list ) )
            return "Function '{$this->change_function}' not registered in batchtool.ini.\n";
        if ( empty( $parm_array[ 'phpfunc' ] ) )
        {
            require_once( "extension/batchtool/operations/nodechangeattributefunc/{$this->change_function}.php" );
        }
        if ( !function_exists( $this->change_function ) )
            return "Function '{$this->change_function}' not found. ";

        return true;
    }

    function runOperation( &$node )
    {
        $content_object = $node->object();
        $datamap = $content_object->fetchDataMap( false, $this->locale );
        $object_attribute = $datamap[$this->attribute];
        if ( !isset( $object_attribute ) )
        {
            echo "Attribute '{$this->attribute}' not found. ";
            return false;
        }
        $old_value = $object_attribute->toString();

        // Set new value to specified attributes
        $arg_array = $this->arguments;
        foreach( $datamap as $key => $object_attribute )
        {
            $value = $object_attribute->toString();
            $arg_array = str_replace( '{'.$key.'}', $value, $arg_array );
        }

        // for userfunc only
        if ( $this->function_type === 'userfunc' )
        {
            // add the old value as a last parameter
            $arg_array['old_value'] = $old_value;
        }

        $new_value = call_user_func_array( $this->change_function, $arg_array );

        if ( $new_value == $old_value )
        {
            // Nothing to change
            return true;
        }

        $db = eZDB::instance();
        $db->begin();
        // Create new version
        $content_object_id = $content_object->attribute( 'id' );
        $version = $content_object->createNewVersion( false, true, $this->locale );
        $version->setAttribute( 'modified', time() );
        $version->setAttribute( 'status', 'EZ_VERSION_STATUS_DRAFT' );
        $version->store();
        $datamap = $version->dataMap();

        $object_attribute = $datamap[$this->attribute];
        $object_attribute->fromString( $new_value );
        $object_attribute->store();

        // Publish new version
        $operation_result = eZOperationHandler::execute( 'content', 'publish', 
                                array( 'object_id' => $content_object_id, 'version' => $version->attribute( 'version' ) ) );
        $db->commit();
        eZContentCacheManager::clearObjectViewCache( $content_object_id );

        return $operation_result['status'] == 1;
    }

    // Attribute of the node that should be changed
    var $attribute;
    // Locale to update
    var $locale;
    // PHP or user function to use for changing the attribute
    var $change_function;
    // Arguments for the function (in an array)
    var $arguments;
    // type of used function (php or user)
    private $function_type;
}