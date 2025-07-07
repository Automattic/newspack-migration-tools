<?php
/**
 * Wrapper class for writing CSV files.
 *
 * @package Newspack\MigrationTools\Util
 */

namespace Newspack\MigrationTools\Util;

use Exception;

class CustomRedirectGenerator {
	private $redirects = [];

	public function __construct() {
		$this->redirects = [];
	}

	public function add_redirect( $from, $to ) {
		$this->redirects[] = [
			'from' => $from,
			'to'   => $to,
		];
	}

	public function add_redirects( $redirects ) {
		$this->redirects = array_merge( $this->redirects, $redirects );
	}

	public function generate_redirects() {
		$redirects = [];

		// Convert redirects to the required format
		foreach ( $this->redirects as $redirect ) {
			$redirects[] = "    '" . addslashes( $redirect['from'] ) . "' => '" . addslashes( $redirect['to'] ) . "'";
		}

		$redirects_string = implode( ",\n", $redirects );

		return "<?php
/**
 * Newspack legacy redirects.
 * 'From' keys are without trailing slashes or query parameters.
 */
\$redirects_from_to = [
{$redirects_string}
];

// Clean up \$current_url.
\$current_url    = \$_SERVER['REQUEST_URI'];
\$query_position = strpos( \$current_url, '?' );
if ( false !== \$query_position ) {
    \$current_url = substr( \$current_url, 0, \$query_position );
}
\$current_url = rtrim( \$current_url, '/' );

// Do redirect.
if ( array_key_exists( \$current_url, \$redirects_from_to ) ) {
    // Send all the headers.
    header('HTTP/1.1 302 Found');
    header('cache-control: max-age=300, must-revalidate');
    header('Location: ' . \$redirects_from_to[ \$current_url ] );
    exit;
}";
	}

	public function save_redirects() {
		$content = $this->generate_redirects();
		return file_put_contents( 'custom-redirects.php', $content );
	}
}
