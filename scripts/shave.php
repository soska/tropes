<?php
function shave($input, $vars = array()){
	if(preg_match_all("/\{\{[^\s]+\}\}\s/",$input,$matches)){
		foreach ($matches[0] as $template) {
			$template = trim($template);
			$varKey = str_replace('{{','',str_replace('}}','',$template));
			if (isset($vars[$varKey])) {
				var_dump($vars[$varKey]);				
				$input = str_replace($template,$vars[$varKey],$input);
			}
		}
	}
}
?>