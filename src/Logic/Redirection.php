<?php

namespace NewspackCustomContentMigrator\Logic;

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
		$match_data_source_flag_trailing = true, $match_data_source_flag_query = 'exact', $match_data_source_flag_case = false
	) {
		\Red_Item::create( [
			'action_code' => 301,
			'action_type' => 'url',
			'action_data' => [
				'url' => $url_to,
			],
			'match_type' => 'url',
			'group_id' => 1,
			'position' => 1,
			'title' => $title,
			'regex' => false,
			'enabled' => true,
			'match_data'  => [
				'source' => [
					'flag_query' => $match_data_source_flag_query,
					'flag_case' => $match_data_source_flag_case,
					'flag_trailing' => $match_data_source_flag_trailing,
					'flag_regex' => $match_data_source_flag_regex,
				],
			],
			'url' => $url_from,
		] );
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
