<?php
/*
   Crawler
   Crawls URLs in WordPressSite, saving them to StaticSite
 */

namespace WP2StaticAdvancedCrawling;

use Wa72\Url\Url;
use WP2Static\WsLog;
use WP2Static\Request;
use DOMDocument;

class Crawler {

    /**
     * @var resource | bool | \CurlHandle
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
        curl_setopt( $this->ch, CURLOPT_FOLLOWLOCATION, 0 );
        curl_setopt( $this->ch, CURLOPT_CONNECTTIMEOUT, 0 );
        curl_setopt( $this->ch, CURLOPT_TIMEOUT, 600 );
        curl_setopt( $this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
        curl_setopt( $this->ch, CURLOPT_MAXREDIRS, 0 );

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
        \WP2StaticAdvancedCrawling\Controller::activateForSingleSite();

        $crawled = 0;
        $cache_hits = 0;
        $crawl_start_time = Controller::dbNow();

        WsLog::l( 'Starting to crawl detected URLs.' );

        $site_url = \WP2Static\SiteInfo::getURL( 'site' );
        $site_path = rtrim( $site_url, '/' );
        $site_url = Url::parse( $site_url );
        $site_host = parse_url( $site_path, PHP_URL_HOST );
        $site_port = parse_url( $site_path, PHP_URL_PORT );
        $site_host = $site_port ? $site_host . ":$site_port" : $site_host;
        $site_urls = [ "http://$site_host", "https://$site_host" ];

        $additional_paths = [ '/' => true ];
        foreach ( Controller::getLineDelimitedBlobValue( 'additionalPathsToCrawl' ) as $path ) {
            $additional_paths[ $path ] = true;
        }
        WsLog::l( count( $additional_paths ) . ' additional paths added.' );
        self::addToCrawlQueue( $site_url, $site_url, $additional_paths );

        $add_urls = intval( Controller::getValue( 'addURLsWhileCrawling' ) ) !== 0;
        WsLog::l( ( $add_urls ? 'Adding' : 'Not adding' ) . ' discovered URLs.' );

        $chunk_size = intval( Controller::getValue( 'crawlChunkSize' ) );
        if ( $chunk_size < 1 ) {
            $chunk_size = PHP_INT_MAX;
        }
        WsLog::l( "Crawling with a chunk size of $chunk_size" );

        $progress_report_interval = intval( Controller::getValue( 'crawlProgressReportInterval' ) );
        if ( $progress_report_interval < 1 ) {
            $progress_report_interval = PHP_INT_MAX;
        }

        $crawl_sitemaps = intval( Controller::getValue( 'crawlSitemaps' ) ) !== 0;
        WsLog::l( ( $crawl_sitemaps ? 'Crawling' : 'Not crawling' ) . ' sitemaps.' );

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
                try {
                    $absolute_uri = new \WP2Static\URL( $site_path . $root_relative_path );
                } catch ( \WP2Static\WP2StaticException $e ) {
                    WsLog::l( "Error creating URL object for $site_path$root_relative_path" );
                    continue;
                }
                $url = $absolute_uri->get();

                $response = $this->crawlURL( $url, $add_urls, $crawl_sitemaps );

                if ( ! $response ) {
                    continue;
                }

                $crawled_contents = $response['body'];
                $redirect_to = null;

                if ( in_array( $response['code'], WP2STATIC_REDIRECT_CODES ) ) {
                    $location = self::getHeader( 'location', $response['headers'] );
                    if ( $location ) {
                        $redirect_to = (string) str_replace(
                            $site_urls,
                            '',
                            $location
                        );
                        if ( $add_urls ) {
                            \WP2Static\CrawlQueue::addUrls( [ $redirect_to ] );
                        }
                    } else {
                        $redirect_to = '/';
                        WsLog::l( "No location found for redirect at $url" );
                    }
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

                if ( $response['urls'] && count( $response['urls'] ) ) {
                    $page_url = Url::parse( $url );
                    if ( $crawl_only_changed ) {
                        self::addToCrawlQueue(
                            $site_url,
                            $page_url,
                            $response['urls'],
                            $crawl_start_time
                        );
                    } else {
                        self::addToCrawlQueue( $site_url, $page_url, $response['urls'] );
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
                if ( $crawled % $progress_report_interval === 0 ) {
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
     * Parse URLs and add to the provided array of URLs
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
     * Parse URLs and add to the provided array of URLs
     *
     * @param \SimpleXMLElement $el
     * @param array<string|true> $urls
     */
    public static function parseURLsSitemapElement( \SimpleXMLElement $el, array &$urls ) : void {
        switch ( $el->getName() ) {
            case 'loc':
                $urls[ strval( $el ) ] = true;
                break;
            case 'sitemap':
            case 'sitemapindex':
            case 'url':
            case 'urlset':
                foreach ( $el as $child ) {
                    self::parseURLsSitemapElement( $child, $urls );
                }
                break;
        }
    }

