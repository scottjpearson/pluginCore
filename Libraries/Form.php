<?PHP

namespace Plugin;
use \Exception;

class Form
{

    static public function repopulate_checkbox_radio( $name = '', $value='', $post = array(), $return = TRUE )
    {
        $value =  (array_key_exists($name, $post)
            && $post[$name] === $value) ? 'checked' : '';

        ## If the value needs to be returned do that
        if( $return ) return $value;
    }

    static public function repopulate_text( $name = '', $post = array(), $return = TRUE )
    {
        $value =  (array_key_exists($name, $post)) ? $post[$name] : '';

        ## If the value needs to be returned do that
        if( $return ) return $value;
    }
}




