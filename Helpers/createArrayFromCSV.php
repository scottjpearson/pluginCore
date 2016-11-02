<?PHP
/**
 * Function that parses de CSV file to an Array
 * @param $filepath, the path of the file
 * @param $filename, the file name
 * @return array, the generated array with the CSV data
 */
function createArrayFromCSV($filepath,$filename){
    $file = $filepath.$filename;
    $csv = array_map('str_getcsv', file($file));
    array_walk($csv, function(&$a) use ($csv) {
        $a = array_combine($csv[0], $a);
    });
    # remove column header
    array_shift($csv);
    return $csv;
}

?>