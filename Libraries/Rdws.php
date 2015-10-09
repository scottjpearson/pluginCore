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
$GLOBALS["Core"]->Libraries("RestCallRequest",false);
class Rdws {

    private $rdURL;

    function doRequest( $params = '', $useTestURL = false, $application = '', $token = '' )
    {
        if( $useTestURL )
        {
            $this->rdURL = 'https://biovutest.vanderbilt.edu/rdws/redcap/data/?app='.$application.'&token='.$token;
        } else {
            $this->rdURL = 'https://biovu2.vanderbilt.edu/rdws/redcap/data/?app='.$application.'&token='.$token;
        }
        global $Core;
		/** @var \Plugin\RestCallRequest $request */
        $request = new $Core->RestCallRequest($this->rdURL, 'POST', $params, true);
        $request->execute();

		$response = $request->getResponseInfo();

		if($response["http_code"] == 500) {
			return array();
		}

        return json_decode($request->getResponseBody());
    }
}