<?php
/**
 * Plugin Name: WP Excerpt Manager
 * Plugin URI: https://github.com/Azanumer/wp-excerpt-manager
 * Description: Control excerpts per post type — custom word lengths, a read-more link with your own text, excerpt support for pages, and a per-post length override.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Azan Umer
 * Author URI: https://azanumer.cv
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-excerpt-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WEM_VERSION', '1.0.0' );
define( 'WEM_OPTION', 'wem_settings' );

/**
 * Default settings: 55 words everywhere, pages get excerpt support.
 */
function wem_default_settings() {
    $lengths = array();
    foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) {
        $lengths[ $type ] = 55;
    }
    return array(
        'lengths'       => $lengths,
        'read_more'     => __( 'Read more &raquo;', 'wp-excerpt-manager' ),
        'read_more_link' => 1,
        'pages_excerpt' => 1,
    );
}

function wem_get_settings() {
    $saved = get_option( WEM_OPTION, array() );
    $defaults = wem_default_settings();
    if ( ! is_array( $saved ) ) {
        return $defaults;
    }
    $settings = wp_parse_args( $saved, $defaults );
    $settings['lengths'] = array_merge( $defaults['lengths'], isset( $saved['lengths'] ) && is_array( $saved['lengths'] ) ? $saved['lengths'] : array() );
    return $settings;
}

/**
 * Add excerpt support to pages (and any public post type missing it).
 */
function wem_add_excerpt_support() {
    $settings = wem_get_settings();
    if ( ! empty( $settings['pages_excerpt'] ) ) {
        add_post_type_support( 'page', 'excerpt' );
    }
}
add_action( 'init', 'wem_add_excerpt_support' );

/**
 * Excerpt length: per-post override first, then the per-type setting.
 */
function wem_excerpt_length( $length ) {
    $post = get_post();
    if ( $post ) {
        $override = (int) get_post_meta( $post->ID, '_wem_length', true );
        if ( $override > 0 ) {
            return $override;
        }
    }

    $settings  = wem_get_settings();
    $post_type = $post ? $post->post_type : get_post_type();
    if ( $post_type && isset( $settings['lengths'][ $post_type ] ) ) {
        return max( 1, (int) $settings['lengths'][ $post_type ] );
    }
    return $length;
}
add_filter( 'excerpt_length', 'wem_excerpt_length', 999 );

/**
 * Excerpt "more" string: custom text, optionally linked to the post.
 */
function wem_excerpt_more( $more ) {
    $settings = wem_get_settings();
    $text     = trim( $settings['read_more'] );
    if ( '' === $text ) {
        return '&hellip;';
    }
    if ( empty( $settings['read_more_link'] ) ) {
        return ' ' . $text;
    }
    $post = get_post();
    if ( ! $post ) {
        return ' ' . $text;
    }
    return sprintf(
        ' <a class="wem-read-more" href="%s">%s</a>',
        esc_url( get_permalink( $post ) ),
        esc_html( $text )
    );
}
add_filter( 'excerpt_more', 'wem_excerpt_more', 999 );

/**
 * Per-post length override meta box.
 */
function wem_add_meta_box() {
    foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) {
        add_meta_box(
            'wem-length',
            __( 'Excerpt Length', 'wp-excerpt-manager' ),
            'wem_meta_box',
            $type,
            'side'
        );
    }
}
add_action( 'add_meta_boxes', 'wem_add_meta_box' );

function wem_meta_box( $post ) {
    wp_nonce_field( 'wem_save_length', 'wem_length_nonce' );
    $value = (int) get_post_meta( $post->ID, '_wem_length', true );
    echo '<p><label>' . esc_html__( 'Words in the auto-excerpt (0 = use the post-type default):', 'wp-excerpt-manager' ) . '<br>';
    printf(
        '<input type="number" name="wem_length" value="%d" min="0" max="1000" style="width:100%%">',
        esc_attr( $value )
    );
    echo '</label></p>';
}

function wem_save_meta_box( $post_id ) {
    if ( ! isset( $_POST['wem_length_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wem_length_nonce'] ) ), 'wem_save_length' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( ! isset( $_POST['wem_length'] ) ) {
        return;
    }
    $length = max( 0, absint( $_POST['wem_length'] ) );
    if ( 0 === $length ) {
        delete_post_meta( $post_id, '_wem_length' );
    } else {
        update_post_meta( $post_id, '_wem_length', $length );
    }
}
add_action( 'save_post', 'wem_save_meta_box' );

