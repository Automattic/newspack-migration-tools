<?php
/**
 * Custom migration scripts for Envira Gallery Lite.
 * 
 * @package NewspackCustomContentMigrator\Command\General
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Command\ShortcodeReplacementInterface;
use Newspack\MigrationTools\Logic\Shortcodes;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use WP_CLI;

/**
 * Custom migration scripts for Envira Gallery Lite.
 */
class EnviraGalleryMigrator implements WpCliCommandInterface, ShortcodeReplacementInterface {

	// Envira Gallery postmeta key containing images belonging to gallery.
	const ENVIRA_POSTMETA_KEY_ATTACHED_IMAGES = '_eg_in_gallery';

	/**
	 * Shortcodes instance.
	 * 
	 * @var Shortcodes $shortcode Shortcodes instance.
	 */
	private $shortcode;

	/**
	 * GutenbergBlockGenerator instance.
	 * 
	 * @var GutenbergBlockGenerator
	 */
	private $gutenberg;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->shortcode = new Shortcodes();
		$this->gutenberg = new GutenbergBlockGenerator();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {
		return [
			[
				'newspack-migration-tools envira-gallery-lite-to-gutenberg-gallery',
				[ __CLASS__, 'cmd_migrate_envira_galleries' ],
				[
					'shortdesc' => 'Converts Envira Gallery Lite galleries to Gutenberg Gallery blocks throughout all Posts and Pages.',
					'synopsis'  => [
						[
							'type'      => 'flag',
							'name'      => 'dry-run',
							'optional'  => true,
							'repeating' => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'post-ids',
							'description' => 'Optional, if not provided will replace for all posts and pages. IDs of posts and pages to remove shortcodes from their content separated by a comma (e.g. 123,456)',
							'optional'    => true,
							'repeating'   => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'post-types',
							'description' => 'Optional CSV of post types to replace shortcodes from their content, if not provided will replace for all posts and pages. E.g. post,page',
							'optional'    => true,
							'repeating'   => false,
							'default'     => 'post,page',
						],
					],
				],
			],
		];
	}

	/**
	 * Command handler for migrating Envira galleries.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_migrate_envira_galleries( $pos_args, $assoc_args ) {
		$dry_run        = isset( $assoc_args['dry-run'] ) ?? false;
		$post_ids_csv   = isset( $assoc_args['post-ids'] ) ? $assoc_args['post-ids'] : null;
		$post_types_csv = isset( $assoc_args['post-types'] ) ? $assoc_args['post-types'] : 'post,page';

		// Leverage and use existing Shortcodes replacement command with a replacement for Envira shortcodes.
		WP_CLI::runcommand(
			sprintf(
				'newspack-content-migrator replace-shortcodes-in-post-body --shortcode=envira-gallery --replace-callback=Newspack\MigrationTools\Command\EnviraGalleryMigrator::replace_shortcode %s %s %s',
				$dry_run ? '--dry-run' : '',
				$post_ids_csv ? '--post-ids=' . $post_ids_csv : '',
				$post_types_csv ? '--post-types=' . $post_types_csv : ''
			),
			[
				'return'     => false,
				'launch'     => false,
				'exit_error' => true,
			]
		);
	}

	/**
	 * Replace Envira Gallery shortcode with Gutenberg gallery block.
	 *
	 * @param string $shortcode The shortcode to replace.
	 * @param int    $post_id   The post ID where the shortcode is used.
	 * 
	 * @return string|false The replacement HTML or false if replacement failed.
	 */
	public function replace_shortcode( string $shortcode, int $post_id ): string|false {
		global $wpdb;

		// Clean up and decode shortcode text.
		$decoded_shortcode = $this->shortcode->decode_shortcode( $shortcode );

		// Get gallery ID.
		$id = $this->shortcode->get_shortcode_attribute( 'id', $decoded_shortcode );

		// Get the gallery post row, check if post_type is 'envira'.
		$gallery_post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( 'envira' !== $gallery_post->post_type ) {
			WP_CLI::warning( sprintf( 'Envira gallery `id` %d used in post ID %d not found.', $id, $post_id ) );
			return false;
		}

		// Get attachment IDs from postmeta meta_key = '_eg_in_gallery'.
		// phpcs:disable -- WordPress.DB.DirectDatabaseQuery.NoCaching
		$att_ids = maybe_unserialize(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s",
					$id,
					self::ENVIRA_POSTMETA_KEY_ATTACHED_IMAGES
					) 
					) 
				);
		// phpcs:enable
		if ( empty( $att_ids ) ) {
			WP_CLI::warning( sprintf( 'Envira gallery `id` %d in post ID %d has no attached images.', $id, $post_id ) );
			return false;
		}

		// Generate the tiled gallery block replacement.
		$gutenberg_block            = $this->gutenberg->get_jetpack_tiled_gallery( $att_ids, 'media' );
		$gutenberg_block_serialized = serialize_block( $gutenberg_block );

		return $gutenberg_block_serialized;
	}
}
