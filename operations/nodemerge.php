<?php

class nodemergeOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodemerge;target=<node id>;move_to_depth=<depth>"

Warning: For experimental use only (url alias update code have shown some issues)!

Will remove specified node, and set up a redirect to the new specified node (which need to exist)
target - Node ID of the target node to move selected nodes under
move_to_depth - Depth of content tree to move the node up to. Must always be above current depth!
';
    }

    function setParameters( $parm_array )
    {
        // Make sure no unsupported parameters are specified
        $supported_parameters = array( 'target', 'move_to_depth' );
        $parm_keys = array_keys( $parm_array );
        $unsupported_list = array_diff( $parm_keys, $supported_parameters );
        if ( isset( $unsupported_list[0] ) )
            return "Unsupported parameter '{$unsupported_list[0]}' in operation";

        if ( isset( $parm_array[ 'target' ] ) )
        {
            $this->target_id = intval( $parm_array[ 'target' ] );
        }
        if ( isset( $parm_array[ 'move_to_depth' ] ) )
        {
            $this->move_to_depth = intval( $parm_array[ 'move_to_depth' ] );
        }
        if ( $this->target_id == 0 AND $this->move_to_depth == 0 )
            return 'Missing target node id and target depth to move to';
        if ( $this->target_id > 0 AND $this->move_to_depth > 0 )
            return 'Target node id and target depth can not be used simultaniously';
        return true;
    }

    // Delete the given node and, if it's the last node, it's object also
    function runOperation( &$node )
    {
        $target_parent_node_id = $this->target_id;

        if ( empty( $target_parent_node_id ) )
        {
            if ( $this->move_to_depth >= $node->attribute( 'depth' ) )
            {
                return false;
            }
            // Find the correct target node for the specified depth
            $path_array = $node->attribute( 'path_array' );
            $target_parent_node_id = $path_array[$this->move_to_depth - 1];
        }

        $assigned_nodes = $node->attribute( 'object' )->attribute( 'assigned_nodes' );
        // Find the target node
        foreach ( $assigned_nodes as $target_node )
        {
            if ( $target_node->attribute( 'parent_node_id' ) == $target_parent_node_id )
            {
                $target_node_id = $target_node->attribute( 'node_id' );
                // Make sure target node is not us
                if ( $node->attribute( 'node_id' ) == $target_node_id )
                {
                    return false;
                }
                $urlalias_list = eZURLAliasML::fetchByAction( 'eznode', $node->attribute( 'node_id' ) );
                $target_node_urlalias_list = eZURLAliasML::fetchByAction( 'eznode', $target_node_id );
                // Sanity check, this should never happen
                if ( !isset( $target_node_urlalias_list[0] ) )
                {
                    eZDebug::writeError( 'Found no url alias records for node with id ' . $target_node_id,
                                         'batchtool/nodemerge' );
                    return false;
                }
                $target_node_urlalias_id = $target_node_urlalias_list[0]->attribute( 'id' );
                $target_parent_urlalias_id = $target_node_urlalias_list[0]->attribute( 'parent' );

                $db = eZDB::instance();
                $db->begin();
                // Make sure any children nodes are moved to the new node
                foreach ( $node->attribute( 'children' ) as $child )
                {
                    moveNode( $child, $target_node_id );
                }

                // Make sure any bookmarks are moved to the new node
                $bookmark_list = eZPersistentObject::fetchObjectList( eZContentBrowseBookmark::definition(),
                                                null, // fields
                                                array( 'node_id' => $node->attribute( 'node_id' ) ) // conditions
                                                );
                foreach ( $bookmark_list as $bookmark )
                {
                    $bookmark->setAttribute( 'node_id', $target_node_id );
                    $bookmark->store();
                }

                // Remove the node in question
                $node->removeNodeFromTree( true );

                // Set up url alias redirects to the new node
                foreach ( $urlalias_list as $url_alias )
                {
                    $url_alias->setAttribute( 'action', 'eznode:' . $target_node_id );
                    $url_alias->setAttribute( 'action_type', 'eznode' );
                    $url_alias->setAttribute( 'link', $target_node_urlalias_id );
                    $url_alias->setAttribute( 'is_original', 0 );
                    $url_alias->store();
                }
                $db->commit();
                return true;
            }
        }

        return false;
    }

    var $target_id;
    var $move_to_depth;
}