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

$context = Model_Context::getInstance();

$bid=$suri['id'];
$blogsetting = Setting::getBlogSettingsGlobal($bid);
$pool = DBModel::getInstance();

?>
						<script type="text/javascript"> 
						//<![CDATA[

						function setDefaultBlog(bid) {
							var request = new HTTPRequest("POST", "<?php echo $context->getProperty('uri.blog');?>/control/action/blog/setDefault/");
							request.onSuccess = function() {
								alert("<?php echo _t('대표 블로그가 설정되었습니다.');?>");
								window.location.reload();
							}
							request.onError = function() {
								msg = this.getText("/response/result");
								alert("<?php echo _t('대표 블로그가 설정되지 못했습니다.');?>\r\nError : " + msg);
							}
							request.send("blogid="+bid);
						}

						function deleteUser(userid, atype) {
							if(atype == 1) { // If there are posts from user.
								tcConfirm("<?php echo _t('선택된 사용자를 정말 삭제하시겠습니까?');?>\n\n<?php echo _t('삭제되는 기존 사용자의 글은 전부 관리자의 글로 변환됩니다.');?>\n(<?php echo _t('글이 전부 삭제되지는 않고 팀블로그의 로그인 데이터만 삭제됩니다');?>)\n<?php echo _t('삭제 이후에는 복원이 불가능합니다.');?> <?php echo _t('정말 삭제 하시겠습니까?');?>", tcDeleteUserGo);	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
								return false;
							} else { // No post from user.
								tcConfirm('<?php echo _t('삭제 하시겠습니까?');?>', tcDeleteUserGo);	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
								return false;
							}
							function tcDeleteUserGo() {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
							var request = new HTTPRequest("POST", "<?php echo $context->getProperty('uri.blog');?>/control/action/blog/deleteUser/");
							request.onSuccess = function() {
								window.location.href="<?php echo $context->getProperty('uri.blog');?>/control/blog/detail/<?php echo $bid?>";
							}
							request.onError = function() {
								alert("<?php echo _t('실패했습니다.');?>");
							}
							request.send("userid=" + userid + "&blogid=" + <?php echo $bid?>);
							}
						}
						
						
						function deleteBlog(bid) {
							tcConfirm("<?php echo _t('되돌릴 수 없습니다.');?>\t\n\n<?php echo _t('계속 진행 하시겠습니까?');?>", function () {	// Textcube-NG: 확인창을 페이지 위 안내 줄의 [확인]/[취소]로 바꿈(r3-17, R-396)
							var request = new HTTPRequest("POST", "<?php echo $context->getProperty('uri.blog');?>/control/action/blog/delete/");
							request.onSuccess = function() {
								PM.removeRequest(this);
								window.location.href = '../';
							}
							request.onError = function() {
								PM.removeRequest(this);
								msg = this.getText("/response/result");
								alert("<?php echo _t('블로그 삭제에 실패하였습니다.');?>\r\nError : " + msg);
							}
							PM.addRequest(request, _t('블로그 삭제중'));
							request.send("item="+bid);
							});
							return false;
						}
						
						function changeOwner(owner) {
							var password = document.getElementById('changeOwnerConfirmativePassword');	// Textcube-NG: 비밀번호 재확인(v3.149)
							if (password.value == '') {
								alert("<?php echo _t('비밀번호를 입력해 주십시오.');?>");
								password.focus();
								return;
							}
							var request = new HTTPRequest("POST", "<?php echo $context->getProperty('uri.blog');?>/control/action/blog/changeOwner/");
							request.onSuccess = function() {
								alert("<?php echo _t('소유자가 변경되었습니다. \r\n기존의 소유자는 관리자로 변경되었습니다.');?>");
								window.location.reload();
							}
							request.onError = function() {
								password.value = '';
								if (parseInt(this.getText("/response/error")) == 9) {
									alert("<?php echo _t('비밀번호가 일치하지 않습니다.');?>");
									return;
								}
								msg = this.getText("/response/result");
								alert("<?php echo _t('소유자를 변경하지 못했습니다.');?>\r\nError : " + msg);
							}
							request.send("owner="+owner+"&blogid="+<?php echo $bid?>+"&confirmativePassword="+encodeURIComponent(password.value));
						}
						
						function changeACL(acltype, userid, checked) {
							var request = new HTTPRequest("POST", "<?php echo $context->getProperty('uri.blog');?>/control/action/blog/changeACL/");
							request.onSuccess = function() {
								PM.showMessage("<?php echo _t('설정을 변경했습니다.');?>", "center", "bottom");
								window.location.reload();
							}
							request.onError = function() {
								PM.showErrorMessage("<?php echo _t('실패했습니다.');?>", "center", "bottom");
							}
							request.send("blogid=" + <?php echo $bid?> + "&acltype=" + acltype + "&userid=" + userid + "&switch=" + checked);
						}
						
						function addUser(user) {
							var request = new HTTPRequest("POST", "<?php echo $context->getProperty('uri.blog');?>/control/action/blog/addUser/");
							request.onSuccess = function() {
								alert("<?php echo _t('팀블로그 참여자를 추가하였습니다.');?>\r\n<?php echo _t('초대 메일은 발송되지 않으며, 새로 추가된 참여자는 기본 권한만을 가지게 됩니다.');?>");
								window.location.reload();
							}
							request.onError = function() {
								msg = this.getText("/response/message");
								PM.showErrorMessage("<?php echo _t('참여자를 추가하지 못하였습니다.');?>\r\nMessage : \r\n" + msg,"center","bottom");
							}
							request.send("user="+encodeURIComponent(user)+"&blogid="+<?php echo $bid?>);	// Textcube-NG: 이메일의 '+'·'&' 가 깨지지 않게 인코딩한다 — 받는 쪽 $IV email 검사(r3-7, 459a)
						}
						//]]> 
						</script>

						<div id="part-blog-about" class="part">
							<h2 class="caption"><span class="main-text"><?php echo _t('블로그 정보');?></span></h2>
							
							<div id="team-blog-about" class="container">
								<h3><?php /* [보안] Textcube-NG: 블로그 주소·제목을 HTML 인코딩해 출력한다 — 원본은 그대로 출력해 저장된 제목으로 관리 화면에 HTML 을 넣을 수 있었다(r3-7, 459e) */ echo empty($blogsetting['title']) ? '<em>'._t('비어 있는 타이틀').'</em>' : '<a href="'.htmlspecialchars((string)getDefaultUrl($bid), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">'.htmlspecialchars((string)$blogsetting['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</a>';?></h3>
								
								<div class="main-explain-box">
									<p class="explain"><?php /* [보안] Textcube-NG: 블로그 설명을 HTML 인코딩해 출력한다(블로그 화면 [##_desc_##] 와 같음) — 원본은 그대로 출력했다(r3-7, 459e) */ echo empty($blogsetting['description']) ? '<em>'._t('비어 있는 블로그 설명').'</em>' : htmlspecialchars((string)$blogsetting['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');?></p>
								</div>
<?php
	$isRepBlog = ($bid == Setting::getServiceSetting("defaultBlogId",1,true) ? true : false);
	
	$pool->reset('Entries');
	$pool->setQualifier('blogid','eq',$bid);
	$pool->setQualifier('draft','eq',0);	
	$numberOfEntries = $pool->getCount();
	
	$pool->reset('RemoteResponses');
	$pool->setQualifier('blogid','eq',$bid);
	$pool->setQualifier('responsetype','eq','trackback',true);
	$pool->setQualifier('isfiltered','eq',0);
	$numberOfTrackbacks = $pool->getCount();
	
	$pool->reset('Comments');
	$pool->setQualifier('blogid','eq',$bid);
	$pool->setQualifier('isfiltered','eq',0);
	$numberOfComments = $pool->getCount();

	$pool->reset('Attachments');
	$pool->setQualifier('blogid','eq',$bid);
	$attachmentSum = $pool->getCell('sum(size)');
?>
								<ul>
									<?php if ($isRepBlog) { ?><li><em><?php echo _t('이 블로그는 대표 블로그입니다.');?></em></li><?php } ?>
									<li><?php echo _f('이 블로그에는 총 %1개의 글이 있습니다.', $numberOfEntries);?></li>
                                    <li><?php echo _f('이 블로그에는 총 %1개의 걸린글(트랙백)이 있습니다.', $numberOfTrackbacks);?></li>
                                    <li><?php echo _f('이 블로그에는 총 %1개의 댓글이 있습니다.', $numberOfComments);?></li>
                                    <li><?php 
		if(empty($attachmentSum)) echo _t('이 블로그에는 첨부파일이 없습니다.');
		else echo _f('이 블로그가 사용중인 첨부파일의 총 용량은 %1입니다.', Misc::getSizeHumanReadable($attachmentSum));?></li>
                                </ul>
							</div>
								
							<div id="team-member-list" class="container">
								<h4><span class="text"><?php echo _t('팀블로그 멤버 목록');?></span></h4>
								
								<table class="data-inbox">
									<thead>
										<tr>
											<th class="name"><?php echo _t('사용자');?></th>
											<th class="role"><?php echo _t('관리자');?></th>
											<th class="role"><?php echo _t('글관리');?></th>
											<th class="action" colspan=2 ><?php echo _t('명령');?></th>
										</tr>
									</thead>
									<tbody>
<?php

	$pool->reset('Privileges');
	$pool->setQualifier('blogid','eq',$bid);
	$teamblog = $pool->getAll();

	foreach ($teamblog as $row){
		echo "<tr>".CRLF;
		echo "<td class=\"name\"><a href=\"".$context->getProperty('uri.blog')."/control/user/detail/{$row['userid']}\">".htmlspecialchars((string)User::getName($row['userid']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."(".htmlspecialchars((string)User::getEmail($row['userid']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').")</a></td>".CRLF;	// [보안] Textcube-NG: 팀원 이름·로그인 아이디를 HTML 인코딩해 출력한다 — 원본은 그대로 출력했다(r3-7, 459e)

		if ($row['acl'] & BITWISE_OWNER) {
			echo '<td class="role" colspan="4">'._t('이 사용자는 블로그의 소유자입니다.').'</td>'.CRLF;
		}
		else {
		echo "<td class=\"role\"><a href=\"#void\" onclick=\"changeACL('admin',".$row['userid'].",".(($row['acl'] & BITWISE_ADMINISTRATOR)?0:1).");return false;\">".(($row['acl'] & BITWISE_ADMINISTRATOR)?_t('ON'):_t('OFF'))."</a></td>".CRLF;
		echo "<td class=\"role\"><a href=\"#void\" onclick=\"changeACL('editor',".$row['userid'].",".(($row['acl'] & BITWISE_EDITOR)?0:1).");return false;\">".(($row['acl'] & BITWISE_EDITOR)?_t('ON'):_t('OFF'))."</a></td>".CRLF;
		echo "<td class=\"role\"><a href=\"#void\" onclick =  \"deleteUser(".$row['userid'].",1);return false;\">" . _t('팀원 제외') . "</a></td>".CRLF;
		echo "<td class=\"role\"><a href=\"#void\" onclick =  \"changeOwner(".$row['userid'].");return false;\">" . _t('소유자 변경') . "</a></td>".CRLF; // Textcube-NG: 위 네 링크 href를 #void로(GET 요청이 나가지 않음, 동작은 onclick의 POST)(r3-8)
		echo "</tr>".CRLF;
		}
	}
?>
									</tbody>
								</table>
							</div>
							
							<div id="team-owner-password" class="container">
								<label for="changeOwnerConfirmativePassword"><?php echo _t('현재 비밀번호');?></label>
								<input type="password" id="changeOwnerConfirmativePassword" class="input-text" value="" />
							</div>
							
							<div id="team-new-member" class="container">
								<h4><?php echo _t('팀원 추가');?></h4>
								
								<form method="post" action="<?php echo $context->getProperty('uri.blog');?>/control/action/blog/addUser/">
									<dl>
										<dt><label for=""><?php echo _t('사용자'); ?></label></dt>
										<dd>
											<span id="suggestContainer"><input type="text" id="bi-owner-loginid" name="user" value="" /></span>
											<input type="hidden" name="blogid" value="<?php echo $bid?>" />
											<input type="submit" class="input-button" value="<?php echo _t('팀원 추가');?>" onclick="addUser(ctlUserSuggestObj.getValue());return false;" />
										</dd>
									</dl>
								</form>
								
								<script type="text/javascript">
									//<![CDATA[
										try {
											document.getElementById("suggestContainer").innerHTML = '';
											var ctlUserSuggestObj = new ctlUserSuggest(document.getElementById("suggestContainer"), false);
											ctlUserSuggestObj.setValue("<?php echo User::getEmail(1);?>");
										} catch (e) {
											document.getElementById("suggestContainer").innerHTML = '<input type="text" id="bi-owner-loginid" name="user" value="" />';
										}
									//]]>
								</script>
							</div>
							
							<div class="button-box">
								<input type="submit" class="input-button" href="#void" onclick="deleteBlog(<?php echo $bid;?>); return false;" value="<?php echo _t('블로그 삭제');?>" />
								<span class="hidden">|</span>
								<?php if (!$isRepBlog) { ?><input type="submit" class="input-button" href="<?php echo $context->getProperty('uri.blog');?>/control/action/blog/setDefault/?blogid=<?php echo $bid;?>" onclick="setDefaultBlog('<?php echo $bid;?>'); return false;" value="<?php echo _t('대표 블로그 설정');?>" /><span class="hidden">|</span><?php } ?>
								<input type="submit" class="input-button" onclick="location.href='<?php echo $context->getProperty('uri.blog');?>/control/blog'" value="<?php echo _t('돌아가기');?>" />
							</div>
						</div>
<?php
require ROOT . '/interface/common/control/footer.php';
?>
