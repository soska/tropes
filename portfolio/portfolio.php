<?php
require_once(dirname(dirname(__FILE__)).'/lib/bootstrap.php');

class PortfolioTrope extends DuperrificTrope{
	
	var $contentTypes = array(
			'Projects',
		);			
	
}

global $Trope;
$Trope = new PortfolioTrope;

?>