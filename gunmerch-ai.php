<?php
/**
 * Plugin Name: GunMerch AI
 * Version: 1.0.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GMA_VERSION', '1.0.8' );
define( 'GMA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GMA_PLUGIN_DIR . 'includes/class-gma-install.php';
require_once GMA_PLUGIN_DIR . 'includes/class-gma-logger.php';
require_once GMA_PLUGIN_DIR . 'includes/class-gma-core.php';
require_once GMA_PLUGIN_DIR . 'includes/class-gma-admin.php';
require_once GMA_PLUGIN_DIR . 'includes/class-gma-trends.php';
require_once GMA_PLUGIN_DIR . 'includes/class-gma-designer.php';
require_once GMA_PLUGIN_DIR . 'includes/class-gma-printfull.php';
require_once GMA_PLUGIN_DIR . 'includes/class-gma-shopify.php';
require_once GMA_PLUGIN_DIR . 'includes/class-gma-ajax.php';

class GunMerch_AI {
    private static $instance = null;
    private $classes = array();
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Ensure database tables exist (for deployments that didn't run activation hook).
        add_action('init', array($this, 'maybe_install'), 1);
        
        $this->classes['logger'] = new GMA_Logger();
        $this->classes['core'] = new GMA_Core();
        $this->classes['admin'] = new GMA_Admin();
        $this->classes['trends'] = new GMA_Trends();
        $this->classes['designer'] = new GMA_Designer();
        $this->classes['printfull'] = new GMA_Printful();
        $this->classes['shopify'] = new GMA_Shopify();
        $this->classes['ajax'] = new GMA_AJAX();
        
        add_action('init', array($this, 'register_post_types'));
    }
    
    /**
     * Ensure tables exist (for FTP deployments that skip activation hook).
     */
    public function maybe_install() {
        if (get_option('gma_db_version') !== GMA_VERSION) {
            GMA_Install::activate();
        }
    }
    
    public function register_post_types() {
        register_post_type('gma_design', array(
            'labels' => array('name' => 'Designs'),
            'public' => false,
            'show_ui' => true,
        ));
    }
    
    public function get_class($class) {
        return isset($this->classes[$class]) ? $this->classes[$class] : null;
    }
}

function gunmerch_ai() {
    return GunMerch_AI::instance();
}

gunmerch_ai();