/**
 * Settings page.
 */
function wem_admin_menu() {
    add_options_page(
        __( 'Excerpt Manager', 'wp-excerpt-manager' ),
        __( 'Excerpt Manager', 'wp-excerpt-manager' ),
        'manage_options',
        'wem-settings',
        'wem_settings_page'
    );
}
add_action( 'admin_menu', 'wem_admin_menu' );

function wem_admin_init() {
    register_setting( 'wem_settings_group', WEM_OPTION, 'wem_sanitize_settings' );
    add_settings_section( 'wem_main', __( 'Excerpt settings', 'wp-excerpt-manager' ), '__return_false', 'wem-settings' );

    add_settings_field(
        'wem_lengths',
        __( 'Excerpt length (words)', 'wp-excerpt-manager' ),
        'wem_render_lengths',
        'wem-settings',
        'wem_main'
    );
    add_settings_field(
        'wem_read_more',
        __( 'Read-more text', 'wp-excerpt-manager' ),
        'wem_render_text',
        'wem-settings',
        'wem_main'
    );
    add_settings_field(
        'wem_read_more_link',
        __( 'Link the read-more text', 'wp-excerpt-manager' ),
        'wem_render_link_checkbox',
        'wem-settings',
        'wem_main'
    );
    add_settings_field(
        'wem_pages_excerpt',
        __( 'Excerpt box for pages', 'wp-excerpt-manager' ),
        'wem_render_pages_checkbox',
        'wem-settings',
        'wem_main'
    );
}
add_action( 'admin_init', 'wem_admin_init' );

function wem_render_lengths() {
    $settings = wem_get_settings();
    foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
        printf(
            '<p><label>%s<br><input type="number" name="%s[lengths][%s]" value="%d" min="1" max="1000"></label></p>',
            esc_html( $type->labels->singular_name ),
            esc_attr( WEM_OPTION ),
            esc_attr( $type->name ),
            esc_attr( isset( $settings['lengths'][ $type->name ] ) ? (int) $settings['lengths'][ $type->name ] : 55 )
        );
    }
}

function wem_render_text() {
    $settings = wem_get_settings();
    printf(
        '<input type="text" name="%s[read_more]" value="%s" class="regular-text"> <p class="description">%s</p>',
        esc_attr( WEM_OPTION ),
        esc_attr( $settings['read_more'] ),
        esc_html__( 'Leave empty to use the default "…" ending.', 'wp-excerpt-manager' )
    );
}

function wem_render_link_checkbox() {
    $settings = wem_get_settings();
    printf(
        '<label><input type="checkbox" name="%s[read_more_link]" value="1"%s> %s</label>',
        esc_attr( WEM_OPTION ),
        checked( 1, (int) $settings['read_more_link'], false ),
        esc_html__( 'Wrap the read-more text in a link to the post.', 'wp-excerpt-manager' )
    );
}

function wem_render_pages_checkbox() {
    $settings = wem_get_settings();
    printf(
        '<label><input type="checkbox" name="%s[pages_excerpt]" value="1"%s> %s</label>',
        esc_attr( WEM_OPTION ),
        checked( 1, (int) $settings['pages_excerpt'], false ),
        esc_html__( 'Add the Excerpt box to the page editor (lets you write manual excerpts for pages).', 'wp-excerpt-manager' )
    );
}

function wem_sanitize_settings( $input ) {
    $clean = wem_default_settings();
    if ( isset( $input['lengths'] ) && is_array( $input['lengths'] ) ) {
        foreach ( $input['lengths'] as $type => $length ) {
            $type = sanitize_key( $type );
            if ( post_type_exists( $type ) ) {
                $clean['lengths'][ $type ] = max( 1, absint( $length ) );
            }
        }
    }
    if ( isset( $input['read_more'] ) ) {
        $clean['read_more'] = sanitize_text_field( $input['read_more'] );
    }
    $clean['read_more_link'] = ! empty( $input['read_more_link'] ) ? 1 : 0;
    $clean['pages_excerpt']  = ! empty( $input['pages_excerpt'] ) ? 1 : 0;
    return $clean;
}

function wem_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    echo '<div class="wrap"><h1>' . esc_html__( 'Excerpt Manager', 'wp-excerpt-manager' ) . '</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'wem_settings_group' );
    do_settings_sections( 'wem-settings' );
    submit_button();
    echo '</form></div>';
}
