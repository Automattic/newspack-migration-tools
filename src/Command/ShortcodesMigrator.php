<?php

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Logic\Posts as PostsLogic;
use ShortcodeReplacementInterface;
use ReflectionMethod;
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
	 * Constructor.
	 */
	private function __construct() {
		$this->posts_logic = new PostsLogic();
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
	
	public function replace_shortcodes_in_posts( $args, $assoc_args ) {
		$shortcode        = $assoc_args['shortcode'];
		$replace_callback = $assoc_args['replace-callback'];
		$post_ids         = isset( $assoc_args['post-ids'] ) ? explode( ',', $assoc_args['post-ids'] ) : null;
		$dry_run          = isset( $assoc_args['dry-run'] ) ? true : false;

		WP_CLI::line( '> replace_shortcodes_in_posts' );
		
		$post_id = 123;
		
		// Invoke the replacement method with arguments (see inteface ShortcodeReplacementInterface for the arguments).
		list( $class_name, $method_name ) = explode( '::', $replace_callback );
		$reflection_method                = new ReflectionMethod( $class_name, $method_name );
		$class_instance                   = new $class_name();		$result                           = $reflection_method->invoke( $class_instance, $shortcode, $post_id );

		WP_CLI::line( 'end.' );
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
