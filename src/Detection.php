<?php

namespace WP2StaticAdvancedCrawling;

use \WP2Static\WsLog;

class Detection {

    /**
     * @param array<string> $url_queue
     * @return array<string>
     */
    public static function wp2staticModifyInitialCrawlList( array $url_queue ) : array {
        if ( 1 === intval( Controller::getValue( 'detectRedirectionPluginURLs' ) ) ) {
            $url_queue = array_merge( $url_queue, self::redirectionPluginURLs() );
        }
        return $url_queue;
    }

    /**
     * @return array<string>
     */
    public static function redirectionPluginURLs() : array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'redirection_items';

        if ( ! $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
            return [];
        }

        $rows = $wpdb->get_results(
            "SELECT url, action_data, regex FROM $table_name"
        );

        $urls = [];
        foreach ( $rows as $row ) {
            $urls[] = $row->action_data;
            if ( 0 === intval( $row->regex ) ) {
                $urls[] = $row->url;
            }
        }

        return $urls;
    }

}
