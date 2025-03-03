<?php
/**
 * Plugin Name: AI Featured Image Generator
 * Plugin URI: https://github.com/hxxy2012/Wordpress-AI-Featured-Image-Generator
 * Description: 使用AI技术从文章标题自动生成特色图片并设置为文章特色图像。
 * Version: 1.0.0
 * Author: hxxy2012
 * Author URI: https://wordpress.org/
 * Text Domain: ai-featured-image-generator
 * Domain Path: /languages
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('AIFIG_VERSION', '1.0.0');
define('AIFIG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIFIG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIFIG_DEFAULT_API_URL', 'http://www.xxx.com:8000/v1/images/generations');
define('AIFIG_DEFAULT_API_KEY', 'cCc$oyA0');

// Include required files
require_once AIFIG_PLUGIN_DIR . 'includes/class-ai-image-generator.php';
require_once AIFIG_PLUGIN_DIR . 'includes/class-admin.php';

// Initialize the plugin
function run_ai_featured_image_generator() {
    // Load text domain for translations
    load_plugin_textdomain('ai-featured-image-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Initialize classes
    $generator = new AI_Image_Generator();
    $admin = new AI_Featured_Image_Generator_Admin($generator);
    
    // Register activation hook
    register_activation_hook(__FILE__, 'aifig_activate');
}

// Plugin activation
function aifig_activate() {
    // Set default options
    add_option('aifig_auto_generate', false);
    add_option('aifig_api_key', AIFIG_DEFAULT_API_KEY);
    add_option('aifig_api_url', AIFIG_DEFAULT_API_URL);
}

run_ai_featured_image_generator();