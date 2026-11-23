<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ST_TrustedProxies — 로드밸런서·리버스 프록시·Cloudflare 뒤에서 방문자 IP 와 스킴을 정하는 설정 화면(v3.151).
/// 서비스 관리자(creators) 전용(index.xml <privilege>administrator</privilege>). 설정은 서비스 전체(모든 블로그·도메인 모드)에
/// 적용되며 ServiceSettings 에 둔다. 판정은 lib/TrustedProxies.php(library/preprocessor.php 가 부름).
/// 이 화면의 모든 저장·수동 갱신은 현재 비밀번호 재확인(isReauthenticated, v3.149)을 요구한다.

if (!defined('ROOT')) {
	header('HTTP/1.1 403 Forbidden');
	header("Connection: close");
	exit;
}

function ST_TrustedProxies_escape($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ST_TrustedProxies_time($timestamp) {
	if ($timestamp <= 0) return '-';
	$date = new DateTime('@' . $timestamp);
	$date->setTimezone(new DateTimeZone(ST_TrustedProxies::timezone()));
	return $date->format('Y-m-d H:i:s');
}

/// 저장(모두 검사한 뒤 하나라도 틀리면 아무것도 저장하지 않음). 반환: 오류 문자열 목록(표시 전 escape)
function ST_TrustedProxies_save($settings) {
	$errors = array();
	$devices = array();
	$total = 0;
	for ($i = 0; $i < ST_TrustedProxies::MAX_DEVICES; $i++) {
		$label = isset($_POST['d' . $i . '_label']) ? trim($_POST['d' . $i . '_label']) : '';
		$input = isset($_POST['d' . $i . '_cidrs']) ? $_POST['d' . $i . '_cidrs'] : '';
		if (trim($input) === '') continue;	// CIDR 을 비우면 그 장치 삭제(빈 행은 무시)
		$row = $i + 1;
		if (UTF8::length($label) > ST_TrustedProxies::LABEL_CHARS || preg_match('/[\x00-\x1f\x7f]/', $label)) {
			$errors[] = _f('%1번째 장치: 이름은 제어 문자 없이 %2자 이하여야 합니다.', $row, ST_TrustedProxies::LABEL_CHARS);
			continue;
		}
		if (strlen($input) > ST_TrustedProxies::CIDRS_CHARS) {
			$errors[] = _f('%1번째 장치: CIDR 입력은 %2자 이하여야 합니다.', $row, ST_TrustedProxies::CIDRS_CHARS);
			continue;
		}
		$cidrs = array();
		foreach (preg_split('/[\s,]+/', $input, -1, PREG_SPLIT_NO_EMPTY) as $cidr) {
			$range = ST_TrustedProxies::parseCidr($cidr);
			if ($range === null) {
				$errors[] = _f('%1번째 장치: 받을 수 없는 주소·CIDR 입니다 — %2', $row, UTF8::lessen($cidr, 64));
				continue;
			}
			$cidrs[$range['text']] = true;
		}
		$switch = array();
		foreach (array('xff', 'xfp', 'fwd', 'xri') as $key)
			$switch[$key] = (isset($_POST['d' . $i . '_' . $key]) && $_POST['d' . $i . '_' . $key] === '1') ? 1 : 0;
		$total += count($cidrs);
		$devices[] = array('label' => $label, 'cidrs' => array_keys($cidrs), 'h' => $switch);
	}
	$preset = isset($_POST['preset']) ? $_POST['preset'] : '';
	if ($preset === 'localhost' || $preset === 'private') {
		$cidrs = ($preset === 'localhost') ? array('127.0.0.1/32', '::1/128') : array('10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16');
		$total += count($cidrs);
		$devices[] = array('label' => ($preset === 'localhost') ? _t('로컬 호스트') : _t('사설망 IPv4'), 'cidrs' => $cidrs,
			'h' => array('xff' => 1, 'xfp' => 1, 'fwd' => 0, 'xri' => 0));
	} else if ($preset !== '') {
		$errors[] = _t('알 수 없는 프리셋입니다.');
	}
	if (count($devices) > ST_TrustedProxies::MAX_DEVICES)
		$errors[] = _f('신뢰 장치는 %1개까지 등록할 수 있습니다.', ST_TrustedProxies::MAX_DEVICES);
	if ($total > ST_TrustedProxies::MAX_CIDRS)
		$errors[] = _f('CIDR 은 모든 장치를 합해 %1개까지 등록할 수 있습니다.', ST_TrustedProxies::MAX_CIDRS);
	$schedule = array(
		'cf_days' => isset($_POST['cf_days']) ? trim($_POST['cf_days']) : '',
		'cf_hour' => isset($_POST['cf_hour']) ? trim($_POST['cf_hour']) : '');
	$rules = array('cf_days' => array('int', 'min' => 1, 'max' => 30), 'cf_hour' => array('int', 'min' => 0, 'max' => 23));
	if (!Validator::validateArray($schedule, $rules))
		$errors[] = _t('갱신 주기는 1~30일, 갱신 시각은 0~23시여야 합니다.');
	if (!empty($errors)) return $errors;

	$now = Timestamp::getUNIXtime();
	$enabled = (isset($_POST['enabled']) && $_POST['enabled'] === '1');
	$cloudflare = array(
		'enabled' => (isset($_POST['cf_enabled']) && $_POST['cf_enabled'] === '1') ? 1 : 0,
		'days' => intval($schedule['cf_days']),
		'hour' => intval($schedule['cf_hour']),
		'since' => $settings['cloudflare']['since']);
	if ($cloudflare['enabled'] && !$settings['cloudflare']['enabled']) $cloudflare['since'] = $now;	// 꺼짐 → 켜짐
	Setting::setServiceSettingGlobal('trustedProxyEnabled', $enabled ? '1' : '0');
	Setting::setServiceSettingGlobal('trustedProxyDevices', json_encode($devices, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	Setting::setServiceSettingGlobal('trustedProxyCloudflare', json_encode($cloudflare, JSON_UNESCAPED_SLASHES));
	$state = $settings['state'];
	$state['next'] = ST_TrustedProxies::nextSchedule($cloudflare, $state, $now);
	Setting::setServiceSettingGlobal('trustedProxyCfState', json_encode($state, JSON_UNESCAPED_SLASHES));
	if (Setting::getServiceSettingGlobal('trustedProxyCfAttempt', null) === null)
		Setting::setServiceSettingGlobal('trustedProxyCfAttempt', '0');	// 잠금 행을 미리 만들어 둔다(이후 잠금은 UPDATE 로만)
	return array();
}

function ST_TrustedProxies_manage() {
	global $blogURL;
	require_once ROOT . '/plugins/ST_TrustedProxies/lib/TrustedProxies.php';
	// 메뉴 캐시와 무관한 최종 확인 — 서비스 관리자만
	if (!Acl::check('group.creators')) {
		echo '<p class="message">' . ST_TrustedProxies_escape(_t('접근권한이 없습니다')) . '</p>';
		return;
	}
	$errors = array();
	$notices = array();
	if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
		$action = isset($_POST['action']) ? $_POST['action'] : '';
		$password = isset($_POST['confirmativePassword']) ? $_POST['confirmativePassword'] : '';
		if ($action !== 'save' && $action !== 'refresh') {
			$errors[] = _t('알 수 없는 요청입니다.');
		} else if ($password === '') {
			$errors[] = _t('비밀번호를 입력해 주십시오.');
		} else if (!isReauthenticated($password)) {
			if (!doesHaveMembership()) {	// 5회 실패로 로그아웃됨
				echo '<p class="message">' . ST_TrustedProxies_escape(_t('비밀번호가 일치하지 않습니다.')) . '</p>';
				return;
			}
			$errors[] = _t('비밀번호가 일치하지 않습니다.');
		} else if ($action === 'save') {
			$errors = ST_TrustedProxies_save(ST_TrustedProxies::loadSettings());
			if (empty($errors)) $notices[] = _t('저장했습니다. 다음 요청부터 적용됩니다.');
		} else {
			$result = ST_TrustedProxies::refresh(true);
			if (!$result['acquired'])
				$errors[] = _f('24시간에 한 번만 받아 올 수 있습니다. 다음 가능 시각: %1', ST_TrustedProxies_time($result['attempt'] + ST_TrustedProxies::CF_INTERVAL));
			else if ($result['ok'])
				$notices[] = _t('Cloudflare 대역을 받아 왔습니다.');
			else
				$errors[] = _f('Cloudflare 대역을 받아 오지 못했습니다(%1). 이전 목록을 계속 씁니다.', $result['error']);
		}
	}

	$settings = ST_TrustedProxies::loadSettings();
	$context = Model_Context::getInstance();
	$config = (isset($GLOBALS['service']['trustedProxies']) && is_array($GLOBALS['service']['trustedProxies']))
		? $GLOBALS['service']['trustedProxies'] : array();
	$cache = $settings['ranges'];
	$cached = is_array($cache) && isset($cache['v4'], $cache['v6'])
		&& ST_TrustedProxies::parseRangeList($cache['v4'], 4) !== null && ST_TrustedProxies::parseRangeList($cache['v6'], 16) !== null;
	$builtin = ST_TrustedProxies::builtinCloudflareRanges();
	$state = $settings['state'];
	$next = ($state['next'] > 0) ? $state['next']
		: ST_TrustedProxies::nextSchedule($settings['cloudflare'], $state, Timestamp::getUNIXtime());
	$token = getCSRFToken();
	$action = $blogURL . '/owner/plugin/adminMenu?name=ST_TrustedProxies/ST_TrustedProxies_manage';
	$switches = array('xff' => 'X-Forwarded-For', 'xfp' => 'X-Forwarded-Proto', 'fwd' => 'Forwarded', 'xri' => 'X-Real-IP');
	$rows = $settings['devices'];
	for ($i = 0; $i < 2 && count($rows) < ST_TrustedProxies::MAX_DEVICES; $i++)
		$rows[] = array('label' => '', 'cidrs' => array(), 'h' => array('xff' => true, 'xfp' => true, 'fwd' => false, 'xri' => false));
	// Textcube-NG: 서비스 관리(/control) 화면 형식에 맞춘다 — 안내는 main-explain-box, 설정은 data-inbox 안의 .section(/control/server 와 같은 구조) (v3.206)
	// Textcube-NG: 안내문을 구역별 도움말(p.ng-help)로 옮기고 구역 제목(h3.ng-section-title)을 붙임, 받아 오기 폼은 dl·button-box 형식(v3.218)
?>
						<div id="part-control-trustedproxies" class="part">
							<h2 class="caption"><span class="main-text"><?php echo ST_TrustedProxies_escape(_t('신뢰 프록시·로드밸런서'));?></span></h2>
<?php
	foreach ($errors as $message) echo '<p class="message">' . ST_TrustedProxies_escape($message) . '</p>' . "\n";
	foreach ($notices as $message) echo '<p class="message">' . ST_TrustedProxies_escape($message) . '</p>' . "\n";
?>
							<div class="main-explain-box">
								<p class="explain"><?php echo ST_TrustedProxies_escape(_t('접속 주소가 아래 장치(또는 Cloudflare 대역)에 속할 때만 그 장치가 붙인 헤더로 방문자 IP 와 스킴(http/https)을 정합니다. 그 밖의 연결은 헤더를 무시합니다.'));?></p>
							</div>
							<div class="data-inbox">
								<form id="trustedproxies-form" class="section" method="post" action="<?php echo ST_TrustedProxies_escape($action);?>">
									<input type="hidden" name="csrfToken" value="<?php echo ST_TrustedProxies_escape($token);?>" />
									<input type="hidden" name="action" value="save" />
									<h3 class="ng-section-title"><?php echo ST_TrustedProxies_escape(_t('사용'));?></h3>
									<fieldset class="container">
										<legend><?php echo ST_TrustedProxies_escape(_t('사용'));?></legend>
										<dl class="line">
											<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('사용'));?></span></dt>
											<dd><input type="checkbox" class="checkbox" id="tp_enabled" name="enabled" value="1"<?php echo $settings['enabled'] ? ' checked="checked"' : '';?> /> <label for="tp_enabled"><?php echo ST_TrustedProxies_escape(_t('신뢰 장치의 헤더로 방문자 IP·스킴을 정합니다'));?></label></dd>
										</dl>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('세션은 접속 주소에 묶이므로 처음 켜거나 장치를 바꾸면 로그인이 한 번 끊길 수 있습니다.'));?></p>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('IPv6 방문자 주소 저장은 추후 지원 예정입니다. 지금은 IPv6 방문자를 판정 결과에만 기록하고 접속 주소(REMOTE_ADDR)는 바꾸지 않습니다.'));?></p>
