<?php

namespace WP2StaticAdvancedCrawling;

class Controller {
    public function run() : void {
        add_filter(
            'wp2static_add_menu_items',
            [ 'WP2StaticAdvancedCrawling\Controller', 'addSubmenuPage' ]
        );

        add_action(
            'admin_post_wp2static_advanced_crawling_save_options',
            [ $this, 'saveOptionsFromUI' ],
            15,
            1
        );

        add_action(
            'admin_menu',
            [ $this, 'addOptionsPage' ],
            15,
            1
        );

        add_action(
            'pre_post_update',
            [ $this, 'prePostUpdateHandler' ],
            0,
            1
        );

        add_action(
            'wp2static_crawl',
            [ 'WP2StaticAdvancedCrawling\Crawler', 'wp2staticCrawl' ],
            15,
            2
        );

        do_action(
            'wp2static_register_addon',
            'wp2static-addon-advanced-crawling',
            'crawl',
            'Advanced Crawling',
            'https://github.com/WP2Static/wp2static-addon-advanced-crawling',
            'Provides advanced crawling options'
        );
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

        \WP2Static\Controller::ensureIndex(
            $table_name,
            'name',
            "CREATE UNIQUE INDEX name ON $table_name (name)"
        );
    }

    public static function addURLQueueCrawledTime() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_urls';

        $row = $wpdb->get_row( "SHOW COLUMNS FROM $table_name WHERE Field = 'crawled_time'" );
        if ( ! $row ) {
            $wpdb->query( "ALTER TABLE $table_name ADD COLUMN crawled_time DATETIME" );
        }

        \WP2Static\Controller::ensureIndex(
            $table_name,
            'crawled_time',
            "CREATE INDEX crawled_time ON $table_name (crawled_time)"
        );
    }

    /**
     *  Get all add-on options
     *
     *  @return mixed[] All options
     */
    public static function getOptions() : array {
        global $wpdb;
        $options = [];

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $rows = $wpdb->get_results( "SELECT * FROM $table_name" );

        foreach ( $rows as $row ) {
            $options[ $row->name ] = $row;
        }

        return $options;
    }

    /**
     * Seed options
     */
    public static function seedOptions() : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $queries = [];

        $query_string =
            "INSERT IGNORE INTO $table_name (name, value, label, description) " .
            'VALUES (%s, %s, %s, %s);';

        $queries[] = $wpdb->prepare(
            $query_string,
            'addURLsWhileCrawling',
            '1',
            'Add URLs Discovered While Crawling',
            ''
        );

        $queries[] = $wpdb->prepare(
            $query_string,
            'crawlChunkSize',
            '20',
            'Crawl Chunk Size',
            'The maximum number of URLs to crawl in one batch.'
        );

        $queries[] = $wpdb->prepare(
            $query_string,
            'crawlOnlyChangedURLs',
            '0',
            'Crawl Only Changed URLs',
            ''
        );

        $wpdb->query( 'START TRANSACTION' );
        foreach ( $queries as $query ) {
            $wpdb->query( $query );
        }
        $wpdb->query( 'COMMIT' );
    }

    public static function activateForSingleSite(): void {
        self::createOptionsTable();
        self::seedOptions();
        self::addURLQueueCrawledTime();
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
                self::activateForSingleSite();
            }

            restore_current_blog();
        } else {
            self::activateForSingleSite();
        }
    }

    public static function deactivate( bool $network_wide = null ) : void {
    }

    /**
     * Save options
     *
     * @param mixed $value option value to save
     */
    public static function saveOption( string $name, $value ) : void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $query_string = "INSERT INTO $table_name (name, value) VALUES (%s, %s);";
        $query = $wpdb->prepare( $query_string, $name, $value );

        $wpdb->query( $query );
    }

    public static function renderOptionsPage() : void {
        self::activateForSingleSite();

        $view = [];
        $view['nonce_action'] = 'wp2static-advanced-crawling-options';
        $view['options'] = self::getOptions();

        require_once __DIR__ . '/../views/options-page.php';
    }

    /**
     * Add WP2Static submenu
     *
     * @param mixed[] $submenu_pages array of submenu pages
     * @return mixed[] array of submenu pages
     */
    public static function addSubmenuPage( array $submenu_pages ) : array {
        $submenu_pages['advanced-crawling'] = [
            'WP2StaticAdvancedCrawling\Controller',
            'renderOptionsPage',
        ];

        return $submenu_pages;
    }

    public static function saveOptionsFromUI() : void {
        check_admin_referer( 'wp2static-advanced-crawling-options' );

        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $wpdb->update(
            $table_name,
            [ 'value' => isset( $_POST['addURLsWhileCrawling'] ) ? 1 : 0 ],
            [ 'name' => 'addURLsWhileCrawling' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => intval( $_POST['crawlChunkSize'] ) ],
            [ 'name' => 'crawlChunkSize' ]
        );

        $wpdb->update(
            $table_name,
            [ 'value' => isset( $_POST['crawlOnlyChangedURLs'] ) ? 1 : 0 ],
            [ 'name' => 'crawlOnlyChangedURLs' ]
        );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-addon-advanced-crawling' ) );
        exit;
    }

    /**
     * Get option value
     *
     * @return string option value
     */
    public static function getValue( string $name ) : string {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp2static_addon_advanced_crawling_options';

        $sql = $wpdb->prepare(
            "SELECT value FROM $table_name WHERE" . ' name = %s LIMIT 1',
            $name
        );

        $option_value = $wpdb->get_var( $sql );

        if ( ! is_string( $option_value ) ) {
            return '';
        }

        return $option_value;
    }

    public function addOptionsPage() : void {
        add_submenu_page(
            '',
            'Advanced Crawling Options',
            'Advanced Crawling Options',
            'manage_options',
            'wp2static-addon-advanced-crawling',
            [ $this, 'renderOptionsPage' ]
        );
    }

    public static function dbNow() : string {
        global $wpdb;

        return $wpdb->get_col( 'SELECT NOW()' )[0];
    }

    public static function prePostUpdateHandler( int $post_id ) : void {
        $permalink = get_permalink( $post_id );
        if ( $permalink ) {
            $post_url = wp_make_link_relative( $permalink );
            CrawlQueue::clearCrawledTime( $post_url );
        }
    }
}
