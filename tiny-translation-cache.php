<?php
/**
 * Plugin Name: Tiny translation cache (MU)
 * Description: Cache .mo files in persistent object cache.
 * Version:     0.1.3
 * Plugin URI:  https://developer.wordpress.org/reference/functions/load_textdomain/
 */

class Tiny_Translation_Cache {

    const GROUP = 'mofile';

    public function __construct() {

        // Prevent usage as a normal plugin in wp-content/plugins
        // We need to cache plugin translations
        if ( did_action( 'muplugins_loaded' ) ) {
            $this->exit_with_instructions();
        }

        // Detect object cache
        if ( ! wp_using_ext_object_cache() ) {
            return;
        }

        add_action( 'muplugins_loaded', array( $this, 'init' ) );
    }

    /**
     * @return void
     */
    public function init() {

        add_filter( 'override_load_textdomain', array( $this, 'load_textdomain' ), 30, 3 );
    }

    /**
     * @param bool $override
     * @param string $domain
     * @param string $mofile
     * @return bool
     */
    public function load_textdomain( $override, $domain, $mofile ) {

        // Copied from core
        do_action( 'load_textdomain', $domain, $mofile );
        $mofile = apply_filters( 'load_textdomain_mofile', $mofile, $domain );

        $mo    = new \MO();
        $key   = $this->get_key( $domain, $mofile );
        $found = false;
        // @TODO Compress stored data unserialize() and gzinflate( $ )
        /** @var array{entries?: string, headers?: array<mixed>} $cache */
        $cache = wp_cache_get( $key, self::GROUP, false, $found );

        if ( $found && isset( $cache['entries'], $cache['headers'] ) ) {
            // Cache hit
            $mo->entries = $cache['entries'];
            $mo->set_headers( $cache['headers'] );
        } else {
            // Cache miss
            if ( ! is_readable( $mofile ) || ! $mo->import_from_file( $mofile ) ) {
                return false;
            }
            $translation = array(
                'entries' => $mo->entries,
                'headers' => $mo->headers,
            );
            // @TODO Compress stored data serilalize() and gzdeflate( $, 6 )
            // Save translation for a day
            wp_cache_set( $key, $translation, self::GROUP, DAY_IN_SECONDS );
        }

        // Setup localization global
        global $l10n;

        if ( array_key_exists( $domain, (array) $l10n ) ) {
            $mo->merge_with( $l10n[ $domain ] );
        }
        // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $l10n[ $domain ] = &$mo;

        return true;
    }

    /**
     * @param string $domain
     * @param string $mofile
     * @return string
     */
    private function get_key( $domain, $mofile ) {

        // @FIXME Why do we need text domain? Isn't the full path exact enough?
        // Hash of text domain and .mo file path
        return md5( $domain . $mofile );
    }

    /**
     * @return void
     */
    private function exit_with_instructions() {

        $doc_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : ABSPATH; // WPCS: input var, sanitization ok.

        $iframe_msg = sprintf(
            '<p style="font:14px \'Open Sans\',sans-serif">
<strong style="color:#DD3D36">ERROR:</strong> This is <em>not</em> a normal plugin,
and it should not be activated as one.<br />
Instead, <code style="font-family:Consolas,Monaco,monospace;background:rgba(0,0,0,0.07)">%s</code>
must be copied to <code style="font-family:Consolas,Monaco,monospace;background:rgba(0,0,0,0.07)">%s</code></p>',
            esc_html( str_replace( $doc_root, '', __FILE__ ) ),
            esc_html( str_replace( $doc_root, '', trailingslashit( WPMU_PLUGIN_DIR ) ) . basename( __FILE__ ) )
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit( $iframe_msg );
    }
}

new Tiny_Translation_Cache();
