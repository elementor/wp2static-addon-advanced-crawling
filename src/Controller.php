<?php

namespace WP2StaticAdvancedCrawling;

class Controller {
    public function run() : void {
    }

    public static function createOptionsTable() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            value VARCHAR(255) NOT NULL,
            label VARCHAR(255) NULL,
            description VARCHAR(255) NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        \WP2Static\Controller::ensure_index(
            $table_name,
            'name',
            "CREATE UNIQUE INDEX name ON $table_name (name)"
        );
    }

    public static function seedOptions(): void {
    }

    public static function activate_for_single_site(): void {
        self::createOptionsTable();
        self::seedOptions();
    }

    public static function activate( bool $network_wide = null ) : void {
        if ( $network_wide ) {
            global $wpdb;

            $query = 'SELECT blog_id FROM %s WHERE site_id = %d;';

            $site_ids = $wpdb->get_col(
                sprintf(
                    $query,
                    $wpdb->blogs,
                    $wpdb->siteid
                )
            );

            foreach ( $site_ids as $site_id ) {
                switch_to_blog( $site_id );
                self::activate_for_single_site();
            }

            restore_current_blog();
        } else {
            self::activate_for_single_site();
        }
    }

    public static function deactivate( bool $network_wide = null ) : void {
    }
}
