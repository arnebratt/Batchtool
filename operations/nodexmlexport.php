<?php

class nodexmlexportOperation extends BatchToolOperation
{
    function getHelpText()
    {
        return '
--operation="nodexmlexport;fields=<field identifier list>;head=<header tag>[;all_languages_fields=<field identifier list>][;format_output]"

Export node to an xml format
fields - select the fields to output, separated by colon
head - specify root tag of the xml output
all_languages_fields - specify which fields to output for all translations of the object
format_output - show formatted output (newline/whitespace not included if this is not specified)
';
    }

    function setParameters( $parm_array )
    {
        $supported_parameters = array( 'fields', 'head', 'all_languages', 'format_output' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
        {
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";
        }

        $this->fields = isset( $parm_array[ 'fields' ] ) ? explode( ':', $parm_array[ 'fields' ] ) : array();
        if ( empty( $this->fields ) )
        {
            return 'No fields given for xml export';
        }
        $this->headtag = isset( $parm_array[ 'head' ] ) ? $parm_array[ 'head' ] : '';
        if ( empty( $this->headtag ) )
        {
            return 'Missing name of root xml tag';
        }
        $this->all_languages_fields = isset( $parm_array[ 'all_languages_fields' ] ) ? explode( ':', $parm_array[ 'all_languages_fields' ] ) : array();
        $this->format_output = isset( $parm_array[ 'format_output' ] ) ? true : false;

        return true;
    }

    function runOperation( &$node )
    {
        $data = array();

        $object = $node->attribute( 'object' );
        $datamap = $object->datamap();
        $content_tag = $this->root->appendChild( $this->dom->createElement( $object->attribute( 'class_identifier' ) ) );

        foreach ( $this->fields as $field )
        {
            if ( !$this->addCustomDOMTags( $content_tag, $node, $object, $datamap, $field ) )
            {
                $this->addDOMTag( $content_tag, $field, $this->getValue( $node, $object, $datamap, $field ) );
            }
        }

        if ( count( $this->all_languages_fields ) )
        {
            $locale_parent_tag = $content_tag->appendChild( $this->dom->createElement( 'localizedData' ) );
            foreach ( $object->attribute( 'available_languages' ) as $locale )
            {
                $node = eZFunctionHandler::execute( 'content', 'node', array( 'node_id' => $node->attribute( 'node_id' ),
                                                                              'language_code' => $locale
                                                                       ) );
                $object = $node->attribute( 'object' );
                $datamap = $node->datamap();
                $object_data = array();
                $locale_tag = $locale_parent_tag->appendChild( $this->dom->createElement( 'localizedValues' ) );
                foreach ( $this->all_languages_fields as $field )
                {
                    $this->addDOMTag( $locale_tag, $field, $this->getValue( $node, $object, $datamap, $field ) );
                }
            }
        }

        return true;
    }

    function startOperations( $count )
    {
        $this->dom = new DOMDocument( '1.0', 'utf-8' );
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = $this->format_output;
        $this->dom->encoding = 'utf-8';
        $this->root = $this->dom->createElement($this->headtag);
        $this->dom->appendChild($this->root);
    }

    function finishOperations()
    {
        echo $this->dom->saveXML();
    }

    protected function getValue( $node, $object, $datamap, $field )
    {
        $result = '';
        if ( isset( $datamap[$field] ) )
        {
            $result = $this->getDatamapValue( $datamap[$field] );
        }
        elseif ( $object->hasAttribute( $field ) )
        {
            // Found field name as an attribute of the content object php class
            $result = $this->getContentValue( $object, $field );
        }
        elseif ( $node->hasAttribute( $field ) )
        {
            // Found field name as an attribute of the node php class
            $result = $this->getNodeValue( $node, $field );
        }
        else
        {
            // Support sub fields by using / as a delimiter between field and subfield name
            $subfield = explode( '/', $field, 2 );
            if ( count( $subfield ) == 2 )
            {
                $result = $this->getSubfieldValue( $node, $object, $datamap, $subfield[0], $subfield[1] );
            }
        }
        return $result;
    }

    protected function addCustomDOMTags( $parent_tag, $node, $object, $datamap, $field )
    {
        return false;
    }

    protected function addDOMTag( $parent_tag, $tag_name, $value )
    {
        $value_tag = $parent_tag->appendChild( $this->dom->createElement( str_replace( '/', '_', $tag_name ) ) );
        $value_object = ( strpos( $value, '<' ) === false ) ?
            $this->dom->createTextNode( $value ) :
            $this->dom->createCDATASection( $value );
        $value_tag->appendChild( $value_object );
    }

    protected function getDatamapValue( $attribute )
    {
        $content = $attribute->attribute( 'content' );
        switch ( $attribute->attribute( 'data_type_string' ) )
        {
            case 'ezxmltext':
                return $content->attribute( 'xml_data' );
            case 'ezobjectrelation':
            case 'ezobjectrelationlist':
            case 'ezkeyword':
            case 'ezselection':
                return $attribute->tostring();
            default:
                break;
        }
        return $content;
    }

    protected function getContentValue( $object, $field )
    {
        return $object->attribute( $field );
    }

    protected function getNodeValue( $node, $field )
    {
        return $node->attribute( $field );
    }

    protected function getSubfieldValue( $node, $object, $datamap, $field, $subfield )
    {
        $field_object = $this->getValue( $node, $object, $datamap, $field );
        $subfields = explode( '/', $subfield );
        if ( is_a( $field_object, 'eZContentObjectAttribute' ) )
        {
            $field_object = $field_object->content();
        }
        foreach ( $subfields as $sub )
        {
            if ( is_array( $field_object ) && isset( $field_object[$sub] ) )
            {
                $field_object = $field_object[$sub];
            }
            elseif ( method_exists( $field_object , 'hasAttribute' ) && $field_object->hasAttribute( $sub ) )
            {
                $field_object = $field_object->attribute( $sub );
            }
            else
            {
                return '';
            }
        }
        return is_object( $field_object ) ? '' :  $field_object;
    }

    var $dom;
    var $root;

    // Command line input parameters
    var $fields;
    var $headtag;
    var $all_languages_fields;
    var $format_output;
}
