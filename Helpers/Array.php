<?php
/**
 * Created by PhpStorm.
 * User: phelpsbk
 * Date: 3/03/2015
 * Time: 2:25 PM
 */

## This function will take a db_query of the redcap_data table
## and provide a multi-dimensional array of records and their
## field_name => value lists.
function redcapDataGroupByRecord( $key, $dbQueryResults )
{
    $newArray = array();

    while( $result = db_fetch_assoc($dbQueryResults) )
    {
        ## See if the $key exists
        if( array_key_exists($key, $result) )
        {
            $newArray[$result[$key]][$result['field_name']] = $result['value'];
        }
    }

    return $newArray;
}