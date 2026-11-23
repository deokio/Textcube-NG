<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
///
/// ---- Modification Notice (GPL §2(a)) ----
/// Modified 2026 by @deokio for PHP 5.5 - 7.3 compatibility,
/// performed with AI assistance (Anthropic Claude) under human review.
/// Changes consist primarily of mechanical PHP migration transformations
/// per the official PHP upgrade documentation.
/// No additional copyright is asserted over these modifications.
/// See CHANGELOG.md and SECURITY.md for full modification history.
$IV = array(
	'POST' => array(
		'fileName' => array('string', 1, 255),
		'order' => array(array('0', '1'))
	)
);
require ROOT . '/library/preprocessor.php';
requireModel('blog.attachment');
requireStrictRoute();

// Filename security validation — runs after preprocessor so Respond is available.
// Blocks threats not covered by DB escaping alone.
call_user_func(function () {	// Textcube-NG: 즉시 호출 (function(){})() 는 PHP 7.0 문법(r4-4)
	$f = $_POST['fileName'];

	// 1. ASCII null bytes and control characters (0x00-0x1F, 0x7F)
	//    Prevents null-byte injection and terminal control sequences.
	if (preg_match('/[\x00-\x1f\x7f]/', $f)) {
		Respond::PrintResult(array('error' => 1)); exit;
	}

	// 2. Path separators and directory traversal
	//    Prevents reading/exposing arbitrary file system paths.
	if (preg_match('#[/\\\\]#', $f) || strpos($f, '..') !== false) {
		Respond::PrintResult(array('error' => 1)); exit;
	}

	// 3. Shell metacharacters
	//    Prevents command injection if the value is ever passed to a shell.
	if (preg_match('/[;|&`$\'\"<>!(){}[\]^~*?]/', $f)) {
		Respond::PrintResult(array('error' => 1)); exit;
	}

	// 4. Unicode control characters, invisible/whitespace codepoints
	//    C1 controls (U+0080-U+009F), soft hyphen (U+00AD),
	//    non-breaking space (U+00A0), zero-width chars (U+200B-U+200F),
	//    line/paragraph separators (U+2028-U+2029),
	//    bidirectional overrides (U+202A-U+202E, U+2066-U+2069) — used for
	//    Unicode filename-spoofing attacks (e.g. RLO trick),
	//    invisible formatting (U+2060-U+206F), BOM (U+FEFF).
	if (preg_match(
		'/[\x{0080}-\x{009f}\x{00ad}\x{00a0}\x{200b}-\x{200f}'
		. '\x{2028}\x{2029}\x{202a}-\x{202e}\x{2060}-\x{206f}'
		. '\x{feff}]/u',
		$f
	)) {
		Respond::PrintResult(array('error' => 1)); exit;
	}
});

/* [보안] 원본은 이름만으로 찾아 다른 사람의 글·임시 첨부의 대표 첨부(enclosure)를 바꿀 수 있었다
   → 그 첨부의 parent 를 다룰 수 있을 때만(blog.attachment.php canEditAttachmentParent(), setEnclosure() 와 같은 blogid·name 조건) */
$pool = DBModel::getInstance();
$pool->reset('Attachments');
$pool->setQualifier('blogid', 'eq', getBlogId());
$pool->setQualifier('name', 'eq', $_POST['fileName'], true);
$enclosureParent = $pool->getCell('parent');
if (!is_null($enclosureParent) && !canEditAttachmentParent(getBlogId(), $enclosureParent)) {
	Respond::PrintResult(array('error' => 1)); exit;
}

$result = setEnclosure($_POST['fileName'], $_POST['order']);
// setEnclosure returns: true (set ok), false (update failed), 0 (cleared ok), 3 (not found).
// Must NOT use numeric comparison ($result < 3) because PHP converts true to bool when
// comparing bool to int, making true < 3 evaluate as false (true < true = false).
$error = ($result === true || $result === 0) ? 0 : 1;
Respond::PrintResult(array('error' => $error, 'order' => $result));
?>
