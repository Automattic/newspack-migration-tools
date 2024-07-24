<?php
/**
 * GhostCMS Migration Logic.
 *
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Logic;

use Newspack\MigrationTools\Log\CliLogger;
use WP_Error;

/**
 * GhostCMS Helper.
 */
class GhostCMSHelper {

	public static function go(): int|WP_Error {

		CliLogger::warning( "this is a warning" );

		return 0;

	}

}
