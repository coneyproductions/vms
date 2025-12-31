<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Core VMS Plugin class.
 */
class VMS_Plugin {

    public function __construct() {
        // Basic hooks.
        add_action( 'init', array( $this, 'init' ) );

        // Admin-specific setup.
        if ( is_admin() ) {
            $this->load_admin();
        }
    }

    public function init() {
        // Register custom post types, taxonomies, etc.
        $this->load_post_types();
    }

    protected function load_post_types() {
        $post_types = new VMS_Post_Types();
        $post_types->register();
    }

    /**
     * Load admin-related functionality.
     */
    protected function load_admin() {
        new VMS_Admin_Vendors();
    }
}
