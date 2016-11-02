<?PHP

global $Core;
$Core->Helpers(array("createArrayFromCSV"));

/**
 * Function that searches the file name in the database, parses it and returns an array with the content
 * @param $dbTable, the table name of the database
 * @param $fileExtension, the format of the file
 * @param $DocID, the id of the document
 * @param $filepath, the path where the file is
 * @return array, the generated array with the data
 */
function parseCSVtoArray($dbTable,$fileExtension, $DocID, $filepath){
    $sqlTableCSV = "SELECT * FROM `".$dbTable."` WHERE file_extension = '".$fileExtension."' AND doc_id = '".$DocID."'";
    $qTableCSV = db_query($sqlTableCSV);
    $csv = array();
    while ($rowTableCSV = db_fetch_assoc($qTableCSV)) {
        $csv = createArrayFromCSV($filepath,$rowTableCSV['stored_name']);
    }
    return $csv;
}
?>