<?php // Textcube-NG: $serviceURL 을 정하면 서비스 주소가 그 값으로 고정되어 이 플러그인의 스킴 판정을 쓰지 않음을 알린다(/control/server 와 같은 조건, r3-36 R-559) ?>
<?php if (isset($GLOBALS['service']['serviceURL'])) { ?>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('config.php 에 서비스 리소스 경로($serviceURL)를 지정해 두어 서비스 주소는 그 값으로 고정되고, 이 플러그인의 스킴 판정은 서비스 주소에 쓰이지 않습니다. 방문자 IP·HSTS 판정과, 경로가 //호스트 형식일 때 소셜 로그인 콜백 주소의 https 판정에는 그대로 씁니다.'));?></p>
<?php } ?>
									</fieldset>
									<h3 class="ng-section-title"><?php echo ST_TrustedProxies_escape(_t('신뢰 장치'));?></h3>
									<fieldset class="container">
										<legend><?php echo ST_TrustedProxies_escape(_t('신뢰 장치'));?></legend>
										<table class="data-inbox" cellspacing="0" cellpadding="0">
											<thead>
												<tr>
													<th>#</th>
													<th><?php echo ST_TrustedProxies_escape(_t('이름'));?></th>
													<th>CIDR</th>
<?php foreach ($switches as $title) { ?>
													<th><?php echo ST_TrustedProxies_escape($title);?></th>
<?php } ?>
												</tr>
											</thead>
											<tbody>
