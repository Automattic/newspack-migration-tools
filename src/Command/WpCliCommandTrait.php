<?php

namespace Newspack\MigrationTools\Command;

/**
 * Utility trait for implementing the WpCliCommandInterface interface.
 *
 * Use this trait so you don't have to copy-paste the singleton instance code and constructor.
 */
trait WpCliCommandTrait {

	/**
	 * Private constructor.
	 *
	 * I don't do anything, and it's on purpose. If you need a lot of initialization in your class,
	 * then make a method for it and call it when you start your command. The reason is that the
	 * get_instance() method will instantiate the class, even if you don't need it. So, use the
	 * constructor sparingly.
	 */
	private function __construct() {
		// Nothing.
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
