<?php
/**
 * Logic for working with the Simple Local Avatars
 * 
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Logic;

use Simple_Local_Avatars;

/**
 * SimpleLocalAvatars implements common migration logic that are used to work with the Simple Local Avatars plugin
 */
class SimpleLocalAvatars {

	/**
	 * Avatar meta key
	 * 
	 * @var string
	 */
	const AVATAR_META_KEY = 'simple_local_avatar';

	/**
	 * Rating meta key
	 * 
	 * @var string 
	 */
	const AVATAR_RATING_META_KEY = 'simple_local_avatar_rating';

	/**
	 * Instance of Simple_Local_Avatars
	 * 
	 * @var null|Simple_Local_Avatars
	 */
	public $simple_local_avatars;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$plugins_path = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : ABSPATH . 'wp-content/plugins';

		$simple_local_avatars_plugin_file = $plugins_path . '/simple-local-avatars/simple-local-avatars.php';
		
		if ( is_file( $simple_local_avatars_plugin_file ) && include_once $simple_local_avatars_plugin_file ) {
			$this->simple_local_avatars = new Simple_Local_Avatars();
		}
	}

	/**
	 * Attach an avatar to a user through Simple Local Avatars
	 * 
	 * @param int    $user_id The user ID.
	 * @param int    $attachment_id The attachment ID that has the avatar.
	 * @param string $rating The avatar rating (G, PG, R, X).
	 *
	 * @return boolean True on success, false otherwise
	 */
	public function import_avatar( $user_id, $attachment_id, $rating = '' ) {
		$this->simple_local_avatars->assign_new_user_avatar( (int) $attachment_id, $user_id );

		if ( '' !== $rating ) {
			update_user_meta( $user_id, self::AVATAR_RATING_META_KEY, $rating );
		}

		$avatar = get_user_meta( $user_id, self::AVATAR_META_KEY, true );
		if ( ! empty( $avatar ) ) {
			return false;
		}

		// Check that the avatar was actually imported.
		if ( ! did_action('simple_local_avatar_updated') ) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether the Simple Local Avatars plugin is active or not
	 * 
	 * @return boolean True if SLA is active, false otherwise
	 */
	public function is_sla_plugin_active() {
		return null !== $this->simple_local_avatars;
	}

	/**
	 * Check whether a user has an avatar or not
	 * 
	 * @param int $user_id The user ID.
	 * 
	 * @return boolean True if the user has an avatar, false otherwise
	 */
	public function user_has_avatar( $user_id ) {
		return ! empty( $this->simple_local_avatars->get_avatar( '', $user_id ) );
	}
}
