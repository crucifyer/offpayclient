<?php

set_time_limit(120);

include_once __DIR__.'/../lib/Offpay.class.php';
\Offpay\Offpay::init(__DIR__.'/../config/example.json'); // 설정파일은 웹에서 접근 못하는 위치에 놓으세요.

// 오류 응답을 하면 오류메세지를 메일로 받을 수 있습니다.
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if(!$data) {
	\Offpay\Offpay::response(500, json_encode(['date' => date('Y-m-d H:i:s'), 'data' => $input, 'error' => __LINE__], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
if(!isset($data['action'])) {
	\Offpay\Offpay::response(500, json_encode(['date' => date('Y-m-d H:i:s'), 'data' => $data, 'error' => __LINE__], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
if(!\Offpay\Offpay::verifyHmac($data)) {
	\Offpay\Offpay::response(500, json_encode(['date' => date('Y-m-d H:i:s'), 'data' => $data, 'error' => __LINE__], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$db = new \PDO(/* */);

switch($data['action']) {
	case 'search': // 아이디 찾기
		if(!isset($data['io_money'], $data['name'], $data['cell'])) {
			\Offpay\Offpay::response(500, json_encode(['date' => date('Y-m-d H:i:s'), 'data' => $data, 'error' => __LINE__], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}
		$wheres = [
			"amount = {$data['io_money']}",
		];
		if($data['name']) {
			$wheres[] = 'name = :name';
			$fields['name'] = $data['name'];
		}
		if($data['cell']) {
			$wheres[] = 'cell = :cell';
			$fields['cell'] = preg_replace('~[^\d]+~', '', $data['cell']);
		}
		if(!count($fields)) \Offpay\Offpay::response(400, '내용을 입력하세요.');
		$stmt = $db->prepare(/* 결제할 내역이 있는 고객을 검색하세요 */);
		$stmt->execute($fields);
		if(!($rows = $stmt->fetchAll(PDO::FETCH_OBJ))) \Offpay\Offpay::response(404, '검색된 회원이 없습니다.');
		if(1 < count($rows)) \Offpay\Offpay::response(400, '검색된 회원이 많습니다.');
		\Offpay\Offpay::response(200, '검색되었습니다.', ['user_id' => $rows[0]->user_id, 'cell' => preg_replace('~[^\d]+~', '', $rows[0]->cell)]);
		break;
	case 'deposit': // 입금
		if(!isset($data['rs_seq'], $data['user_id'], $data['sender_name'], $data['bank_account'], $data['io_money'], $data['io_time'], $data['cash_receipt']) || !preg_match('~^[1-9]\d*$~', $data['rs_seq'])) {
			\Offpay\Offpay::response(400, json_encode(['date' => date('Y-m-d H:i:s'), 'data' => $data, 'error' => __LINE__], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}
		$db->beginTransaction();
		try {
			$fields = [
				'tid' => 'offpay'.$data['rs_seq']
			];
			$stmt = $db->prepare("/* 예약 번호로 이미 처리 되었는지 확인 */ WHERE tid = :tid LIMIT 1 FOR UPDATE");
			$stmt->execute($fields);
			if ($stmt->fetchObject()) {
				\Offpay\Offpay::response(200, '이미 입금처리 되었습니다.');
			} else {
				$fields = [
					'user_id' => $data['user_id']
				];
				$stmt = $db->prepare("/* 로그인 아이디 확인 */ WHERE user_id = :user_id LIMIT 1");
				$stmt->execute($fields);
				if (!($user = $stmt->fetch())) {
					\Offpay\Offpay::response(404, 'unknownuser');
				} else {
					/* 입금 처리 */
					$db->commit();
					\Offpay\Offpay::response(200, '입금 되었습니다.');
				}
			}
		} catch (\Exception $e) {
			\Offpay\Offpay::response(500, json_encode(['date' => date('Y-m-d H:i:s'), 'data' => $data, 'error' => $e->getMessage().' '.__LINE__], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		}
		break;
}