<?php

class noderemovetranslationOperation extends BatchToolOperation
{
    // Return help text for this filter
    function getHelpText()
    {
        return '
--operation="noderemovetranslation;remove_locale=<locale identifier>;alternative_locale=<locale identifier>"

remove_locale - locale which translation is to be removed
alternative_locale - alternative locale for translation, that, if not existing, will be created for object as copy of one that is removed
locale identifier - Valid locale identifier like eng-GB

logic:
step 1. if alternative locale translation is missing it is created.
step 2. if objects main translation is in locale to be removed, alternative locale is set for main translation
step 3. translation in remove_locale is removed
';
    }

    function setParameters( $parm_array )
    {
        if ( !isset( $parm_array[ 'remove_locale' ] ) )
            return 'Missing locale identifier to be removed';
        if ( !isset( $parm_array[ 'alternative_locale' ] ) )
            return 'Missing alternative locale identifier';
        $this->remLocale = $parm_array[ 'remove_locale' ];
        $language = eZContentLanguage::fetchByLocale( $this->remLocale );
        if ( $language )
            $this->remLangID = $language->attribute('id');
        else
            return 'Missing locale to be removed in database';

        $this->altLocale = $parm_array[ 'alternative_locale' ];
        $language = eZContentLanguage::fetchByLocale( $this->altLocale );
        if ( $language )
            $this->altLangID = $language->attribute('id'); 
        else
            return 'Missing alternative locale in database';
        
        return true;
    }

    // Commence removing translation
    function runOperation( &$node )
    {
        $object = $node->attribute('object');
        $object_id = $object->attribute( 'id' );
        $objectLocales = $object->attribute('available_languages');
        
        // If the alternative locale does not exist for object, create it
        if ( !in_array( $this->altLocale, $objectLocales) )
        {
            // The only translation is in locate to be removed - create a version in another locale first.
            echo "Copying the single translation in " . $this->remLocale . " to " . $this->altLocale . " so former could be removed.\n";
            $newVersion = $object->createNewVersionIn( $this->altLocale, $this->remLocale, false, true, eZContentObjectVersion::STATUS_DRAFT );
            $publishResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $object_id, 
                                                                                       'version' => $newVersion->attribute('version') ) );
            eZContentObject::clearCache();
            $object = eZContentObject::fetch( $object_id );
        }

        // Change objects main language to alternative language, if its current main language is to be removed.
        if ( $object->attribute('initial_language_code') == $this->remLocale )
        {
            eZContentObject::clearCache();
            $object = eZContentObject::fetch( $object_id );
            
            echo "Switching initial language to $this->altLocale so that " . $this->remLocale . " could be removed.\n";
            $updateResult = eZContentOperationCollection::updateInitialLanguage( $object_id, $this->altLangID );
            $object->store();
            
            eZContentObject::clearCache();
            $object = eZContentObject::fetch( $object_id );
        }
        
        // Now it should be safe to remove translation.        
        return $object->removeTranslation( $this->remLangID );
    }
    

    var $remLocale;
    var $remLangID;
    
    var $altLocale;
    var $altLangID;
}