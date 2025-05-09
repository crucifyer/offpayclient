<?php

namespace Offpay;

/**
 * Class OffpayException
 * @package Offpay
 */
class OffpayException extends \Exception {}

/**
 * Class Offpay
 * @package Offpay
 */
class Offpay
{
	const RESERVEURL = 'https://api%s.offpay.kr/reserver.php';
	private static $_config = null;

	/**
	 * @param string $config
	 * @return void
	 */
	public static function init($config) {
		if(!is_array($config)) {
			if(!file_exists($config)) throw new OffpayException("$config 파일이 없습니다.", 500);
			$config = json_decode(file_get_contents($config), true);
			if(false === $config) throw new OffpayException("$config json decode error".json_last_error_msg(), 500);
		}
		if(!isset($config['secret'], $config['mertid'], $config['apino'])) throw new OffpayException('config 내용이 잘못되었습니다.', 500);
		return self::$_config = $config;
	}

	/**
	 * @param array $data
	 * @return string
	 */
	private static function _hmac($data) {
		$hk = [];
		foreach($data as $k => $v) {
			$hk[] = "$k=$v";
		}
		$text = implode('', $hk) . self::$_config['secret'];
		return hash('sha256', $text);
	}

	/**
	 * @param array $data
	 * @return string
	 */
	public static function makeHmac(&$data) {
		ksort($data);
		$data['_'] = time();
		$data['hash'] = self::_hmac($data);
	}

	/**
	 * @param array $data
	 * @return bool
	 */
	public static function verifyHmac($data) {
		if(!isset($data['hash'], $data['_'])) return false;
		$time = $data['_'];
		$now = time();
		if($time < $now - 60 || $time > $now + 60) return false;
		$hash = $data['hash'];
		unset($data['hash']);
		unset($data['_']);
		ksort($data);
		$data['_'] = $time;
		return $hash === self::_hmac($data);
	}

	/**
	 * @param int $status
	 * @param string $message
	 * @return void
	 */
	public static function response($status, $message = null, $data = null) {
		header('Content-Type: application/json');
		$json = ['status' => $status];
		if($message) $json['message'] = $message;
		if($data) $json['data'] = $data;
		echo json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}

	/**
	 * @param string $url
	 * @param array $data
	 * @return array
	 */
	public static function call($url, $data) {
		self::makeHmac($data);
		$options = [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_SSL_VERIFYPEER => 0,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_USERAGENT => 'Offpay PhpApi/1.0',
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		];

		$ch = curl_init();
		curl_setopt_array($ch, $options);

		$output = curl_exec($ch);
		$errno = curl_errno($ch);
		if(0 !== $errno) throw new OffpayException("errno: $errno - ".curl_error($ch), 500);
		if(false === $output) throw new OffpayException('result error', 500);
		curl_close($ch);

		$json = json_decode($output, true);
		if(!$json) throw new OffpayException("json decode error : $output");
		return $json;
	}

	/**
	 * @param array $data
	 * @return array
	 */
	private static function serverCall($data) {
		$data['mertid'] = self::$_config['mertid'];
		return self::call(sprintf(self::RESERVEURL, self::$_config['apino']), $data);
	}

	/**
	 * @param string $user_id
	 * @return array
	 */
	public static function getReserveList($user_id) {
		$data = [
			'action' => 'list',
			'site' => self::$_config['site'],
			'user_id' => $user_id,
		];
		return self::serverCall($data);
	}

	/**
	 * @param string $user_id
	 * @param string $sender_name
	 * @param int $reserve_money
	 * @param string $bank_account
	 * @return array
	 */
	public static function doReserve($user_id, $sender_name, $reserve_money, $bank_account, $cash_receipt = null) {
		$data = [
			'action' => 'reserve',
			'site' => self::$_config['site'],
			'user_id' => $user_id,
			'sender_name' => $sender_name,
			'reserve_money' => $reserve_money,
			'bank_account' => $bank_account,
		];
		if($cash_receipt) $data['cash_receipt'] = $cash_receipt;
		return self::serverCall($data);
	}

	const CASH_NONE = 'none', CASH_PHONE = 'phone', CASH_PERSONALID = 'personalid', CASH_PERMITNUMBER = 'permitnumber', CASH_RECEIPTCARD = 'receiptcard';
	/**
	 * @param string $cash_type
	 * @param string $cash_number
	 * @return string
	 */
	public static function encodeCashReceipt($cash_type, $cash_number = '') {
		switch ($cash_type) {
			case self::CASH_NONE:
				$cash_number = '';
				break;
			case self::CASH_PHONE:
			case self::CASH_PERSONALID:
			case self::CASH_PERMITNUMBER:
			case self::CASH_RECEIPTCARD:
				if(!preg_match('~^\d+$~', $cash_number)) throw new OffpayException('cash_number 는 숫자로만 보내야 합니다.', 500);
				break;
			default:
				throw new OffpayException('cash_type 은 \Offpay\Offpay::CASH_ 상수를 이용하세요.', 500);
		}
		return json_encode(['cash_type' => $cash_type, 'cash_number' => $cash_number], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * @param string $cash_receipt
	 * @return string
	 */
	private static $CASH_TEXTS = ['none' => '-', 'phone' => '휴대전화번호', 'personalid' => '주민등록번호', 'permitnumber' => '사업자번호', 'receiptcard' => '현금영수증카드번호'];
	public static function cashReceptCodeToText($cash_receipt, $object = false) {
		$json = json_decode($cash_receipt);
		if(!isset(self::$CASH_TEXTS[$json->cash_type])) return '-';
		return $object ? (object)['cash_type' => self::$CASH_TEXTS[$json->cash_type], 'cash_number' => $json->cash_number] : self::$CASH_TEXTS[$json->cash_type].':'.$json->cash_number;
	}
}
