# AI agent guidelines for newspack-migration-tools

Shared PHP library (Composer package) providing migration helpers, WP-CLI commands, and utilities for migrating content to WordPress. No frontend code. Consumed as a dependency by `newspack-custom-content-migrator` and other migration plugins.

## Core architecture

Two patterns coexist for WP-CLI commands. Both implement `WpCliCommandInterface` with a `get_cli_commands()` method that returns arrays of `WP_CLI::add_command()` arguments.

### Pattern 1: static methods (preferred for new NMT commands)

```php
<?php

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Util\Log\MultiLog;

class ExampleMigrator implements WpCliCommandInterface {

	public static function get_cli_commands(): array {
		return [
			[
				'newspack-migration-tools example-import',
				[ __CLASS__, 'cmd_example_import' ],
				[
					'shortdesc' => 'Import example data.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'json-file',
							'description' => 'Path to input JSON.',
							'optional'    => false,
							'repeating'   => false,
						],
					],
				],
			],
		];
	}

	public static function cmd_example_import( array $pos_args, array $assoc_args ): void {
		$logger = MultiLog::get_cli_and_file_logger( 'example-import' );
		$logger->info( 'Starting import...' );
		// ...
		\WP_CLI::success( 'Done.' );
	}
}
```

After creating the file, add the class to the list in `WpCliCommands::get_classes_with_cli_commands()`.

### Pattern 2: instance methods via WpCliCommandTrait

Used by 7 existing commands (PostsMigrator, ShortcodesMigrator, etc.). Uses `self::get_command_closure('method_name')` for lazy singleton instantiation. The trait declares a `private __construct()`, so you cannot define your own constructor.

### Command naming

NMT commands use the `newspack-migration-tools` prefix (e.g., `newspack-migration-tools ghostcms-import`). The consuming plugin NCCM uses `newspack-content-migrator`.

## Linting

```bash
composer phpcs     # Check all files (src/ + tests/, PHP 8.3 target)
composer phpcbf    # Auto-fix
```

WordPress + VIP-Go + WordPress-Docs standards. Notable relaxations: PSR-4 filenames allowed, short array syntax `[]` allowed, file operations (`file_put_contents`, `unlink`) allowed, most doc comment requirements excluded. See `phpcs.xml` for the full ruleset.

## Testing

```bash
./bin/install-wp-tests.sh    # First-time setup (downloads WP test suite, creates DB)
composer phpunit             # Run tests
composer code-coverage       # Tests with Xdebug coverage report
```

Tests require a WordPress test environment with MySQL. The bootstrap activates three plugins: `co-authors-plus`, `newspack-plugin`, `simple-local-avatars`.

### Test patterns

Two base classes are used depending on whether you need WordPress:

- **`WP_UnitTestCase`** for tests that need WordPress (posts, meta, users, plugins). Use `set_up()`/`tear_down()` (WordPress snake_case style). Use `$this->factory()->post->create()` for test data.
- **`PHPUnit\Framework\TestCase`** for pure unit tests (CsvWriter, JsonWriter). Use `setUp()`/`tearDown()` (PHPUnit camelCase style).

```php
<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\Posts;
use WP_UnitTestCase;

class ExampleTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// Disable logging noise in tests.
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );
	}

	public function test_something() {
		$post_id = $this->factory()->post->create( [ 'post_title' => 'Test' ] );
		// Assert...
	}
}
```

Test file naming: `tests/` mirrors `src/` structure. Files are named `*Test.php` or `Test*.php` (both conventions exist).

## Directory structure

```
src/
  NMT.php                   # Singleton bootstrap, log level config
  Command/                   # WP-CLI commands (19 migrators + infrastructure)
    WpCliCommandInterface.php  # Interface: get_cli_commands(): array
    WpCliCommandTrait.php      # Singleton + closure trait (for instance-method pattern)
    WpCliCommands.php          # Command registry (hardcoded list, filterable)
  Logic/                     # Business logic helpers (27 files)
  Hooks/                     # WordPress hook wrappers (MemoryCleanupHook, PostUpdateHook)
  Traits/                    # Shared traits (HasUniqueIdentifier)
  Util/                      # I/O, batching, meta tracking, logging
    Log/                     # PSR-3 logging via Monolog (CliLog, FileLog, MultiLog)
```

