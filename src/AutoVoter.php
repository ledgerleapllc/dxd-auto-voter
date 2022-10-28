<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class AutoVoter {
	public static $api = array(
		"base_url"  => "https://backend.devxdao.com/api",
		"endpoints" => array(
			"login"          => "/login",
			"cast_vote"      => "/user/vote",
			"formal_votes"   => "/shared/active-formal-votes",
			"informal_votes" => "/shared/active-informal-votes",
		)
	);

	static function elog($msg) {
		file_put_contents('php://stderr', print_r("\n", true));
		file_put_contents('php://stderr', '[DxD AutoVoter '.(date('c')).'] - ');
		file_put_contents('php://stderr', print_r($msg, true));
	}

	static function email(
		$recipients = array(),
		$subject,
		$body
	) {
		$emailer = new PHPMailer(true);
		// $emailer->SMTPDebug = SMTP::DEBUG_SERVER;
		$emailer->isSMTP();

		$emailer->Host          = 'smtp.gmail.com';
		$emailer->Port          = '587';
		$emailer->SMTPKeepAlive = true;
		$emailer->SMTPSecure    = 'tls';
		$emailer->SMTPAuth      = true;
		$emailer->Username      = '';
		$emailer->Password      = '';

		$emailer->setFrom('dev@ledgerleap.com', 'DxD AutoVoter');
		$emailer->addReplyTo('dev@ledgerleap.com', 'DxD AutoVoter');
		$emailer->isHTML(true);

		$img_src    = 'https://portal.devxdao.com/logoblue-min-big.png';
		$year       = date('Y', time());
		$template   = file_get_contents(__DIR__.'/email-template.html');
		$template   = str_replace('[IMG_SRC]', $img_src, $template);
		$template   = str_replace('[SUBJECT]', $subject, $template);
		$template   = str_replace('[BODY]',    $body,    $template);
		$template   = str_replace('[YEAR]',    $year,    $template);
		$recipients = (array)$recipients;

		if (
			count($recipients) == 0 ||
			empty($recipients)
		) {
			return false;
		}

		try {
			foreach ($recipients as $recipient) {
				$emailer->addAddress($recipient);
			}

			$emailer->Subject = $subject;
			$emailer->Body = $template;
			$emailer->send();
			self::elog("SENT notification email to: ".implode(', ', $recipients));
		} catch (Exception $e) {
			// elog($e);
			$emailer->getSMTPInstance()->reset();
			self::elog("Failed to send notification email to ".implode(', ', $recipients));
		}

		$emailer->clearAddresses();
		$emailer->clearAttachments();

		return true;
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
		$value = 0
	) {
		if ((int)$value == 0) {
			$value = rand(1, 20) / 10;
		}

		$response = self::request_post(
			self::$api['base_url'].
			self::$api['endpoints']['cast_vote'],
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
		$emails   = array();

		foreach ($accounts as $account) {
			$global_token = '';

			if(
				$account->email &&
				$account->password
			) {
				$emails[] = $account->email;
				self::elog('Auto voting for account: '.$account->email);

				$response = self::request_post(
					self::$api['base_url'].
					self::$api['endpoints']['login'],
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
					self::$api['base_url'].
					self::$api['endpoints']['informal_votes'], 
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

						$value = 0;

						if ($account->email == 'charles@ledgerleap.com') {
							$value = 0.01;
						}

						$vote_response = self::vote(
							$proposalId,
							$voteId,
							"for",
							$global_token,
							$value
						);

						// self::elog($vote_response);

						sleep(4);
						$success = $vote_response->success ?? 0;

						if ($success) {
							self::elog("Success\n");
						} else {
							self::elog("Failed\n");
						}
					}
				}


				$formal_ballots = self::request_get(
					self::$api['base_url'].
					self::$api['endpoints']['formal_votes'], 
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

						$value = 0;

						if ($account->email == 'charles@ledgerleap.com') {
							$value = 0.01;
						}

						$vote_response = self::vote(
							$proposalId,
							$voteId,
							"for",
							$global_token,
							$value
						);

						// self::elog($vote_response);

						sleep(4);
						$success = $vote_response->success ?? 0;

						if ($success) {
							self::elog("Success\n");
						} else {
							self::elog("Failed\n");
						}
					}
				}
			}
		}

		self::elog('Finishing up email notifications');

		self::email(
			$emails,
			'DxD AutoVoter',
			'Your DxD portal votes have been cast automatically today.'
		);

		self::elog("Done\n");
	}
}
