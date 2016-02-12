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

	public $font = "Arial";
	/**
	 * @param $project \Plugin\Project
	 * @param $record \Plugin\Record
	 * @param $formName string
	 */
	function __construct($project, $record, $formName) {
		$this->project = $project;
		$this->record = $record;
		$this->formName = $formName;

		parent::FPDF();
	}

	public function printPdf() {
		if(!is_a($this->project,"\\Plugin\\Project") || !is_a($this->record, "\\Plugin\\Record") || $this->formName == "") {
			echo "Invalid parameters";
			return;
		}

		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename='TestDownload.pdf'");

		//Set the character limit per line for questions (left column) and answers (right column)
		$char_limit_q = 54; //question char limit per line
		$char_limit_a = 51; //answer char limit per line

		//Set column width and row height
		$col_width_a = 105; //left column width
		$col_width_b = 75;  //right column width
		$row_height = 4;

		$bottom_of_page = 290;

		$this->SetAutoPageBreak('auto'); # on by default with 2cm margin
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
		$this->SetY(18);

		## Loop through all the fields in this form
		/** @var \Plugin\Metadata $metadata */
		foreach($this->project->getMetadata() as $metadata) {
			if($metadata->getFormName() != $this->formName) continue;

			$value = $this->record->getDetails($metadata->getFieldName());
			$enum = $metadata->getElementEnum();

			if($enum != "") {
				$value = \Plugin\Project::renderEnumData($value, $enum);
			}

			$labelLines = [];
			$dataLines = [];
			$label = preg_replace("/\\<.*?\\>/","",$metadata->getElementLabel());
			$data = $value || ($metadata->getElementType() == "descriptive") ? $value : "__________________________________";

			foreach([[$label, $char_limit_q, &$labelLines],[$data, $char_limit_a, &$dataLines]] as $details) {
				while(strlen($details[0]) > 0 && count($details[2]) < 100) {
					if(strlen($details[0]) > $details[1]) {
						$details[2][] = substr($details[0], 0, $details[1]);
						$details[0] = substr($details[0], $details[1]);
					}
					else {
						$details[2][] = $details[0];
						$details[0] = "";
					}
				}
			}

			for($i = 0; $i < max(count($labelLines), count($dataLines)); $i++) {
				if($i < count($labelLines)) {
					$this->SetFont($this->font,'B',10);
					$this->Cell($col_width_a,$row_height,$labelLines[$i],0);
				}
				else {
					$this->Cell($col_width_a,$row_height,"",0);
				}

				if($i < count($dataLines)) {
					$this->SetFont($this->font,'',10);
					$this->Cell($col_width_b,$row_height,$dataLines[$i],0);
				}
				$this->Ln();
			}
			$this->Ln();
			$this->Ln();
		}

		echo $this->Output('', 'S');
	}
}