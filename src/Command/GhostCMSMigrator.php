<?php
/**
 * GhostCMSMigrator class.
 * 
 * @link: https://ghost.org/
 *
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Logic\GhostCMSHelper;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;

/**
 * GhostCMS general Migrator command class.
 */
class GhostCMSMigrator implements WpCliCommandInterface {

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {
		
		return [
			[
				'newspack-migration-tools ghostcms-import',
				[ __CLASS__, 'cmd_ghostcms_import' ],
				[
					'shortdesc' => 'Import content from Ghost JSON export.',
					'synopsis'  => array(

						// required:
						array(
							'type'        => 'assoc',
							'name'        => 'default-user-id',
							'description' => 'User ID for default "post_author" for wp_insert_post(). Integer.',
							'optional'    => false,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'ghost-url',
							'description' => 'Public URL of current/live Ghost Website. Scheme with domain: https://www.mywebsite.com',
							'optional'    => false,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'json-file',
							'description' => 'Path to Ghost JSON export file.',
							'optional'    => false,
							'repeating'   => false,
						),

						// optional:
						array(
							'type'        => 'assoc',
							'name'        => 'visibility-csv',
							'description' => 'Comma separated list of post visibility (i.e. post status) values to import. ' .
											'This command will scan for existing visibilities in ALL posts in the JSON data before applying any filters (like --created-after). ' .
											'You will be warned if there are multiple visibility values besides the default `public`. ' .
											'Note: The visibility scan in this command reports on the entire dataset, which may include visibility values not present in the filtered date range. ' .
											'E.g. `--visibility-csv=public,members,paid,tiers`.',
							'optional'    => true,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'created-after',
							'description' => 'Datetime cut-off to only import posts AFTER this date. (Must be parseable by strtotime).',
							'optional'    => true,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'json-data-path',
							'description' => 'Standard jq-style path notation to node in JSON where posts (and other objects) are stored (e.g., `.db[0].data` or `.data`). ' . 
											'To test for path, use jq commands like: ' .
											" - list posts:  `jq '.db[0].data.posts' export.json` " .
											" - count posts: `jq '.db[0].data.posts | length' export.json` " .
											'Default value (path to posts) is `.db[0].data`.',
							'optional'    => true,
							'repeating'   => false,
						),

					),
				],
			],
			[
				'newspack-migration-tools ghostcms-check-imported-posts-for-custom-html-content',
				[ __CLASS__, 'cmd_check_imported_posts_for_custom_html_content' ],
				[
					'shortdesc' => "After the content has been imported, run this command to check the imported posts for yet unvalidated/unsupported custom HTML content/syntax from the Ghost Koenig editor (such as different custom embeds, or Ghost's equivalents to Gutenberg blocks). This scans all HTML elements with kg-* classes.",
				],
			],
		];
	}
	
	/**
	 * GhostCMS Import command.
	 */
	public static function cmd_ghostcms_import( array $pos_args, array $assoc_args ): void {

		// Set log slug: Class name without namespace plus function: "GhostCMSMigrator_cmd_ghostcms_import" .
		$log_slug = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__;

		$logger = MultiLog::get_logger( 
			'multi-' . $log_slug,
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			]
		);
		
		$logger->info( 'Starting CLI - GhostCMS Import...' );

		// Do helper.
		$helper = new GhostCMSHelper();
		$helper->ghostcms_import( $pos_args, $assoc_args, $log_slug );
	}

	/**
	 * Callable for the 'newspack-migration-tools ghostcms-check-imported-posts-for-custom-html-content' command.
	 * 
	 * @param array $pos_args The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public static function cmd_check_imported_posts_for_custom_html_content( array $pos_args, array $assoc_args ): void {
		$logger = MultiLog::get_cli_and_file_logger( __FUNCTION__ );
		$logger->info( 'Starting CLI - Scanning imported posts for custom HTML content...' );

		$helper = new GhostCMSHelper();
		$helper->cmd_check_imported_posts_for_custom_html_content( $pos_args, $assoc_args, $logger );
	}
}
