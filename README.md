# Tiny cache

Cache post content, translations and nav menu output in persistent object cache.

This MU plugin works well in **production** providing you understand its source code (133 sloc).

### WordPress performance

Please see https://github.com/szepeviktor/wordpress-website-lifecycle/blob/master/WordPress-performance.md

### Usage

Of course you need **persistent** object cache. Consider Redis server and `wp-redis` plugin.

Replace `the_content()` calls in your theme.

**NOTICE** Replace only argument-less calls! `$more_link_text` and `$strip_teaser` are not supported.

```bash
find -type f -name "*.php" | xargs -r -L 1 sed -i -e 's|\bthe_content();|the_content_cached();|g'
```

### No-cache situations

- `wp_suspend_cache_addition( true );`
- `define( 'DONOTCACHEPAGE', true );`

### Prevent missing plugin

Protection against plugin deactivation.

Copy these to your theme's functions.php.

```php
    if ( ! function_exists( 'the_content_cached' ) ) {
        function the_content_cached( $more_link_text = null, $strip_teaser = false ) {
            the_content( $more_link_text, $strip_teaser );
        }
    }
    if ( ! function_exists( 'get_the_content_cached' ) ) {
        function get_the_content_cached( $more_link_text = null, $strip_teaser = false ) {
            return get_the_content( $more_link_text, $strip_teaser );
        }
    }
    if ( ! function_exists( 'get_template_part_cached' ) ) {
        function get_template_part_cached( $slug, $name = null, $version_hash = '' ) {
            get_template_part( $slug, $name );
        }
    }
```

## Little sisters

1. Tiny **navigation menu** cache - for nav menu output
1. Tiny **translation** cache - for translations (.mo files)

## Alternative

https://github.com/Rarst/fragment-cache
