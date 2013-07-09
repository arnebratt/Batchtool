<?php

class Batchtool
{
    var $filter_objects = array();
    var $operation_objects = array();
    var $object_list = array();

    var $id_field_name = '';

    var $total_count = 0;
    var $changed_count = 0;

    public function __construct()
    {
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
        $this->filter_objects = $this->createCommandObjects( $options['filter'], $filter_list, 'BatchToolFilter' );
        $operations_list = $iniBT->variable( 'BatchToolSettings', 'OperationList' );
        $this->operation_objects = $this->createCommandObjects( $options['operation'], $operations_list, 'BatchToolOperation' );

        // If help requested, exit program here
        if ( isset( $options['help'] ) )
        {
            exit 0;
        }

        // Get objects requested by the specified filters
        $this->$object_list = $this->getObjectsFromFilters();
        unset( $this->filter_objects );
    }

    protected function createCommandObjects( $command_list, $enabled_list, $base_class )
    {
        $command_objects = array();
        // Create filter objects and make basic checks
        foreach ( $command_list as $command )
        {
            $parameter_array = explode( ';', $command );
            $command_name = $parameter_array[0];
            if ( !in_array( $command_name, $enabled_list ) )
            {
                echo "Error: Command '$filter_name' not enabled in batchtool.ini.\n";
                exit( 1 );
            }
            $classname = "{$command_name}Filter";
            if ( !class_exists( $classname ) )
            {
                echo "Error: Command class '$classname' not found.\n";
                exit( 2 );
            }
            $command_object = new $classname();

            if( !( $command_object instanceof $base_class ) )
            {
                echo "Class '$classname' does not extend '$base_class' as is required.\n";
                exit( 3 );
            }

            $result = $command_object->setParameters( $this->getParameters( $parameter_array ) );
            if ( $result !== true )
            {
                echo "Error: Filter '$command_name' have faulty parameters: '$result'.\n";
                echo $command_object->getHelpText();
                exit( 4 );
            }

            if ( isset( $options['help'] ) )
            {
                echo $command_object->getHelpText();
            }

            $command_objects[] = $command_object;
            unset( $command_object );
        }

        if ( empty( $command_objects ) )
        {
            echo "Error: No filters specified or no operations specified.\n";
            exit( 5 );
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

    protected function getObjectsFromFilters()
    {
        $object_list = array();
        $is_first_filter = true;
        $this->id_field_name = $filter_objects[0]->getIDField();

        // Do the filters object fetch
        foreach ( $this->filter_objects as $filter )
        {
            $object_list = array_merge( $object_list, $filter->getObjectList() );
        }
        if ( empty( $object_list ) )
        {
            return array();
        }
        // Validate and summarize objects
        $object_class = get_class( $object_list[0] );
        $id_array = array();
        foreach ( $object_list as $key => $object )
        {
            if ( get_class( $object ) != $object_class )
            {
                echo "Error: Illegal mixing of different filter objects.\n";
                exit( 6 );
            }
            if ( $options['and'] === true )
            {
                $id = $object->attribute( $this->id_field_name );
                if ( isset( $id_array[$id] ) )
                {
                    // Remove duplicate object
                    unset( $object_list[$key] );
                }
                $id_array[$id] = true;
            }
        }

        // Make sure all operations accept the object classes that have been fetched
        foreach ( $this->$operation_objects as $operation )
        {
            $operation_class = $operation->getClassName();
            if ( strcasecmp( $object_class, $operation_class ) != 0 )
            {
                echo "Error: Operation not created for these object types.\n";
                exit( 7 );
            }
        }
        return $object_list;
    }

    public function runOperations()
    {
        $number_of_objects = count( $this->object_list );
        echo "Running operations on $number_of_objects objects.\n";
        foreach( $this->object_list as $object )
        {
            $object_id = $object->attribute( $this->id_field_name );

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
                echo ( $result ) ? "Done operations on object $object_id [$changed_count/$number_of_objects] ($mem MB)\n" : "Operations failed on object $object_id\n";
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
        echo "{$this->total_count} objects processed, {$this->changed_count} objects successfull.\n";
    }
}

?>
