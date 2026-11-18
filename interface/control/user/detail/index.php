<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ---- Modification Notice (GPL §2(a)) ----
/// Modified 2026 by @deokio for PHP 8.5 compatibility,
/// performed with AI assistance (Anthropic Claude) under human review.
/// Changes consist primarily of mechanical PHP migration transformations
/// per the official PHP upgrade documentation.
/// No additional copyright is asserted over these modifications.
/// See CHANGELOG.md and SECURITY.md for full modification history.

$service['admin_script']='control.js';

require ROOT . '/library/preprocessor.php';
require ROOT . '/interface/common/control/header.php';

requirePrivilege('group.creators');
global $database;

$uid = $suri['id'];

$usersetting= POD::queryRow("SELECT * FROM {$database['prefix']}Users WHERE userid = " . $uid);
$usersetting['owner']= POD::queryCell("SELECT userid FROM {$database['prefix']}Privileges WHERE acl & ".BITWISE_OWNER." != 0 AND blogid = " . $blogid);
$AuthToken = Auth::getValidAuthToken($uid);	// [보안] 유효한 인증 토큰만(만료·사용된 토큰은 null → 다시 발급 가능)
/* Textcube-NG: 임시 암호 안내문에 보일 유효 기간 — Auth::authTokenLifetime()(초, 0 이면 만료 없음)을 일·시간 중
   나누어떨어지는 가장 큰 단위로, 둘 다 아니면 분으로 적는다. 분은 실제보다 길게 보이지 않게 내림하고 최소 1분(r3-14) */
$ngTokenLifetime = Auth::authTokenLifetime();
$ngTokenLifetimeText = '';
if ($ngTokenLifetime > 0) {
	if ($ngTokenLifetime % 86400 == 0) $ngTokenLifetimeText = _f('%1일', intval($ngTokenLifetime / 86400));
	else if ($ngTokenLifetime % 3600 == 0) $ngTokenLifetimeText = _f('%1시간', intval($ngTokenLifetime / 3600));
	else $ngTokenLifetimeText = _f('%1분', max(1, intval(floor($ngTokenLifetime / 60))));
}
/* Textcube-NG: 이미 발급한 임시 암호의 만료 시각 — 발급 시각 UserSettings 'AuthTokenIssued'(UNIX 시각, Auth::issueAuthToken 이
   저장) + 유효 기간. 바로 위 getValidAuthToken() 이 만료 토큰을 지우고 발급 시각이 없던 토큰엔 지금을 적으므로 캐시를 거치지
   않고 DB 에서 다시 읽는다. 유효 기간 0(만료 없음)이면 적지 않는다. 그 사이 지났으면 '만료됨'(r3-14) */
$ngTokenExpiresText = '';
if (!is_null($AuthToken) && $ngTokenLifetime > 0) {
	$ngTokenIssued = Setting::getUserSettingGlobal('AuthTokenIssued', null, intval($uid), true);
	if (is_numeric($ngTokenIssued)) {
		$ngTokenExpires = intval($ngTokenIssued) + $ngTokenLifetime;
		$ngTokenExpiresText = ($ngTokenExpires >= Timestamp::getUNIXtime()) ? date("D M j G:i:s T Y", $ngTokenExpires) : _t('만료됨');
	}
}
?>
						<script type="text/javascript"> 
						//<![CDATA[

						function makeToken(uid) {
							var password = document.getElementById('makeTokenConfirmativePassword');	// Textcube-NG: 비밀번호 재확인(v3.149)
							if (password.value == '') {
								alert("<?php echo _t('비밀번호를 입력해 주십시오.');?>");
								password.focus();
								return;
							}
							var request = new HTTPRequest("POST", "<?php echo $blogURL;?>/control/action/user/makeToken/");
							request.onSuccess = function() {
								alert("<?php echo _t('임시 암호가 설정되었습니다.');?>");
								window.location.reload();
							}
							request.onError = function() {
								password.value = '';
								if (parseInt(this.getText("/response/error")) == 9) {
									alert("<?php echo _t('비밀번호가 일치하지 않습니다.');?>");
									return;
								}
								msg = this.getText("/response/result");
								alert("<?php echo _t('임시 암호가 설정되지 못했습니다.');?>\r\nError : " + msg);
							}
							request.send("userid="+uid+"&confirmativePassword="+encodeURIComponent(password.value));
						}
						</script>

						<div id="part-user-about" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('사용자 정보');?></span></h2>
							
							<div id="team-user-about" class="container">
								<h3><?php echo htmlspecialchars((string)$usersetting['name']);?></h3>
								
								<div class="main-explain-box">
									<p class="explain"><?php echo htmlspecialchars((string)$usersetting['loginid']); // Textcube-NG: 로그인 ID 를 HTML 로 escape 해 낸다(위 h3 name 출력과 같음) — 원본은 그대로 냈다(r3-16, R-387) ?></p>
								</div>
								
								<ul>
									<?php if(!is_null($AuthToken)) { ?>
										<li><em><?php echo _t('임시 암호가 설정되어 있습니다.');?></em></li>
										<li><em><?php echo $AuthToken;?></em></li>
									<?php }?>
									<li><?php echo _f('이 계정은 %1에 생성되었습니다.', date("D M j G:i:s T Y", $usersetting['created']));?></li>
                                </ul>
							</div>
							
							<!--div id="developer-description" class="section">
								<h3><span class="text"><?php echo _t('팀블로그');?></span></h3>
								
								<div id="maintainer-container" class="container">
									<h4><span class="text"><?php echo _t('Action');?></span></h4>
									
									<table>
										<colgroup>
											<col class="name"></col>
										</colgroup>
										<thead>
											<tr>
												<th class="name"><?php echo _t('사용자');?></th>
											</tr>
										</thead>
										<tbody>
											<tr>
	<?php echo "<td class=\"name\"></td>";?>
											</tr>
												<td></td>
											<tr>
											</tr>
										</tbody>
									</table>
								</div>
							</div -->
							
							<div id="team-joined-list" class="container">
								<h4><span class="text"><?php echo _t('참여중인 블로그');?></span></h4>
								
								<table class="data-inbox">
									<thead>
										<tr>
											<th class="name"><?php echo _t('블로그');?></th>
											<th class="role"><?php echo _t('권한 그룹');?></th>
										</tr>
									</thead>
									<tbody><?php
