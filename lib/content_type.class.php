<?php
/**
 * DuperContentType is an object wrapper for creating custom content types in tropes.
 *
 * @package default
 * @author Armando Sosa
 */
class DuperrificContentType{
	
	var $name = null;
	var $options = array();
	var $labels;
	var $categories;
	var $tags;
	var $object;
	var $features = array();
	var $metaboxes = array();
	
	var $alwaysHideCustomFields = false;
	
	
	var $Query;
	var $current;
	var $currentMeta;
	
	/**
	 * Constructor
	 *
	 * @param string $options 
	 * @author Armando Sosa
	 */
	function __construct($options = array()){

		// set a global variable for this object.
		// sucks a little, but it will feel more 'WP-Style' in the templates
		global ${get_class($this)};
		${get_class($this)} = $this;

		if (!$this->name) {
			$this->name = strtolower(get_class($this));
		}

		if (!empty($this->metaboxes)) {
			if (!in_array('custom-fields',$this->features)) {
				$this->alwaysHideCustomFields = true;
				// $this->features[]='custom-fields';
			}
		}
		

		$this->options = array_merge(array(
				'public'=>true,
				'show_ui'=>true,
				'label'=>ucfirst($this->name),
				'singular_label'=>ucfirst(dumb_inflect($this->name)),
				'id'=>"menu-".$this->name,
				'labels'=>$this->labels,
				// 'rewrite'=>false,
				'register_meta_box_cb'=>array($this,'initMetaboxes'),
				'supports'=>$this->features,
				'menu_icon'=>TROPE_URL."content_types/{$this->name}_icon.png",
		),$this->options,$options);
				
		$this->setup();
		
		$this->object = register_post_type($this->name, $this->options);	

		add_action('_admin_menu',array($this,'setMenuId'));
		add_action('save_post', array($this,'save'));			

		add_filter('wp_title', array($this,'titleFilter'));			

		// hook to set rewrite rules;
		add_Action('rewrite_rules_array',array($this,'rewriteFilter'));
		
		$this->initTaxonomies();
		
	}	
	

	function rewriteFilter($rules){
		$newrules = array();
		$newrules["({$this->name})/?$"] = 'index.php?post_type='.$this->name;
		return $newrules + $rules;
	}


	
/*
	TEMPLATE METHODS
	********************************************************************
*/

	function get($queryArgs = null){

		$defaults = array(
			'post_type' => $this->name,
		);
		
		$paged = intval(get_query_var('paged'));
		if ($paged) {			
			$defaults['paged'] = $paged;
		}

		if (isset($_GET[$this->name])) {
			$defaults['name'] = sanitize_title($_GET[$this->name]);
		}else{
			$pagename = get_query_var('pagename');
			if (!empty($pagename)) {
				$defaults['name'] = sanitize_title($pagename);
			}
		}

		$queryArgs = wp_parse_args( $queryArgs, $defaults );

		$posts = query_posts($queryArgs);
		
		if (isset($queryArgs['name'])) {
			$GLOBALS['wp_query']->is_single = true;
		}
		
		// remove the 404 from the main wp_query object
		if (is_single() && !empty($post)) {
			$GLOBALS['wp_query']->is_404 = false;
		}
		
		return $this->Query;
	}


	
	function grabMetaFromId( $postId = null){
		if (!$postId) {
			global $post;
			$postId = $post->ID;
		}
		$meta = get_post_custom($postId);
		foreach ($this->metaboxes as $id => $box) {
			$box = $this->defaultMetaboxOptions($id,$box);
			if (!isset($meta[$id])) {
				$meta[$id] = null;
			}
			if ($box['unique']) {
				$meta[$id] = $meta[$id][0];
			}
			$this->currentMeta[$id] = $meta[$id];
		}		
	}
	
	function getMeta($key = null, $postId = null){

		$this->grabMetaFromId($postId);
		
		if (!$key) {
			return $this->currentMeta;
		}
		if (isset($this->currentMeta[$key])) {
			return $this->currentMeta[$key];
		}
	}
	
	function titleFilter($title, $sep = " : ", $seplocation = null){
		global $post;
		// pr($post->post_title);
		return $post->post_title . $sep;
	}
	
/*
	BACKEND METHODS
	********************************************************************
*/
	
	/**
	 * This function is intended to overriden in child classes
	 * it can be used to intialize options that need translation
	 *
	 * @return void
	 * @author Armando Sosa
	 */
	function setup(){
		
	}

