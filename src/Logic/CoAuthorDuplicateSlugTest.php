<?php
namespace NewspackCustomContentMigrator\Logic;

use CoAuthors_Guest_Authors;
use CoAuthors_Plus;
use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use WP_CLI;
use WP_User;

class CoAuthorDuplicateSlugTest extends CoAuthorsPlusHelper {

	protected $authorsWithoutUniqueSlugs = array(
		array(
			'username'  => 'Sanding Down',
			'password'  => '',
			'email'     => 'sanding@down.com',
		),
		array(
			'username'  => 'Quarantine Cuarentena',
			'password'  => '',
			'email'     => 'wear.a.mask@everywhere.com',
		),
		array(
			'username'  => 'wanda_vision_',
			'password'  => '',
			'email'     => 'w.maxi@disney.com',
		),
	);

	protected $authorsWithUniqueSlugs = array(
	    array(
            'username'  => 'Monkey D. Luffy',
            'password'  => '',
            'email'     => 'luffy@onepiece.com',
        ),
        array(
            'username'  => 'Roronoa Zoro',
            'password'  => '',
            'email'     => 'zoro@onepiece.com',
        ),
        array(
            'username'  => 'Vinsmoke Sanji',
            'password'  => '',
            'email'     => 'sanji@onepiece.com',
        ),
    );

    protected $usersWithoutUniqueSlugs = array(
        array(
            'username'  => 'Eren Yeager',
            'password'  => '',
            'email'     => 'eren@aot.com',
        ),
        array(
            'username'  => 'Mikasa Ackerman',
            'password'  => '',
            'email'     => 'mikasa@aot.com',
        ),
        array(
            'username'  => 'Armin Arlet',
            'password'  => '',
            'email'     => 'armin@aot.com',
        ),
    );

    protected $usersWithUniqueSlugs = array(
        array(
            'username'  => 'Takumi Fujiwara',
            'password'  => '',
            'email'     => 'a86@initiald.com',
        ),
        array(
            'username'  => 'Itsuki Takeuchi',
            'password'  => '',
            'email'     => 'itsuki@initiald.com',
        ),
        array(
            'username'  => 'Satou Mako',
            'password'  => '',
            'email'     => 'mako@initiald.com',
        ),
    );

	public function __construct() {
		parent::__construct();
	}

	/**
	 * Overwriting this function so that it just checks if the CoAuthors Plugin is or has been on the system.
	 *
	 * @return bool
	 */
	public function validate_co_authors_plus_dependencies() {
		if ( ( ! $this->coauthors_plus instanceof CoAuthors_Plus ) || ( ! $this->coauthors_guest_authors instanceof CoAuthors_Guest_Authors ) ) {
			return false;
		}

		return true;
	}

    /**
     * @return void
     */
    public function run() {
        WP_CLI::line( "Co-Author Duplicate Slug Test - Checking CAP Dependency\n" );
        if ( $this->validate_co_authors_plus_dependencies() ) {
            WP_CLI::line( "Setting up test data\n" );
            $this->setup_test_data();
        }
	}

    /**
     * Creates necessary data for 4 different scenarios:
     * 1. Authors who have unique slugs. This is to test that the migration will not create a slug
     * which already matches someone else's slug.
     * 2. Authors without unique slugs. This is the main reason for the script. These slugs
     * should be updated once the script is finished.
     * 3. Users with unique slugs. This is to make sure that only Authors are affected by the
     * migration script.
     * 4. Users without unique slugs. Even though they don't have unique slugs, these records should
     * not be touched because they are not Authors.
     *
     * @return void
     */
	protected function setup_test_data() {
        foreach ( $this->authorsWithUniqueSlugs as $author ) {
            WP_CLI::line( "Creating author! u:{$author['username']} e:{$author['email']}" );

            $authorId = wp_create_user( $author['username'], $author['password'], $author['email'] );
            WP_CLI::line( "User ID: {$authorId}" );
            WP_CLI::line( "Setting Author Role." );
            ( new WP_User($authorId) )->set_role( 'author' );
        }

		foreach ( $this->authorsWithoutUniqueSlugs as $author ) {
		    WP_CLI::line( "Creating author! u:{$author['username']} e:{$author['email']}" );

		    $authorId = wp_create_user( $author['username'], $author['password'], $author['email'] );
            WP_CLI::line( "User ID: {$authorId}" );
            WP_CLI::line( "Setting Author Role." );
			( new WP_User($authorId) )->set_role( 'author' );

			$this->create_guest_author_for_test( array(
			    'display_name'  => $author['username'],
			    'user_email'    => $author['email'],
            ) );
		}

        foreach ( $this->usersWithUniqueSlugs as $user ) {
            WP_CLI::line( "Creating user! u:{$user['username']} e:{$user['email']}" );

            $userId = wp_create_user( $user['username'], $user['password'], $user['email'] );
            WP_CLI::line( "User ID: {$userId}" );
        }

		foreach ( $this->usersWithoutUniqueSlugs as $user ) {
            WP_CLI::line( "Creating user! u:{$user['username']} e:{$user['email']}" );

            $userId = wp_create_user( $user['username'], $user['password'], $user['email'] );
            WP_CLI::line( "User ID: {$userId}" );

            $this->create_guest_author_for_test( array(
                'display_name'  => $user['username'],
                'user_email'    => $user['email'],
            ) );
        }

	}

    /**
     * Creates Guest Authors
     *
     * @param array $args {
     *
     *     @type string $display_name
     *     @type string $user_email
     * }
     *
     * @return void
     */
    protected function create_guest_author_for_test(array $args) {
        WP_CLI::line("Creating Guest Author Record for {$args['user_email']}");
        // First Create Post
        $postId = wp_insert_post( array(
            'post_date'         => date( 'Y-m-d H:i:s', time() ),
            'post_title'        => $args['display_name'],
            'post_status'       => 'publish',
            'comment_status'    => 'closed',
            'ping_status'       => 'closed',
            'post_name'         => "cap-test-" . sanitize_title( $args['display_name'] ),
            'post_modified'     => date( 'Y-m-d H:i:s', time() ),
            'post_type'         => 'guest-author'
        ) );

        // Then Create PostMeta
        add_post_meta( $postId, 'cap-user_login', sanitize_title( $args['display_name'] ));
        add_post_meta( $postId, 'cap-display_name', $args['display_name'] );
	}
}

( new CoAuthorDuplicateSlugTest() )->run();
