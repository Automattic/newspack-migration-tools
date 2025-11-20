<?php
/**
 * Logic for working with Publish Press
 */

namespace Newspack\MigrationTools\Logic;

use WP_Error;

/**
 * PublishPressHelper migration logic.
 */
class PublishPressHelper {

	/**
	 * Set a post to expire using PublishPress Future / post-expirator plugin.
	 * 
	 * Set a post to a given status or delete it completely at a future date using PublishPress Future / post-expirator
	 * plugin. This function will cause PublishPress to add the appropriate postmeta `_expiration-date%` (multiple keys) and
	 * also add a related action in action-scheduler.
	 *
	 * @link https://wordpress.org/plugins/post-expirator/
	 * @link https://publishpress.com/knowledge-base/programmatically-schedule-actions/

	 * @param int    $post_id   Post ID.
	 * @param string $status    Options: draft, trash, or delete.
	 * @param int    $epoch_gmt Expiration date in GMT epoch time. PHP: date( 'U' ) format.
	 * 
	 * @return bool|WP_Error  True or WP_Error.
	 */
	public static function set_post_expiration( int $post_id, string $status, int $epoch_gmt ): bool|WP_Error {
		
		if ( ! defined( 'PUBLISHPRESS_FUTURE_LOADED' ) || ! defined( '\PublishPress\Future\Modules\Expirator\HooksAbstract::ACTION_SCHEDULE_POST_EXPIRATION' ) ) {
			return new WP_Error( 'ERROR_PUBLISHPRESS', 'PublishPress Future ( post-expirator ) not loaded.' );
		}

		$options = [
			'id' => $post_id,
		];

		// status: draft, trash, delete
		if ( 'delete' === $status ) {
			$options['expireType'] = 'delete';
			// newStatus not needed since post will be deleted.
		} else {
			$options['expireType'] = 'change-status';
			$options['newStatus']  = $status;
		}

		do_action(
			\PublishPress\Future\Modules\Expirator\HooksAbstract::ACTION_SCHEDULE_POST_EXPIRATION,
			$post_id,
			$epoch_gmt,
			$options
		);

		return true;
	}
}
