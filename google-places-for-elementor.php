<?php
// google-places-for-elementor.php
/*
Plugin Name: Google Places for Elementor
Description: Add Google Places dynamic tags for Elementor
Version: 1.2
Author: Dmytro Besh
*/

// Define constants
define('GOOGLE_PLACES_FOR_ELEMENTOR_PATH', plugin_dir_path(__FILE__));

// Include files
require_once GOOGLE_PLACES_FOR_ELEMENTOR_PATH . 'settings.php';
require_once GOOGLE_PLACES_FOR_ELEMENTOR_PATH . 'elementor-extension.php';