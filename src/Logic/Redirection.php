<?php

namespace NewspackCustomContentMigrator\Logic;

use Red_Group;

class Redirection {

	/**
	 * Creates a redirection rule with the johngodley/redirection plugin.
	 *
	 * Arguments documented at https://redirection.me/developer/rest-api/
	 *
	 * @param string $title
	 * @param string $url_from
	 * @param string $url_to
	 * @param false  $match_data_source_flag_regex
	 * @param bool   $match_data_source_flag_trailing
	 * @param string $match_data_source_flag_query
	 * @param false  $match_data_source_flag_case
	 */
	public function create_redirection_rule( $title, $url_from, $url_to, $match_data_source_flag_regex = false,
		$match_data_source_flag_trailing = true, $match_data_source_flag_query = 'exact', $match_data_source_flag_case = false, $group_id = 1 ) {
		\Red_Item::create( [
			'action_code' => 301,
			'action_type' => 'url',
			'action_data' => [
				'url' => $url_to,
			],
			'match_type'  => 'url',
			'group_id'    => $group_id,
			'position'    => 1,
			'title'       => $title,
			'regex'       => false,
			'enabled'     => true,
			'match_data'  => [
				'source' => [
					'flag_query'    => $match_data_source_flag_query,
					'flag_case'     => $match_data_source_flag_case,
					'flag_trailing' => $match_data_source_flag_trailing,
					'flag_regex'    => $match_data_source_flag_regex,
				],
			],
			'url'         => $url_from,
		] );
	}

	/**
	 * Get or create a migration group by name.
	 *
	 * @param string $group_name Name of group to get or create.
	 *
	 * @return int A group id of the group gotten or created.
	 */
	private function get_or_create_group_id( string $group_name ): int {
		$module_id = 1; // WordPress is the one we'd use, and it has an ID of 1.

		$groups = Red_Group::get_all_for_module( $module_id ); // WP is 1.
		$group  = array_filter( $groups, fn( $group ) => $group['name'] === $group_name );
		if ( empty( $group ) ) {
			return Red_Group::create( $group_name, $module_id )->get_id();
		}

		return current( $group )['id'];
	}

	/**
	 * Create a redirection rule in a group after deleting existing redirects for the same url if any.
	 *
	 * @param string $title Title
	 * @param string $url_from From URL
	 * @param string $url_to To URL
	 * @param string $group_name Group name
	 *
	 * @return void
	 */
	public function create_redirection_rule_in_group( string $title, string $url_from, string $url_to, string $group_name ): void {

		// If we have existing ones, get rid of them.
		$existing_redirects = $this->get_redirects_by_exact_from_url( $url_from );
		if ( ! empty( $existing_redirects ) ) {
			foreach ( $existing_redirects as $existing_redirect ) {
				$existing_redirect->delete();
			}
		}

		$this->create_redirection_rule(
			$title,
			$url_from,
			$url_to,
			false,
			true,
			'exact',
			false,
			$this->get_or_create_group_id( $group_name )
		);

	}

	/**
	 * Get redirects for a url.
	 *
	 * Will not return regex matches – only an exact match on the from-url.
	 */
	public function get_redirects_by_exact_from_url( string $from ): array {
		$redirect = \Red_Item::get_for_url( $from );
		if ( ! empty( $redirect ) ) {
			return array_filter( $redirect, fn( $item ) => $from === $item->get_url() );
		}

		return [];
	}

	/**
	 * Helper to see if a redirect already exists for a url.
	 *
	 * @param string $from Url to check redirects from.
	 *
	 * @return bool true if a redirect from that url already exists.
	 */
	public function redirect_from_exists( string $from ): bool {
		$existing_redirects = $this->get_redirects_by_exact_from_url( $from );

		return ! empty( $existing_redirects );
	}

}
