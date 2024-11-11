<?php
/**
 * TODO. Explain, link, and credit.
 */

namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Psr\Log\LoggerInterface;

class FgHelper {
	private string $type;

	private LoggerInterface $cli_log;
	private LoggerInterface $file_log;
	private LoggerInterface $multilog;
	private string $function_prefix;
	private string $db_import_tables_prefix;

	public function __construct( string $type ) {
		$this->cli_log  = CliLog::get_logger( 'fg-helper' );
		$this->file_log = FileLog::get_logger( 'fg-helper' );
		$this->multilog = MultiLog::get_logger( 'fg-multi', [ $this->cli_log, $this->file_log ] );

		$type = strtolower( $type );
		if ( ! in_array( $type, [ 'drupal', 'joomla' ] ) ) {
			NMT::exit_with_message( sprintf( 'Invalid type "%s" is not supported', $type ), [ $this->cli_log ] );
		}

		switch ( $type ) {
			case 'drupal':
				$this->type                    = 'drupal';
				$this->function_prefix         = 'fgd2wp';
				$this->db_import_tables_prefix = 'drupal_';
				break;
			case 'joomla':
				$this->type                    = 'joomla';
				$this->function_prefix         = 'fgj2wp';
				$this->db_import_tables_prefix = 'drupal_';
				break;
		}

		// If a constant is defined, use it as the prefix for the import tables.
		if ( defined( 'NCCM_FG_MIGRATOR_PREFIX' ) && ! empty( NCCM_FG_MIGRATOR_PREFIX ) ) {
			$this->db_import_tables_prefix = NCCM_FG_MIGRATOR_PREFIX;
		}


		if ( ! defined( 'NCCM_SOURCE_WEBSITE_URL' ) ) {
			NMT::exit_with_message( 'NCCM_SOURCE_WEBSITE_URL is not defined in wp-config.php', [ $this->cli_log ] );
		}

		$this->type = $type;
	}

	private function add_hooks(): void {
		add_filter( "option_{$this->function_prefix}_options", [ $this, 'filter_options' ] );
	}

	/**
	 * Run the import.
	 *
	 * We simply wrap the import command from FG Joomla and add our hooks before running the import.
	 * Note that we can't batch this at all, so timeouts might be a thing.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import( array $pos_args, array $assoc_args ): void {
		$this->add_hooks();

		do_action( 'fg_helper_pre_import', [ $pos_args, $assoc_args ] );

		// Note that the 'launch' arg is important – without it the hooks above will not be registered.

		\WP_CLI::runcommand( "import-$this->type import", [ 'launch' => false ] );
	}

	/**
	 * Override the database connection details with environment variables.
	 *
	 * @param array $options Options for the fgj2wp plugin (it's fgj2wp_options in the database).
	 */
	public function filter_options( $options ): array {
		$options['hostname'] = getenv( 'DB_HOST' );
		$options['database'] = getenv( 'DB_NAME' );
		$options['username'] = getenv( 'DB_USER' );
		$options['password'] = getenv( 'DB_PASSWORD' );

		if ( empty( $options['hostname'] ) || empty( $options['database'] ) || empty( $options['username'] ) || empty( $options['password'] ) ) {
			NMT::exit_with_message( 'Could not get database connection details from environment variables.' );
		}

		$options['prefix'] = $this->db_import_tables_prefix;

		return $options;
	}

	/**
	 *
	 *
	 * @return string
	 */
	public function get_import_tables_prefix(): string {
		return $this->db_import_tables_prefix;
	}
}
