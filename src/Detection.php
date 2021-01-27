<?php

namespace WP2StaticAdvancedCrawling;

use \WP2Static\WsLog;

class Detection {

    public function wp2staticDetect() : void {
        \WP2StaticAdvancedCrawling\Controller::activateForSingleSite();
    }

    /**
     * @param array<string> $filenames
     * @return array<string|int>
     */
    public static function wp2staticFilenamesToIgnore( array $filenames ) : array {
        return Controller::getLineDelimitedBlobValue( 'filenamesToIgnore' );
    }

    /**
     * @param array<string> $extensions
     * @return array<string|int>
     */
    public static function wp2staticFileExtensionsToIgnore( array $extensions ) : array {
        return Controller::getLineDelimitedBlobValue( 'fileExtensionsToIgnore' );
    }

    /**
     * @param array<string> $url_queue
     * @return array<string>
     */
    public static function wp2staticModifyInitialCrawlList( array $url_queue ) : array {
        if ( 1 === intval( Controller::getValue( 'detectRedirectionPluginURLs' ) ) ) {
            $redirection_urls = self::redirectionPluginURLs();
            WsLog::l( count( $redirection_urls ) . ' URLs detected from Redirection plugin.' );
            $url_queue = array_merge( $url_queue, $redirection_urls );
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
            "SELECT url, action_data, regex FROM $table_name WHERE status='enabled'"
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