    /**
     * Return an array of URLs parsed from the provided XML sitemap, or an empty array
     * if not a valid sitemap.
     *
     * @param string $xml
     * @return array<string|true>
     */
    public static function parseURLsSitemap( string $xml ) : array {
        libxml_use_internal_errors( true );
        $el = simplexml_load_string( $xml );
        libxml_use_internal_errors( false );
        if ( false === $el ) {
            WsLog::l( 'Error parsing XML.' );
            return [];
        }

        $urls = [];

        // Parse xml-stylesheet processing instructions.
        $dom = dom_import_simplexml( $el );
        if ( $dom ) {
            if ( $dom->ownerDocument instanceof DOMDocument ) {
                foreach ( $dom->ownerDocument->childNodes as $child ) {
                    if ( XML_PI_NODE === $child->nodeType ) {
                        $matches = [];
                        $c = preg_match_all(
                            '/href\=\"([^\"]+)\"/i',
                            $child->textContent,
                            $matches
                        );

                        if ( 0 < $c ) {
                            $urls[ $matches[1][0] ] = true;
                        }
                    }
                }
            }
        }

        self::parseURLsSitemapElement( $el, $urls );

        return $urls;
    }

    /**
     * Add to crawl queue URLs matching $site_url, ignoring any others.
     *
     * @param Url $site_url
     * @param array<string|true> $urls
     */
    public static function addToCrawlQueue(
        Url $site_url, Url $page_url, array &$urls, ?string $crawl_start_time = null
    ) : void {
        $site_host = $site_url->getHost();
        $local_urls = [];

        foreach ( array_keys( $urls ) as $s ) {
            $url = Url::parse( $s );
            $scheme = $url->getScheme();

            if ( ! $scheme ) {
                $url = $url->makeAbsolute( $page_url );
                $scheme = $url->getScheme();
            }

            $path = $url->getPath();

            if ( ( 'http' === $scheme || 'https' === $scheme ) &&
                 $url->equalsHost( $site_host ) && 0 < strlen( $url->getPath() ) &&
                 \WP2Static\FilesHelper::filePathLooksCrawlable( $path ) ) {
                $local_urls[] = $path;
            }
        }

        if ( count( $local_urls ) ) {
            \WP2Static\CrawlQueue::addURLs( $local_urls );
            if ( $crawl_start_time ) {
                CrawlQueue::updateCrawledTimes( $local_urls, $crawl_start_time, true );
            }
        }
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
    public function crawlURL( string $url, bool $parse_urls, bool $crawl_sitemaps ) : ?array {
        $handle = $this->ch;

        if ( ! is_resource( $handle ) ) {
            return null;
        }

        $response = $this->request->getURL( $url, $handle );
        if ( ! $response ) {
            return null;
        }
        $crawled_contents = $response['body'];

        if ( $response['code'] === 404 ) {
            $site_path = rtrim( \WP2Static\SiteInfo::getURL( 'site' ), '/' );
            $url_slug = str_replace( $site_path, '', $url );
            WsLog::l( '404 for URL ' . $url_slug );
            $response['body'] = null;
        } elseif ( in_array( $response['code'], WP2STATIC_REDIRECT_CODES ) ) {
            $response['body'] = null;
        } elseif ( $parse_urls || $crawl_sitemaps ) {
            $content_type = self::getHeader( 'content-type', $response['headers'] );

            if ( $content_type ) {
                if ( $parse_urls && false !== stripos( $content_type, 'text/html' ) ) {
                    $response['urls'] = self::parseURLsHTML( $response['body'] );
                } elseif ( $crawl_sitemaps &&
                           false !== stripos( $content_type, 'application/xml' ) ) {
                    $response['urls'] = self::parseURLsSitemap( $response['body'] );
                }
            }
        }

        return $response;
    }

}
