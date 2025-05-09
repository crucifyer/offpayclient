<?php

include_once __DIR__.'/../lib/Offpay.class.php';
\Offpay\Offpay::init(__DIR__.'/../config/example.json'); // 설정파일은 웹에서 접근 못하는 위치에 놓으세요.
$res = \Offpay\Offpay::getReserveList($_SESSION['user_id']);

?>
<table>
	<thead>
	<tr>
		<th>예약일시</th>
		<th>계좌번호</th>
		<th>입금예정금액</th>
		<th>입금자명</th>
		<th>현금영수증신청</th>
		<th></th>
	</tr>
	</thead>
	<tbody>
	<?php
	if ($res['status'] != 200) {
		?><tr>
			<td colspan="6">예약 내역이 없습니다.</td>
		</tr><?php
	} else {
	/*
	$res['data']
	[
		{
			"rs_seq": 1, // 예약 번호
			"mertid": "demosite",
			"site": "www.demosite.com",
			"user_id": "demouser",
			"sender_name": "데모유저",
			"bank_account": "율도은행 1024031999231 홍길동",
			"rs_money": 15000,
			"rs_time": 1701375682,
			"cash_receipt": "{\"cash_type\":\"phone\",\"cash_number\":\"0100001234\"}",
		},
		...
	]

	 */

	foreach ($res['data'] as $row) { ?>
	<tr>
		<td><?php echo date('Y-m-d H:i:s', $row['rs_time']); ?></td>
		<td><?php echo $row['bank_account']; ?></td>
		<td><?php echo number_format($row['rs_money']); ?>원</td>
		<td><?php echo htmlspecialchars($row['sender_name']); ?></td>
		<td><?php echo \Offpay\Offpay::cashReceptCodeToText($row['cash_receipt']); ?></td>
		<td data-bank="<?php echo $row['bank_account']; ?>:<?php echo $row['rs_money']; ?>">
			<a class="copy" href="#">복사</a>
			<a class="toss" href="#">토스로 열기</a>
		</td>
	</tr>
	<?php }
	}
	?>
	</tbody>
</table>
<style>
	#qrbox { display:none; }
	.qrcode {
		width: 300px;
		height: 300px;
		margin: 0 auto;
	}
</style>
<!-- QR코드는 모달 등 디자인 적용해서 만드세요 -->
<div id="qrbox">
	<div class="qrcode"></div>
	<p>기본 카메라 앱으로 스캔하면 토스앱이 열립니다.</p>
</div>

<script src="https://xeno.work/js/qrcode.min.js"></script><!-- https://github.com/papnkukn/qrcode-svg 받아서 사용하세요 -->
<script>
	(function (d, n, ua) {
		const qrbox = d.getElementById('qrbox'), ismobile = /mobile/i.test(ua);

		function parseBankData(data) {
			let args = data.split(':'), bank = args[0].split(' ');
			return [bank[0], bank[1], args[1]];
		}

		d.querySelectorAll('.copy').forEach(a => {
			let data = parseBankData(a.parentNode.getAttribute('data-bank')), text = `${data[0]} ${data[1]}\n${data[2]}원`;
			a.addEventListener('click', event => {
				event.preventDefault();

				n.clipboard.writeText(text);
				// 버튼이 동작했다는 깜박임 효과
				a.classList.add('btn-danger');
				setTimeout(() => {
					a.classList.remove('btn-danger');
				}, 1000);
			});
		});

		d.querySelectorAll('.toss').forEach(a => {
			let data = parseBankData(a.parentNode.getAttribute('data-bank')), link = `supertoss://send?amount=${data[2]}&bank=${data[0]}&accountNo=${data[1]}&origin=qr'`;
			a.addEventListener('click', event => {
				event.preventDefault();

				if(ismobile) {
					let t = Date.now();
					top.location.href = link;
					setTimeout(() => {
						if (Date.now() - 3000 > t) {
							if (/ios/i.test(ua)) {
								top.location.href = 'https://itunes.apple.com/app/id839333328';
							} else {
								top.location.href = 'market://details?id=viva.republica.toss';
							}
						}
					}, 1000);
				} else {
					qrbox.querySelector('.qrcode').innerHTML = new QRCode({
						content: link,
						join: true,
						container: 'svg-viewbox',
						padding: 0,
						width: 300,
						height: 300
					}).svg();
					qrbox.style.display = 'block'; // 모달 띄우기 코드로 바꾸세요
				}

			}, {passive: false});
		});
	})(document, navigator, navigator.userAgent);
</script>