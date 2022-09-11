<?php
require_once("../modules/StudentResults/lib/dompdf/dompdf_config.inc.php");
$dompdf = new DOMPDF();

$ofilename="Transcript";
$inst_id=$_REQUEST["inst_id"];
$ff = I2CE_FormFactory::instance();
$instObj=$ff->createContainer($inst_id);
$instObj->populate();
$footer=$instObj->address." = "."TEL:".$instObj->telephone." = "."FAX:".$instObj->fax." = "."EMAIL:".$instObj->email." = ©". date("Y");
   $filename='/tmp/transcript.html';
   $dompdf->load_html(file_get_contents($filename));
   $dompdf->set_paper("A3", "Landscape");
   $dompdf->render();
   $canvas = $dompdf->get_canvas();
	$font1 = Font_Metrics::get_font("helvetica", "bold");
	$font2 = Font_Metrics::get_font("helvetica", "normal");
	$canvas->page_text(72, 790, "PAGE: {PAGE_NUM}" , $font1, 12, array(0,0,0));
	$canvas->page_text(150, 790, "$footer" , $font2, 12, array(0,0,0));
	$canvas->page_text(150, 810, "This is an official transcript only when it bears the stamp and signature of the Registrar/Principal of the Institution" , $font2, 12, array(0,0,0));
    $dompdf->stream($ofilename.".pdf", array("Attachment" => 0));    
    unset($dompdf);

class IHS_DownloadTranscript {
	
	}
?>