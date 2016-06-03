<?PHP


/**
 * Created by PhpStorm.
 * User: phelpsbk
 * Date: 3/03/2015
 * Time: 2:25 PM
 */

## This function will take the product of REDCap::getData('array') and
## collapse all events into a single record.  Data will be collapsed so that
## more recent events overwrite the data from previous ones.
function collapseREDCapDataEvents( $data = array() )
{
    $collapsedData = array();
    foreach( $data as $record => $events )
    {
        $collapsedData[$record] = array();
        foreach( $events as $eventId => $eventData )
        {
            foreach( $eventData as $field=>$value )
            {
                if( is_array($value) && count($value) > 0 )
                    $collapsedData[$record][$field] = $value;
                
                if( is_string($value) && trim($value) !== '' )
                    $collapsedData[$record][$field] = $value;
            }
        }
    }
    
    
    ## Returned array looks like;
    ## array( 'record_id' => array( 'field1' => 'value1', 'field2' => 'value2' ) )
    return $collapsedData;
}


