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

/*
	id : d
	frame : f
	transition : t
	navigation : n
	slideshowInterval : si
	page : p
	align : a
	image : i (*!)
*/

// Textcube-NG: 이 파일은 rewrite.php 를 거치지 않고 preprocessor 를 직접 부르므로, rewrite.php 첫 줄과 같이 입력 개수 경고를 먼저 받아 둔다(r3-20)
$__tcStartupError = error_get_last();
define('ROOT', '../../..');
require ROOT . '/library/preprocessor.php';

$images      = array_values(array_filter(explode('*!', isset($_GET['i']) ? $_GET['i'] : '')));
$navigation  = isset($_GET['n'])  ? htmlspecialchars($_GET['n'],  ENT_QUOTES, 'UTF-8') : '1';
$interval    = isset($_GET['si']) ? (int)$_GET['si'] : 3000;
if ($interval > 0 && $interval <= 100) $interval *= 1000;
$startPage   = isset($_GET['p'])  ? max(0, (int)$_GET['p'] - 1) : 0;
$align       = isset($_GET['a'])  ? htmlspecialchars($_GET['a'],  ENT_QUOTES, 'UTF-8') : 'center';
$imgCount    = count($images);
?>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<style type="text/css">
	body { margin:0; padding:0; background:#000; width:100%; height:100%; overflow:hidden; }
	#tc-gallery { position:relative; width:100%; height:100%; text-align:<?php echo $align; ?>; }
	#tc-gallery img { position:absolute; top:0; left:0; width:100%; height:100%; object-fit:contain; opacity:0; transition:opacity 0.6s ease; }
	#tc-gallery img.active { opacity:1; }
	#tc-nav { position:absolute; bottom:8px; width:100%; text-align:center; z-index:10; display:<?php echo ($navigation === '0') ? 'none' : 'block'; ?>; }
	#tc-nav a { display:inline-block; width:10px; height:10px; margin:0 3px; background:#fff; border-radius:50%; opacity:0.5; cursor:pointer; }
	#tc-nav a.active { opacity:1; }
	#tc-prev, #tc-next { position:absolute; top:50%; transform:translateY(-50%); z-index:10; background:rgba(0,0,0,0.4); color:#fff; border:none; padding:8px 12px; cursor:pointer; font-size:18px; display:<?php echo ($navigation === '0') ? 'none' : 'block'; ?>; }
	#tc-prev { left:4px; }
	#tc-next { right:4px; }
	</style>
</head>
<body>
<div id="tc-gallery">
<?php foreach ($images as $idx => $src): ?>
	<img src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo ($idx === $startPage) ? 'active' : ''; ?>" alt="" />
<?php endforeach; ?>
	<div id="tc-nav">
<?php for ($i = 0; $i < $imgCount; $i++): ?>
		<a class="<?php echo ($i === $startPage) ? 'active' : ''; ?>" onclick="tcGal_go(<?php echo $i; ?>)"></a>
<?php endfor; ?>
	</div>
	<button id="tc-prev" onclick="tcGal_prev()">&#8249;</button>
	<button id="tc-next" onclick="tcGal_next()">&#8250;</button>
</div>
<script type="text/javascript">
//<![CDATA[
(function() {
	var imgs = document.querySelectorAll('#tc-gallery img');
	var dots = document.querySelectorAll('#tc-nav a');
	var cur  = <?php echo $startPage; ?>;
	var total = imgs.length;
	var iv = <?php echo $interval; ?>;
	var timer;

	function show(n) {
		imgs[cur].classList.remove('active');
		dots[cur] && dots[cur].classList.remove('active');
		cur = (n + total) % total;
		imgs[cur].classList.add('active');
		dots[cur] && dots[cur].classList.add('active');
	}

	function startTimer() {
		clearInterval(timer);
		if (iv > 0) timer = setInterval(function(){ show(cur + 1); }, iv);
	}

	window.tcGal_go   = function(n) { show(n); startTimer(); };
	window.tcGal_prev = function()  { show(cur - 1); startTimer(); };
	window.tcGal_next = function()  { show(cur + 1); startTimer(); };

	startTimer();
})();
//]]>
</script>
</body>
</html>
