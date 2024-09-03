<?php

namespace Newspack\MigrationTools\Command;

/**
 * Utility trait for implementing the WpCliCommandInterface interface.
 *
 * Use this trait so you don't have to copy-paste the constructor.
 */
trait WpCliCommandTrait {

	/**
	 * Constructor.
	 *
	 * I don't do anything at all and that is on purpose. You probably don't want to override this.
	 */
	private function __construct() {
		// Nothing.
	}

}
