<?php
namespace Plugin;

include_once(APP_PATH_DOCROOT."/Classes/tFPDF.php");

if(!defined("FPDF_FONTPATH")) {
	define("FPDF_FONTPATH",   APP_PATH_WEBTOOLS . "pdf" . DS . "font" . DS);
}

class PDF extends \FPDF{
	public $project;
	public $record;
	public $formName;
	private $callbackName;
	private $callbackClass;

	public $font = "Arial";

	public static $enumTypes = array("yesno" => array(0 => "No", 1 => "Yes"));

	const CHAR_LIMIT_QUESTION = 54;
	const CHAR_LIMIT_VALUE = 51;
	const CHAR_LIMIT_FULL = 111;
	const COL_WIDTH_QUESTION = 105;
	const COL_WIDTH_VALUE = 75;
	const COL_WIDTH_FULL = 180;
	const ROW_HEIGHT = 4;
	const BOTTOM_OF_PAGE = 285;
	const START_OF_PAGE = 18;

	/**
	 * @param $project \Plugin\Project
	 * @param $record \Plugin\Record
	 * @param $formName string
	 */
	public function __construct($project, $record, $formName) {
		$this->project = $project;
		$this->record = $record;
		$this->formName = $formName;

		parent::FPDF();
	}

	public function setFieldCallbackFunction($functionName, $className = "") {
		$this->callbackName = $functionName;
		$this->callbackClass = $className;
	}

	public function printPdf($title = "TestDownload") {
		if(!is_a($this->project,"\\Plugin\\Project") || !is_a($this->record, "\\Plugin\\Record") || $this->formName == "") {
			echo "Invalid parameters";
			return;
		}

		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename='$title.pdf'");

		$this->SetAutoPageBreak('auto'); # on by default with 2cm margin
		$this->startNewPage();

		## Loop through all the fields in this form
		/** @var \Plugin\Metadata $metadata */
		foreach($this->project->getMetadata() as $metadata) {
			if($metadata->getFormName() != $this->formName) continue;

			$this->printField($metadata);

			## Add spacing after each question and start new page if needed
			$this->Ln();
			if($this->GetY() > self::BOTTOM_OF_PAGE) {
				$this->startNewPage();
			}
			else {
				$this->Ln();
			}
		}

		echo $this->Output('', 'S');
	}

	private function startNewPage() {
		$this->AddPage();
		$this->SetFillColor(0,0,0); # Set fill color (when used) to black
		$this->SetFont($this->font,'I',8); # retained from page to page. #  'I' for italic, 8 for size in points.

		$this->Cell(0,2,$this->project->getProjectDetails()["app_title"],0,1,'R');
		$this->Ln();

		//Display record name (top right), if displaying data for a record
		$this->SetFont($this->font,'BI',8);
		$this->Cell(0,2,$this->record->getId(),0,1,'R');
		$this->Ln();
		$this->SetFont($this->font,'',8);

		## Set REDCap Footer (not sure if need?)
		$this->SetY(-4);
		$this->SetFont($this->font,'',8);
		// Set the current date/time as the left-hand footer
		$this->Cell(40,0,date("M d, Y h:m:s"));
		// Set REDCap Consortium URL as right-hand footer
		$this->Cell(85,0,'');
		$this->Cell(0,0,'www.projectredcap.org',0,0,'L',false,'http://projectredcap.org');
		//Set the REDCap logo
		$this->Image(APP_PATH_DOCROOT . "Resources/images/redcaplogo2.jpg", 176, 289, 24, 8);
		//Reset position to begin the page
		$this->SetY(self::START_OF_PAGE);
	}

