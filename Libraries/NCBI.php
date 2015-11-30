<?PHP

class NCBI
{
    private $dbname = 'pubmed';
    private $results;
    private $resultsXml;
    
    public function getResults()
    {
        return $this->results;
    }
    
    ## performs a call to the ESearch webservice
    public function ESearch( $params = array() )
    {
        ## If grants is not an array or is an empty array return FALSE
        if( !is_array($params['grants']) || count($params['grants']) < 1 ) return false;

        try
        {
            ## URL
            $url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi';
            $fields = '';
            $fieldCount = 3;

            ## Params to be sent to NCBI
            $term = implode('+OR+', $params['grants']);
            $retmax = (array_key_exists('retmax', $params)) ? $params['retmax'] : 100;
            $retstart = (array_key_exists('retstart', $params)) ? $params['retstart'] : 0;

            ## Attach params to URL
            $fields .= 'term=' . $term; // TERMS
            $fields .= '&retmax=' . $retmax; // RETURN MAXIMUM
            $fields .= '&retstart=' . $retstart; // RETURN START

            ## Get xml back from request
            $this->resultsXml = $this->sslCurl( $url, $fields, $fieldCount );

            ## Build simpleXML object
            $results = new SimpleXMLElement($this->resultsXml);
            $this->results = $results->IdList;

            ## Success
            return true;
        }
        catch(Exception $e)
        {
            $this->results = array();
            return false;
        }
    }
    
    public function eSummary( $params = array() )
    {
        if( !is_array($params['id']) || count($params['id']) < 1 ) return false;

        try
        {
            ## URL
            $url = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?';
            $fields = '';
            $fieldCount = 3;

            ## Params to be sent to NCBI
            $db = (array_key_exists('db', $params)) ? $params['db'] : 'pubmed';
            $retmode = (array_key_exists('retmode', $params)) ? $params['retmode'] : 'xml';
            $id = implode(',', $params['id']);

            ## Attach params to URL
            $fields .= 'db=' . $db; // DATABASE
            $fields .= '&id=' . $id; // IDS
            $fields .= '&retmode=' . $retmode; // RETURN MODE

            ## Get xml back from request
            $this->resultsXml = $this->sslCurl( $url, $fields, $fieldCount );

            ## Build simpleXML object
            $results = new SimpleXMLElement($this->resultsXml);
            $this->results = $results;

            ## Success
            return true;
        }
        catch (Exception $e)
        {
            $this->results = array();
            return false;
        }
    }
    
    ## Mosely J, Van Driest SL, Larkin EK, Weeke PE, Witte JS...Roden DM.
    ## Mechanistic Phenotypes: An Aggregative Phenotyping Strategy to Identify Disease Mechanisms Using GWAS Data.
    ## PLoS ONE. 2013 Dec 12;8(12):e81503. PMID: 24349080
    
    public function buildCitationFromSummary( $docsum, $return = false )
    {
        $authors = array();
        $title = '';
        $fullJournalName = '';
        $so = '';
        $pmcid = '';
        $citation = '';
        
        foreach( $docsum->Item as $item )
        {
            ## Author List
            if( $item['Name'] == 'AuthorList' )
            {
               foreach( $item->Item as $author )
               {
                    array_push($authors, $author);
               }
            }
            
            ## Title
            if( $item['Name'] == 'Title' )
            {
               $title = $item;
            }
            
            ## Full Journal Name
            if( $item['Name'] == 'FullJournalName' )
            {
               $fullJournalName = $item . '.';
            }
            
            ## SO
            if( $item['Name'] == 'SO' )
            {
               $so = $item . '.';
            }
            
            ## PMCID
            if( $item['Name'] == 'ArticleIds' )
            {
                foreach( $item->Item as $article )
                {
                    if( $article['Name'] == 'pmcid' )
                    {
                        $pmcid = $article;
                    }
                }
            }
        }
        
        ## Build the citation string
        
        ## Authors
        if( count($authors) >= 5 )
        {
            $citation .= array_shift($authors) . ', ';
            $citation .= array_shift($authors) . ', ';
            $citation .= array_shift($authors) . ', ';
            $citation .= array_shift($authors) . ', ';
            $citation .= array_pop($authors) . ', ';

            // If we have more than 5 authors we need to append
            // a et al. at the end of the authors list.
            if( count($authors) > 5 )
            {
                $citation .= '<span class="tooltip" title="'.join(', ', $authors).'">et al</span>  ';
            }

        } else {
            foreach( $authors as $author )
            {
                $citation .= $author . ', ';
            }
        }

        ## remove ,<space> at end
        $citation = substr($citation, 0, -2) . '.';
        $citation .= ' ';
        
        ## Title
        $term = str_replace(' ', '+', $title);
        $citation .= "<a href='https://www.ncbi.nlm.nih.gov/pubmed/?term={$term}' target='_blank'>{$title}</a>";
        $citation .= ' ';
        
        ## Full Journal Name
        $citation .= $fullJournalName;
        $citation .= ' ';
        
        ## SO
        $citation .= $so;
        $citation .= ' ';
        
        ## PMCID
        $citation .= $pmcid;

        if( $return )
        {
            return $citation;
        }
        else
        {
            print $citation . '<br/><br/>';
        }
    }

    public function sslCurl( $url, $fields, $fieldCount )
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, $fieldCount);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}