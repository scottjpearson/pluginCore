<?php

function jQueryAutoPopulate( $json = '' )
{
    print '<script>';

    print 'var jQAP = ' . $json . ';';

    print 'console.log(jQAP);';

    print '</script>';
}