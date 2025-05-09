<?php

if(!isset($_POST['name'], $_POST['price'], $_POST['bank_account'], $_POST['cash_type'], $_POST['cash_number'])) { /* 404 보안 차단 */ }
if(!$_POST['name']) { /* 메세지 '입금인명을 입력하세요.' */ }
$_POST['price'] = preg_replace('~[^\d]+~', '', $_POST['price']);
if(!$_POST['price']) { /* 메세지 '금액을 입력하세요.' */ }
/* 예시는 계좌번호를 post 로 받지만, 실제로는 동명이인 입금내역 등 확인하여 중복없이 할당하세요 */
$bank_accounts = [
	'1' => '율도은행 1024031999231 홍길동',
	'2' => '율도은행 1024031349239 홍길동',
	'3' => '혜성은행 298273947132 설까치',
];
if(!preg_match('~^[1-3]$~u', $_POST['bank_account'])) { /* 400 보안 차단 */ }
if(!preg_match('~^(?:phone|personalid|permitnumber|receiptcard)$~', $_POST['cash_type'])) { /* 400 보안 차단 */ }

$_POST['cash_number'] = preg_replace('~[^\d]+~', '', $_POST['cash_number']);
if($_POST['cash_number'] == '') {
	$_POST['cash_type'] = 'phone';
	$_POST['cash_number'] = '0100001234'; // 현금영수증을 요청하지 않아도 이 번호로 무조건 신고하게 되어 있습니다.
}

include_once __DIR__.'/../lib/Offpay.class.php';
\Offpay\Offpay::init(__DIR__.'/../config/example.json'); // 설정파일은 웹에서 접근 못하는 위치에 놓으세요.
$res = \Offpay\Offpay::doReserve(
	$_SESSION['user_id'].':'.$ordid, // noti 받을 때 주문건 구별을 하려면 주문번호를 추가하세요.
	$_POST['name'], $_POST['price'], $bank_accounts[$_POST['bank_account']], \OffPay\Offpay::encodeCashReceipt($_POST['cash_type'], $_POST['cash_number']));
if($res['status'] == 200) {
	$res['data']['rs_seq'] // 예약 번호 - 수동 처리시 새로 생성되기에 매칭에 적합하지 않습니다.
	$message = '['.$_POST['bank_account'].'] '.$_POST['price'].'원 입금인:'.$_POST['name'].' 으로 예약되었습니다.';
	/* 예약 완료 메세지 출력 */
}
if($res['status'] >= 500 && !관리자) { /* 내부 오류인 경우 관리자만 확인 */ }
/* http status 400, $res['message'] // 그 외 오류메세지는 고객 확인 - 주로 같은계좌 같은이름 같은금액 차단 메세지