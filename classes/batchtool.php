<?php

class Batchtool
{
    var $filter_objects = array();
    var $operation_objects = array();
    var $object_list = array();

    var $total_count = 0;
    var $changed_count = 0;

    public function __construct()
    {
        $cli = eZCLI::instance();
        $standard_options = "[h|help][q|quiet][d;*|debug;*][c|colors][no-colors][logfiles][no-logfiles][s:|siteaccess:][l:|login:][p:|password:][v*|verbose*]";
        $script_options = "[f:|filter:*][o:|operation:*][and][or]";
        // Fetch all command line options
        $options = $cli->getOptions( $standard_options.$script_options, '' );

        $ini = eZINI::instance( 'site.ini' );
        $iniBT = eZINI::instance( 'batchtool.ini' );
        // User to run the specified commands with (set to admin pr.default)
        $user_id = $iniBT->variable( 'BatchToolSettings', 'UserID' );
        $user = eZUser::fetch( $user_id );
        eZUser::setCurrentlyLoggedInUser( $user, $user_id );

        // Define filters and operations to combine
        $filter_list = $iniBT->variable( 'BatchToolSettings', 'FilterList' );
        $this->filter_objects = $this->createCommandObjects( $options['filter'], $filter_list, false, isset( $options['help'] ) );
        $operations_list = $iniBT->variable( 'BatchToolSettings', 'OperationList' );
        $this->operation_objects = $this->createCommandObjects( $options['operation'], $operations_list, true, isset( $options['help'] ) );

        // If help requested, exit program here (before objects are fetched)
        if ( isset( $options['help'] ) )
        {
            return;
        }

        // Get objects requested by the specified filters
        $this->object_list = $this->getObjectsFromFilters( $options['and'] );
        unset( $this->filter_objects );
    }

    protected function createCommandObjects( $command_list, $enabled_list, $is_operation, $need_help )
    {
        $cli = eZCLI::instance();
        $command_objects = array();
        $base_class = ( $is_operation ) ? 'BatchToolOperation' : 'BatchToolFilter';

        // Create filter objects and make basic checks
        foreach ( $command_list as $command )
        {
            $parameter_array = explode( ';', $command );
            $command_name = $parameter_array[0];
            if ( !in_array( $command_name, $enabled_list ) )
            {
                throw new Exception( "Command '$filter_name' not enabled in batchtool.ini." );
            }
            $classname = $command_name . ( ( $is_operation ) ? "Operation" : "Filter" );
            if ( !class_exists( $classname ) )
            {
                throw new Exception( "Command class '$classname' not found." );
            }
            $command_object = new $classname();

            if( !( $command_object instanceof $base_class ) )
            {
                throw new Exception( "Class '$classname' does not extend '$base_class' as is required." );
            }

            $result = $command_object->setParameters( $this->getParameters( array_slice( $parameter_array, 1 ) ) );
            if ( $result !== true )
            {
                $cli->output( $command_object->getHelpText() );
                throw new Exception( "Filter '$command_name' have faulty parameters: '$result'." );
            }

            if ( $need_help )
            {
                $cli->output( $command_object->getHelpText() );
            }

            $command_objects[] = $command_object;
            unset( $command_object );
        }

        if ( empty( $command_objects ) )
        {
            throw new Exception( "No filters specified or no operations specified." );
        }
        return $command_objects;
    }

    protected function getParameters( $parm_array )
    {
        $result_array = array();
        foreach ( $parm_array as $parm )
        {
            list( $name, $value ) = explode( '=', $parm );
            if ( isset( $value ) )
                $result_array[$name] = $value;
            else
                $result_array[$name] = true;
        }
        return $result_array;
    }

    protected function getObjectsFromFilters( $is_logical_and )
    {
        $object_list = array();
        $tmp_list = array();
        $is_first_filter = true;

        // Do the filters object fetch, and merge from different filters
        foreach ( $this->filter_objects as $filter )
        {
            $tmp_list = $this->array_hashify( $filter->getObjectList(), $filter->getIDField() );
            if ( !$is_first_filter AND $is_logical_and )
            {
                $object_list = array_intersect_key( $object_list, $tmp_list );
            }
            else
            {
                foreach ( $tmp_list as $key => $object )
                {
                    if ( !isset( $object_list[$key] ) )
                    {
                        $object_list[$key] = $object;
                    }
                }
            }
            $is_first_filter = false;
        }
        if ( empty( $object_list ) )
        {
            return array();
        }
        // Validate objects against object class
        foreach ( $object_list as $object )
        {
            $object_class = get_class( $object );
            break;
        }
        $id_array = array();
        foreach ( $object_list as $object )
        {
            if ( get_class( $object ) != $object_class )
            {
                throw new Exception( "Illegal mixing of different filter objects." );
            }
        }

        // Make sure all operations accept the object classes that have been fetched
        foreach ( $this->operation_objects as $operation )
        {
            if ( strcasecmp( $object_class, $operation->getClassName() ) != 0 )
            {
                throw new Exception( "Operation not created for object types of class '$object_class'." );
            }
        }
        return $object_list;
    }

    private function array_hashify( $object_list, $id_name )
    {
        $result = array();
        foreach ( $object_list as $object )
        {
            $result[$object->attribute( $id_name )] = $object;
        }
        return $result;
    }

    public function runOperations()
    {
        $cli = eZCLI::instance();
        $number_of_objects = count( $this->object_list );
        $cli->output( "Running operations on $number_of_objects objects." );

        foreach ( $this->object_list as $object_id => $object )
        {
            // Run through all operations for this job
            foreach( $this->operation_objects as $operation )
            {
                $result = $operation->runOperation( $object );
                if ( !$result )
                {
                    break;
                }
            }
            $this->total_count++;
            if ( $result )
            {
                $this->changed_count++;
            }

            $this->clearCache();

            if ( empty( $options['quiet'] ) )
            {
                $mem = intval( memory_get_usage() / 1024 / 1024 );
                $cli->output( ( ( $result ) ? "Done operations" : "Operations failed" ) . " on object $object_id [{$this->total_count}/$number_of_objects] ($mem MB)" );
            }
        }
    }

    protected function clearCache()
    {
        if ( $this->total_count % 100 == 0 )
        {
            // Clear content object cache sometimes, to avoid memory constipation
            unset( $GLOBALS['eZContentObjectContentObjectCache'] );
            unset( $GLOBALS['eZContentObjectDataMapCache'] );
            unset( $GLOBALS['eZContentObjectVersionCache'] );
            return true;
        }
        return false;
    }

    public function summary()
    {
        $cli = eZCLI::instance();
        $cli->output( "{$this->total_count} objects processed, {$this->changed_count} objects successfull." );
    }
}

?>
