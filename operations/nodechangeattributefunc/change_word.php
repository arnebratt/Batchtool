<?php

/**
 * Function used for replaceing one word with another in given attribute.
 * Function requires three arguments to be passed as command line parameters (word_from, word_to and exclude_words). In case when there are no excluded words, it is
 * required to put a colon after word_to argument.
 * php runcronjobs.php batchtool -ssweadmin --filter="fetchnodelist;parent=136;depth=2;locales=swe-SE;classname=recipe;limit=5" --operation="nodechangeattribute;attribute=name;userfunc=change_word;arguments=lax:laks:"
 * @param string $word_from
 * @param string $word_to
 * @param string $exclude_words - a list of words separated by commas
 * @param string $old_value - this parameter is added automatically as an user func default
 * @return string
 */
function change_word( $word_from, $word_to, $exclude_words, $old_value )
{
    if ( !empty( $exclude_words ) )
    {
        // generate the array of excluded words
        $exclude = array();
        foreach ( explode( ',', $exclude_words ) as $word )
        {
            if ( empty( $word ) )
            {
                continue;
            }

            $exclude[] = $word;
            $exclude[] = ucfirst( $word );
        }

        // checked whether old value contains any of exluded words
        if ( !empty( $exclude ) && preg_match( '/' . implode( '|', $exclude ) . '/', $old_value ) )
        {
            return $old_value;
        }
    }

    $old_value = preg_replace( '/' . ucfirst( $word_from ) . '/', ucfirst( $word_to ), $old_value );
    return preg_replace( '/' . $word_from . '/', $word_to, $old_value );
}