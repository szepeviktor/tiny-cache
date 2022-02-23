<?php
/**
 * Plugin Name: Tiny navigation menu cache (MU)
 * Description: Cache nav menu's HTML content in persistent object cache.
 * Version:     0.2.0
 * Constants:   TINY_CACHE_NAV_MENU_EXCLUDES
 */

class Tiny_Nav_Menu_Cache {

    /**
     * @var string Name of the cache group.
     */
    private const GROUP = 'navmenu';

    /**
     * @var array List of whitelisted query string fields (these do not prevent cache write).
     */
    private $whitelisted_query_string_fields = [
        // https://support.google.com/searchads/answer/7342044
        'gclid',
        'gclsrc',
        // https://www.facebook.com/business/help/330994334179410 "URL in ad can't contain Facebook Click ID" section
        'fbclid',
        // https://en.wikipedia.org/wiki/UTM_parameters
        'utm_campaign',
        'utm_content',
        'utm_medium',
        'utm_source',
        'utm_term',
    ];

    public function __construct() {

        add_action( 'init', array( $this, 'init' ) );
    }

    public function init() {

        // Detect object cache
        if ( ! wp_using_ext_object_cache() ) {
            return;
        }

        add_action( 'save_post', array( $this, 'flush_all' ) );
        add_action( 'wp_create_nav_menu', array( $this, 'flush_all' ) );
        add_action( 'wp_update_nav_menu', array( $this, 'flush_all' ) );
        add_action( 'wp_delete_nav_menu', array( $this, 'flush_all' ) );
        add_action( 'split_shared_term', array( $this, 'flush_all' ) );

        // Learned from W3TC Page Cache rules and WP Super Cache rules
        if ( ! ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) /* Not a GET request */ // WPCS: input var OK.
            || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) /* DO-NOT-CACHE tag present */
        ) {
            return;
        }

	// Add user-defined query parameters to the whitelist. Define the parameters you
	// want whitelisted in wp-config in the following way:
	//
	// define('TINY_NAV_CACHE_WHITELIST_QUERY_STRING_FIELDS', 'XDEBUG_TRIGGER, do_xhprof_profile');

	if ( defined( 'TINY_NAV_CACHE_WHITELIST_QUERY_STRING_FIELDS' ) ) {
		$fields = array_map( 'trim', explode( ',', TINY_NAV_CACHE_WHITELIST_QUERY_STRING_FIELDS) );
		$this->whitelisted_query_string_fields = array_merge( $this->whitelisted_query_string_fields, $fields );
	}

        add_filter( 'pre_wp_nav_menu', array( $this, 'get_nav_menu' ), 30, 2 );
        add_filter( 'wp_nav_menu', array( $this, 'save_nav_menu' ), PHP_INT_MAX, 2 );
    }

    /**
     * @param string $nav_menu_html
     * @param object $args
     * @return string
     */
    public function get_nav_menu( $nav_menu_html, $args ) {

        $enabled = $this->is_enabled( $args );
        if ( $enabled ) {
            $found = null;
            $cache = wp_cache_get( $this->get_cache_key( $args ), self::GROUP, false, $found );
            if ( $found ) {
                return $cache;
            }
        }

        return $nav_menu_html;
    }

    /**
     * @param string $nav_menu_html
     * @param object $args
     * @return string
     */
    public function save_nav_menu( $nav_menu_html, $args ) {

        $enabled = $this->is_enabled( $args );
        if ( $enabled ) {
            $key = $this->get_cache_key( $args );
            wp_cache_set( $key, $nav_menu_html, self::GROUP, DAY_IN_SECONDS );
            $this->remember_key( $key );
        }

        return $nav_menu_html;
    }

    public function flush_all() {

        foreach ( $this->get_all_keys() as $key ) {
            wp_cache_delete( $key, self::GROUP );
        }
        wp_cache_delete( 'key_list', self::GROUP );
    }

    /**
     * @param string $key
     */
    private function remember_key( $key ) {

        // @TODO Not atomic
        $found    = null;
        $key_list = wp_cache_get( 'key_list', self::GROUP, false, $found );
        if ( $found ) {
            $key_list .= '|' . $key;
        } else {
            $key_list = $key;
        }
        wp_cache_set( 'key_list', $key_list, self::GROUP, DAY_IN_SECONDS );
    }

    /**
     * @return array
     */
    private function get_all_keys() {

        $found    = null;
        $key_list = wp_cache_get( 'key_list', self::GROUP, false, $found );
        if ( ! $found ) {
            $key_list = '';
        }

        return explode( '|', $key_list );
    }

    /**
     * Check excluded nav menus and the query string.
     *
     * @param object $args
     * @return bool
     */
    private function is_enabled( $args ) {

        // Excluded theme locations.
        if ( defined( 'TINY_CACHE_NAV_MENU_EXCLUDES' ) && TINY_CACHE_NAV_MENU_EXCLUDES ) {
            $excludes = explode( '|', TINY_CACHE_NAV_MENU_EXCLUDES );

            if ( property_exists( $args, 'theme_location' )
                && ! empty( $args->theme_location )
                && in_array( $args->theme_location, $excludes, true )
            ) {
                return false;
            }
        }

        // Do not cache requests with query string except whitelisted ones.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( [] !== array_diff( array_keys( $_GET ), $this->whitelisted_query_string_fields) ) {
            return false;
        }

        return true;
    }

    /**
     * @param object $args
     * @return string
     */
    private function get_cache_key( $args ) {

        $request_uri = isset( $_SERVER['REQUEST_URI'] )
            ? $_SERVER['REQUEST_URI']
            : ''; // WPCS: sanitization, input var OK.

        return md5( 'nav_menu-' . $args->menu_id . $request_uri );
    }
}

new Tiny_Nav_Menu_Cache();
