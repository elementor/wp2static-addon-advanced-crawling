<?php
/*
   Crawler
   Crawls URLs in WordPressSite, saving them to StaticSite
 */

namespace WP2StaticAdvancedCrawling;

use Wa72\Url\Url;
use WP2Static\WsLog;

class Crawler {

    /**
     * @var resource | bool
     */
    private $ch;
    /**
     * @var \WP2Static\Request
     */
    private $request;

    /**
     * Crawler constructor
     */
    public function __construct() {
        $this->ch = curl_init();
        curl_setopt( $this->ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $this->ch, CURLOPT_SSL_VERIFYHOST, 0 );
        curl_setopt( $this->ch, CURLOPT_SSL_VERIFYPEER, 0 );
        curl_setopt( $this->ch, CURLOPT_HEADER, 0 );
        curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, 1 );
        curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 0 );
        curl_setopt( $this->ch, CURLOPT_TIMEOUT, 600 );
        curl_setopt( $this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
        curl_setopt( $this->ch, CURLOPT_MAXREDIRS, 1 );

        $this->request = new \WP2Static\Request();

        $port_override = apply_filters(
            'wp2static_curl_port',
            null
        );

        if ( $port_override ) {
            curl_setopt( $this->ch, CURLOPT_PORT, $port_override );
        }

        curl_setopt(
            $this->ch,
            CURLOPT_USERAGENT,
            apply_filters( 'wp2static_curl_user_agent', 'WP2Static.com' )
        );

        $auth_user = \WP2Static\CoreOptions::getValue( 'basicAuthUser' );

        // quick return to avoid extra options fetch
        if ( ! $auth_user ) {
            return;
        }

        $auth_password = \WP2Static\CoreOptions::getValue( 'basicAuthPassword' );

        if ( $auth_user && $auth_password ) {
            curl_setopt(
                $this->ch,
                CURLOPT_USERPWD,
                $auth_user . ':' . $auth_password
            );
        }
    }

    public static function wp2staticCrawl( string $static_site_path, string $crawler_slug ) : void {
        if ( 'wp2static-addon-advanced-crawling' === $crawler_slug ) {
            $crawler = new Crawler();
            $crawler->crawlSite( $static_site_path );
        }
    }

    /**
     * Crawls URLs in WordPressSite, saving them to StaticSite
     */
    public function crawlSite( string $static_site_path ) : void {
        $crawled = 0;
        $cache_hits = 0;
        $crawl_start_time = Controller::dbNow();

        WsLog::l( 'Starting to crawl detected URLs.' );

        $site_path = rtrim( \WP2Static\SiteInfo::getURL( 'site' ), '/' );
        $site_host = parse_url( $site_path, PHP_URL_HOST );
        $site_port = parse_url( $site_path, PHP_URL_PORT );
        $site_host = $site_port ? $site_host . ":$site_port" : $site_host;
        $site_urls = [ "http://$site_host", "https://$site_host" ];

        $chunk_size = intval( Controller::getValue( 'crawlChunkSize' ) );
        if ( $chunk_size < 1 ) {
            $chunk_size = PHP_INT_MAX;
        }
        WsLog::l( "Crawling with a chunk size of $chunk_size" );

        $use_crawl_cache = apply_filters(
            'wp2static_use_crawl_cache',
            \WP2Static\CoreOptions::getValue( 'useCrawlCaching' )
        );

        WsLog::l( ( $use_crawl_cache ? 'Using' : 'Not using' ) . ' CrawlCache.' );

        $crawl_only_changed = Controller::getValue( 'crawlOnlyChangedURLs' );
        if ( $crawl_only_changed ) {
            WsLog::l( 'Crawling only changed URLs.' );
            $chunk = CrawlQueue::getChunkNulls( $chunk_size );
        } else {
            WsLog::l( 'Crawling all URLs.' );
            $chunk = CrawlQueue::getChunk( $crawl_start_time, $chunk_size );
        }
        while ( ! empty( $chunk ) ) {
            foreach ( $chunk as $root_relative_path ) {
                $absolute_uri = new \WP2Static\URL( $site_path . $root_relative_path );
                $url = $absolute_uri->get();

                $response = $this->crawlURL( $url );

                if ( ! $response ) {
                    continue;
                }

                $crawled_contents = $response['body'];
                $redirect_to = null;

                if ( in_array( $response['code'], WP2STATIC_REDIRECT_CODES ) ) {
                    $redirect_to = (string) str_replace(
                        $site_urls,
                        '',
                        $response['effective_url']
                    );
                    $page_hash = md5( $response['code'] . $redirect_to );
                } elseif ( ! is_null( $crawled_contents ) ) {
                    $page_hash = md5( $crawled_contents );
                } else {
                    $page_hash = md5( $response['code'] );
                }

                if ( $use_crawl_cache ) {
                    // if not already cached
                    if ( \WP2Static\CrawlCache::getUrl( $root_relative_path, $page_hash ) ) {
                        $cache_hits++;
                        continue;
                    }
                }

                $crawled++;

                if ( $crawled_contents ) {
                    // do some magic here - naive: if URL ends in /, save to /index.html
                    // TODO: will need love for example, XML files
                    // check content type, serve .xml/rss, etc instead
                    if ( mb_substr( $root_relative_path, -1 ) === '/' ) {
                        \WP2Static\StaticSite::add(
                            $root_relative_path . 'index.html',
                            $crawled_contents
                        );
                    } else {
                        \WP2Static\StaticSite::add( $root_relative_path, $crawled_contents );
                    }
                }

                \WP2Static\CrawlCache::addUrl(
                    $root_relative_path,
                    $page_hash,
                    $response['code'],
                    $redirect_to
                );

                // incrementally log crawl progress
                if ( $crawled % 300 === 0 ) {
                    $notice = "Crawling progress: $crawled crawled, $cache_hits skipped (cached).";
                    WsLog::l( $notice );
                }
            }

            CrawlQueue::updateCrawledTimes( $chunk );

            if ( $crawl_only_changed ) {
                $chunk = CrawlQueue::getChunkNulls( $chunk_size );
            } else {
                $chunk = CrawlQueue::getChunk( $crawl_start_time, $chunk_size );
            }
        }

        WsLog::l(
            "Crawling complete. $crawled crawled, $cache_hits skipped (cached)."
        );

        $args = [
            'staticSitePath' => $static_site_path,
            'crawled' => $crawled,
            'cache_hits' => $cache_hits,
        ];

        do_action( 'wp2static_crawling_complete', $args );
    }

    /**
     * Add and parsed URLs to the provided array of URLs
     *
     * @param \DOMNode $node
     * @param array<string|true> $urls
     */
    public static function parseURLsDOMNode( \DOMNode $node, array &$urls ) : void {
        foreach ( $node->childNodes as $child ) {
            if ( $child instanceof \DOMElement ) {
                $tag_name = strtolower( $child->tagName );
                switch ( $tag_name ) {
                    case 'a':
                        $urls[ $child->getAttribute( 'href' ) ] = true;
                        break;
                    case 'img':
                        $urls[ $child->getAttribute( 'src' ) ] = true;
                        break;
                    case 'link':
                        $urls[ $child->getAttribute( 'href' ) ] = true;
                        break;
                    case 'script':
                        $urls[ $child->getAttribute( 'src' ) ] = true;
                        break;
                    case 'source':
                        $urls[ $child->getAttribute( 'src' ) ] = true;
                        break;
                }
                self::parseURLsDOMNode( $child, $urls );
            }
        }
    }

    /**
     * Return an array of URLs parsed from the provided HTML
     *
     * @param string $html
     * @return array<string|true>
     */
    public static function parseURLsHTML( string $html ) : array {
        $html5 = new \Masterminds\HTML5();
        $dom = $html5->loadHTML( $html );
        $urls = [];
        self::parseURLsDOMNode( $dom, $urls );
        return $urls;
    }

    /**
     * Add to crawl queue URLs matching $site_url, ignoring any others.
     *
     * @param Url $site_url
     * @param array<string|true> $urls
     */
    public static function addToCrawlQueue( Url $site_url, array &$urls ) : void {
        $site_host = $site_url->getHost();
        $local_urls = [];

        foreach ( array_keys( $urls ) as $s ) {
            $url = Url::parse( $s );
            if ( $url->equalsHost( $site_host ) || $url->equalsHost( '' ) ) {
                if ( 0 < strlen( $url->getPath() ) ) {
                    $local_urls[] = $url->getPath();
                }
            }
        }

        \WP2Static\CrawlQueue::addURLs( $local_urls );
    }

    /**
     * Return a given header value from an array of raw headers
     *
     * @param string $header_name
     * @param array<string> $headers
     * @return ?string
     */
    public static function getHeader( string $header_name, array $headers ) : ?string {
        foreach ( $headers as $row ) {
            if ( 0 === stripos( $row, $header_name ) ) {
                return trim( substr( $row, 1 + strlen( $header_name ) ) );
            }
        }
        return null;
    }

    /**
     * Crawls a string of full URL within WordPressSite
     *
     * @return mixed[]|null response object
     */
    public function crawlURL( string $url ) : ?array {
        $handle = $this->ch;

        if ( ! is_resource( $handle ) ) {
            return null;
        }

        $response = $this->request->getURL( $url, $handle );

        $crawled_contents = $response['body'];

        if ( $response['code'] === 404 ) {
            $site_path = rtrim( \WP2Static\SiteInfo::getURL( 'site' ), '/' );
            $url_slug = str_replace( $site_path, '', $url );
            WsLog::l( '404 for URL ' . $url_slug );
            $response['body'] = null;
        } elseif ( in_array( $response['code'], WP2STATIC_REDIRECT_CODES ) ) {
            $response['body'] = null;
        } else {
            $content_type = self::getHeader( 'content-type', $response['headers'] );
            if ( $content_type && false !== stripos( $content_type, 'text/html' ) ) {
                $site_url = Url::parse( \WP2Static\SiteInfo::getURL( 'site' ) );
                $urls = self::parseURLsHTML( $response['body'] );
                self::addToCrawlQueue( $site_url, $urls );
            }
        }

        return $response;
    }
}