Discovery commands:
```bash
# List all registered CLI commands
grep -n 'class.*implements WpCliCommandInterface' src/Command/*.php

# Find all Logic helpers
ls src/Logic/

# Find all Util classes
find src/Util -name '*.php' -not -path '*/Log/*'
```

## Gotchas

- Logging is disabled by default. Enable via `newspack_migration_tools_enable_cli_log` and `newspack_migration_tools_enable_file_log` filters. Without these, all log output goes to `/dev/null`.
- `WpCliCommandTrait::get_command_closure()` throws `ErrorException` if the method is static. For static methods, use `[__CLASS__, 'method']` directly.
- `get_command_closure()` validates the method name at runtime, not registration time. A typo only surfaces when the command is invoked.
- `BatchLogic` is 1-indexed and `end` is exclusive. `--start=1 --end=10` processes 9 items (1 through 9). `--num-items` is silently ignored if `--end` is also provided.
- `Posts::create_or_get_post()` is get-or-create, not upsert. It never updates existing posts.
- `MigrationMetaForCommand::should_skip_post()` uses strict `>`, not `>=`. The stored version is always `command_version + 1`.
- `MemoryCleanupHook::cleanup()` fires on step 0 (`0 % 50 === 0`), causing a 1-second sleep before the first item. Passing `null` for step or interval triggers cleanup on every call.
- Two separate meta systems exist: `MigrationMeta` (single serialized array under `newspack_migration_meta`, not queryable) vs `OriginalValueStore` (individual `_nmt_original_{key}` meta rows, queryable via `meta_query`).
- `MultiLog::get_logger()` cache key includes `spl_object_hash()` of each logger. Creating new logger objects bypasses the cache. Use `get_cli_and_file_logger()` for stable caching.
- `GutenbergBlockGenerator::get_quote()` generates `core/pullquote`, not `core/quote`.
- `GutenbergBlockGenerator` `innerContent` arrays use `null` as placeholders for inner block positions. Getting this wrong produces silently broken block markup.
- `PostUpdateHook` must be detached after use. Always call `Posts::update_post_without_modified_date()` rather than the hook directly.
- `Posts::throttled_posts_loop()` uses recursion. Could hit stack depth limits on very large datasets.
- Tests need `./bin/install-wp-tests.sh` run first. Missing plugin errors are verbose by design (see `bootstrap.php`).
- Two different filter names disable logs: `newspack_migration_tools_enable_cli_log` (production enable) vs `newspack_migration_tools_log_clilog_disable` (test disable).

## Recipes

### Add a new WP-CLI command to NMT

1. Create `src/Command/MyMigrator.php` implementing `WpCliCommandInterface`
2. Use the static method pattern (Pattern 1 above) with `newspack-migration-tools` prefix
3. Add the class to the array in `WpCliCommands::get_classes_with_cli_commands()`
4. Add a test in `tests/Command/`

### Add a new Logic helper

1. Create `src/Logic/MyHelper.php` in the `Newspack\MigrationTools\Logic` namespace
2. Use static methods for stateless operations, instance methods when state is needed
3. Do not check plugin dependencies in the constructor (see `docs/coding-conventions-and-standards.md`)
4. Add tests in `tests/Logic/`

### Add a new Util class

1. Create `src/Util/MyUtil.php` in the `Newspack\MigrationTools\Util` namespace
2. Add tests in `tests/Util/`
3. Document in `docs/` if the class has a non-trivial API

## Documentation

Detailed docs for specific helpers live in `docs/`. See [README.md](README.md) for the full index. Key docs:
- [Coding conventions](docs/coding-conventions-and-standards.md)
- [Logging](docs/logging.md)
- [Original values](docs/original-values.md) and [original permalinks](docs/original-permalinks.md)
- [Guest contributors](docs/guest-contributors.md)
