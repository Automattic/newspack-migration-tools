<?php

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

namespace NewspackCustomContentMigrator\Logic;

use NewspackCustomContentMigrator\Utils\Logger;

/**
 * Class Medium
 *
 * Class responsible for converting Medium export archive to WXR format.
 *
 * This is based on the WXR_Converter_Medium class from here: fbhepr%2Skers%2Sjcpbz%2Sova%2Svzcbeg%2Sjke%2Qpbairegre%2Spynff%2Qjke%2Qpbairegre%2Qzrqvhz.cuc-og
 *
 * Medium currently exports user's posts as .zip archive consisting
 * of individual HTML files, one per each post. Important thing to
 * note is that users comments are also exported as separate HTML files,
 * and they are formatted in the same way as regular posts. That means
 * that it is not possible to differentiate between user's posts and
 * comments based only on the content of the exported files.
 *
 * IMPORTANT NOTE: This converter performs HTTP GET requests for each
 * HTML file contained in the archive in order to fetch its JSON
 * representation, which contains other data required for correct
 * importing. This will cause some large imports to last longer when
 * compared to standard archive-only based imports. Additionally,
 * we might need to stagger requests in order to avoid being blocked.
 *
 * For more details please refer to:
 * pek1ag-g1-p2
 */
class Medium {
	/**
	 * Filename of where to save the log.
	 *
	 * @var string $log_file Log file name.
	 */
	private static $log_file = 'medium-migrator.log';

	/**
	 * Logger instance.
	 *
	 * @var Logger.
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * HTML elements in exported Medium files contain lots of superfluous
	 * attributes. Attributes listed here will be removed from WXR content,
	 * in order to avoid possible collisions with existing ids and styles.
	 *
	 * Other attributes present in exported files are stripped during the import
	 * process. Currently these are: name, data-href, data-src, data-image-id,
	 * data-width, data-height, data-action, data-action-value, crossorigin.
	 *
	 * @var array $attributes_to_remove
	 */
	protected $attributes_to_remove = array( 'id', 'class', 'style', 'rel' );

