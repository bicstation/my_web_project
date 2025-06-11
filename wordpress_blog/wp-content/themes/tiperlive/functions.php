<?php
/**
 * My Custom Theme functions and definitions
 */

if ( ! function_exists( 'my_custom_theme_setup' ) ) :
    /**
     * Sets up theme defaults and registers support for various WordPress features.
     */
    function my_custom_theme_setup() {
        // Add default posts and comments RSS feed links to head.
        add_theme_support( 'automatic-feed-links' );

        // Let WordPress manage the document title.
        add_theme_support( 'title-tag' );

        // Enable support for Post Thumbnails on posts and pages.
        add_theme_support( 'post-thumbnails' );

        // Switch default core markup for search form, comment form, and comments
        // to output valid HTML5.
        add_theme_support( 'html5', array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ) );
    }
endif;
add_action( 'after_setup_theme', 'my_custom_theme_setup' );

/**
 * Enqueue scripts and styles.
 */
function my_custom_theme_scripts() {
    // テーマフォルダ内のstyle.cssを読み込む
    wp_enqueue_style( 'my-custom-theme-style', get_stylesheet_uri() );

    // テーマフォルダ内のcss/style_v2.cssを読み込む
    wp_enqueue_style( 'my-custom-theme-v2-style', get_template_directory_uri() . '/css/style_v2.css', array(), '1.0.0' );

    // CDNからのBootstrap CSSとFont Awesome CSSは直接header.phpに記述しています
    // もしwp_enqueue_styleで管理したい場合は、header.phpから削除し、ここに記述してください。
    // wp_enqueue_style( 'bootstrap-css', '[https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css](https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css)' );
    // wp_enqueue_style( 'font-awesome-css', '[https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css](https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css)' );


    // Bootstrap JS (CDN)
    wp_enqueue_script( 'bootstrap-bundle', '[https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js](https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js)', array(), '5.3.0', true );

    // Custom JS (テーマ固有のJS)
    wp_enqueue_script( 'my-custom-theme-script', get_template_directory_uri() . '/js/script.js', array('bootstrap-bundle'), '1.0.0', true );
}
add_action( 'wp_enqueue_scripts', 'my_custom_theme_scripts' );

// その他のWordPress機能やカスタム関数をここに追加