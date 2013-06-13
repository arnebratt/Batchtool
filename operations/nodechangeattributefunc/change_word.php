<?php

/**
 * Function used for replaceing one word with another in given attribute.
 * php runcronjobs.php -d batchtool --filter="fetchnodelist;parent=136;depth=2;locales=swe-SE;classname=recipe;limit=5" --operation="nodechangeattribute;attribute=name;userfunc=change_word;arguments=lax:laks"
 * @param string $word_from
 * @param string $word_to
 * @param string $old_value
 * @return string
 */
function change_word( $word_from, $word_to, $old_value )
{
    $old_value = preg_replace( '/\b' . ucfirst( $word_from ) . '\b/', ucfirst( $word_to ), $old_value );
    return preg_replace( '/\b' . $word_from . '\b/', $word_to, $old_value );
}