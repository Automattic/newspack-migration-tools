<?php

namespace Newspack\MigrationTools\Command\Json;

use Exception;
use Laminas\Xml2Json\Xml2Json;
use Newspack\MigrationTools\Command\WpCliCommandInterface;

// phpcs:ignore Squiz.PHP.CommentedOutCode.Found,Squiz.Commenting.InlineComment.InvalidEndChar
// use NewspackCustomContentMigrator\Utils\CommonDataFileIterator\CSVFile;
use WP_CLI;
use WP_Filesystem_Base;

/**
 * Converts a CSV or an XML file to JSON format.
 */
class XmlConverter implements WpCliCommandInterface {

	/**
	 * List of supported file extensions.
	 *
	 * @var array|string[] $supported_extensions Supported file extensions.
	 */
	private static array $supported_extensions = [
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		// 'csv', // TODO - Once CSVFile is moved to Newspack Migration Tools, uncomment this line.
		'xml',
	];

	/**
	 * Returns the CLI commands for this class.
	 *
	 * @inheritDoc
	 */
	public static function get_cli_commands(): array {
		return [
			[
				'newspack-migration-tools convert-directory-to-json',
				[
					__CLASS__,
					'cmd_convert_directory_to_json',
				],
				[
					'shortdesc' => 'Converts a CSV or an XML file to JSON format.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'directory',
							'description' => 'The directory where the CSV or XML file is located.',
							'optional'    => false,
							'repeating'   => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'output-directory',
							'description' => 'The directory where the JSON files will be saved.',
							'optional'    => true,
							'repeating'   => false,
						],
					],
				],
			],
			[
				'newspack-migration-tools convert-file-to-json',
				[
					__CLASS__,
					'cmd_convert_file_to_json',
				],
				[
					'shortdesc' => 'Converts a single CSV or XML file to JSON format.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'file',
							'description' => 'The path to the CSV or XML file.',
							'optional'    => false,
							'repeating'   => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'output-directory',
							'description' => 'The directory where the JSON files will be saved.',
							'optional'    => true,
							'repeating'   => false,
						],
					],
				],
			],
		];
	}

	/**
	 * Converts any CSV or XML files in a directory to JSON format.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 * @throws WP_CLI\ExitException If invalid directory provided.
	 */
	public static function cmd_convert_directory_to_json( array $args, array $assoc_args ): void {
		$target_directory = untrailingslashit( $assoc_args['directory'] );
		$output_directory = untrailingslashit( $assoc_args['output-directory'] ?? $target_directory );

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		/* @var $wp_filesystem WP_Filesystem_Base */

		if ( ! file_exists( $target_directory ) || ! is_dir( $target_directory ) ) {
			WP_CLI::error( 'Invalid directory provided.' );
		}

		$supported_extensions_string = implode( ',', static::$supported_extensions );

		$files = glob( $target_directory . '/*.{' . $supported_extensions_string . '}', GLOB_BRACE );

		foreach ( $files as $file ) {
			static::convert_file_to_json( $file, $output_directory );
		}

		WP_CLI::log( 'Conversion complete.' );
	}

	/**
	 * Converts a single CSV or XML file to JSON format.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 * @throws WP_CLI\ExitException If an invalid directory is provided.
	 */
	public static function cmd_convert_file_to_json( array $args, array $assoc_args ): void {
		$file_path        = untrailingslashit( $args[0] );
		$output_directory = untrailingslashit( $assoc_args['output-directory'] ?? dirname( $file_path ) );

		if ( ! file_exists( $output_directory ) || ! is_dir( $output_directory ) ) {
			WP_CLI::error( 'Invalid output directory provided.' );
		}

		static::convert_file_to_json( $file_path, $output_directory );

		WP_CLI::log( 'Conversion complete.' );
	}

	/**
	 * Converts a single CSV or XML file to JSON format.
	 *
	 * @param string $file_path Path to the CSV or XML file.
	 * @param string $output_directory Directory where the JSON file will be saved.
	 *
	 * @return void
	 * @throws WP_CLI\ExitException If the file does not exist.
	 */
	private static function convert_file_to_json( string $file_path, string $output_directory ) {
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		/* @var $wp_filesystem WP_Filesystem_Base */
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		WP_CLI::log( "Processing $file_path...\n" );

		if ( ! file_exists( $file_path ) ) {
			WP_CLI::error( 'Invalid file provided.' );
		}

		$file_name = pathinfo( $file_path, PATHINFO_FILENAME );
		$ext       = pathinfo( $file_path, PATHINFO_EXTENSION );
		$ext       = strtolower( $ext );

		if ( ! in_array( $ext, static::$supported_extensions, true ) ) {
			WP_CLI::error( "Unsupported file type: $ext" );
		}

		switch ( $ext ) {
			case 'csv':
				// TODO - move CSVFile class to Newspack Migration Tools
				// $data = iterator_to_array( ( new CSVFile( $file_path ) )->getIterator() );
				break;
			case 'xml':
				$data = json_decode(
					Xml2Json::fromXml(
						$wp_filesystem->get_contents( $file_path ),
						false
					),
					true
				);
				break;
		}

		$json_data = wp_json_encode( $data, JSON_PRETTY_PRINT );

		$output_file = $output_directory . '/' . $file_name . '.json';

		$wp_filesystem->put_contents( $output_file, $json_data );

		WP_CLI::success( "Converted $output_file." );
	}
}
