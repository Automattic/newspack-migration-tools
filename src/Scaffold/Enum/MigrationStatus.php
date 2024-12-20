<?php

namespace Newspack\MigrationTools\Scaffold\Enum;

/**
 * Represents the migration status.
 */
enum MigrationStatus: int {
	case STARTING  = 0;
	case STARTED   = 1;
	case RUNNING   = 2;
	case COMPLETED = 3;
	case FAILED    = 4;
	case CANCELLED = 5;
}
