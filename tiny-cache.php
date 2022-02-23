<?php
/**
 * @package Tinycache
 *
 * @wordpress-plugin
 * Plugin name: Tiny cache (MU)
 * Description: Cache post content in persistent object cache for 1 day.
 * Version:     0.8.0
 * Plugin URI:  https://developer.wordpress.org/reference/functions/the_content/
 */

/**
 * Determine whether caching should be disabled.
 *
 * Learned from W3TC Page Cache rules, WP Super Cache rules and BC Cache skipCache().
 *
 * @return bool
 */
function tiny_cache_skip_cache() {

    return ( ! wp_using_ext_object_cache() /* When object cache is unavailable. */
        || ! ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' === $_SERVER['REQUEST_METHOD'] ) /* Not a GET request. */
        || ! ( defined( 'WP_USE_THEMES' ) && WP_USE_THEMES ) /* Request not coming from /index.php. */
        || is_user_logged_in() /* If user is logged in. */
        || ( is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || post_password_required() ) /* Uncacheable request. */
        || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) /* WooCommerce's DO-NOT-CACHE tag present. */
    );
}

/**
 * Display content from the object cache.
 *
 * @param string $more_link_text
 * @param bool   $strip_teaser
 * @return void
 */
function the_content_cached( $more_link_text = null, $strip_teaser = false ) {

    $post_id = get_the_ID();
    if ( false === $post_id /* Not possible to tie content to post ID. */
        || ! ( null === $more_link_text && false === $strip_teaser ) /* TODO Pull requests are welcome! */
        || tiny_cache_skip_cache()
    ) {
        the_content( $more_link_text, $strip_teaser );

        return;
    }

    $found  = false;
    $cached = wp_cache_get( $post_id, 'the_content', false, $found );

    // Cache hit.
    if ( $found ) {
        /** @var string $cached */
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        print $cached;

        return;
    }

    // Cache miss.
    $save_to_cache = false;
    $post          = get_post( $post_id );
    if ( is_object( $post ) ) {
        // Cache only public posts.
        if ( 'publish' === $post->post_status && empty( $post->post_password ) ) {
            $save_to_cache = true;
        }
    }

    // Print and save the content.
    if ( $save_to_cache ) {
        add_filter( 'the_content', 'tiny_cache_save_the_content', PHP_INT_MAX );
    }
    the_content( $more_link_text, $strip_teaser );
    if ( $save_to_cache ) {
        remove_filter( 'the_content', 'tiny_cache_save_the_content', PHP_INT_MAX );
    }
}

/**
 * Retrieve content from the object cache.
 *
 * @param string $more_link_text
 * @param bool   $strip_teaser
 * @return string
 */
function get_the_content_cached( $more_link_text = null, $strip_teaser = false ) {

    $post_id = get_the_ID();
    if ( false === $post_id /* Not possible to tie content to post ID. */
        || ! ( null === $more_link_text && false === $strip_teaser ) /* TODO Pull requests are welcome! */
        || tiny_cache_skip_cache()
    ) {
        return get_the_content( $more_link_text, $strip_teaser );
    }

    $found  = false;
    $cached = wp_cache_get( $post_id, 'get_the_content', false, $found );

    // Cache hit.
    if ( $found ) {
        /** @var string $cached */
        return $cached;
    }

    // Cache miss.
    $save_to_cache = false;
    $post          = get_post( $post_id );
    if ( is_object( $post ) ) {
        // Cache only public posts.
        if ( 'publish' === $post->post_status && empty( $post->post_password ) ) {
            $save_to_cache = true;
        }
    }

    $content = get_the_content( $more_link_text, $strip_teaser );
    if ( $save_to_cache ) {
        $message_tpl = '<!-- Cached content generated by Tiny cache on %s -->';
        $timestamp   = gmdate( 'c' );
        $message     = sprintf( $message_tpl, esc_html( $timestamp ) );
        wp_cache_add( $post_id, $content . $message, 'get_the_content', DAY_IN_SECONDS );
    }

    return $content;
}


/**
 * Save the content to the object cache.
 *
 * @param string $content
 * @return string
 */
function tiny_cache_save_the_content( $content ) {

    $post_id = get_the_ID();
    // Tie content to post ID.
    if ( false !== $post_id ) {
        $message_tpl = '<!-- Cached content generated by Tiny cache on %s -->';
        $timestamp   = gmdate( 'c' );
        $message     = sprintf( $message_tpl, esc_html( $timestamp ) );
        wp_cache_add( $post_id, $content . $message, 'the_content', DAY_IN_SECONDS );
    }

    return $content;
}

/**
 * Display a template part.
 *
 * @param string $slug
 * @param string $name
 * @param string $version_hash
 * @return void
 */
function get_template_part_cached( $slug, $name = null, $version_hash = '' ) {

    // The default version is the current post ID.
    if ( '' === $version_hash ) {
        $version_hash = get_the_ID();
    }

    if ( false === $version_hash /* Not possible to tie content to post ID. */
        || tiny_cache_skip_cache()
    ) {
        get_template_part( $slug, $name );
        return;
    }

    $file_suffix = (string) $name;
    if ( '' !== $file_suffix ) {
        $file_suffix = '-' . $file_suffix;
    }
    $key = sprintf( '%s%s:%s', $slug, $file_suffix, $version_hash );

    $found  = false;
    $cached = wp_cache_get( $key, 'template_part', false, $found );

    // Cache hit.
    if ( $found ) {
        /** @var string $cached */
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        print $cached;

        return;
    }

    // Cache miss.
    ob_start();
    get_template_part( $slug, $name );
    $output = ob_get_contents();
    ob_end_clean();

    // Save and print the template part.
    $timestamp = gmdate( 'c' );
    $message   = sprintf( '<!-- Cached @%s -->', esc_html( $timestamp ) );
    $template  = $output . $message;
    wp_cache_add( $key, $template, 'template_part', HOUR_IN_SECONDS );
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    print $template;
}

/**
 * Delete cached content by ID.
 *
 * @param int $post_id
 * @return void
 */
function tiny_cache_delete_the_content( $post_id ) {

    wp_cache_delete( $post_id, 'the_content' );
}

/**
 * Delete cached content on transition_post_status.
 *
 * @param string   $new_status
 * @param string   $old_status
 * @param \WP_Post $post
 * @return void
 */
function tiny_cache_post_transition( $new_status, $old_status, $post ) {

    // Post unpublished or published.
    if ( $old_status !== $new_status
        && ( 'publish' === $old_status || 'publish' === $new_status )
    ) {
        tiny_cache_delete_the_content( $post->ID );
    }
}

/**
 * Hook cache delete actions.
 *
 * @return void
 */
function tiny_cache_actions() {

    // Post ID is received.
    add_action( 'save_post', 'tiny_cache_delete_the_content', 0 );
    add_action( 'edit_post', 'tiny_cache_delete_the_content', 0 );
    add_action( 'delete_post', 'tiny_cache_delete_the_content', 0 );
    add_action( 'wp_trash_post', 'tiny_cache_delete_the_content', 0 );
    add_action( 'clean_post_cache', 'tiny_cache_delete_the_content', 0 );
    // Post as third argument.
    add_action( 'transition_post_status', 'tiny_cache_post_transition', 10, 3 );
};

add_action( 'init', 'tiny_cache_actions' );
