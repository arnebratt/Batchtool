<?php

/**
 * Imports a value to an attribute adapting it to the proper type.
 * Not written by me, downloaded from ez.no! Extended it only!
 * @param data The value (string/int/float).
 * @param contentObjectAttribute The attribute to modify.
 */
function importAttribute( $data, &$contentObjectAttribute )
{
    $contentClassAttribute = $contentObjectAttribute->attribute( 'contentclass_attribute' );
    $dataTypeString = $contentClassAttribute->attribute( 'data_type_string' );
    
    ezDebug::writeDebug( "Converting " . $data . " to expected " . $dataTypeString );
    
    switch( $dataTypeString )
    {
    case 'ezfloat' :
    case 'ezprice' :
        $contentObjectAttribute->setAttribute( 'data_float', $data );
        $contentObjectAttribute->store();
        break;
    case 'ezboolean' :
    case 'ezdate' :
    case 'ezdatetime' :
    case 'ezinteger' :
    case 'ezsubtreesubscription' :
    case 'eztime' :
        $contentObjectAttribute->setAttribute( 'data_int', $data );
        $contentObjectAttribute->store();
        break;
    case 'ezobjectrelation' :
        // $data is contentobject_id to relate to
//            $oldData = $contentObjectAttribute->attribute( 'data_int' );
        $contentObjectAttribute->setAttribute( 'data_int', $data );
        $contentObjectAttribute->store();
        $object = $contentObjectAttribute->object();
        $contentObjectVersion = $contentObjectAttribute->attribute( 'version' );
        $contentClassAttributeID = $contentObjectAttribute->attribute( 'contentclassattribute_id' );
        // Problem with translations if removing old relations ?!
//            $object->removeContentObjectRelation( $oldData, $contentObjectVersion, $contentClassAttributeID, eZContentObject::RELATION_ATTRIBUTE );
        $object->addContentObjectRelation( $data, $contentObjectVersion, $contentClassAttributeID, RELATION_ATTRIBUTE );
        break;
    case 'ezurl' :
        $urlID = eZURL::registerURL( $data );
        $contentObjectAttribute->setAttribute( 'data_int', $urlID );
        // Fall through to set data_text
    case 'ezemail' :
    case 'ezisbn' :
    case 'ezstring' :
    case 'eztext' :
        $contentObjectAttribute->setAttribute( 'data_text', $data );
        $contentObjectAttribute->store();
        break;
    case 'ezxmltext' :
/*            $parser = new eZXMLInputParser();
        $document = $parser->process( $data );
        $data = eZXMLTextType::domString( $document );
        $contentObjectAttribute->fromString( $data );*/
        $contentObjectAttribute->setAttribute( 'data_text', $data );
        $contentObjectAttribute->store();
        break;
//    case 'ezimage':
//        $this->saveImage( $data, $contentObjectAttribute );
//        break;
//    case 'ezbinaryfile':
//        $this->saveFile( $data, $contentObjectAttribute );
//        break;
//    case 'ezenum':
        //removed enum - function can be found at ez.no 
//        break;
    case 'ezuser':
        // $data is assumed to be an associative array( login, password, email );
        $user = new eZUser( $contentObjectAttribute->attribute( 'contentobject_id' ) );
        if ( isset( $data['login'] ) )
            $user->setAttribute('login', $data['login'] );
        if ( isset( $data['email'] ) )
            $user->setAttribute('email', $data['email'] );

        if ( isset( $data['password'] ) )
        {
            $hashType = eZUser::hashType() . '';
            $newHash = $user->createHash( $data['login'], $data['password'], eZUser::site(), $hashType );
            $user->setAttribute( 'password_hash_type', $hashType );
            $user->setAttribute( 'password_hash', $newHash );
        }

        $user->store();
        break;
    default :
        die( 'Can not store ' . $data . ' as datatype: ' . $dataTypeString );
    }
}

function addNodeAssignment( $content_object, $parent_node_id, $set_as_main_node = false )
{
    $main_node_id = $content_object->attribute( 'main_node_id' );
    $insertedNode = $content_object->addLocation( $parent_node_id, true );
    // Now set it as published and fix main_node_id
    $insertedNode->setAttribute( 'contentobject_is_published', 1 );
    $parentContentObject = eZContentObject::fetchByNodeID( $parent_node_id );
    if ( $set_as_main_node )
    {
        $main_node_id = $insertedNode->attribute( 'node_id' );
        $insertedNode->updateMainNodeID( $main_node_id, $content_object->attribute( 'id' ), false, $parent_node_id );
    }
    $insertedNode->setAttribute( 'main_node_id', $main_node_id );
    $insertedNode->setAttribute( 'contentobject_version', $content_object->attribute( 'current_version' ) );
    // Make sure the path_identification_string is set correctly.
    $insertedNode->updateSubTreePath();
    $insertedNode->sync();
    return $insertedNode->attribute( 'node_id' );
}

