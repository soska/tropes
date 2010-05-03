<?php
class DuperrificTrope{
	
	var $name;
	var $contentTypes = array();
	var $defaultTheme = null;
	
	var $customAdmin = false;
	
	protected $__types = array();
	
	function __construct(){
		
		if (!$this->name) {
			$this->name = str_replace('Trope','',get_class($this));
		}

		if (!defined('TROPE_DIR')) define('TROPE_DIR',TROPES_ROOT.DS.strtolower(underscorize($this->name)).DS); 
		if (!defined('TROPE_CONTENT_TYPES')) define('TROPE_CONTENT_TYPES',TROPE_DIR.'content_types'.DS); 

		if (!defined('TROPE_URL')) define('TROPE_URL',get_bloginfo('wpurl')."/wp-content/duperrific/tropes/".strtolower(underscorize($this->name))."/"); 

		add_action('admin_init',array($this,'excludeMenus'));
				
				
		$this->loadContentTypes();		
		$this->activate();				
				
		$this->__assets();		
		
	}
	
	function activate(){
		
		$currentTrope = get_option('dup.active_trope');
		
		if ( ($currentTrope === false) && ($currentTrope != $this->name)) {
			upsert_option('dup.active_trope',$this->name);
			$this->setDefaultTheme();
			return $this->name;
		}
		
	}
	
	function loadContentTypes(){
		
		foreach ($this->contentTypes as $type => $options) {
			if (!is_string($type)) {
				$type = $options;
				$options = array();
			}
			$fileName = TROPE_CONTENT_TYPES.strtolower($type).'.php';
			$className = ucfirst($type);
			
			if (file_exists($fileName)) {
				require_once(TROPES_LIB.DS.'content_type.class.php');
				require_once($fileName);
				if (class_exists($className)) {
					$this->__types[$type] = new $className($options);
				}else{
					trigger_error("$className is not present");
				}				
			}
		}
		
	}
	
	function excludeMenus(){
		global $menu,$submenu;
		// not implemented YET
	}
	
	function setDefaultTheme(){
		if ($this->defaultTheme) {

			if (is_array($this->defaultTheme)) {
				list ($template, $stylesheet)  = $this->defaultTheme;
			}else{
				$template = $stylesheet = $this->defaultTheme;
			}
			
			switch_theme($template, $stylesheet);			
			
		}
	}
	
	function __assets(){
		$folder = underscorize(strtolower($this->name));
		if ($this->customAdmin) {

			// just in case we want to remove the core colors class
			// global $wp_styles;
			// if ( !is_a($wp_styles, 'WP_Styles') ){
			// 	$wp_styles = new WP_Styles();
			// }
			// $wp_styles->remove( 'colors' );
			
			$adminCSS = 'wp-content/duperrific/tropes/'.$folder.'/css/admin.css';		
			if (file_exists(ABSPATH . $adminCSS)) {			
				add_action('admin_head', array($this,'__echoAdminCSS'));
			}
		}
	}
	
	function __echoAdminCSS(){
		$folder = underscorize(strtolower($this->name));
		$adminCSS = 'wp-content/duperrific/tropes/'.$folder.'/css/admin.css';		
		echo '<link rel="stylesheet" type="text/css" href="'.get_option('siteurl')."/".$adminCSS.'"/>'."\n";		
	}
	
	
}


?>