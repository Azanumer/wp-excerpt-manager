<?php
/**
 * Uninstall: remove the settings option and per-post length overrides.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'wem_settings' );
delete_post_meta_by_key( '_wem_length' );