	/**
	 * Sets the correct ID for the menu item in the generated UI. Unless $this->options['id'] == false
	 *
	 * @return void
	 * @author Armando Sosa
	 */
	function setMenuId(){
		if ($this->options['id']) {
			global $menu;
			$pattern = "edit.php?post_type=".$this->name;
			foreach ($menu as &$item) {
				if ($item[2] == $pattern) {
					$item[5] = $this->options['id'];
				}
			}
		}
	}	
	
	/**
	 * Intialize the Taxonomies for this content type.
	 * This is how it works:
	 * - $this->categories is used to create hierarchical taxonomies.
	 * - $this->tags is used to create non-hierarchical taxonomies.
	 *
	 * @return void
	 * @author Armando Sosa
	 */
	private function initTaxonomies(){
		foreach ( (array) $this->categories as $name => $label) {
			if (!is_string($name)) {
				$name = $label;
				$label = ucfirst($name);
			}
			register_taxonomy($name,$this->name,array('hierarchical'=>true,'label'=>$label));
		}

		foreach ( (array) $this->tags as $name => $label) {
			if (!is_string($name)) {
				$name = $label;
				$label = ucfirst($name);
			}
			register_taxonomy($name,$this->name,array('hierarchical'=>false,'label'=>$label));
		}

	}
	
	
	/**
	 * This function is used as a callback to initialize the metaboxes for this content type
	 *
	 * @return void
	 * @author Armando Sosa
	 */
	function initMetaboxes(){
		foreach ($this->metaboxes as $box => &$options) {
			if (!is_string($box)) {
				$box = $options;
				$options = array();
			}
			$options = $this->defaultMetaboxOptions($box,$options);
			
			if (!isset($options['label'])) {
				$options['label'] = $options['title'];
			}
			
			add_meta_box($box,$options['title'],$options['callback'],$options['page'],$options['context'],$options['priority']);
		}
	}
	
	function defaultMetaboxOptions($box,$options){
		$options = wp_parse_args( $options, array(
				'id'=>$box,
				'title'=>$box,
				'type'=>'text',
				'page'=>$this->name,	
				'context'=>'side',				
				'priority'=>'low',				
				'hint'=>'',
				'unique'=>true,
				'callback'=>array($this,'displayMetabox'),
			));		
		return $options;
	}	
	
	/**
	 * Display the metaboxes
	 *
	 * @param string $content 
	 * @param string $metabox 
	 * @return void
	 * @author Armando Sosa
	 */
	function displayMetabox($content, $metabox){
		// only required if needed.
		require_once(TROPES_LIB.DS.'csml.php');

		global $post;

		$box = $this->metaboxes[$metabox['id']];
		$value = get_post_meta($post->ID,$box['id'],true);
		$fieldName = "dup_meta[{$this->name}][{$box['id']}]";
		
		// required box stuff
		echo wp_nonce_field($box['id'],underscorize($box['id'])."_wpnonce",true,false);		

		// render the correct input type
		$method = "__{$box['type']}Input";
		if (method_exists($this,$method)) {
			$this->{$method}($box,$fieldName,$value);
		}

		// render the hint paragraph if needed
		if ($box['hint']) {
			echo csml::entag($box['hint'],'p.hint');
		}
		
	}
	
	function __multipleInput($parent){
		global $post;

		foreach ($parent['multiple'] as $id=>$box) {

			echo csml::tag('div.input.multiple');
			
			$value = get_post_meta($post->ID,$id,true);
			$fieldName = "dup_meta[{$this->name}][$id]";

			// required box stuff
			echo wp_nonce_field($id,underscorize($id)."_wpnonce",true,false);		

			if ($box['title']) {
				echo csml::entag($box['title'],'h4',array('for'=>$fieldName,'style'=>'margin-bottom:0'));
				echo "<br/>";
			}			

			// render the correct input type
			$method = "__{$box['type']}Input";
			if (method_exists($this,$method)) {
				$this->{$method}($box,$fieldName,$value);
			}

			// render the hint paragraph if needed
			if (isset($box['hint'])) {
				echo csml::entag($box['hint'],'p.hint');
			}			

			echo csml::tag('/div.input.multiple');
			
		}
	}
	
	
	/**
	 * Handles the save part of the meta boxes fields. It's hooked into the save_post action.
	 *
	 * @param string $postId 
	 * @return void
	 * @author Armando Sosa
	 */
	function save($postId,$multiple = null){

		$this->initMetaboxes();
		if (isset($_POST['dup_meta'][$this->name])) {
			$meta = $_POST['dup_meta'][$this->name];			
		}
		
		if ($multiple) {
			$boxes = $multiple;
		}else{
			$boxes = $this->metaboxes;
		}
		
		foreach ($boxes as $id=>$box) {
			extract($box);
			
			if (isset($meta[$id])) {
				if ( !isset($_POST[underscorize($id)."_wpnonce"]) || !wp_verify_nonce( $_POST[underscorize($id)."_wpnonce"], $id )) {  
					return $postId;  
				}
				$data = $meta[$id];
				
				if ($type == "multiple") {
					$this->save($postId,$multiple);
				}
				
				// format date/time data
				if ($type == 'date' || $type == 'datetime' || $type == 'time' ) {
					extract($data);
					$data = "$year-$month-$day";
					if (isset($hour)) {
						$data .= " $hour:$mins:00";
					}
				}
								
				// let's make available a callback for the developer
				if (!empty($box['beforeSave'])  && is_callable($box['beforeSave']) ) {
					$data = call_user_func_array($box['beforeSave'],array($data,$id,$postId,$this));
				}
				
				if (!isset($box['unique'])) {
					$box['unique'] = true;
				}
				
				$this->updateField($postId,$id,$data,$box['unique']);
				
			}
			
		}
	}
	
