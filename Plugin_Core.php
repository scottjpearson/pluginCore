<?PHP

class Plugin_Core
{

    public function Libraries( $libraries = array() )
    {
        $success = true;
        $message = '';
        
        ## If $libraries is an array we need to step through
        ## it loading each given library.
        if( is_array($libraries) )
        {
            foreach( $libraries as $library )
            {
                if(!self::loadLibrary( $library ))
                {
                    $success = false;
                    $message = $library;
                }
            }
        }
        ## Else if it's a string we need to just load the one.
        else if( is_string( $libraries ) )
        {
            if( !self::loadLibrary( $libraries ) )
            {
                $success = false;
                $message = $libraries;
            }
        }
        else
        {
            $success = false;
            $message = 'No library defined.';
        }
        
        if( !$success ) die( "Error loading Core::Libraries ({$message})" );
    }
    
    private function loadLibrary( $library = '' )
    {
        $return = false;

        ## We want to load the Core_ library first since the local one
        ## might extend it.
        if( file_exists( __DIR__ . '/Libraries/Core_' . $library . '.php' ) )
        {
            require_once(__DIR__ . '/Libraries/Core_' . $library . '.php' );
            $library = 'Core_' . $library;

			self::$library = new $library();
            //$this->$library = new $library_file();
            $return = true;
        }

        ## See if the library exists locally in Plugin/Library
        if( file_exists( self::currentDirectory() . '/Libraries/' . $library . '.php' ) )
        {
            require_once( self::currentDirectory() . '/Libraries/' . $library . '.php' );
			self::$library = new $library();
            //$this->$library = new $library();
            $return = true;
        }

        return $return;
    }
    
    public function Helpers( $helpers = array() )
    {
        $success = true;
        $message = '';

        ## If $libraries is an array we need to step through
        ## it loading each given library.
        if( is_array($helpers) )
        {
            foreach( $helpers as $helper )
            {
                if(!Core_Core::loadHelper( $helper ))
                {
                    $success = false;
                    $message = $helper;
                }
            }
        }
        ## Else if it's a string we need to just load the one.
        else if( is_string( $helpers ) )
        {
            if( !self::loadHelper( $helpers ) )
            {
                $success = false;
                $message = $helpers;
            }
        }
        else
        {
            $success = false;
            $message = 'No helper defined.';
        }

        if( !$success ) die( "Error loading Core::Helpers ({$message})" );
    }

    private function loadHelper( $helper )
    {
        ## Check if Helper file exists locally first since that should
        ## override the Core/Helpers one.
        if( file_exists( self::currentDirectory() . '/Helpers/' . $helper . '.php' ) )
        {
            require_once( self::currentDirectory() . '/Helpers/' . $helper . '.php' );
            return true;
        }
        ## If the Helper file did not exist in Core/Helpers then we need to look
        ## in Plugin/Helpers for it.
        elseif( file_exists( __DIR__ . '/Helpers/' . $helper . '.php' ) )
        {
            require_once(__DIR__ . '/Helpers/' . $helper . '.php' );
            return true;
        }
        ## If it didn't exist in either Core or Plugin then it's not found
        else
        {
            return false;
        }
    }
    
    private function currentDirectory()
    {
        return getcwd();
    }

    private function getResourceURL()
    {
        $pageURL = 'http';
        if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
        $pageURL .= "://";
        if ($_SERVER["SERVER_PORT"] != "80") {
            $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"];
        } else {
            $pageURL .= $_SERVER["SERVER_NAME"];
        }
        return $pageURL;
    }

    public function getCss()
    {
        print '<link rel="stylesheet" href="'.self::getResourceURL().'/plugins/Core/css/bootstrap.min.css">';
        print '<link rel="stylesheet" href="'.self::getResourceURL().'/plugins/Core/css/bootstrap-theme.min.css">';
    }

    public function getJs()
    {
        print '<script src="'.self::getResourceURL().'/plugins/Core/js/jquery.min.js"></script>';
        print '<script src="'.self::getResourceURL().'/plugins/Core/js/bootstrap.min.js"></script>';
    }


    public function test()
    {
        print __DIR__;
    }
}