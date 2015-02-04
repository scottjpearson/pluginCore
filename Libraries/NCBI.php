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
        
        ## URL
        $url = 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?';
        
        ## Params to be sent to NCBI
        $term = implode('+OR+', $params['grants']);
        $retmax = (array_key_exists('retmax', $params)) ? $params['retmax'] : 100;
        $retstart = (array_key_exists('retstart', $params)) ? $params['retstart'] : 0;
        
        ## Attach params to URL
        $url .= 'term=' . $term; // TERMS
        $url .= '&retmax=' . $retmax; // RETURN MAXIMUM
        $url .= '&retstart=' . $retstart; // RETURN START
        
        ## Get xml back from request
        $this->resultsXml = file_get_contents( $url );
        
        ## Build simpleXML object
        $results = new SimpleXMLElement($this->resultsXml);
        $this->results = $results->IdList;
        
        ## Success
        return true;
    }
    
    public function eSummary( $params = array() )
    {
        if( !is_array($params['id']) || count($params['id']) < 1 ) return false;
        
        ## URL
        $url = 'http://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?';
        
        ## Params
        $db = (array_key_exists('db', $params)) ? $params['db'] : 'pubmed';
        $retmode = (array_key_exists('retmode', $params)) ? $params['retmode'] : 'xml';
        $id = implode(',', $params['id']);
        
        $url .= 'db=' . $db; // DATABASE
        $url .= '&id=' . $id; // IDS
        $url .= '&retmode=' . $retmode; // RETURN MODE
        
        $this->resultsXml = file_get_contents( $url );
        
        //print $this->resultsXml;
        
        ## Build simpleXML object
        $results = new SimpleXMLElement($this->resultsXml);
        
        //print '<pre>'.print_r($results, true).'</pre>';
        
        $this->results = $results;
        
        ## Success
        return true;
        
    }
    
    ## Mosely J, Van Driest SL, Larkin EK, Weeke PE, Witte JS...Roden DM.
    ## Mechanistic Phenotypes: An Aggregative Phenotyping Strategy to Identify Disease Mechanisms Using GWAS Data.
    ## PLoS ONE. 2013 Dec 12;8(12):e81503. PMID: 24349080
    
    public function buildCitationFromSummary( $docsum )
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
        foreach( $authors as $author )
        {
            $citation .= $author . ', ';
        }
        ## remove ,<space> at end
        $citation = substr($citation, 0, -2) . '.';
        $citation .= ' ';
        
        ## Title
        $term = str_replace(' ', '+', $title);
        $citation .= "<a href='http://www.ncbi.nlm.nih.gov/pubmed/?term={$term}' target='_blank'>{$title}</a>";
        $citation .= ' ';
        
        ## Full Journal Name
        $citation .= $fullJournalName;
        $citation .= ' ';
        
        ## SO
        $citation .= $so;
        $citation .= ' ';
        
        ## PMCID
        $citation .= $pmcid;
        
        print $citation . '<br/><br/>';
        //print $citation . '<br/>';
        
    }
}




