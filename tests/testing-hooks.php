<?php

function fetch_local_image( $initial_data, $url ) {
	global $num_appended_bytes;
	$path = parse_url( $url, PHP_URL_PATH );
	$pieces = explode( '/', $path );
	$file = array_pop( $pieces );
	return file_get_contents( __DIR__ . "/data/$file" ) . str_repeat( 'A', $num_appended_bytes );
}
add_filter( 'override_raw_data_fetch', 'fetch_local_image', 10, 2 );

function enable_all_functions( $functions ) {
	return array(
		'zoom'       => true,
		'quality'    => true,
		'strip'      => true,
		'h'          => 'set_height',
		'w'          => 'set_width',
		'crop'       => 'crop',
		'resize'     => 'resize_and_crop',
		'fit'        => 'fit_in_box',
		'lb'         => 'letterbox',
		'ulb'        => 'unletterbox',
		'filter'     => 'filter',
		'brightness' => 'brightness', 
		'contrast'   => 'contrast', 
		'colorize'   => 'colorize', 
		'smooth'     => 'smooth',  
	);
}
add_filter( 'allowed_functions', 'enable_all_functions' );
