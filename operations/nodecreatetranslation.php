<?php

class nodecreatetranslationOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="nodecreatetranslation;original_locale=<locale identifier>;new_locale=<locale identifier>"

original_locale - locale which translation is to be used/copied
new_locale - new locale for translation, that, if not existing, will be created for object as copy of original
locale identifier - Valid locale identifier like eng-GB

logic:
step 1. if alternative locale translation is missing it is created.
';
    }

    function setParameters( $parm_array )
    {
        if ( !isset( $parm_array[ 'original_locale' ] ) )
            return 'Missing locale identifier of original';
        if ( !isset( $parm_array[ 'new_locale' ] ) )
            return 'Missing new locale identifier';
        $this->orgLocale = $parm_array[ 'original_locale' ];
        $language = eZContentLanguage::fetchByLocale( $this->orgLocale );
        if ( $language )
            $this->orgLangID = $language->attribute('id');
        else
            return 'Missing original locale to be copied in database';

        $this->newLocale = $parm_array[ 'new_locale' ];
        $language = eZContentLanguage::fetchByLocale( $this->newLocale );
        if ( $language )
            $this->newLangID = $language->attribute('id'); 
        else
            return 'Missing alternative locale in database';
        
        return true;       
    }

    // Commence copying translation
    function runOperation( &$node )
    {
        $object = $node->attribute('object');
        $object_id = $object->attribute( 'id' );
        $objectLocales = $object->attribute('available_languages');
        
        // If the new locale does not exist for object, create it
        if ( !in_array( $this->newLocale, $objectLocales) )
        {
            // Create a new version of the original in another locale.
            $cli = eZCLI::instance();
            $cli->output( "Copying the single translation in {$this->orgLocale} to {$this->newLocale}" );
            $newVersion = $object->createNewVersionIn( $this->newLocale, $this->orgLocale, false, true, eZContentObjectVersion::STATUS_DRAFT );
            $publishResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $object_id, 
                                                                                       'version' => $newVersion->attribute('version') ) );
            eZContentObject::clearCache();
            $object = eZContentObject::fetch( $object_id );
        }

        return true;
    }
    

    var $orgLocale;
    var $orgLangID;
    
    var $newLocale;
    var $newLangID;
}

?>
