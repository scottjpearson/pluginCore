<?php
/**
 * User: phelpsbk
 * Date: 1/8/2015
 * Time: 10:48 AM
 *
 * Description:
 * Class to handle the connection to the RD web service.
 *
 * Dependencies:
 * RestCallRequest (Class)
 */

namespace Plugin;
$GLOBALS["Core"]->Libraries("RestCallRequest");
class Rdws {

    private $rdURL;

    function doRequest( $params = '', $useTestURL = false )
    {
        if( $useTestURL )
        {
            $this->rdURL = 'https://biovutest.vanderbilt.edu/rdws/redcap/data';
        } else {
            $this->rdURL = 'https://biovu2.vanderbilt.edu/rdws/redcap/data';
        }

        global $Core;
        $request = new $Core->RestCallRequest($this->rdURL, 'POST', $params);
        $request->execute();
        return json_decode( $request->getResponseBody() ) ;
    }
}