<?php foreach ($rows as $i => $row) { ?>
												<tr>
													<td><?php echo $i + 1;?></td>
													<td><input type="text" class="input-text" style="width:10em" name="d<?php echo $i;?>_label" value="<?php echo ST_TrustedProxies_escape($row['label']);?>" /></td>
													<td><textarea name="d<?php echo $i;?>_cidrs" cols="30" rows="2"><?php echo ST_TrustedProxies_escape(implode("\n", $row['cidrs']));?></textarea></td>
<?php	foreach ($switches as $key => $title) { ?>
													<td><input type="checkbox" class="checkbox" name="d<?php echo $i;?>_<?php echo $key;?>" value="1" title="<?php echo ST_TrustedProxies_escape($title);?>"<?php echo $row['h'][$key] ? ' checked="checked"' : '';?> /></td>
<?php	} ?>
												</tr>
<?php } ?>
											</tbody>
										</table>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_f('CIDR 은 공백·쉼표·줄바꿈으로 구분합니다(단일 주소는 /32·/128). IPv4 는 /8~/32, IPv6 는 /16~/128 만 받으며 호스트 비트가 있으면 받지 않습니다. CIDR 을 비우면 그 장치를 지웁니다. 장치 %1개, CIDR 합계 %2개까지.', ST_TrustedProxies::MAX_DEVICES, ST_TrustedProxies::MAX_CIDRS));?></p>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('Forwarded·X-Real-IP 는 장치가 방문자가 보낸 같은 이름의 헤더를 지우거나 덮어쓸 때만 켜십시오. Forwarded·X-Forwarded-For 가 함께 오면 두 결과가 같을 때만 씁니다.'));?></p>
										<dl class="line">
											<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('프리셋 추가'));?></span></dt>
											<dd>
												<select name="preset">
													<option value=""><?php echo ST_TrustedProxies_escape(_t('추가하지 않음'));?></option>
													<option value="localhost"><?php echo ST_TrustedProxies_escape(_t('로컬 호스트'));?> (127.0.0.1/32, ::1/128)</option>
													<option value="private"><?php echo ST_TrustedProxies_escape(_t('사설망 IPv4'));?> (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)</option>
												</select>
											</dd>
										</dl>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('저장할 때 고른 프리셋의 CIDR 로 장치를 하나 더합니다(X-Forwarded-For·X-Forwarded-Proto 켜짐).'));?></p>
									</fieldset>
									<h3 class="ng-section-title">Cloudflare</h3>
									<fieldset class="container">
										<legend>Cloudflare</legend>
										<dl class="line">
											<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('사용'));?></span></dt>
											<dd><input type="checkbox" class="checkbox" id="tp_cf_enabled" name="cf_enabled" value="1"<?php echo $settings['cloudflare']['enabled'] ? ' checked="checked"' : '';?> /> <label for="tp_cf_enabled"><?php echo ST_TrustedProxies_escape(_t('Cloudflare 대역에서 온 연결은 CF-Connecting-IP·CF-Visitor 로 판정합니다'));?></label></dd>
										</dl>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('위의 사용이 켜져 있을 때만 적용됩니다. Cloudflare 대역에서 온 연결은 등록한 장치보다 먼저 판정합니다.'));?></p>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('Cloudflare 대역에서 오는 연결은 다른 Cloudflare 고객의 요청일 수도 있습니다. 원 서버가 Cloudflare 에서만 연결을 받도록 Authenticated Origin Pulls(mTLS) 사용을 권장합니다.'));?></p>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('Cloudflare 대시보드 Network 의 Pseudo IPv4 를 Overwrite Headers 로 두면 IPv6 방문자도 CF-Connecting-IP 에 IPv4 형식(Class E) 주소가 들어와 그 주소로 기록됩니다. Add Header(Cf-Pseudo-IPv4)는 읽지 않습니다.'));?></p>