function &createObject( $object_class_name, $parent_node_id, $import_field_list, $sectionID = 1, $priority = 0, $add_node_list = false )
{
    $class = eZContentClass::fetchByIdentifier( $object_class_name );
    $user =& eZUser::currentUser();
    $contentObject =& $class->instantiate( $user->attribute( 'contentobject_id' ), $sectionID );
    unset( $user );
    $contentObject->remoteID();
    $contentObjectID = $contentObject->attribute( 'id' );

    $nodeAssignment = eZNodeAssignment::create( array(
                                                'contentobject_id' => $contentObjectID,
                                                'contentobject_version' => 1,
                                                'parent_node' => $parent_node_id,
                                                'is_main' => 1
                                                )
                                            );
    $nodeAssignment->setAttribute( 'parent_remote_id', "VHB_" . $parent_node_id );
    $nodeAssignment->store();
    if ( $priority )
    {
        $node = $nodeAssignment->attribute( 'node' );
        $node->setAttribute( 'priority', $priority );
        $node->store();
    }

    $version =& $contentObject->version( 1 );
    $version->setAttribute( 'modified', time() );
    $version->setAttribute( 'status', EZ_VERSION_STATUS_DRAFT );
    $version->store();

    $contentObjectID = $contentObject->attribute( 'id' );
    $contentObjectAttributes =& $version->contentObjectAttributes();

    foreach ($contentObjectAttributes as $attribute)
    {
        if ( isset( $import_field_list[$attribute->attribute("contentclass_attribute_identifier")] ) )
        {
            $data = $import_field_list[$attribute->attribute("contentclass_attribute_identifier")];
            importAttribute( $data, $attribute );
        }
    }

    if ( is_array( $add_node_list ) )
    {
        foreach ( $add_node_list as $parent_node_id )
        {
            addNodeAssignment( $contentObject, $parent_node_id );
        }
    }

    $operationResult = eZOperationHandler::execute( 'content', 'publish', 
                                    array( 'object_id' => $contentObjectID, 'version' => 1 ) );
    eZContentCacheManager::clearObjectViewCache( $contentObjectID, 1 );

    return $contentObject;
}


function copyObject( $object, $allVersions, $newParentNodeID )
{
    if ( !$newParentNodeID )
    {
        eZDebug::writeError( "Missing new parent node id for object id ".$object->attribute( 'id' ),
                             'batchtool/copy' );
        return false;
    }

    // check if we can create node under the specified parent node
    if( ( $newParentNode = eZContentObjectTreeNode::fetch( $newParentNodeID ) ) === null )
    {
        eZDebug::writeError( "Missing new parent node for object id ".$object->attribute( 'id' ),
                             'batchtool/copy' );
        return false;
    }

    $classID = $object->attribute('contentclass_id');

    if ( !$newParentNode->checkAccess( 'create', $classID ) )
    {
        $objectID = $object->attribute( 'id' );
        eZDebug::writeError( "Cannot copy object $objectID to node $newParentNodeID, " .
                               "the current user does not have create permission for class ID $classID",
                             'batchtool/copy' );
        return false;
    }

    $db = eZDB::instance();
    $db->begin();
    $newObject = $object->copy( $allVersions );
    // We should reset section that will be updated in updateSectionID().
    // If sectionID is 0 then the object has been newly created
    $newObject->setAttribute( 'section_id', 0 );
    $newObject->store();

    $curVersion        = $newObject->attribute( 'current_version' );
    $curVersionObject  = $newObject->attribute( 'current' );
    $newObjAssignments = $curVersionObject->attribute( 'node_assignments' );
    unset( $curVersionObject );

    // remove old node assignments
    foreach( $newObjAssignments as $assignment )
    {
        $assignment->purge();
    }

    // and create a new one
    $nodeAssignment = eZNodeAssignment::create( array(
                                                     'contentobject_id' => $newObject->attribute( 'id' ),
                                                     'contentobject_version' => $curVersion,
                                                     'parent_node' => $newParentNodeID,
                                                     'is_main' => 1
                                                     ) );
    $nodeAssignment->store();

    // publish the newly created object
    //include_once( 'lib/ezutils/classes/ezoperationhandler.php' );
    eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $newObject->attribute( 'id' ),
                                                              'version'   => $curVersion ) );
    // Update "is_invisible" attribute for the newly created node.
    $newNode = $newObject->attribute( 'main_node' );
    eZContentObjectTreeNode::updateNodeVisibility( $newNode, $newParentNode );

    $db->commit();
    return true;
}

function moveNode( $node, $new_parent_node_id )
{
    $node_id = $node->attribute( 'node_id' );
    $class_id = $node->attribute( 'class_identifier' );
    if ( !$node->canMoveFrom() )
    {
        eZDebug::writeError( "Missing permissions to move node with id $node_id",
                             'batchtool/move' );
        return false;
    }
    $new_parent_node = eZContentObjectTreeNode::fetch( $new_parent_node_id );
    if ( !$new_parent_node )
    {
        eZDebug::writeError( "Can't fetch new parent $new_parent_node_id for node with id $node_id",
                             'batchtool/move' );
        return false;
    }
    if ( !$new_parent_node->canMoveTo( $class_id ) )
    {
        eZDebug::writeError( "Missing permissions to move node with id $node_id to $new_parent_node_id",
                             'batchtool/move' );
        return false;
    }
    if ( in_array( $node->attribute( 'node_id' ), $new_parent_node->pathArray() ) )
    {
        eZDebug::writeError( "Can't move node with id $node_id to itself",
                             'batchtool/move' );
        return false;
    }

    return eZContentObjectTreeNodeOperations::move( $node_id, $new_parent_node_id );
}