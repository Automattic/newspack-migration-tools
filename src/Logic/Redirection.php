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

}
