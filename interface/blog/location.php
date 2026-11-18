<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
require ROOT . '/library/preprocessor.php';
if (false) {
	fetchConfigVal();
}
$locatives = getLocatives($blogid);
fireEvent('OBStart');	// Textcube-NG: archive·entry·cover 처리기와 같이 화면 출력 앞뒤로 OBStart·OBEnd 를 낸다(SearchOptimizer 가 스킨의 description 메타 중복을 뺀다)(r3-5)
require ROOT . '/interface/common/blog/begin.php';
require ROOT . '/interface/common/blog/locatives.php';
require ROOT . '/interface/common/blog/end.php';
fireEvent('OBEnd');	// (r3-5)
?>
