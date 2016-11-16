<?php


namespace Plugin;

use \Exception;

class Database
{
    public static function query($sql){
        $result = db_query($sql);

        if($result == false){
            throw new Exception("Error executing query: $sql");
        }

        return $result;
    }

    public static function arrayToValueListSQL($array){
        $sql = "";

        foreach($array as $item){
            if(!empty($sql)){
                $sql .= ', ';
            }

            $item = db_real_escape_string($item);

            $sql .= "'$item'";
        }

        return "($sql)";
    }
}