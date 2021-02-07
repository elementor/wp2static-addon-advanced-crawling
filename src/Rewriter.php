<?php

namespace WP2StaticAdvancedCrawling;

use \WP2Static\URLHelper;

class Rewriter {

    /**
     * Rewrite URLs in file to destination_url
     *
     * @param string $filename file to rewrite URLs in
     * @throws \WP2Static\WP2StaticException
     */
    public static function rewrite( string $filename ) : void {
        $file_contents = file_get_contents( $filename );

        if ( $file_contents === false ) {
            $file_contents = '';
        }

        $rewritten_contents = self::rewriteFileContents( $file_contents );

        file_put_contents( $filename, $rewritten_contents );
    }

    /**
     * Rewrite URLs in a string to destination_url
     *
     * @param string $file_contents
     * @return string
     */
    public static function rewriteFileContents( string $file_contents ) : string
    {
        // TODO: allow empty file saving here? Exception for style.css
        if ( ! $file_contents ) {
            return '';
        }

        $destination_url = apply_filters(
            'wp2static_set_destination_url',
            \WP2Static\CoreOptions::getValue( 'deploymentURL' )
        );

        $destination_url = untrailingslashit( $destination_url );
        $destination_url_rel = URLHelper::getProtocolRelativeURL( $destination_url );
        $destination_url_rel_cslashes = addcslashes( $destination_url_rel, '/' );

        $search_patterns = [];
        $replace_patterns = [];

        $hosts = Controller::getLineDelimitedBlobValue( 'additionalHostsToRewrite' );

        foreach ( $hosts as $host ) {
            if ( $host ) {
                $host_rel = URLHelper::getProtocolRelativeURL( 'http://' . $host );

                $search_patterns[] = 'http://' . $host;
                $search_patterns[] = 'https:// ' . $host;
                $search_patterns[] = $host_rel;
                $search_patterns[] = addcslashes( $host_rel, '/' );
                $replace_patterns[] = $destination_url;
                $replace_patterns[] = $destination_url;
                $replace_patterns[] = $destination_url_rel;
                $replace_patterns[] = $destination_url_rel_cslashes;
            }
        }

        $rewritten_contents = str_replace(
            $search_patterns,
            $replace_patterns,
            $file_contents
        );

        return $rewritten_contents;
    }
}
