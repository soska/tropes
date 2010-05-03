<?php
class Projects extends DuperrificContentType{
	
	var $features = array(
			'editor',
			'title',
			'thumbnail',
		);
		
	var $metaboxes = array(
			'url'=>array(
					'title'=>'Project Url',
					'type'=>'text',
					'hint'=>'Put the website url here.',
				),
		);		
	
}

function have_projects(){
	global $Projects;
	return $Projects->have_posts();
}

function the_project(){
	global $Projects;
	return $Projects->the_post();
}

function get_project_meta($key,$echo = true){
	global $Projects;
	$meta = $Projects->get_meta($key);
	if ($echo) {
		echo $meta;
	}
	return $meta;
}


?>