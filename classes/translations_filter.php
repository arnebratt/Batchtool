<?php

class eZTranslationsFilter
{
    function eZTranslationsFilter()
    {
    }

    function createSqlParts( $params )
    {
        $sqlJoins = '';
        $is_or_logic = isset( $params['or'] ) && ( $params['or'] === true );

        if ( isset( $params['locales'] ) )
        {
            $language_mask = 0;
            $locales = eZContentLanguage::fetchList();
            foreach ( $locales as $locale )
            {
                if ( in_array( $locale->attribute( 'locale' ), $params['locales'] ) )
                {
                    $language_mask |= $locale->attribute( 'id' );
                }
            }
            if ( $language_mask )
            {
                $sqlJoins = ( $is_or_logic ) ? " ezcontentobject.language_mask & $language_mask AND " :
                                               " ezcontentobject.language_mask & $language_mask = $language_mask AND ";
            }
        }

        return array( 'tables' => '', 'joins' => $sqlJoins, 'columns' =>'' );
    }
}

?>