<?php // Textcube-NG: 웹 서버가 접속 주소를 먼저 바꾸는 조합(mod_remoteip 등) 안내(r3-36 R-566) ?>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('웹 서버(Apache mod_remoteip 등)가 접속 주소를 먼저 방문자 IP 로 바꾸면 그 연결은 Cloudflare 대역·신뢰 장치에 속하지 않아 헤더를 무시합니다(CF-Visitor 스킴도 쓰지 않음). 이 플러그인으로 판정하려면 웹 서버에서 접속 주소를 바꾸지 마십시오.'));?></p>
										<dl class="line">
											<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('대역 갱신'));?></span></dt>
											<dd>
												<input type="text" class="input-text" style="width:3em" name="cf_days" value="<?php echo $settings['cloudflare']['days'];?>" /> <?php echo ST_TrustedProxies_escape(_t('일마다'));?>
												<select name="cf_hour">
<?php for ($hour = 0; $hour < 24; $hour++) { ?>
													<option value="<?php echo $hour;?>"<?php echo ($hour === $settings['cloudflare']['hour']) ? ' selected="selected"' : '';?>><?php echo $hour;?></option>
<?php } ?>
												</select> <?php echo ST_TrustedProxies_escape(_f('시 (%1)', ST_TrustedProxies::timezone()));?>
											</dd>
										</dl>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('예정 시각이 지난 뒤 들어온 첫 요청이 응답을 마친 다음 받아 옵니다. 받아 오지 못하면 다음 날 같은 시각에 다시 시도합니다.'));?></p>
									</fieldset>
									<h3 class="ng-section-title"><?php echo ST_TrustedProxies_escape(_t('비밀번호 확인'));?></h3>
									<fieldset class="container">
										<legend><?php echo ST_TrustedProxies_escape(_t('비밀번호 확인'));?></legend>
										<dl class="line">
											<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('현재 비밀번호'));?></span></dt>
											<dd><input type="password" class="input-text" style="width:14em" name="confirmativePassword" value="" autocomplete="current-password" /></dd>
										</dl>
										<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('저장과 지금 받아 오기는 현재 비밀번호를 다시 확인한 뒤에만 처리합니다.'));?></p>
									</fieldset>
									<div class="button-box">
										<input type="submit" class="save-button input-button wide-button" value="<?php echo ST_TrustedProxies_escape(_t('저장하기'));?>" />
									</div>
								</form>
							</div>
							<h2 class="caption"><span class="main-text"><?php echo ST_TrustedProxies_escape(_t('Cloudflare 대역'));?></span></h2>
							<div class="data-inbox">
								<div class="section">
								<dl class="line">
									<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('사용 중인 목록'));?></span></dt>
									<dd><?php echo ST_TrustedProxies_escape($cached
										? _f('받아 온 목록 (IPv4 %1개, IPv6 %2개, %3)', count($cache['v4']), count($cache['v6']), ST_TrustedProxies_time(isset($cache['fetched']) && is_int($cache['fetched']) ? $cache['fetched'] : 0))
										: _f('내장 목록 (IPv4 %1개, IPv6 %2개)', count($builtin['v4']), count($builtin['v6'])));?></dd>
								</dl>
								<dl class="line">
									<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('마지막 성공·시도'));?></span></dt>
									<dd><?php echo ST_TrustedProxies_escape(ST_TrustedProxies_time($state['lastSuccess']) . ' / ' . ST_TrustedProxies_time($state['lastAttempt']) . ($state['lastError'] !== '' ? ' (' . $state['lastError'] . ')' : ''));?></dd>
								</dl>
								<dl class="line">
									<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('다음 예정'));?></span></dt>
									<dd><?php echo ST_TrustedProxies_escape($settings['cloudflare']['enabled'] ? ST_TrustedProxies_time($next) . ' (' . ST_TrustedProxies::timezone() . ')' : _t('Cloudflare 사용이 꺼져 있습니다'));?></dd>
								</dl>