$teamblog = POD::queryAll("SELECT * FROM `{$database['prefix']}Privileges` WHERE userid = " . $uid);
	foreach ($teamblog as $row){
		echo "<tr>";
		echo "<td class=\"name\"><a href=\"{$blogURL}/control/blog/detail/{$row['blogid']}\">".POD::queryCell("SELECT value FROM `{$database['prefix']}BlogSettings` WHERE name = 'name' AND blogid = " . $row['blogid'])."</a></td>";

		$tmpstr = '';
		if ($row['acl'] & BITWISE_ADMINISTRATOR) $tmpstr .= _t('관리자')." ";
		if ($row['acl'] & BITWISE_OWNER) $tmpstr .= _t('소유자')." ";
		if ($row['acl'] & BITWISE_EDITOR) $tmpstr .= _t('글관리')." ";
		$tmpstr = ($tmpstr?$tmpstr:_t('없음'));
		echo "<td class=\"role\">".$tmpstr."</td>";
		echo "</tr>";
	}
?>
									</tbody>
								</table>
							</div>
							
							<?php /* Textcube-NG: 임시 암호 발급을 버튼 줄에서 따로 떼어, 무엇을 하는지·누구 비밀번호인지·유효 기간·만료 시각을 함께 보인다(r3-14) */ ?>
							<div id="team-user-token" class="container">
								<h4><span class="text"><?php echo _t('임시 암호 발급');?></span></h4>
								<p><?php echo _t('비밀번호를 잊은 사용자가 로그인할 때 비밀번호 대신 쓰는 임시 암호를 만듭니다. 메일로 보내지 않으므로, 발급한 뒤 이 화면 위쪽에 나오는 값을 사용자에게 직접 알려 주십시오.');?></p>
								<p><?php echo ($ngTokenLifetime > 0) ? _f('임시 암호는 로그인에 한 번 쓰면 없어지고, 쓰지 않아도 발급 뒤 %1이 지나면 만료됩니다.', $ngTokenLifetimeText) : _t('임시 암호는 로그인에 한 번 쓰면 없어집니다. 유효 기간은 없습니다.');?> <?php echo ($ngTokenLifetime > 0) ? _f('기존 비밀번호도 그대로 쓸 수 있습니다. 임시 암호로 로그인한 사용자는 그 로그인 뒤 %1 안에서만 현재 비밀번호를 몰라도 계정 설정에서 새 비밀번호를 정할 수 있고, 한 번 바꾸면 끝납니다.', $ngTokenLifetimeText) : _t('기존 비밀번호도 그대로 쓸 수 있습니다. 임시 암호로 로그인한 사용자는 그 로그인에서 현재 비밀번호를 몰라도 계정 설정에서 새 비밀번호를 정할 수 있습니다.');?></p>
								<?php if (is_null($AuthToken)) { ?>
								<p class="token-note"><?php echo _t('발급하려면 지금 로그인한 관리자 본인의 비밀번호를 입력하십시오(이 사용자의 비밀번호가 아닙니다).');?></p>
								<p class="token-form"><label for="makeTokenConfirmativePassword"><?php echo _t('본인 비밀번호 확인');?></label> <input type="password" id="makeTokenConfirmativePassword" class="input-text" value="" /> <a class="button" href="<?php echo $blogURL;?>/control/action/user/makeToken/?userid=<?php echo $uid;?>" onclick="makeToken('<?php echo $uid;?>'); return false;"><?php echo _t('임시 암호 발급');?></a></p>
								<?php } else { ?>
								<p class="token-note"><?php echo _t('이미 발급한 임시 암호가 있습니다(위 표시). 쓰이거나 만료되면 다시 발급할 수 있습니다.');?><?php if ($ngTokenExpiresText !== '') { ?> <?php echo htmlspecialchars(_f('만료 시각: %1', $ngTokenExpiresText), ENT_QUOTES, 'UTF-8');?><?php } ?></p>
								<?php } ?>
							</div>
							
							<div class="button-box">
								<a class="button" href="<?php echo $blogURL;?>/control/action/user/delete/?userid=<?php echo $uid;?>" onclick="cleanUser('<?php echo $uid;?>'); return false;"><?php echo _t('사용자 삭제');?></a>
								<a class="button" href="<?php echo $blogURL;?>/control/user"><?php echo _t('돌아가기');?></a>
							</div>
						</div>
<?php
require ROOT . '/interface/common/control/footer.php';
?>
