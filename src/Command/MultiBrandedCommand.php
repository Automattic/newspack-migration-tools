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
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\PostSelect;
use WP;

class MultiBrandedCommand implements WpCliCommandInterface {

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {

		return [
			[
				'newspack-migration-tools multi-branded assign-all-posts-from-categories-to-brands',
				[ __CLASS__, 'cmd_posts_from_categories_to_brands' ],
				[
					'shortdesc' => 'Assigns all the posts from one or more given categories to one or more given brands. Note, this does not assign a category to a brand, but rather assigns all posts from a category to a brand, it assigns individual posts to one or more brands.',
					'synopsis'  => [
						[
							'category-id'     => [
								'type'        => 'assoc',
								'name'        => 'category-ids-csv',
								'description' => 'CSV IDs of categories with all the posts.',
								'optional'    => false,
								'repeating'   => false,
							],
							'brand-id'     => [
								'type'        => 'assoc',
								'name'        => 'brand-ids-csv',
								'description' => "CSV IDs of brands to assign to all posts from given category/categories.",
								'optional'    => false,
								'repeating'   => false,
							],
						]
					],
				],
			],
		];
	}

	public function cmd_posts_from_categories_to_brands( array $pos_args, array $assoc_args ): void {

		// Get params.
		$category_ids = explode( ',', $assoc_args['category-ids-csv'] );
		$brand_ids   = explode( ',', $assoc_args['brand-ids-csv'] );

		// Validate categories.
		$category_ids_to_names = [];
		foreach ( $category_ids as $category_id ) {
			$category = get_term( $category_id, 'category' );
			if ( is_null( $category ) || is_wp_error( $category ) ) {
				throw new UnexpectedValueException( sprintf( 'Category with ID %d does not exist.', $category_id ) );
			}
			$category_ids_to_names[ $category_id ] = $category->name;
		}

		// Validate brands.
		$brand_ids_to_names = [];
		foreach ( $brand_ids as $brand_id ) {
			$brand = get_term( $brand_id, 'brand' );
			if ( is_null( $brand ) || is_wp_error( $brand ) ) {
				throw new UnexpectedValueException( sprintf( 'Brand with ID %d does not exist.', $brand_id ) );
			}
			$brand_ids_to_names[ $brand_id ] = $brand->name;
		}

		\WP_CLI::line( sprintf(
			'About to assign all posts from categories `%s` to brands `%s`',
			implode( ', ', array_values( $category_ids_to_names ) ),
			implode( ', ', array_values( $brand_ids_to_names ) )
		) );
		
		// Get all posts in category.
		$posts = new Posts();
		foreach ( $category_ids as $category_id ) {
			$post_ids = $posts->get_all_posts_ids_in_category( $category_id );
			$category_name = get_term( $category_id )->name;
			$msg = sprintf( 'Updating %d posts in category %d,`%s` ...', count( $post_ids ), $category_id, $category_name );
			\WP_CLI::line( $msg );
			file_put_contents( 'cat_posts_to_brands.csv', sprintf( "%s\n", $msg ), FILE_APPEND );
			foreach ( $post_ids as $post_id ) {
				// Set brand(s).
				foreach ( $brand_ids as $key_brand_id => $brand_id ) {
					$append = $key_brand_id > 0;
					// wp_set_object_terms takes one term at a time, and it's crucial that it's an integer.
					wp_set_object_terms( $post_id, (int) $brand_id, 'brand', $append );
				}

				file_put_contents( 'cat_posts_to_brands.csv', sprintf( "post_id:%d brand_ids:%s\n", $post_id, implode( ',', $brand_ids ) ), FILE_APPEND );
			}
		}
	}
}