	/**
	 * @param \Plugin\Metadata $metadata
	 */
	public function printField($metadata)
	{
		$value = $this->record->getDetails($metadata->getFieldName());
		$enum = $metadata->getElementEnum();

			$value = $this->record->getDetails($metadata->getFieldName());

			## Use callback function to update the value
			if($this->callbackName != "") {
				if($this->callbackClass != "") {
					$value = (($this->callbackClass)::$this->callbackName($value));
				}
			}

			$enum = $metadata->getElementEnum();
		## Convert enum fields and yesno fields
		if ($enum != "") {
			$value = Project::renderEnumData($value, $enum);
		} else if (in_array($metadata->getElementType(), array_keys(self::$enumTypes))) {
			$value = self::$enumTypes[$metadata->getElementType()][$value];
		}

			if($enum != "") {
				$value = Project::renderEnumData($value, $enum);
		## Replace blank data with an underscored line
		$value = $value ? $value : "__________________________________";

		## Don't process a $value for descriptive text
		if ($metadata->getElementType() == "descriptive") {
			$value = "";
		}

		## Get array that splits the label and value strings into a list of lines
		list($labelLines, $dataLines) = self::splitLabelAndValue($metadata->getElementLabel(), $value);

		## Check if need to start this question on a new line (if it will run over)
		if ($this->GetY() > self::START_OF_PAGE && ($this->GetY() + self::ROW_HEIGHT * max(count($labelLines), count($dataLines))) > self::BOTTOM_OF_PAGE) {
			$this->startNewPage();
		}

		## Clear data lines so they don't print for files
		if ($metadata->getElementType() == "file") {
			$dataLines = array();
		}

		## For file, right before label is printed, set X and print image, then reset X and Y
		if ($metadata->getElementType() == "file") {
			$startX = $this->GetX();
			$this->SetX(self::COL_WIDTH_QUESTION);
			$endY = $this->printFileLine($this->record->getDetails($metadata->getFieldName()));
			$this->SetX($startX);
		}

		## Print out the lines to the PDF
		for ($i = 0; $i < max(count($labelLines), count($dataLines)); $i++) {
			## If don't even have blank space for Data Lines, must be descriptive text, so use full width
			if (count($dataLines) == 0) {
				$questionWidth = self::COL_WIDTH_FULL;
			} else {
				$questionWidth = self::COL_WIDTH_QUESTION;
			}

			if ($i < count($labelLines)) {
				$this->SetFont($this->font, 'B', 10);
				$this->Cell($questionWidth, self::ROW_HEIGHT, $labelLines[$i], 0);
			} else {
				$this->Cell($questionWidth, self::ROW_HEIGHT, "", 0);
			}

			if ($i < count($dataLines)) {
				$this->SetFont($this->font, '', 10);
				$this->Cell(self::COL_WIDTH_VALUE, self::ROW_HEIGHT, $dataLines[$i], 0);
			}

			if ($this->GetY() > self::BOTTOM_OF_PAGE) {
				$this->startNewPage();
			} else {
				$this->Ln();
			}
		}

		if ($metadata->getElementType() == "file") {
			$this->SetY(max($this->GetY(), $endY));
		}
	}

	/**
	 * @param $edocId int
	 */
	public function printFileLine($edocId) {
		$sql = "SELECT stored_name, file_extension, mime_type
				FROM redcap_edocs_metadata
				WHERE doc_id = $edocId";

		$q = db_query($sql);

		$row = db_fetch_assoc($q);

		if(!$row) return $this->GetY();

		## Only include images
		if($row["file_extension"] == "png" || $row["file_extension"] == "jpg") {
			$imagePath = EDOC_PATH.$row["stored_name"];

			$imageSize = getimagesize($imagePath);
			$imageWidth = min($imageSize[0]/4, self::COL_WIDTH_VALUE);
			$imageHeight = $imageSize[1]/4 * ($imageSize[0]/4 / $imageWidth);

			if($this->GetY() > self::START_OF_PAGE && ($imageHeight + $this->GetY()) > self::BOTTOM_OF_PAGE) {
				$this->startNewPage();
			}

			$this->Image($imagePath, self::COL_WIDTH_QUESTION + 1, $this->GetY(), $imageWidth);

			return ($this->GetY() + $imageHeight);
		}

		return $this->GetY();
	}

	public static function splitLabelAndValue($label, $value) {
		$labelLines = [];
		$dataLines = [];
		## Remove HTML from the label, converting </h1> tags to new lines
		$label = preg_replace("/\\<\\/h[0-6]\\>/","\n",$label);
		$label = preg_replace("/\\<.*?\\>/","",$label);

		if($value == "") {
			$lineSplitArray = [[$label, self::CHAR_LIMIT_FULL, &$labelLines]];
		}
		else {
			$lineSplitArray = [[$label, self::CHAR_LIMIT_QUESTION, &$labelLines], [$value, self::CHAR_LIMIT_VALUE, &$dataLines]];
		}

		## Break label and value into separate lines based on the length
		foreach($lineSplitArray as $details) {
			$text = $details[0];
			$width = $details[1];
			$lineArray = &$details[2];

			## Check $text strlen and split off one line and add to $lineArray
			while(strlen($text) > 0 && count($lineArray) < 100) {
				## If new lines exist in the current width, use that to split the line instead
				$lineBreakPos = strpos(substr($text, 0, $width), "\n");
				if($lineBreakPos !== false) {
					$lineArray[] = substr($text, 0, $lineBreakPos);
					$text = ltrim(substr($text, $lineBreakPos + 1)," ");
					continue;
				}

				if(strlen($text) > $width) {
					# Find last space so that words don't get cutoff between lines
					$lastSpace = strrpos(substr($text, 0, $width), " ");
					$lastSpace = $lastSpace === false ? $width : $lastSpace + 1;

					$lineArray[] = substr($text, 0, $lastSpace);
					$text = ltrim(substr($text, $lastSpace)," ");
				}
				else {
					$lineArray[] = $text;
					$text = "";
				}
			}
		}

		return array($labelLines, $dataLines);
	}
}