<?php if (!empty($state['history'])) { ?>
								<table class="data-inbox" cellspacing="0" cellpadding="0">
									<thead>
										<tr><th><?php echo ST_TrustedProxies_escape(_t('시각'));?></th><th><?php echo ST_TrustedProxies_escape(_t('결과'));?></th><th>IPv4</th><th>IPv6</th><th><?php echo ST_TrustedProxies_escape(_t('수동'));?></th></tr>
									</thead>
									<tbody>
<?php	foreach ($state['history'] as $entry) { ?>
										<tr><td><?php echo ST_TrustedProxies_escape(ST_TrustedProxies_time($entry['t']));?></td><td><?php echo ST_TrustedProxies_escape($entry['ok'] ? _t('성공') : _f('실패(%1)', $entry['error']));?></td><td><?php echo $entry['v4'];?></td><td><?php echo $entry['v6'];?></td><td><?php echo $entry['manual'] ? 'O' : '';?></td></tr>
<?php	} ?>
									</tbody>
								</table>
<?php } ?>
								<form id="trustedproxies-refresh-form" method="post" action="<?php echo ST_TrustedProxies_escape($action);?>">
									<input type="hidden" name="csrfToken" value="<?php echo ST_TrustedProxies_escape($token);?>" />
									<input type="hidden" name="action" value="refresh" />
									<p class="ng-help"><?php echo ST_TrustedProxies_escape(_t('출처는 https://www.cloudflare.com/ips-v4 · ips-v6 뿐이며, 받아 오기는 실패한 시도를 포함해 24시간에 한 번입니다.'));?></p>
									<dl class="line">
										<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('현재 비밀번호'));?></span></dt>
										<dd><input type="password" class="input-text" style="width:14em" name="confirmativePassword" value="" autocomplete="current-password" /></dd>
									</dl>
									<div class="button-box">
										<input type="submit" class="input-button" value="<?php echo ST_TrustedProxies_escape(_t('지금 받아 오기'));?>" />
									</div>
								</form>
								</div>
							</div>
							<h2 class="caption"><span class="main-text"><?php echo ST_TrustedProxies_escape(_t('현재 요청의 판정'));?></span></h2>
							<div class="data-inbox">
								<div class="section">
								<dl class="line">
									<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('상태'));?></span></dt>
									<dd><?php echo ST_TrustedProxies_escape(is_null($context->getProperty('proxy.status')) ? _t('적용 안 됨(꺼짐)') : $context->getProperty('proxy.status'));?></dd>
								</dl>
<?php foreach (array('peer' => _t('접속 주소'), 'device' => _t('장치'), 'client' => _t('방문자 IP'), 'scheme' => _t('스킴'), 'source' => _t('사용한 헤더')) as $key => $title) { ?>
								<dl class="line">
									<dt><span class="label"><?php echo ST_TrustedProxies_escape($title);?></span></dt>
									<dd><?php echo ST_TrustedProxies_escape(is_null($context->getProperty('proxy.' . $key)) ? '-' : $context->getProperty('proxy.' . $key));?></dd>
								</dl>
<?php } ?>
								<dl class="line">
									<dt><span class="label"><?php echo ST_TrustedProxies_escape(_t('config.php 신뢰 프록시'));?></span></dt>
									<dd><?php echo ST_TrustedProxies_escape(_f('%1개 (config.php trustedProxies — 이 화면의 설정과 함께 적용됩니다)', count($config)));?></dd>
								</dl>
								</div>
							</div>
						</div>
<?php
}
?>
