# Logging
The loggers are PSR-3 compliant and use [Monolog](https://github.com/Seldaek/monolog).

By default the code logs to `dev/null` (as in – it logs nothing). If you want logging, you can enable it with a filter. There are currently 3 different loggers: A file logger, a CLI logger, and a plain file logger useful for log files with no formatting. You can enable them like this respectively:

```php
add_filter( 'newspack_migration_tools_enable_file_log', '__return_true' );
add_filter( 'newspack_migration_tools_enable_cli_log', '__return_true' );
add_filter( 'newspack_migration_tools_enable_plain_log', '__return_true' );
```
Note that the loggers check that filter only on logger creation. There is no support for toggling logging on/of after you have already created the logger. There is a logger that you can use to log to multiple loggers at once. See `MulitLog.php`. The `LoggerManager.php` keeps tabs of all loggers in use and you can get a logger from it by name if you want to use the same logger to log to from mulitple classes/functions in your code.

The loggers that write to file all write to current dir. If you don't like that, you can either pass in an absolute path to a directory to use as the log dir, set the constant `NMT_LOG_DIR` to a dir, or implement your the filter `newspack_migration_tools_log_dir`.

The log level is configured to `INFO` by default. To change it, you can set the constant `NMT_LOG_LEVEL` (see the constructor in [NMT.php](src/NMT.php)).

You can customize the date format and the line output format of the CliLogger with either filters or constants (note that the constants will override the filters). The default format is [Lineformatter.php](https://github.com/Seldaek/monolog/blob/main/src/Monolog/Formatter/LineFormatter.php)'s `SIMPLE_FORMAT`.
```php
// Use PHP's date format.
define( 'NMT_CLI_LOG_DATE_FORMAT', 'H:i:s' );
define( 'NMT_CLI_LOG_FORMAT', "[%datetime%] %level_name%: %message% %context% %extra%\n" ); 
```

If you want to add loggers or formatters to the logging in this project, please make a pull request! 🙏

## Example usage
### Basic CLI logging
```php
$cli_logger = CliLog::get_logger( 'log-demo' );
// These are the different log levels.
$cli_logger->debug( 'This is a log message.' );
$cli_logger->info( 'This is a log message.' );
$cli_logger->notice( 'This is a log message.' );
$cli_logger->warning( 'This is a log message.' );
$cli_logger->error( 'This is a log message.' );
$cli_logger->critical( 'This is a log message.' );
$cli_logger->alert( 'This is a log message.' );
$cli_logger->emergency( 'This is a log message.' );
```
### Logging to multiple channels
```php
$logger = MultiLog::get_logger(
    'logz', [
        CliLog::get_logger( 'log-demo' ),
        FileLog::get_logger( 'log-demo', 'log-file-name.log' ),
        PlainFileLog::get_logger( 'log-demo' ),
    ]
);
// This should go to all 3 channels.
$logger->info( 'This is a log message.' );
````
### Log and exit
```php
// Will halt execution after logging.
NMT::exit_with_message( '🔥🔥🔥🔥🔥 Dying in flames.', [ FileLog::get_logger( 'log-demo' ) ] );
// You can also exit with just the message not using a logger.
NMT::exit_with_message('Aarrrrgh');
```
