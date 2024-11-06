<?php
/**
 * Commands functionality for Multibranded plugin https://github.com/Automattic/newspack-multibranded-site.
 *
 * Methods for dealing with multi branded functionality .
 */

namespace Newspack\MigrationTools\Command;

use UnexpectedValueException;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\PlainFileLog;
use Bramus\Monolog\Formatter\ColoredLineFormatter;

class MultiBrandedCommand implements WpCliCommandInterface {

	/**
	 * CLI logger.
	 *
	 * @var CliLog $logger_cli CLI Logger.
	 */
	private $logger_cli;
	
	/**
	 * CLI logger plain, just level and message.
	 *
	 * @var CliLog $logger_cli_plain CLI Logger.
	 */
	private $logger_cli_plain;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// CLI log, just message.
		$this->logger_cli = CliLog::get_logger( 'cli' );
		// CLI log, just level and message.
		$this->logger_cli_plain = CliLog::get_logger( 'cli-level-message', new ColoredLineFormatter( null, "%level_name%: %message%\n", null, true ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {

		return [
			[
				'newspack-migration-tools multi-branded assign-brands-to-all-posts-in-category',
				[ __CLASS__, 'cmd_assign_brands_to_posts_in_category' ],
				[
					'shortdesc' => 'Assigns one or more brands to every individual post in a category.',
					'synopsis'  => [
						[
							'category-id' => [
								'type'        => 'assoc',
								'name'        => 'category-id',
								'description' => 'Category with the posts.',
								'optional'    => false,
								'repeating'   => false,
							],
							'brand-id'    => [
								'type'        => 'assoc',
								'name'        => 'brand-ids-csv',
								'description' => 'CSV IDs of brands to assign to every individual post in category.',
								'optional'    => false,
								'repeating'   => false,
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Assigns one or more brands to every individual post in a category.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * 
	 * @throws UnexpectedValueException If category or brand does not exist.
	 * 
	 * @return void
	 */
	public function cmd_assign_brands_to_posts_in_category( array $pos_args, array $assoc_args ): void {

		// Get params.
		$category_id = $assoc_args['category-id'];
		$brand_ids   = explode( ',', $assoc_args['brand-ids-csv'] );

		// Validate category.
		$category = get_term( $category_id, 'category' );
		if ( is_null( $category ) || is_wp_error( $category ) ) {
			throw new UnexpectedValueException( sprintf( 'Category with ID %d does not exist.', wp_kses( $category_id ) ) );
		}
		$category_name = $category->name;

		// Validate brands.
		$brand_ids_to_names = [];
		foreach ( $brand_ids as $brand_id ) {
			$brand = get_term( $brand_id, 'brand' );
			if ( is_null( $brand ) || is_wp_error( $brand ) ) {
				throw new UnexpectedValueException( sprintf( 'Brand with ID %d does not exist.', wp_kses( $brand_id ) ) );
			}
			$brand_ids_to_names[ $brand_id ] = $brand->name;
		}

		// Plain file logger.
		$log_filename     = 'assign_posts_in_categories_to_brands.log';
		$logger_plainfile = PlainFileLog::get_logger( 'plainfile-demo', $log_filename );

		// Get all posts in category.
		$posts    = new Posts();
		$post_ids = $posts->get_all_posts_ids_in_category( $category_id );
		
		// Log.
		$msg = sprintf( 'Assigning brands %s `%s` to %d posts in category %d `%s` ...', implode( ', ', array_keys( $brand_ids_to_names ) ), implode( ', ', array_values( $brand_ids_to_names ) ), count( $post_ids ), $category_id, $category_name );
		$this->logger_cli->info( $msg );
		$logger_plainfile->info( $msg );

		// Assign brands to posts.
		foreach ( $post_ids as $post_id ) {
			$this->set_brands_to_post( $post_id, $brand_ids );
			// Log IDs to file.
			$logger_plainfile->notice( sprintf( 'post_id:%d brand_ids:%s', $post_id, implode( ',', $brand_ids ) ) );
		}

		$this->logger_cli->info( sprintf( 'Done 👍 Check %s for IDs.', $log_filename ) );
	}

	/**
	 * Assigns brands to a post.
	 * 
	 * @param int   $post_id     Post ID.
	 * @param array $brand_ids Brand IDs.
	 * @return void
	 */
	private function set_brands_to_post( int $post_id, array $brand_ids ): void {
		// Brand IDs passed to wp_set_object_terms must be strictly integers, otherwise they will be
		// treated as new brand names and created as new brands/terms.
		$brand_ids = array_map( 'intval', $brand_ids );

		wp_set_object_terms( $post_id, $brand_ids, 'brand' );
	}
}
