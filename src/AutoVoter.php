<?php

class AutoVoter {
	static function elog($msg) {
		file_put_contents('php://stderr', print_r("\n", true));
		file_put_contents('php://stderr', '[DXD AutoVoter '.(date('c')).'] - ');
		file_put_contents('php://stderr', print_r($msg, true));
	}

	static function get_accounts() {
		$content  = file_get_contents(__DIR__.'/../accounts.json');
		$json     = json_decode($content);
		$accounts = $json->accounts ?? array();

		foreach ($accounts as &$account) {
			if ($account->email == 'example@test.com') {
				$account->email    = '';
				$account->password = '';
			}
		}

		return $accounts;
	}

	static function request_post(
		$url         = '', 
		$token       = '', 
		$post_fields = array()
	) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
		$headers   = array();
		$headers[] = 'Content-Type: application/json';
		$headers[] = 'Authorization: Bearer '.$token;
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result    = curl_exec($ch);
		curl_close($ch);
		return json_decode($result);
	}

	static function request_get(
		$url   = '', 
		$token = ''
	) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		$headers   = array();
		$headers[] = 'Authorization: Bearer '.$token;
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result    = curl_exec($ch);
		curl_close($ch);
		return json_decode($result);
	}

	static function vote(
		$proposal_id,
		$vote_id,
		$direction = "for",
		$token,
		$value = 0.01
	) {
		$response = self::request_post(
			"https://backend.devxdao.com/api/user/vote",
			$token,
			$post_fields = array(
				"proposalId" => $proposal_id,
				"voteId"     => $vote_id,
				"type"       => $direction,
				"value"      => $value
			)
		);

		return $response;
	}

	public static function run() {
		$accounts = self::get_accounts();

		foreach ($accounts as $account) {
			$global_token = '';

			if(
				$account->email &&
				$account->password
			) {
				self::elog('Auto voting for account: '.$account->email);

				$response = self::request_post(
					"https://backend.devxdao.com/api/login",
					$global_token,
					$post_fields = array(
						"email"    => $account->email,
						"password" => $account->password
					)
				);

				$global_token = $response->user->accessTokenAPI ?? '';

				if ($global_token) {
					self::elog("Retrieved bearer token...\n");
				} else {
					self::elog("Failed to retrieve bearer token\n");
				}
			}

			if($global_token) {
				$informal_ballots = self::request_get(
					'https://backend.devxdao.com/api/shared/active-informal-votes', 
					$global_token
				);

				$votes = $informal_ballots->votes ?? array();

				foreach($votes as $vote) {
					$vote_result_type = $vote->vote_result_type ?? null;

					if(!$vote_result_type) {
						$proposalId = $vote->proposalId ?? 0;
						$timeLeft   = $vote->timeLeft ?? 0;
						$title      = $vote->title ?? '';
						$voteId     = $vote->id ?? 0;

						self::elog("Informal Proposal ".$proposalId);
						self::elog($title);
						self::elog("Voting 'For'...");

						$vote_response = self::vote(
							$proposalId,
							$voteId,
							"for",
							$global_token,
							0.1
						);

						self::elog($vote_response);

						sleep(4);
						self::elog("Success\n");
					}
				}


				$formal_ballots = self::request_get(
					'https://backend.devxdao.com/api/shared/active-formal-votes', 
					$global_token
				);

				$votes = $formal_ballots->votes ?? array();

				foreach($votes as $vote) {
					$vote_result_type = $vote->vote_result_type ?? null;

					if(!$vote_result_type) {
						$proposalId = $vote->proposalId ?? 0;
						$timeLeft   = $vote->timeLeft ?? 0;
						$title      = $vote->title ?? '';
						$voteId     = $vote->id ?? 0;

						self::elog("Formal Proposal ".$proposalId);
						self::elog($title);
						self::elog("Voting 'For'...");

						$vote_response = self::vote(
							$proposalId,
							$voteId,
							"for",
							$global_token,
							0.1
						);

						sleep(4);
						self::elog("Success\n");
					}
				}
			}
		}

		self::elog("Done\n");
	}
}
