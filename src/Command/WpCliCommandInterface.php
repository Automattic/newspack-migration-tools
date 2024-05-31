<?php

namespace Newspack\MigrationTools\Command;

interface WpCliCommandInterface {

	public static function get_instance(): self;

	public function get_cli_commands(): array;

}