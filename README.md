# Tiny cache

Cache post content, translations and nav menu output in persistent object cache.

This MU plugin works well in **production** providing you understand its source code (133 sloc).

### WordPress performance

How to achieve high performance in WordPress?

| Item                          | Tool                               | Speedup                       |
| ----------------------------- | ---------------------------------- | ----------------------------- |
| [Infrastructure](https://github.com/szepeviktor/debian-server-tools/blob/master/CV-WordPress.md#webserver-stack) | CPU, disk, web server, PHP ([OPcache](http://php.net/manual/en/opcache.configuration.php#ini.opcache.validate-timestamps)) and DNS | Overall performance |
| In-memory object cache        | Redis, Memcached, APCu             | options, post, post meta etc. |
| Server-side functionality plugins<br> (backup, db cleanup) | Use WP-CLI instead | **Degrades** performance |
| Theme and plugins             | Cache-aware ones using object cache or transients |                |
| Translations                  | `tiny-translation-cache`           | .mo file parsing              |
| Navigation menus              | `tiny-nav-menu-cache`              | `wp_nav_menu()`               |
| Post content                  | `tiny-cache`                       | `the_content()`               |
| Widgets                       | `widget-output-cache` plugin       | `dynamic_sidebar()`           |

This list concentrates on WordPress core generating HTML code. Frontend loading (full page cache) is another topic.

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
```

## Little sisters

1. Tiny **navigation menu** cache - for nav menu output
1. Tiny **translation** cache - for translations (.mo files)

## Alternative

https://github.com/Rarst/fragment-cache