	function updateField($postId,$key,$data = '',$unique = true){
		$meta = get_post_meta($postId,$key);

		if(empty($data)){
			// if no data, we delete the custom field
			delete_post_meta($postId,$key,$data,$unique);
		}elseif (empty($meta)) {
			// no previous existence of the custom field, so we create it
			add_post_meta($postId,$key,$data,$unique);
		}elseif($data != $meta){
			// update an existing custom field
			update_post_meta($postId,$key,$data);
		}		
	}	
		
	function __textInput($box,$fieldName,$value){

		// initialize attributes
		$attributes =  (isset($box['attributes'])) ? $box['attributes'] :  array();
		$attributes['name']=$fieldName;
		$attributes['value']=$value;
		echo csml::tag('input[type="text"]',$attributes);				
	}

	function __textareaInput($box,$fieldName,$value){
		echo csml::entag($value,'textarea',array('name'=>$fieldName));				
	}


	function __checkboxInput($box,$fieldName,$value){
		$attributes =  (isset($box['attributes'])) ? $box['attributes'] :  array();
		$attributes['name']=$fieldName;

		$value = (empty($value))?0:1;

		$attributes['value'] = 0;
		echo csml::tag('input[type="hidden"]',$attributes);						
		
		if ($value) {
			$attributes['checked'] = "checked";
		}
		
		$attributes['value'] = 1;
		echo "<label>";
		echo csml::tag('input[type="checkbox"]',$attributes);						
		echo "{$box['label']}</label>";
		
	}
	

	function __datetimeInput($box,$fieldName,$value){
		$this->__dateInput($box,$fieldName,$value);
		echo "@";
		$this->__timeInput($box,$fieldName,$value);
	}
	
	function __dateInput($box,$fieldName,$value, $format = null){
		global $wp_locale;
		
		$date  = (!empty($value))? $value : time();
		
		$day = mysql2date( 'd', $date ) ;
		$month = mysql2date( 'm', $date ) ;
		$year = mysql2date( 'Y', $date ) ;
		
		$months = '';
		for ( $i = 1; $i < 13; $i = $i +1 ) {
			$m = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
			$attr = array('value'=>$i);
			if ($i == $month) {
				$attr['selected'] = 'selected';
			}
			$months .= csml::entag($m,'option',$attr);
		}
		echo csml::entag($months,'select',array('name'=>$fieldName."[month]"));
		echo csml::tag('input[type="text"]',array('name'=>$fieldName."[day]",'size'=>"2",'value'=>$day));		
		echo ",";
		echo csml::tag('input[type="text"]',array('name'=>$fieldName."[year]",'size'=>"4",'value'=>$year));
		
		return;

	}

	function __timeInput($box,$fieldName,$value, $format = null){
		global $wp_locale;
		
		$date  = (!empty($value))? $value : time();
		
		$hour = mysql2date( 'H', $date );
		$mins = mysql2date( 'i', $date );
		
		$months = '';
		echo csml::tag('input[type="text"]',array('name'=>$fieldName."[hour]",'size'=>"2",'value'=>$hour));
		echo " : ";
		echo csml::tag('input[type="text"]',array('name'=>$fieldName."[mins]",'size'=>"2",'value'=>$mins));
		
		return;

	}
	

}

?>