	/**
	 * User agent that will be used with HTTP GET request when calling
	 * this script from CLI (called by executing wxr-converter.php).
	 *
	 * @var string USER_AGENT User agent passed with GET requests.
	 */
	const USER_AGENT = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko)' .
		' Chrome/41.0.2228.0 Safari/537.36';

	/**
	 * Meta key that will be used for posts that failed to import properly.
	 *
	 * @var string IMPORT_FAILED Meta key for failed posts.
	 */
	const IMPORT_FAILED = 'medium-import-failed';

	/**
	 * Message that will be used as reason (meta value) when the GET request to fetch
	 * post's meta from Medium fails.
	 *
	 * @var string COULD_NOT_FETCH_META Reason for import failure.
	 */
	const COULD_NOT_FETCH_META = "Metadata for this post couldn't be fetched from Medium.";

	/**
	 * Message that will be used as reason (meta value) when we are unable to find
	 * the original Medium URL for the current post.
	 *
	 * @var string MISSING_POST_URL Reason for import failure.
	 */
	const MISSING_POST_URL = "The original URL couldn't be found in exported file.";

	/**
	 * Array containing categories of all imported posts. category's slug will be used
	 * as array key, so we can use it to avoid adding duplicate elements.
	 *
	 * @access protected
	 *
	 * @var array $all_categories All categories that will be imported.
	 */
	protected $all_categories = array();

	/**
	 * List of all posts extracted from export archive.
	 *
	 * @access protected
	 *
	 * @var array $items Array of all extracted posts.
	 */
	protected $items = array();

	/**
	 * Author of the imported posts.
	 *
	 * @access protected
	 *
	 * @var array $author Author of the imported posts.
	 */
	protected $author = [];

	/**
	 * Returns all author for current import.
	 *
	 * @return array $author
	 */
	public function get_author() {
		return $this->author;
	}

	/**
	 * Returns empty array since there are no categories to be imported.
	 *
	 * @return array Empty array.
	 */
	public function get_categories() {
		return $this->all_categories;
	}

	/**
	 * Returns tags of all extracted posts.
	 *
	 * @return array $all_tags List of all tags for current import.
	 */
	public function get_tags() {
		return array();
	}

	/**
	 * Returns list of all posts extracted from export archive.
	 *
	 * @return array List of all extracted posts.
	 */
	public function get_items() {
		return $this->items;
	}

	/**
	 * Returns the blog title.
	 *
	 * @return string Blog title.
	 */
	public function get_title() {
		return 'Medium export';
	}

	/**
	 * Returns the blog description.
	 *
	 * @return string Blog description.
	 */
	public function get_description() {
		return '';
	}

	/**
	 * Returns the blog URL.
	 *
	 * @return string Exported blog URL.
	 */
	public function get_link() {
		return '';
	}

	/**
	 * Returns the URL for current post.
	 *
	 * The URL is contained in footer of exported post HTML.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current post HTML.
	 *
	 * @return string|WP_Error URL extracted from post's HTML or error on failure.
	 */
	private function get_post_url( $xpath ) {
		$nodes = $xpath->query( '//footer//a/@href' );

		// Post url example: https://medium.com/p/56e61edefe2c.
		$post_url_pattern = '/^http[s]?:\/\/medium\.com\/p\/\w+$/';

		foreach ( $nodes as $item ) {
			if ( preg_match( $post_url_pattern, $item->textContent ) ) {
				return $item->textContent;
			}
		}

		return new \WP_Error( 'get_post_url', __( 'Failed to retrieve post URL' ) );
	}

	/**
	 * Retrieves the JSON encoded post for given post URL.
	 *
	 * @access private
	 *
	 * @see WP_Http
	 * @link https://developer.wordpress.org/reference/classes/wp_http
	 *
	 * @param string $url Medium URL for the current post.
	 *
	 * @return object|WP_Error JSON object on success or error on failure.
	 */
	private function fetch_post_json( $url ) {
		$request = new \WP_Http();
		// Append format parameter to get JSON representation for post.
		$query_args = array( 'format' => 'json' );
		$url        = add_query_arg( $query_args, $url );

		/*
		 * If user agent is left empty the server will refuse the request.
		 * This occurs when this importer is invoked from CLI with wxr-converter.php script.
		 */

		$response = $request->get( $url, [ 'user-agent' => self::USER_AGENT ] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== $response['response']['code'] ) {
			return new \WP_Error( 'get_post_json', __( 'Failed to retrieve post JSON' ) );
		}

		/*
		 * Every JSON encoded post starts with '])}while(1);</x>' in order to prevent
		 * JSON hijacking. Lets remove that before decoding.
		 *
		 * For more info on JSON hijacking please refer to:
		 * http://stackoverflow.com/questions/2669690/why-does-google-prepend-while1-to-their-json-responses
		 */
		$raw_json = mb_substr( $response['body'], mb_strpos( $response['body'], '{' ) );

		return json_decode( $raw_json );
	}

	/**
	 * Determines if current post is a comment.
	 *
	 * Medium export archive also exports user's comments in same the
	 * format as posts. This check is needed in order to prevent importing
	 * comments as regular posts.
	 *
	 * @access private
	 *
	 * @param object $post_json JSON object for current post.
	 *
	 * @return bool True if post is a comment, false otherwise.
	 */
	private function is_comment( $post_json ) {
		/*
		 * In case of comments this field is set to parent post id.
		 * Otherwise it is left empty.
		 */
		if ( empty( $post_json->payload->value->inResponseToPostId ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns post's GMT publish date, or GMT created date in case of drafts.
	 *
	 * All published posts on Medium have firstPublishedAt timestamp
	 * that corresponds to publishing date. In case of drafts and scheduled posts
	 * this value is set to zero and we have to use createdAt value instead.
	 *
	 * @access private
	 *
	 * @param object $post_json JSON object for current post.
	 *
	 * @return object Date object corresponding to post's publish date.
	 */
	private function get_post_date_gmt( $post_json ) {
		$date_format    = 'Y-m-d H:i:s';
		$post_timestamp = intval( $post_json->payload->value->firstPublishedAt );

		if ( empty( $post_timestamp ) ) {
			// Post isn't published yet, let's use the date when it was created.
			$post_timestamp = intval( $post_json->payload->value->createdAt );
		}

		// Provided value in JSON is in milliseconds, date() expects seconds.
		$post_timestamp = intval( $post_timestamp / 1000 );

		return gmdate( $date_format, $post_timestamp + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Returns the status of the post.
	 *
	 * There are four possible values for post status Medium:
	 * public, unlisted, draft and scheduled. At present time,
	 * based on the export data and JSON that Medium provides,
	 * it's not possible to tell drafts and scheduled posts apart.
	 *
	 * @access private
	 *
	 * @param object $post_json JSON object for current post.
	 *
	 * @return string
	 */
	private function get_post_status( $post_json ) {
		/**
		 * If firstPublishedAt value is set to 0, the post is not published yet.
		 * That means it can either be a draft or a scheduled post.
		 * Since we can't differentiate between them, we'll default to draft.
		 */
		if ( empty( $post_json->payload->value->firstPublishedAt ) ) {
			return 'draft';
		}

		// Visibility is set to 1 for unlisted posts.
		if ( 1 === $post_json->payload->value->visibility ) {
			return 'private';
		}

		// At this point there is only one option left.
		return 'publish';
	}

	/**
	 * Returns all categories for provided post.
	 *
	 * @access private
	 *
	 * @param object $post_json JSON object of current post.
	 *
	 * @return array $post_categories Array containing all categories of provided post.
	 */
	private function get_post_categories( $post_json ) {
		// Categories are stored in tags field of JSON object.
		$categories_from_json = $post_json->payload->value->virtuals->tags;

		if ( ! is_array( $categories_from_json ) ) {
			return array();
		}

		$post_categories = array();

		foreach ( $categories_from_json as $category ) {
			$new_category = array(
				'slug' => $category->slug,
				'name' => $category->name,
			);

			$post_categories[] = $this->create_new_category( $new_category );
		}

		return $post_categories;
	}

	/**
	 * Returns the title of the current post.
	 *
	 * The title is stored in <header> section inside <h1> element.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current post HTML.
	 *
	 * @return string $post_title Post's title, or empty string if none is found.
	 */
	private function get_post_title( $xpath ) {
		$post_title = '';

		$nodes = $xpath->query( '//header//h1' );
		if ( $nodes->length ) {
			$post_title = $nodes->item( 0 )->textContent;
		}

		return $post_title;
	}

	/**
	 * Returns the subtitle of the current post.
	 *
	 * The subtitle is stored in <section class='e-content'> section inside <h4 class='graf--subtitle'> element.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current post HTML.
	 *
	 * @return string $post_title Post's title, or empty string if none is found.
	 */
	private function get_post_subtitle( $xpath ) {
		$post_title = '';

		$nodes = $xpath->query( '//section[contains(@class, "e-content")]//h4[contains(@class, "graf--subtitle")]' );
		if ( $nodes && $nodes->length ) {
			$post_title = $nodes->item( 0 )->textContent;
		}

		return $post_title;
	}

	/**
	 * Returns the subtitle of the current post.
	 *
	 * The image is stored in <img data-is-featured='true'> element.
	 * The caption is stored in the img parent node in the <figcaption class='imageCaption'> element.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current post HTML.
	 *
	 * @return string $post_title Post's title, or empty string if none is found.
	 */
	private function get_post_featured_image( $xpath ) {
		$featured_image = [];

		$img_nodes = $xpath->query( '//img[@data-is-featured="true"]' );
		if ( $img_nodes->length ) {
			$featured_image_node   = $img_nodes->item( 0 );
			$featured_image['url'] = $featured_image_node->attributes->getNamedItem( 'src' )->value;

			$caption_nodes             = $xpath->query( '//img[@data-is-featured="true"]//parent::*/figcaption[@class="imageCaption"]' );
			$featured_image['caption'] = $caption_nodes->length ? $caption_nodes->item( 0 )->textContent : '';
		}

		return $featured_image;
	}


	/**
	 * Returns the content of the current post.
	 *
	 * Content is contained in several section tags that are contained in
	 * the section that has data-field attribute set to 'body'.
	 *
	 * @access private
	 *
	 * @param object $doc DOMDocument object for current post.
	 * @param object $xpath DOMXPath object for current post HTML.
	 *
	 * @return string $post_title Post's content, or empty string if none is found.
	 */
	private function get_post_content( $doc, $xpath ) {
		$content = '';
		// Query all of the <section> tags and combine their contents.
		$nodes = $xpath->query( '//section[@data-field="body"]//section' );

		foreach ( $nodes as $node ) {
			// Get the contents of this section without including the actual section tag.
			foreach ( $node->childNodes as $child_node ) {
				$content .= $doc->saveHTML( $child_node );
			}
		}

		$content = $this->strip_html_tags( $content );

		return $content;
	}

	/**
	 * Adds provided categories to all categories list.
	 *
	 * If the category is already present in the list it won't be duplicated.
	 *
	 * @access private
	 *
	 * @param array $post_categories Array of categories for current post.
	 */
	private function add_categories( $post_categories ) {
		// Use category's slug as array key to avoid appending duplicates.
		foreach ( $post_categories as $post_category ) {
			$this->all_categories[ $post_category['slug'] ] = $post_category;
		}
	}

	/**
	 * Removes unneeded attributes from HTML code.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	public function remove_attributes( $xpath ) {
		foreach ( $this->attributes_to_remove as $attribute ) {
			$nodes = $xpath->query( "/html/body//*[@$attribute]" );

			foreach ( $nodes as $node ) {
				$node->removeAttribute( $attribute );
			}
		}
	}

	/**
	 * Removes all non-whitelisted tags from post's content.
	 *
	 * @see https://developer.wordpress.org/reference/functions/wp_kses_allowed_html/
	 * @see https://developer.wordpress.org/reference/functions/wp_kses/
	 *
	 * @param string $content HTML content of the post.
	 *
	 * @return string $content Content free of unwanted tags.
	 */
	public function strip_html_tags( $content ) {
		$allowed_tags = wp_kses_allowed_html( 'post' );

		// Remove unneeded divs from imported posts.
		unset( $allowed_tags['div'] );

		return wp_kses( $content, $allowed_tags );
	}

	/**
	 * Removes duplicate title from content.
	 *
	 * Exported HTML file contains the post title in header section,
	 * but it is also inserted at the start of post's content,
	 * where it does not originally belong.
	 *
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	private function remove_duplicate_title( $xpath ) {
		for ( $i = 1; $i <= 6; $i ++ ) {
			$nodes = $xpath->query( "//h{$i}[ contains( @class, 'graf--title' ) ]" );

			if ( $nodes->length ) {
				$duplicate_title = $nodes->item( 0 );
				$duplicate_title->parentNode->removeChild( $duplicate_title );

				return;
			}
		}
	}

	/**
	 * Removes duplicate sub-title from content.
	 *
	 * Exported HTML file contains the post title in header section,
	 * but it is also inserted at the start of post's content,
	 * where it does not originally belong.
	 *
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	private function remove_duplicate_subtitle( $xpath ) {
		for ( $i = 1; $i <= 6; $i ++ ) {
			$nodes = $xpath->query( "//h{$i}[ contains( @class, 'graf--subtitle' ) ]" );

			if ( $nodes->length ) {
				$duplicate_title = $nodes->item( 0 );
				$duplicate_title->parentNode->removeChild( $duplicate_title );

				return;
			}
		}
	}

	/**
	 * Removes unneeded <hr> tag that every exported post starts with.
	 *
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	private function remove_leading_hr( $xpath ) {
		$nodes = $xpath->query( '//hr' );

		if ( $nodes->length ) {
			$leading_hr = $nodes->item( 0 );
			$leading_hr->parentNode->removeChild( $leading_hr );
		}
	}

	/**
	 * Removes duplicate image tags from exported HTML.
	 *
	 * For every image in original Medium post there are three image tags
	 * present in exported HTML files. The first one is the thumbnail
	 * of the original image, the second is the original image itself,
	 * and the third one is nested inside noscript tag.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	private function remove_duplicate_images( $xpath ) {
		$images = $xpath->query( '//img' );

		foreach ( $images as $image ) {
			/*
			 * Actual images that we want to import have data-src attribute
			 * set instead of src. Let's assign that value to src first.
			 */
			$data_src = $image->getAttribute( 'data-src' );
			$src      = $image->getAttribute( 'src' );
			if ( ! empty( $data_src ) && empty( $src ) ) {
				$image->setAttribute( 'src', $data_src );
				$image->removeAttribute( 'data-src' );
			}

			// Thumbnail duplicates contain 'thumbnail' in class attribute.
			$img_class = $image->getAttribute( 'class' );
			if ( stristr( $img_class, 'thumbnail' ) ) {
				$image->parentNode->removeChild( $image );
			}
		}

		// Delete duplicate images contained inside noscript tags.
		$images = $xpath->query( '//noscript/img' );
		foreach ( $images as $image ) {
			$image->parentNode->removeChild( $image );
		}
	}

	/**
	 * Removes image tags with inaccessible URLs from exported HTML.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	private function remove_broken_images( $xpath ) {
		$images = $xpath->query( '//img' );

		foreach ( $images as $image ) {
			// remove the image that have NaN in the URL.
			if ( preg_match( '/\/NaN\/NaN\//', $image->getAttribute( 'src' ) ) === 1 ) {
				$image->parentNode->removeChild( $image );
			}
		}
	}

	/**
	 * Removes featured image from the post body.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	private function remove_featured_image( $xpath ) {
		$featured_image_figure_parent_node = $xpath->query( '//img[@data-is-featured="true"]/ancestor::figure' );
		if ( $featured_image_figure_parent_node->length ) {
			$featured_image_figure_parent_node = $featured_image_figure_parent_node->item( 0 );
			$featured_image_figure_parent_node->parentNode->removeChild( $featured_image_figure_parent_node );
		}
	}

	/**
	 * Replaces Medium's embedded content in current post.
	 *
	 * This functions first fetches the original URLs of embedded content,
	 * and then uses them for embedding if possible.
	 *
	 * @access private
	 *
	 * @param object $doc DOMDocument object for current post.
	 * @param object $xpath DOMXPath object for current HTML file.
	 * @param object $post_json JSON object for current post.
	 */
	private function replace_embeds( $doc, $xpath, $post_json = null ) {
		/*
		 * First type of embeds is exported as divs with graf--mixtapeEmbed class.
		 * For example, this is used for Flicker images (and possibly some other embedded images).
		 */
		$div_embeds = $xpath->query( "//div[ contains( @class, 'graf--mixtapeEmbed' ) ]" );

		foreach ( $div_embeds as $div_embed ) {
			$embed_link = $xpath->query( "//a[ contains( @class, 'markup--mixtapeEmbed-anchor' ) ]", $div_embed );

			if ( empty( $embed_link->item( 0 ) ) ) {
				continue;
			}

			$embed_url = $embed_link->item( 0 )->getAttribute( 'href' );

			$embed_content = '[embed]' . $embed_url . '[/embed]';

			$embed_node = $doc->createElement( 'div', $embed_content );

			$div_embed->parentNode->replaceChild( $embed_node, $div_embed );
		}

		/*
		 * Second type is used for video embeds (YouTube, Vimeo) and Twitter embeds and in exported HTML it shows up like:
		 * <figure name="3634" id="3634" class="graf graf--figure graf--iframe graf-after--p">
		 *     [ empty or content ]
		 * </figure>
		 *
		 * In most cases this is useless and won't produce any output when imported, but when we combine it with
		 * post's JSON representation it's possible to extract the actual embed URL. Here are the steps required:
		 *
		 * For each <figure> described above:
		 * 1. Locate object with name="{id}" in post's JSON, and find its 'mediaResourceId' (under iframe attribute).
		 * 2. Perform request to `https://medium.com/media/{mediaResourceId}?format=json` to fetch embeds's JSON representation.
		 *    (this is similar to JSON representation for posts)
		 * 3. Locate the 'href' attribute in the obtained JSON - that is the actual embed URL.
		 * 4. Replace <figure> node with obtained (embedded) URLs.
		 */
		if ( empty( $post_json ) ) {
			// Can't proceed with this if we don't have post's JSON.
			return;
		}

		// Recursively convert JSON objects to associative arrays.
		$post_json_array = json_decode( wp_json_encode( $post_json ), true );

		$paragraphs = $post_json_array['payload']['value']['content']['bodyModel']['paragraphs'];

		$figure_embeds = $xpath->query( "//figure[ contains( @class, 'graf--iframe' ) ]" );

		for ( $i = $figure_embeds->length - 1; $i >= 0; $i-- ) {
			$figure_embed = $figure_embeds->item( $i );
			// This id is the one that is also used in <figure>'s `name` and `id` attributes.
			$embed_id = $figure_embed->getAttribute( 'name' );

			$media_resource_id = '';
			foreach ( $paragraphs as $paragraph ) {
				if ( isset( $paragraph['name'] ) && $paragraph['name'] === $embed_id ) {
					// Locate `mediaResourceId` for given embed.
					$media_resource_id = $paragraph['iframe']['mediaResourceId'];
					break;
				}
			}

			// Fetch JSON for given embed.
			$embed_json = $this->fetch_post_json( 'https://medium.com/media/' . $media_resource_id );

			if ( is_wp_error( $embed_json ) ) {
				continue;
			}

			// Extract embed URL from returned JSON.
			$embed_url = $embed_json->payload->value->href;

			if ( wp_oembed_get( $embed_url ) ) {
				// Show embed if URL is embeddable.
				$embed_content = '[embed]' . $embed_url . '[/embed]';
				$embed_node    = $doc->createElement( 'div', $embed_content );
			} else {
				// Otherwise, default to showing link for given URL.
				$embed_node = $doc->createElement( 'a', $embed_url );
				$embed_node->setAttribute( 'href', $embed_url );
			}

			$figure_embed->parentNode->replaceChild( $embed_node, $figure_embed );
		}

	}

	/**
	 * Medium adds redirects for some links that appear in export data.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	private function replace_url_redirects( $xpath ) {
		// '/r/?url=' is prepended to actual URL which causes incorrect import.
		$links = $xpath->query( "//a [ starts-with( @href, '/r/?url=' ) ]" );

		foreach ( $links as $link ) {
			$href     = urldecode( $link->getAttribute( 'href' ) );
			$new_href = preg_replace( '/^\/r\/\?url\=/', '', $href, 1 );

			$link->setAttribute( 'href', $new_href );
		}
	}

	/**
	 * Replaces div background images with actual img tags.
	 *
	 * Some Medium images are not contained in <img> tags, but are instead displayed
	 * in <div> elements which contain inline css background-image property set to the
	 * actual URL of that image. This function replaces those occurrences with <img>
	 * tags and sets correct src attribute based on original image URL.
	 *
	 * @access private
	 *
	 * @see wp_extract_urls
	 * @link https://developer.wordpress.org/reference/functions/wp_extract_urls/
	 *
	 * @param object $doc DOMDocument object for current post.
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	private function replace_background_images( $doc, $xpath ) {
		$background_images = $xpath->query( "//div [ contains( @style, 'background-image' ) ]" );

		foreach ( $background_images as $background_image ) {
			/*
			 * We need to extract background image URL from style attribute.
			 * Example: style="background-image: url( [image-url] );"
			 */
			$style      = $background_image->getAttribute( 'style' );
			$image_urls = wp_extract_urls( $style );

			if ( empty( $image_urls ) ) {
				continue;
			}

			/*
			 * Enclose background images in figure tag to make it consistent with regular ones.
			 * This will also help add_image_caption_class handle these images too.
			 */
			$figure = $doc->createElement( 'figure' );
			$image  = $doc->createElement( 'img' );

			$image->setAttribute( 'src', $image_urls[0] );
			$figure->appendChild( $image );

			// Some really dirty code to extract image caption if it exists...
			$section = $background_image->parentNode->parentNode;

			if ( 'section' === $section->tagName ) {
				// Seventh son of a seventh son... :).
				$label = $section->lastChild->lastChild->lastChild;

				if ( 'label' === $label->tagName ) {
					$caption = $doc->createElement( 'figcaption', $label->nodeValue );

					$figure->appendChild( $caption );
					// Delete original caption to avoid duplication.
					$label->parentNode->removeChild( $label );
				}
			}

			$background_image->parentNode->replaceChild( $figure, $background_image );
		}
	}

	/**
	 * Used to assign appropriate WordPress CSS classes to caption text
	 * and captioned images.
	 *
	 * @access private
	 *
	 * @param object $xpath DOMXPath object for current HTML file.
	 */
	private function add_image_caption_class( $xpath ) {
		$captions = $xpath->query( '//figcaption' );

		foreach ( $captions as $caption ) {
			$figure = $caption->parentNode;

			if ( 'figure' === $figure->tagName ) {
				$figure->setAttribute( 'class', 'wp-caption' );
				$caption->setAttribute( 'class', 'wp-caption-text' );
			}
		}
	}

	/**
	 * Removes superfluous content by mutating DOMDocument of current HTML file.
	 *
	 * Medium's exported files contain superfluous content that will cause
	 * posts to be imported incorrectly (e.g. inline styles, duplicate images,
	 * iframes, duplicate title, redirected URLs in href attribute).
	 * Let's remove it here before it ends up in the WXR.
	 *
	 * @access private
	 *
	 * @param object $doc DOMDocument object for current post.
	 * @param object $xpath DOMXPath object for current HTML file.
	 * @param string $post_json The post in JSON.
	 */
	private function preprocess_current_post( $doc, $xpath, $post_json = null ) {
		$this->replace_url_redirects( $xpath );
		$this->replace_embeds( $doc, $xpath, $post_json );
		$this->replace_background_images( $doc, $xpath );
		/**
		 * Removal functions should be called after all the replacements have been
		 * performed, since the replace functions might need some attributes or
		 * elements that will ultimately be removed from the doc.
		 */
		$this->remove_duplicate_title( $xpath );
		$this->remove_duplicate_subtitle( $xpath );
		$this->remove_leading_hr( $xpath );
		$this->remove_duplicate_images( $xpath );
		$this->remove_broken_images( $xpath );
		$this->remove_featured_image( $xpath );
		$this->remove_attributes( $xpath );

		// After removals we can insert some WordPress specific attributes.
		$this->add_image_caption_class( $xpath );
	}

	/**
	 * A generator for extracting the HTML files from the provided Zip
	 *
	 * This generator is used by the main processing function to retrieve potential
	 * posts for importing from the Zip file stored in the `import_file_location`
	 * property. A `WP_Error` object is returned if the the Zip can't be read or is
	 * empty, otherwise any file that ends in `.html` whose content has a basic HTML
	 * structure will be read in to string and yielded.
	 *
	 * @param string $archive_file Path to the archive file.
	 *
	 * @return mixed The generator will yield the contents of the HTML files as strings,
	 * or return a WP_Error object on failure which can be retrieved using `getReturn`
	 */
	public function get_html_files( $archive_file ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'unzip_exec', __( 'Could not unzip your Medium export file.' ) );
		}
		$zip     = new \ZipArchive();
		$success = $zip->open( trim( $archive_file ) );
		if ( true !== $success || 0 === $zip->numFiles ) {
			return new \WP_Error( 'unzip_exec', __( 'Could not unzip your Medium export file.' ) );
		}

		for ( $file_index = 0; $file_index < $zip->numFiles; $file_index++ ) {
			$file_name = $zip->getNameIndex( $file_index );
			if ( ! $file_name || substr( $file_name, -5 ) !== '.html' ) {
				continue;
			}
			$html = $zip->getFromIndex( $file_index );
			if ( false !== $html && preg_match( '|^<!DOCTYPE html>.*<html>.*</html>$|is', trim( $html ) ) ) {
				yield $file_name => $html;
			}
		}
	}

	/**
	 * Create a new item structure with all of the keys prepopulated
	 *
	 * @param  array $item An array of item data to merge with the default values.
	 * @return array     The merged array of item data.
	 */
	private function create_new_item( $item = array() ) {
		return array_merge(
			array(
				'title'          => '',
				'link'           => '',
				'published'      => '',
				'post_date'      => '',
				'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', time() ),
				'author'         => null,
				'guid'           => '',
				'description'    => '',
				'content'        => '',
				'excerpt'        => '',
				'id'             => 0,
				'comment_status' => 'open',
				'ping_status'    => 'open',
				'post_name'      => '',
				'status'         => 'draft',
				'post_parent'    => '',
				'menu_order'     => 0,
				'post_type'      => 'post',
				'post_password'  => '',
				'is_sticky'      => 0,
				'comments'       => array(),
				'categories'     => array(),
				'tags'           => array(),
				'meta'           => array(),
			),
			$item
		);
	}

	/**
	 * Create a new category structure with all the keys prepopulated
	 *
	 * @param  array $category An array of category data to merge with the default values.
	 * @return array   The merged array of category data.
	 */
	private function create_new_category( $category = array() ) {
		return array_merge(
			array(
				'slug'   => '',
				'name'   => '',
				'domain' => 'category',
			),
			$category
		);
	}

	/**
	 * Creates a new post_meta entry.
	 *
	 * @param string $meta_key Meta key.
	 * @param string $meta_value Meta value.
	 *
	 * @return array
	 */
	protected function create_new_meta( $meta_key, $meta_value ) {
		return array(
			'key'   => $meta_key,
			'value' => $meta_value,
		);
	}

	/**
	 * Process the list of extracted HTML files.
	 *
	 * @param string $archive_file Path to the archive file.
	 *
	 * @return \WP_Error|bool True on success, WP_Error on failure.
	 */
	public function process_file( $archive_file ) {

		// Suppress warning about HTML5 tags in the html.
		$use_errors = libxml_use_internal_errors( true );

		$html_files = $this->get_html_files( $archive_file );

		foreach ( $html_files as $file => $html ) {
			$this->logger->log( self::$log_file, " -- Processing $file\n" );

			$doc = new \DOMDocument();
			$doc->loadHTML( $html );
			$xpath = new \DOMXPath( $doc );

			// If it's a profile file.
			if ( str_ends_with( $file, 'profile/profile.html' ) ) {
				$this->logger->log( self::$log_file, " -- Processing $file as profile\n" );

				// Get the display name.
				$display_name = $xpath->query( '//h3[contains(concat(" ", normalize-space(@class), " "), " p-name")]' )->item( 0 );

				if ( ! $display_name ) {
					// No display found, so this HTML file probably isn't a post.
					$this->logger->log( self::$log_file, self::$log_file, "   -- Skipping $file as no display found\n" );
					continue;
				}

				$display_name = $display_name->textContent;

				if ( empty( $display_name ) ) {
					// No display found, so this HTML file probably isn't a post.
					$this->logger->log( self::$log_file, self::$log_file, "   -- Skipping $file as no display found\n" );
					continue;
				}

				// check if the display name is an email.
				if ( str_contains( $display_name, '@' ) ) {
					$display_name = explode( '@', $display_name )[0];
				}

				// Get avatar.
				$avatar = $xpath->query( '//img[contains(concat(" ", normalize-space(@class), " "), " u-photo")]' )->item( 0 );

				if ( ! $avatar ) {
					// No display found, so this HTML file probably isn't a post.
					$this->logger->log( self::$log_file, self::$log_file, "   -- Skipping $file as no display found\n" );

					$avatar = '';
				} else {
					$avatar = $avatar->attributes->getNamedItem( 'src' )->nodeValue;
				}


				// Get email address.
				$email = $xpath->query( "//ul/li/b[contains(text(),'Email address')]/ancestor::li" )->item( 0 );

				if ( ! $email ) {
					// No display found, so this HTML file probably isn't a post.
					$this->logger->log( self::$log_file, self::$log_file, "   -- No email found\n" );
				}

				$email = str_replace( 'Email address: ', '', $email->textContent );

				$user_login = sanitize_user( $display_name, true );

				$this->author = [
					'user_login'   => $user_login,
					'display_name' => $display_name,
					'email'        => $email,
					'avatar'       => $avatar,
				];

				$this->logger->log( self::$log_file, "   -- Author:  {$display_name}\n" );

				continue;
			}

			// Create a new empty item to start off with.
			$item = $this->create_new_item();

			$_author = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " p-author ")]' )->item( 0 );

			if ( ! $_author ) {
				// No author found, so this HTML file probably isn't a post.
				$this->logger->log( self::$log_file, self::$log_file, "   -- Skipping $file as no author found\n" );
				continue;
			}

			$_author = $_author->textContent;

			if ( empty( $_author ) ) {
				// No author found, so this HTML file probably isn't a post.
				$this->logger->log( self::$log_file, self::$log_file, "   -- Skipping $file as no author found\n" );
				continue;
			}

			// Let's first extract the data that is present in HTML files.
			$item['title']          = $this->get_post_title( $xpath );
			$item['subtitle']       = $this->get_post_subtitle( $xpath );
			$item['featured_image'] = $this->get_post_featured_image( $xpath );

			/*
			 * After that, attempt to fetch additional post metadata from Medium.
			 * If we are unable to do this, we won't be able to import it correctly,
			 * so we will default to saving it as draft and adding appropriate metadata
			 * that will record the reasons for failure.
			 */
			$post_url = $this->get_post_url( $xpath );

			if ( is_wp_error( $post_url ) ) {
				$this->logger->log( self::$log_file, "   -- Skipping $file as no post URL found\n", Logger::WARNING );
				continue;
			}

			// We need post's JSON object in order to extract required metadata.
			$post_json = $this->fetch_post_json( $post_url );

			if ( is_wp_error( $post_json ) ) {
				$this->logger->log( self::$log_file, "   -- Skipping $file as no post JSON found\n", Logger::WARNING );
				continue;
			}

			// Skip comments, instead of importing them as posts.
			if ( $this->is_comment( $post_json ) ) {
				continue;
			}

			// We save some data that can be used as post meta.
			$item['post_url']      = $post_url;
			$item['original_id']   = $post_json->payload->value->id;
			$item['original_slug'] = $post_json->payload->value->uniqueSlug;

			/*
			 * We must do the pre-processing step before setting the content in order
			 * to prevent superfluous data from ending up in WXR. This call will
			 * mutate the $doc object of the current post.
			 */
			$this->preprocess_current_post( $doc, $xpath, $post_json );

			$item['content']         = $this->get_post_content( $doc, $xpath );
			$item['status']          = $this->get_post_status( $post_json );
			$item['post_date_gmt']   = $this->get_post_date_gmt( $post_json );
			$item['post_taxonomies'] = $this->get_post_categories( $post_json );

			// Add the newly created item to the list of items.
			$this->items[] = $item;
			// Add extracted categories to all categories list.
			$this->add_categories( $item['post_taxonomies'] );
		}

		// Set the value to what it was before we got a hold of it.
		libxml_use_internal_errors( $use_errors );

		$ret = $html_files->getReturn();
		if ( is_wp_error( $ret ) ) {
			return $ret;
		}

		// Success.
		return true;
	}
}
