<?php

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Logic\Posts as PostsLogic;
use Newspack\MigrationTools\Logic\Shortcodes;
use Newspack\MigrationTools\Command\ShortcodeReplacementInterface;
use ReflectionMethod;
use ReflectionException;
use WP_CLI;

/**
 * Custom migration scripts for Posts' content.
 */
class ShortcodesMigrator implements WpCliCommandInterface {

	use WpCliCommandTrait;

	const POST_CONTENT_SHORTCODES_MIGRATION_LOG = 'POST_CONTENT_SHORTCODES_MIGRATION.log';

	/**
	 * @var PostsLogic.
	 */
	private $posts_logic;
	
	/**
	 * Shortcodes logic.
	 * 
	 * @var Shortcodes $shortcodes Shortcodes logic.
	 */
	private $shortcodes;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->posts_logic = new PostsLogic();
		$this->shortcodes  = new Shortcodes();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {
		return [
			[
				'newspack-content-migrator remove-shortcodes-from-post-body',
				self::get_command_closure( 'remove_shortcodes_from_post_body' ),
				[
					'shortdesc' => 'Remove shortcodes from post body.',
					'synopsis'  => array(
						array(
							'type'        => 'flag',
							'name'        => 'dry-run',
							'description' => 'Do a dry run simulation and don\'t actually edit the posts content.',
							'optional'    => true,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'shortcodes',
							'description' => 'List of shortcodes to delete from all the posts content separated by a comma (e.g. shortcode1,shortcode2)',
							'optional'    => false,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'post_ids',
							'description' => 'IDs of posts and pages to remove shortcodes from their content separated by a comma (e.g. 123,456)',
							'optional'    => true,
							'repeating'   => false,
						),
					),
				],
			],
			[
				'newspack-content-migrator replace-shortcodes-in-post-body',
				self::get_command_closure( 'replace_shortcodes_in_posts' ),
				[
					'shortdesc' => 'Replaces shortcodes from post body of all published posts and pages.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'shortcode',
							'description' => 'Shortcode name to replace, e.g. --shortcode=shortcode1 .',
							'optional'    => false,
							'repeating'   => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'replace-callback',
							'description' => 'Fully qualified path to a callback method which will be used to generate a replacement. E.g. --replace-callback="Vendor\Package\ClassA::myShortcodeReplacementMethod" . Must implement ShortcodeReplacementInterface.',
							'optional'    => false,
							'repeating'   => false,
						],
						[
							'type'        => 'flag',
							'name'        => 'dry-run',
							'description' => 'Do a dry run simulation and don\'t actually edit the posts content.',
							'optional'    => true,
							'repeating'   => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'post-ids',
							'description' => 'Optional, if not provided will replace for all posts and pages. IDs of posts and pages to remove shortcodes from their content separated by a comma (e.g. 123,456)',
							'optional'    => true,
							'repeating'   => false,
						],
					],
				],
			],
		];
	}
	
	/**
	 * Callable for `newspack-content-migrator replace-shortcodes-in-post-body`.
	 *
	 * @param array $args_pos   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function replace_shortcodes_in_posts( array $args_pos, array $assoc_args ): void {
		global $wpdb;
		
		$shortcode        = $assoc_args['shortcode'];
		$replace_callback = $assoc_args['replace-callback'];
		$post_ids         = isset( $assoc_args['post-ids'] ) ? explode( ',', $assoc_args['post-ids'] ) : null;
		$dry_run          = isset( $assoc_args['dry-run'] ) ? true : false;

		$post_types    = [ 'post', 'page' ];
		$post_statuses = [ 'publish' ];

		// Get the replacement class method.
		list( $class_name, $method_name ) = explode( '::', $replace_callback );
		try {
			$reflection_method = new ReflectionMethod( $class_name, $method_name );
		} catch ( ReflectionException $e ) {
			WP_CLI::error( sprintf( 'Invalid provided replacement callback `%s`. See comment description for example usage.', $replace_callback ) );
			exit( 1 );
		}
		
		// Check if $class_instance is instance of ShortcodeReplacementInterface.
		$class_instance = new $class_name();
		if ( ! $class_instance instanceof ShortcodeReplacementInterface ) {
			WP_CLI::error( sprintf( 'The class `%s` with method `%s` does not implement ShortcodeReplacementInterface.', $class_name, $method_name ) );
			exit( 1 );
		}
	
		// Get posts.
		if ( is_null( $post_ids ) ) {
			$post_ids = $this->posts_logic->get_all_posts_ids( $post_types, $post_statuses );
		}
		WP_CLI::line( sprintf( 'Searching total %s posts for shortodes and replacing them...', count( $post_ids ) ) );

		// Replace in all posts IDs.
		foreach ( $post_ids as $key => $post_id ) {
			
			$post_content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM $wpdb->posts WHERE ID = %d", $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( empty( $post_content ) || ! $this->shortcodes->has_shortcode( $shortcode, $post_content ) ) {
				continue;
			}
			
			// Replace in post_content, parse_blocks() handles both raw HTML and shortcode blocks.
			$content_blocks         = parse_blocks( $post_content );
			$content_blocks_updated = [];
			foreach ( $content_blocks as $content_block ) {
				
				/**
				 * If it's a shortcode block, replace the entire block.
				 */
				if ( 'core/shortcode' === $content_block['blockName'] ) {

					$found_shortcode = trim( $content_block['innerHTML'] );
					WP_CLI::line( sprintf( 'ID %d, replacing shortcode: %s', $post_id, $found_shortcode ) );

					// Get replacement.
					$replacement_for_shortcode = $reflection_method->invoke( $class_instance, $found_shortcode, $post_id );
					if ( false === $replacement_for_shortcode ) {
						WP_CLI::warning( sprintf( 'No replacement generated for shortcode: %s', $found_shortcode ) );
						$content_blocks_updated[] = $content_block;
						continue;
					}

					// Replace the whole shortcode block with a new replacement block.
					$replacement_block        = [
						'blockName'    => null,
						'attrs'        => [],
						'innerBlocks'  => [],
						'innerHTML'    => $replacement_for_shortcode,
						'innerContent' => [
							$replacement_for_shortcode,
						],
					];
					$content_blocks_updated[] = $replacement_block;

				} elseif (
					( 'core/html' === $content_block['blockName'] )
					|| ( 'core/paragraph' === $content_block['blockName'] )
					|| ( ! $content_block['blockName'] )
				) {

					/**
					* If the shortcode is inside any of these blocks -- Core HTML, Paragraph, Classic blocks,
					* and NULL 'blockName' which is raw HTML -- do replacements inside these blocks.
					*/
					$replacement_block = $content_block;

					// Get all shortcodes.
					$found_shortcodes = $this->shortcodes->get_all_shortcodes_from_content( $shortcode, $replacement_block['innerHTML'] );
					if ( ! $found_shortcodes ) {
						$content_blocks_updated[] = $replacement_block;
						continue;
					}
					
					// Replace shortcodes in innerHTML.
					foreach ( $found_shortcodes as $found_shortcode ) {
						// Output message just once in innerHTML, no need to repeat same finds in innerContent.
						WP_CLI::line( sprintf( 'ID %d, replacing shortcode: %s', $post_id, $found_shortcode ) );

						// Get replacement.
						$replacement_for_shortcode = $reflection_method->invoke( $class_instance, $found_shortcode, $post_id );
						if ( false === $replacement_for_shortcode ) {
							WP_CLI::warning( sprintf( 'No replacement generated for shortcode: %s', $found_shortcode ) );
							$content_blocks_updated[] = $content_block;
							continue;
						}
	
						// Do replacement.
						$replacement_block['innerHTML'] = str_replace( $found_shortcode, $replacement_for_shortcode, $replacement_block['innerHTML'] );
					}

					// Also replace shortcodes in innerContent.
					foreach ( $replacement_block['innerContent'] as $key_iner_content => $inner_content ) {
						$found_shortcodes = $this->shortcodes->get_all_shortcodes_from_content( $shortcode, $inner_content );

						foreach ( $found_shortcodes as $found_shortcode ) {
							// Get replacement.
							$replacement_for_shortcode = $reflection_method->invoke( $class_instance, $found_shortcode, $post_id );
							if ( false === $replacement_for_shortcode ) {
								WP_CLI::warning( sprintf( 'No replacement generated for shortcode: %s', $found_shortcode ) );
								$content_blocks_updated[] = $content_block;
								continue;
							}
							
							// Do replacement.
							$replacement_block['innerContent'][ $key_iner_content ] = str_replace( $found_shortcode, $replacement_for_shortcode, $replacement_block['innerContent'][ $key_iner_content ] );
						}
					}

					$content_blocks_updated[] = $replacement_block;
				}
			}

			// Save.
			if ( $content_blocks_updated !== $content_blocks ) {
				if ( ! $dry_run ) {
					$post_content_updated = serialize_blocks( $content_blocks_updated );
					// phpcs:disable -- WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update(
						$wpdb->prefix . 'posts',
						[ 'post_content' => $post_content_updated ],
						[ 'ID' => $post_id ]
					);
					// phpcs:enable
				}
			}
		}

		// For $wpdb->update() to sink in.
		wp_cache_flush();

		/**
		 * Do an extra QA and check if all shortcodes were replaced.
		 */
		$post_types_placeholders  = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$post_status_placeholders = implode( ',', array_fill( 0, count( $post_statuses ), '%s' ) );
		// phpcs:disable -- $wpdb->prepare is used and all params are prepared and escaped.
		// Remember, double %% is used to escape % in LIKE query.
		$post_ids_qa = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID
				FROM $wpdb->posts
				WHERE post_content LIKE '%%%s%%'
				AND post_type IN ($post_types_placeholders)
				AND post_status IN ($post_status_placeholders)",
				array_merge(
					[ '[' . $shortcode ],
					$post_types,
					$post_statuses
				)
			)
		);
		// phpcs:enable
		if ( ! empty( $post_ids_qa ) ) {
			WP_CLI::warning(
				sprintf(
					'Some shortcodes were not replaced in total %d posts of post_type `%s` and post_status `%s`. Example first 10 post IDs: %s',
					count( $post_ids_qa ),
					implode( ',', $post_types ),
					implode( ',', $post_statuses ),
					implode( ',', array_slice( $post_ids_qa, 0, 10 ) )
				) 
			);
		}
	}

	/**
	 * Callable for `newspack-content-migrator remove-shortcodes-from-post-body`.
	 */
	public function remove_shortcodes_from_post_body( $args, $assoc_args ) {
		$shortcodes = isset( $assoc_args['shortcodes'] ) ? explode( ',', $assoc_args['shortcodes'] ) : null;
		$post_ids   = isset( $assoc_args['post_ids'] ) ? explode( ',', $assoc_args['post_ids'] ) : null;
		$dry_run    = isset( $assoc_args['dry-run'] ) ? true : false;

		if ( is_null( $shortcodes ) || empty( $shortcodes ) ) {
			WP_CLI::error( 'Invalid shortcodes list.' );
		}

		if ( $dry_run ) {
			WP_CLI::warning( 'Dry mode, no changes are going to affect the database' );
		} else {
			WP_CLI::confirm( 'This will remove all the shortcodes with their content from all the posts content, do you want to continue?' );
		}

		$this->posts_logic->throttled_posts_loop(
			array(
				'post_type'   => array( 'post', 'page' ),
				'post_status' => array( 'publish' ),
				'post__in'    => $post_ids,
			),
			function( $post ) use ( $shortcodes, $dry_run ) {
				$post_content_blocks = array();

				foreach ( parse_blocks( $post->post_content ) as $content_block ) {
					// remove shortcodes from Core shortcode, Core HTML, Paragraph, and Classic blocks.
					if (
						'core/shortcode' === $content_block['blockName']
						|| 'core/html' === $content_block['blockName']
						|| ( 'core/paragraph' === $content_block['blockName'] )
						|| ( ! $content_block['blockName'] )
					) {
						$pattern = get_shortcode_regex( $shortcodes );

						if ( preg_match_all( '/' . $pattern . '/s', $content_block['innerHTML'], $matches )
							&& array_key_exists( 2, $matches )
						) {
							$content_without_shortcodes = $this->strip_shortcodes( $shortcodes, $content_block['innerHTML'] );
							// remove resulting empty paragraphs if any.
							$cleaned_content = trim( preg_replace( '/<p[^>]*><\\/p[^>]*>/', '', $content_without_shortcodes ) );

							if ( empty( $cleaned_content ) ) {
								$content_block = null;
								continue;
							}

							$content_block['innerHTML']    = $cleaned_content;
							$content_block['innerContent'] = array_map(
								function( $inner_content ) use ( $shortcodes ) {
									return $this->strip_shortcodes( $shortcodes, $inner_content );
								},
								$content_block['innerContent']
							);
						}
					}

					$post_content_blocks[] = $content_block;
				}

				$post_content_without_shortcodes = serialize_blocks( $post_content_blocks );

				if ( $post_content_without_shortcodes !== $post->post_content ) {
					if ( ! $dry_run ) {
						$update = wp_update_post(
							array(
								'ID'           => $post->ID,
								'post_content' => $post_content_without_shortcodes,
							)
						);

						if ( is_wp_error( $update ) ) {
							$this->log( self::POST_CONTENT_SHORTCODES_MIGRATION_LOG, sprintf( 'Failed to update post %d because %s', $post->ID, $update->get_error_message() ) );
						} else {
							$this->log( self::POST_CONTENT_SHORTCODES_MIGRATION_LOG, sprintf( 'Post %d cleaned from shortcodes.', $post->ID ) );
						}
					} else {
						WP_CLI::line( sprintf( 'Post %d cleaned from shortcodes.', $post->ID ) );
						WP_CLI::line( $post_content_without_shortcodes );
					}
				}
			}
		);
	}

	/**
	 * Strip shortcodes from content.
	 *
	 * @param string[] $shortcodes Shortcodes to strip.
	 * @param string   $text Content to strip the shortcodes from.
	 * @return string
	 */
	private function strip_shortcodes( $shortcodes, $text ) {
		if ( ! ( empty( $shortcodes ) || ! is_array( $shortcodes ) ) ) {
			$tagregexp = join( '|', array_map( 'preg_quote', $shortcodes ) );
			$regex     = '\[(\[?)';
			$regex    .= "($tagregexp)";
			$regex    .= '\b([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)';

			$text = preg_replace( "/$regex/s", '', $text );
		}

		return $text;
	}

	/**
	 * Simple file logging.
	 *
	 * @param string  $file    File name or path.
	 * @param string  $message Log message.
	 * @param boolean $to_cli Display the logged message in CLI.
	 */
	private function log( $file, $message, $to_cli = true ) {
		$message .= "\n";
		if ( $to_cli ) {
			WP_CLI::line( $message );
		}
		file_put_contents( $file, $message, FILE_APPEND );
	}
}
