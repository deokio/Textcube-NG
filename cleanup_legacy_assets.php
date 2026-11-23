<?php
/// Textcube-NG (PHP port) — 미사용 레거시 정적 자산 정리 (명령줄 전용, CHANGELOG v3.127)
///
/// 이전 판(Textcube 1.10.10 원본 또는 v3.127 이전 Textcube-NG)을 덮어써 업그레이드한 설치본에는
/// 이 배포본에서 뺀 파일이 그대로 남는다. 이 스크립트는 그 파일만 지운다.
///   - 교체된 옛 판: jQuery 1.11.2·jQuery UI 1.11.2·Lodash 2.4.1, periwinkle jQuery UI 1.10.3,
///     TinyMCE 4.1 잔여(layer 플러그인·moxieplayer.swf·skins/light), CodeMirror 5.2.0 데모·테스트·문서
///   - 참조 없는 파일: Flash SWF·FLA 와 부속(iMazing·Jukebox·업로더·클립보드, flash.js, detectFlash.inc),
///     jquery.json.js, periwinkle 의 쓰지 않는 CSS·JS·less
///   - v3.175 에서 뺀 Dojo 0.4.3(resources/script/dojo/)·MooTools 1.0/1.2b2(mootools10.js·mootools.js)·helpdialog.js, 원본 판 dojo/iframe_history.html (v3.197)
///   - v3.127 목록에서 빠진 CodeMirror 데모 mode/rpm/changes/index.html (r3-36, R-542)
///   - r3 에서 뺀 PHPMailer 5.x(library/contrib/phpmailer/ 의 class.phpmailer.php·class.smtp.php·class.pop3.php·ChangeLog.txt·codeworxtech.html·README). 6.x(src/·legacy_shim.php·language/·LICENSE)는 남긴다
///   - 원본에 있던 OpenID 2.0 라이브러리(library/contrib/phpopenid/, v3.53)·jpgraph 그래프(plugins/StatGraph/count/, v3.52) — 웹에서 직접 열리는 PHP 가 남지 않게 지운다
///   - r4 에서 뺀 CodeMirror node.js 전용 파일(package.json·bin/source-highlight·addon/runmode/runmode.node.js, 편집기는 쓰지 않음) — 지운 뒤 ED_tinyMCE 는 플러그인 화면 '다시 검사하고 풀기'
/// 배포 내용과 같은 파일(sha256 일치)만 지우고, 내용이 바뀐 파일은 건너뛰어 목록만 보여 준다.
/// 비게 된 디렉토리는 목록에 있는 것만 지운다. skin/customize·attach·cache 는 건드리지 않는다.
///
/// 사용 (Textcube 루트에서):
///   php cleanup_legacy_assets.php                       미리보기 — 아무것도 지우지 않음
///   php cleanup_legacy_assets.php --apply               지우기
///   php cleanup_legacy_assets.php --apply --backup=DIR  DIR(미리 만든 웹 루트 밖 디렉토리)에 복사한 뒤 지우기
/// 웹 요청으로는 실행되지 않는다(404).

if (PHP_SAPI !== 'cli' || isset($_SERVER['REQUEST_METHOD'])) {
	if (!headers_sent()) header('HTTP/1.1 404 Not Found');
	exit;
}

$files = array(
	array('resources/script/jquery/jquery-1.11.2.min.js', array('2ecd295d295bec062cedebe177e54b9d6b19fc0a841dc5c178c654c9ccff09c0')),
	array('resources/script/jquery/jquery.ui.essentials-1.11.2.min.js', array('59ff7e71c84f97a6ece46ff4d0831d408e30d1f4521a567f517311972aa5b556')),
	array('resources/script/jquery/jquery.ui.effects-1.11.2.min.js', array('470ca5b55f943b7ba12419d7e6c7b3fe53a21564802b1ad7198f030c46f6c204')),
	array('resources/script/lodash/lodash.2.4.1.min.js', array('80ea67035bd48ad0e9afaa95d8e353cc5c5740a827c2f0423a49491fa847ab21')),
	array('skin/blog/periwinkle/js/jquery-ui-1.10.3.custom.min.js', array('b726e9af58a9822e53161b6d3121a877af0c8cc897ddcdf18e22dd3a17281f2b')),
	array('plugins/ED_tinyMCE/tinymce/plugins/layer/plugin.min.js', array('53fd6853bcd687e326292c404a00b4a088672ea8122d896c7e19f89798cfc9f0')),
	array('plugins/ED_tinyMCE/tinymce/plugins/media/moxieplayer.swf', array('a5eaaca702e4f3e0f519bab981c184992c89a9c67e1cd8148b18751cc15a8f7d')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/.gitattributes', array('63124425b4ee8ace94ad85e5f40c9ddca2588632dbbe0f0558dfdd1f40fa6e7c')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/.travis.yml', array('a74a0a320aa986067e3507230514cc33aaeb032d73512a58dea71693c59bda82')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/bin/authors.sh', array('b41f83889b3ac66e1e823ad6d574d691f1f180f886d3655e8b7b00ac6753cc49')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/bin/compress', array('6d71922c844c32f5043761b33caaa941ea3001ca5249cdfc39b7855127009ec5')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/bin/lint', array('77ad05dd0823e31d7095d5207fb3d9292bf2dff0d90f1ff11c85aac4981285aa')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/bin/release', array('9478ac27948148a652e2490e187f9a89408b0589ee31462e8404290477010b67')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/bower.json', array('ab77ce677cdc1f461d32ae865acfd6bae7cac253fa957aa4e0efb9a012f6f605')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/activebookmark.js', array('de5b99618fc02cca44433220ec0f93aaca1d7b0ce0e8d06e1e5d3f91ee33d5e8')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/compress.html', array('52d48dd0bd1a144d28c679434db71eac0930346c087dd061ae86fa58ad08b0b2')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/docs.css', array('ea886c9c9ef2e1fc1395e94d1c01ba2be604e9d3bcff765446055dba71472891')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/internals.html', array('b4762160bb5df5b9e5340224339d9e4832114ade985025d2424a1146bce11062')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/logo.png', array('78234e273c369976e4885386d682939cdbaf069ac3fcbd74ed614282c6712cf2')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/logo.svg', array('ff37062bc71a46dae7036e18faaa89b56112278029f94bf2acedf7e8ca6706a4')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/manual.html', array('0ef32bc4eef4417a61c6a1ddfe98f0920bed611f976ad5de7140c6b16478c341')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/realworld.html', array('543242082f7a2aaaef65f1434abad272dce2b154681185ed0033e3cd5181a5e6')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/releases.html', array('caf863aa9f87d0498f5d8871291207c861faa49a7ffa65378d1fc4da677845f0')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/reporting.html', array('3d648dbaedb499bc02e2f8c701075f2a571720a960dd8a270acac0e3751f2892')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/upgrade_v2.2.html', array('d49188ba5e8d74c1474e66069d1fa05cb28c6e4295cf5ef05538ef02000a2437')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/upgrade_v3.html', array('7345cae5ac250678d3095f83ae8359ee5434f5eb619d57de29d87fa3381f570a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/upgrade_v4.html', array('b7dd95e828030cfb999e270709c3f46c8fa176e2010730ad9524ac6233cd7eb0')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc/yinyang.png', array('b75f3be5143e2b90614da583c6b1f3adff670d4ba14b757cf339fba4a4988f3b')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/index.html', array('0e5a32fd69f58018e231d2151379be34f56fbe506b4e91de517b27a512f15dc0')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/apl/index.html', array('e82b1625444d69bfd47da2f8386dd8cfc833612cc3fe0252762b293cfe0bc42d')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/asciiarmor/index.html', array('7d605ea77dbaf6f671e407ace693e00bdffbd69b4fe53ac23712a4224b2987ac')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/asterisk/index.html', array('538cb3083f7d770874aa0cf46c18b0c93f005ae7db805614bb7ad5f905a000d3')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/clike/index.html', array('3ebb562420216e3644d86ca0d699646567c810784b6e855a84b9eadee4076096')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/clike/scala.html', array('72099d11aed78ef0a8522c60f88ba5d05e3398cf58d25b7432616096391219d4')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/clojure/index.html', array('47ac18dfb9bed2315e505b5a61b084ca9b6813cd3bcb479e4062dbd5b473575f')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/cmake/index.html', array('70c56cbf84a0644375e5a05ab8e9b68854ba4bf8295050a3af905d5e51991583')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/cobol/index.html', array('5d3ac81c0ed82da72f5016505c957996c95893d09b837b6e5283575d9744dd2e')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/coffeescript/index.html', array('9b4ced92491b5ec60366f88e263f9f95015f3872c2350b368b6cb1956a58797a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/commonlisp/index.html', array('48304f82d2691b5690111a61da280a7d0329824ff1967632db394f4107ca11f3')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/css/index.html', array('a542850e4bb2185a77d321be8e90e5a9cdc9e2949d50d435b26b45c9d12886a4')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/css/less.html', array('f7c3a9cfe1b5f3135f2e01037e9020d50b9c23103b8065514095fbbdea4e0764')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/css/less_test.js', array('f515841d02c5b8902930be9da04d7b4597b58848dbc8de1950b5baa1c8830806')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/css/scss.html', array('bad8aebefd205cd2e6eae3abbed6ffc808bbe7e7eea75acb9b1586ef2a762b3f')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/css/scss_test.js', array('5fc2a657615ffe11d5a7ca3e8d70a01efeca288d92a02fc7afe61d40deb8f95a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/css/test.js', array('44d053ca3987c873b1bf5760517cd7f80792578dd48a6bab67787dd8abe4b6fd')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/cypher/index.html', array('25593e8a4c7e047b523fc96228625a8a13afc9f7fb214974da0ce591054f96c4')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/d/index.html', array('5cdfcc46e0c88f11311c5d932908efe7b27a9ceee3dd1feafa4bb0b6f896d2d3')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/dart/index.html', array('c03784de63af8f63bafc8b036bc2318c01b8d8613ead5fc61519e1be1bad13fd')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/diff/index.html', array('f4c85d5a8b5dbd55837515c495bc0159a81a5e3028b367f263140624152a4114')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/django/index.html', array('cbf57f02cea56162efcc200f4d6f3aea42576bb538b4a11446a72239866742f0')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/dockerfile/index.html', array('837202d427f6994af58ea72fbd08ec8f7f3b14887448fd84e31ee68852bda824')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/dtd/index.html', array('95a09de1e3b7cfac2d173c0122061d24ec5443349a6e90e0eb60c37a6cb104b6')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/dylan/index.html', array('5fc1569b8ea290ccd09ee7e4c3a5ad2a293dae2b900a3eb9c852b02c0eda60c7')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/ebnf/index.html', array('4a84b7daeb03d0d13694856346275efa75ea479916facc8f2e51e7449a614bc7')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/ecl/index.html', array('40b83fd98fb29f95c99f07e0241cfc83258f0d7fb5b6bd218a8b8aa6f727c99d')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/eiffel/index.html', array('633c0371cfb21c067bb4b882a9e09c159095c476d4875cd54abffbc2817dc30d')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/erlang/index.html', array('aea2656ee99602ca62ccacddd6e77c1c03ac514926cba2db7ee3aab92e8f9500')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/forth/index.html', array('b41b4d5daa7774401579db3d0d1d68c166b97da46c3fd1c72fbfe13d70ae4cc2')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/fortran/index.html', array('f81e9b5cdc19e6703eb3e2477d53b6d6c778cb3d5d8360d658fff3ae9d0962cd')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/gas/index.html', array('84080f5d09d01d8b6e6211cbed029e53f4b92a3eea3e662306e6282aa2b81d79')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/gfm/index.html', array('c160a39d93f31a72060f1488273a9d62fd32705f2d3bacba525460ba576c2915')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/gfm/test.js', array('3c4dbfe7130eb44274a90a737fd7cca16ebcb846f13686893ffd7845424ff7d1')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/gherkin/index.html', array('43eeb9bc6783d63288bb59560e7fae8b8073671c26d83dd2e094bbd4b5c43dcb')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/go/index.html', array('4c0ba11e08a29b6396787edcaffce4ec2148426d8276d329596d47e92477801c')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/groovy/index.html', array('108e5a8c12693ebcaa1fefabcb2674fba31687c8cc86d6bb422abc3d085108e9')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/haml/index.html', array('c6fc029a6ab99692007433b79e187d25c3e779645d0cf1039dda43bc983d5022')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/haml/test.js', array('38777cbf9db7d727bdb8ec7f618e2c44a53334fb634e0b2ec96457459c756a7b')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/handlebars/index.html', array('43d9061b084b6dd5e60246b243da8a95343a5d857cab414855e16094170a40a3')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/haskell/index.html', array('2d97b253b8c1e55fa57b53a01a6b2dea00044a94c547013ba556048c57ad1220')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/haxe/index.html', array('0157d6b13154667286ac02ade73a4751fa14d1556de17e3aea300662a2c1495b')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/htmlembedded/index.html', array('83bf4d3af71a2de6ac3ced0c63ecf05d37fe684a8ce9cc7d67a10d4de009809a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/htmlmixed/index.html', array('489fbfd5b5d1b3881fb004b4efce8d384440a1a81247e7129b5a3f48dbb6f619')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/http/index.html', array('e23ab67dbfa5e965759ce5674b9805f7b2e865df9cd7a5687428168b96d7fa09')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/idl/index.html', array('eef618ca8efbdfe31b9c4c05f7c59113e394abe8a5c34ed02fc6019bfcdfa810')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/index.html', array('c81b7005daebc1bdff1b0f049343587051a1635b43132d8d575075f2f600fe2b')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/jade/index.html', array('14c16d8520e4dfde6de6d7f5d0f1e5edbee429aa5b0deeec0745caa2f022c660')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/jade/jade.js', array('d93c2aa7434e8b1212ec75013dfc4d69ae39b2d828b66a46c5dedcbf1dd33045')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/javascript/index.html', array('bd3eb54016a0d4bba846aa86a8dd5f451459f02c0a09352094b3d974ddabd6c3')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/javascript/json-ld.html', array('ac787941ea8a1b871daf68054ca196a99d05cd6873587d4b62586aff9fb92452')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/javascript/test.js', array('4c63eec359fcd0a0bed35e7eecb4a045d3f911b0be1c0c5279cbf0818b14101a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/javascript/typescript.html', array('a29c079f73956b80973c613887660c237fcee6c9a703fd22d3397fc2d21efbf9')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/jinja2/index.html', array('608e6e527bdc28247284a45fd070e4100bcd1f476dfbb74ba063418420b2334f')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/julia/index.html', array('b4104011f18d67932667afa644812a14b68f9046f8ac15172b4a2a1669db5357')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/kotlin/index.html', array('059748a46063c1ced29b99e2813901cafb89c0e39b7a324412ed26de4920684f')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/kotlin/kotlin.js', array('e0cc9b9c15743c34ac19bbaca4e3776816035e66eb98daa26425e089f646e344')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/livescript/index.html', array('e9fdf512b3321fa72c23e729c40820b6059dba89b1ec2605d4d9a3bfecf88360')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/lua/index.html', array('51fdca64612a75538b38f4b4395d96147f1691185c447d1bc624577f62503fb7')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/markdown/index.html', array('bf0bc9308b210d2e19c1675db4590fe51839d9689ad7a159fe7de4460c9cbe6d')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/markdown/test.js', array('e6cf268678c248ab5dcae41e389854c78792d2287c8598155b9118868d5a4a9a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/mirc/index.html', array('b0e481aa910a9fb697e8f3b199a7fc824768e606f89a4e5b0b66b2962e896a82')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/mllike/index.html', array('c0f7c1bf7b6819a0321afb25004e6cd985d25cb10da241f68d29570b47bb8f9c')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/modelica/index.html', array('49461eedb80f01dcc87eafe11f7546f1523b6e9bf414bd50640f7f71384a7c4e')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/mumps/index.html', array('8f542b98f145d8c0a7950b1612628149b551079cc9cd9b4f7e5081b8eb0f1941')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/nginx/index.html', array('17824cfd66ab561bf31a9449ffa280e3635d83af495a6473df00ae6b1737e697')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/ntriples/index.html', array('67fac63b38d5f5263dc7fb850903d39766f3bf12e492c417ed617c55f8899e5a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/octave/index.html', array('8b9d323754519b21f013150f7ae718d2c367a98311d22254c706bf8f6664ebc2')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/pascal/index.html', array('0c1b9513656b584e6ee216c216fb61b3dea5d99688cfefabdc557e75e5ee1865')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/pegjs/index.html', array('2a91062d1e3316c68c6f37ea563cc2dc1d7e0267b16226b5b1e40ebe16ed399e')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/perl/index.html', array('2a592e6ef6bea7d797fa6b8f29d14588026c956c7e775463ede6deff36555373')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/php/index.html', array('40fb40791478bb5fb61edd34a8a7e08ac590ec7a21b2b335d85d7ebd90618628')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/php/test.js', array('05deb4c55c621b90fced9cb6b46cb9b2edee01a8fb47266eff7dcb1962b91238')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/pig/index.html', array('76781fc2ff85be2b7e948bf14e566bdc483c495db632be63f117ee8f9f29014e')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/properties/index.html', array('a261171d5f85d0b5098635d00f4c8b3390fc5a1bee030b5a9506c80d590aaa46')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/puppet/index.html', array('7b59ea058ab4941bc7f368f1aa8b38bb9a3875d5077dd5a46422a508f328376f')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/python/index.html', array('97632c2653731ca5c3dd002d3ca6c382c20e6880a2d97a0267a7c9cec409468c')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/q/index.html', array('aefdb56e4ca7eac678569d0faabc2ded6797a6eb4e4432632e9a708aadc89790')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/r/index.html', array('412c8f95de797c5ffa01a4a7004b95e874c596882e716ab568586a6a0e857737')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/rpm/changes/index.html', array('29c17c32afbbbbf513542d44ccdb29167944677f12d1350348f050e412b8bbce')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/rpm/index.html', array('97ebe73276ae323601194b0adc0bd0e55fafb8387da1901fc8ccad74feb2235f')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/rst/index.html', array('aeeb4f3a8079f5dbb2dcff34c45f4567b946c49de91efea9caf60a4f8eb98350')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/ruby/index.html', array('00f454d5eff4cef18448d1b8a0052595272d1414c208fb346222ab81eaef1d5a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/ruby/test.js', array('e5e97a5712f1f1cfa7643394090e73708b2dff3a6bbf21f4cafbc26ab72d52bc')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/rust/index.html', array('c684e70689bc8108e67413c11e4ca68f4f267710795569f5d7a64935ca00858e')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/sass/index.html', array('d08e9d1738b274d76720f56840b85fafd0294718d58e56965e89d6eb60899063')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/scheme/index.html', array('bfe7c782b06074acead8d4f1746c3c3b270860cf0ea00950d890cbd99e75f71d')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/shell/index.html', array('0f2ee5468bdeda2a1970ccb11376a720399fe9c2aa80210127a8f30909dfb6b0')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/shell/test.js', array('cdaff690080dec73e2f9044eb89e1426fd2645ea6d8b253cd51cd992717b8a06')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/sieve/index.html', array('a24a23764765a02d10f78354bf405c08ecf38c617218319b008fe163cbd4b03d')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/slim/index.html', array('457fd0808eba92e4bb712541ef94bccc0b7c0f880bd0119e413f752a198b842e')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/slim/test.js', array('4c7e76beb724f3954a55a6a3fd2e96bc2a2baed32907c6bc9f457771f2d3580e')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/smalltalk/index.html', array('75abf2b24f6557a413072ca3d8c76b0594617c6de8c33b1c1d2da10e27e2765d')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/smarty/index.html', array('91c3ac08978b1db4807d353460ad367f713ff259cda2981f8f839567b7032e42')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/solr/index.html', array('cfafee84ee32d526e973a20d6cd7f4851e4b8b5621cf57a6400c0c0cc021d8ea')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/soy/index.html', array('cc65aaad55e20d0a06ebb15a7920de4b36a82548eabaa23712f1dd800bfdc803')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/sparql/index.html', array('6969fd470f5c10032a3c530f1bc4c0801ff2bdc12f232371df16f71406020abf')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/spreadsheet/index.html', array('818f6cfa02ef52c7f988c5af7d4570c096023893e18c8e6eed8ec5e0155dc083')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/sql/index.html', array('56647ce78d89f5fb5c246cb2cde524da92c35933678ba7b474a7661b08724815')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/stex/index.html', array('2618a134d06084758619cbe414c86dee10eff1e7ed3b24517e71cc0caece5c0a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/stex/test.js', array('98079995a5f1aef432e18112073fb7590fe40202e958088358fd3805e5fac022')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/stylus/index.html', array('39f7f0b8540cddbdb006ca70ae56f5d61c69848eb819da4c424d0afd6bce513a')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/tcl/index.html', array('1a03f78f8583051e262410faa8f294ebf003acc0bb200e9d3e1629a0d76864f8')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/textile/index.html', array('3d5aebf5b7b7d6e590f2ceaaf1d8c1c9faeee0d1ab92a3741ab3a5ec2d736dcb')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/textile/test.js', array('09418771660cfdd14563a73ce735512271e17667376378d62fbc0ad1c02e9089')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/tiddlywiki/index.html', array('bcbee637025612882cc372a16079939bdd14b3391f01956637340d078df12b53')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/tiki/index.html', array('4f91bbaae451a16fb274af0c31a23a5a8c6a2f07c27f8481ecba56566a1078f6')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/toml/index.html', array('3d4e1701062ad0190a1f00e97330ee3ee16bb0848bff96a097445653ef2179a6')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/tornado/index.html', array('a904afec9d78cec77574991a9f7218406548c1f90253d9868108b0568e6bc097')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/troff/index.html', array('0272fd0535e11f01bd009e8e5fe1d708305d460c5ec36379c32ce4f96c0616b4')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/turtle/index.html', array('7874367f2d85335edb2061d0ef8ad9cb3668cff64fd6b58633fbcb1c2e964124')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/vb/index.html', array('59c99015e349910d3dc04b4febb522a6657105912d2b32f25101ec45c96120e7')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/vbscript/index.html', array('6325c73a8dc155e1fb598b20577c4017dea6a65f85c3c00d11e01c81466c9e50')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/velocity/index.html', array('3fdff11ffdc8c321b2b5d7e022955b1fdfd6fe8e44e8ee68a7d91785104d2887')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/verilog/index.html', array('c89dc4992e0cfcf0df5e6f0de4ae13d2072efda9002beb2226fe3e1ab8a54dfe')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/verilog/test.js', array('5880e689160e4164b98087c51e6f770d0f2c27b05b58af9fed82598e6e4c30b9')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/xml/index.html', array('b351304e7502ff28489fef9d00672ada8123468b75a19c2016c5ba06089d82b9')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/xml/test.js', array('4fc59c5c6c9d08e422cb65fda79a565d541653d9fc0f0359ccd6dc0ff1231863')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/xquery/index.html', array('fcf3f8c87d85fd3d7f8d15a24e49cac68ea225ffa7f195769401632e58702562')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/xquery/test.js', array('6cb9e048e1ee5c9ada53bf39f105185f03e99a2618d62cc3fa4a1af846b4e1a3')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/yaml/index.html', array('f998ecd3cffb4f8f1b0d8fcff32462681f7ea38e59bd66599ccd789d3d8b638f')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/z80/index.html', array('fd8f18f5f7e23492dcb7048a6804b0d98be902e92e1abcac79713bc2b226acca')),
	array('resources/script/jquery/jquery.json.js', array('5b6ebf82113398043f260f53acd914cedbddb11de55c2710537954a0990631cf')),
	array('resources/script/clipboardPoter/clipboardPoter.swf', array('e8ef2c86280d69fa29828aadfb3dd72d0efbbe7541fe8dc7a8552e5fa68ef5f6')),
	array('resources/script/gallery/iMazing/main.swf', array('6ec0d4e60af27b6827cecae33e520cacf60e4470e374952b26d057d01023186c')),
	array('resources/script/gallery/iMazing/net_imazing_frame_none/control.swf', array('dc08196aaa440fb7a5530c2f99528b3e8be6f785bc4b1f7059f9ab2d23305b6c')),
	array('resources/script/gallery/iMazing/net_imazing_frame_none/main.swf', array('84f5b3355acfaf6d3d12158dbec75e4c5e3f51a9b6dc35365331745f0297fa5c')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_none/control.swf', array('e3ff5bf054d0e547c78f71ff14d73bf0f0b1b63aab8fe98cd8495dfad07ec8b6')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_none/main.swf', array('26bfc3cf33d6958d717930ab5c8c87d48891c75819cdc401fa63ebd91e3d5f97')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_showroom/control.swf', array('cc670380e237283ca641674c76e494c4abb39b2175b9fcb07107c4988cec3d31')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_showroom/main.swf', array('59e006c05f743c2d4ff9c09ea5db8067783f8110c337f826054f0b6663d35776')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_sidebar/control.swf', array('b9ea614e74769c80f35ceee48d53fd94f5239b44d70412a4a1fd2c9211294789')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_sidebar/main.swf', array('366107c44ae76540b7bb7cee17b0e6e010a1f759067855bba555b7e6ed107b02')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_sidebar/preview.swf', array('f41d66cdb75354dab2e60971f41cb1d18740bafabc9d61514526ae9ca6b911ea')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_simple/control.swf', array('cc670380e237283ca641674c76e494c4abb39b2175b9fcb07107c4988cec3d31')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_simple/main.swf', array('30d857d07b4877f9b664ff05f0697fdea01f971ca45bc0579f0ca237ff03862a')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_alpha/control.swf', array('1562150c14cca0c181a5233a9254acaed43030780fc4ca4792e8a9cb88cae6f7')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_alpha/main.swf', array('62d716c599e7c912ed0a0af9fc6ca6928c63d0602a80d561515823adb671149a')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_alpha/preview.swf', array('e149f04ddedcdde08fee4dbfc4fb088c9baa9cf0506e0deeb80ea5a3253cb80b')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_contrast/control.swf', array('1562150c14cca0c181a5233a9254acaed43030780fc4ca4792e8a9cb88cae6f7')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_contrast/main.swf', array('4811611c4e4e31c2753c6cbbb701fb92c3c16e12f5f7111e56b5caa84581e579')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_contrast/preview.swf', array('e149f04ddedcdde08fee4dbfc4fb088c9baa9cf0506e0deeb80ea5a3253cb80b')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_none/control.swf', array('1562150c14cca0c181a5233a9254acaed43030780fc4ca4792e8a9cb88cae6f7')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_none/main.swf', array('5eb60546edb32fe70c99b8db5de290233f0788f44a74bd62c7679c95675030ef')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_none/preview.swf', array('e149f04ddedcdde08fee4dbfc4fb088c9baa9cf0506e0deeb80ea5a3253cb80b')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_sliding/control.swf', array('1562150c14cca0c181a5233a9254acaed43030780fc4ca4792e8a9cb88cae6f7')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_sliding/main.swf', array('d9ebb3b4eb759bf1cc1c7437ec147aa0ef73acddd2be3001b59e557ab8a87752')),
	array('resources/script/jukebox/flash/main.swf', array('75b60073a1eac8eb5d86c7c7bdd0bf54f9a786facadf7ad7551892d9ec4a70aa')),
	array('resources/script/jukebox/flash/mini.swf', array('c6b6caba09cfd4305ec300bf969735316765e8a6b06beab5c584d135f4ad8c20')),
	array('resources/script/uploader/uploader.fla', array('4764e2ead3b460a3efadab5095342184ef649059bfa1e0ecd5b6818794597dec')),
	array('resources/script/uploader/uploader.swf', array('8f6fe65eb2f5efe5f94459ecc51a0272017829db02f4a0c1f13dd0f36e6e2ad6')),
	array('skin/blog/periwinkle/css/bootstrap.min.css', array('cef7b5122315b3f0e84ab1539cd690e33e45bc13dd5f094b02ceec8f08696455')),
	array('skin/blog/periwinkle/css/bootstrap-theme.css', array('d0cc79e811969b3f1196b0ee2e4fddbfd1e9f819879f1eecb00205310eecc5c7')),
	array('skin/blog/periwinkle/css/bootstrap-theme.min.css', array('b1874dab85dee4d31e6f616d6be2f5c16433d723eaaf067a03ee6fadd7dfc2ff')),
	array('skin/blog/periwinkle/css/bootstrap_bak.css', array('0cfbef6678f77e0c2d479a096e394c5c6342acdbd76d16dbf45819f28b95bd69')),
	array('skin/blog/periwinkle/js/application.js', array('199b7c5b943b99f6cc8e9721ea413a2b60a369fe97d8a3bcf57eca2717fbe602')),
	array('skin/blog/periwinkle/js/icon-font-ie7.js', array('ec7d6a375ccf3ddf274eeff8f125b884535560a7c19f855d4dd1b0c927d9543f')),
	array('skin/blog/periwinkle/js/less-1.3.3.min.js', array('7bb9e794c59412e657a262b42eea7eb4193e864d339ba15acc8fa62172cfdae1')),
	array('skin/blog/periwinkle/js/typeahead.js', array('8cb12c9f3b936c83508e21425282664d134df3e91a21128d9ac1b408d02191a4')),
	array('skin/blog/periwinkle/less/alerts.less', array('2f5ce49a557c56aeb054f3055cd32bc4b40730c145f25fc38d7ac62f9fa9b845')),
	array('skin/blog/periwinkle/less/badges.less', array('3152dcc69e04b507f27ac79680e95af101d13ef5ec842a3eb9f632d9db01dd17')),
	array('skin/blog/periwinkle/less/bootstrap.less', array('a91061493c39d27216b24c396f88e75b76de6377d6b822e3223ea47554f0ea68')),
	array('skin/blog/periwinkle/less/breadcrumbs.less', array('521b9ae864c0ab44f8f496859a6e8b28cea19264a6ece0239555bc146d2f8db1')),
	array('skin/blog/periwinkle/less/button-groups.less', array('1c1c3f0d3f76927a8db0944ab4ce6564bf5cdfa23bb50f8696fba2dc10d694a1')),
	array('skin/blog/periwinkle/less/buttons.less', array('7d1a6a066133c9c64acc16de6bb4a1d9f3daa2b34865f8b2d60f42a75f8edd57')),
	array('skin/blog/periwinkle/less/carousel.less', array('00a9cf8863a87c4ab6be0c230ddaa708fc28b3ba9173e499d8fe8c59b475caba')),
	array('skin/blog/periwinkle/less/close.less', array('bdcc37809c31ed798116fadd3e3092b6459bb1b32ac0b7f7cb3de2b7764a962f')),
	array('skin/blog/periwinkle/less/code.less', array('53281e5b00f9cdfacb1389104c350f0df7e689c0982d82cd58568bbeb9a5b5b4')),
	array('skin/blog/periwinkle/less/component-animations.less', array('f839b70f446a55545f9f1e6a12425a83306c6001de17db101fa581ad0886ead0')),
	array('skin/blog/periwinkle/less/dropdowns.less', array('1b54ce38d910d86dfa5aa55959103b0c565fb46a8098ff837a958de689d005a3')),
	array('skin/blog/periwinkle/less/forms.less', array('7c92b07f7d77f2a313310b4a82b7940c41d3f1101060ea10702a302407b63ba9')),
	array('skin/blog/periwinkle/less/glyphicons.less', array('0065bc82417f96689b24d8d81512cfa701dd9a6fab8fa50fdda43bb21bc81f4a')),
	array('skin/blog/periwinkle/less/grid.less', array('5c93e5d2156116a721f64fb56d1c1cae056a95caa8668c9e9fe5c528602a350a')),
	array('skin/blog/periwinkle/less/input-groups.less', array('3b8e12e8c10bd7cc62a730e3ae9ca4ff79b740ee3732b947be0e5cefba24ff9d')),
	array('skin/blog/periwinkle/less/jumbotron.less', array('71991f1ef8c00ecac1fb5e733bae0dd324886f9c33b32b9591e73b9c0ae19ded')),
	array('skin/blog/periwinkle/less/labels.less', array('ef07f05da025e2fbba9470f8b6c273541f6a5a41b8d5e155cfec38ce1788dd21')),
	array('skin/blog/periwinkle/less/list-group.less', array('5e20bbfbee46c059178cfe0d80ed7068019bfdb46d130db66c22cb0a409d118d')),
	array('skin/blog/periwinkle/less/media.less', array('5eab757b15c103bf176c89387f77e660d7f618f08df68b8506362293eaf51769')),
	array('skin/blog/periwinkle/less/mixins.less', array('803a9c81c396f198ea1cbee57574e2ba21b96fd0b7a0273a38bad9174624133c')),
	array('skin/blog/periwinkle/less/modals.less', array('a8fa2045db208558fa4818ce964af88d36b3105127aadcd077abe9c741f050a4')),
	array('skin/blog/periwinkle/less/navbar.less', array('c05bdbbc6525a36ed3be126ddfea0ebf23418ccba2f5c3b52e1ca6683bd97aff')),
	array('skin/blog/periwinkle/less/navs.less', array('aae9939909df6ed5f7c60795d8963e13cf0797a0fde914b1346e948c768fa1bf')),
	array('skin/blog/periwinkle/less/normalize.less', array('98021479637dfe376586028205c50e355589702b8c80e1752cb2997f61c8a7fd')),
	array('skin/blog/periwinkle/less/pager.less', array('24807b12e90e348178eea017f4d575d7c38897e1468fd40996a06abe4773b4be')),
	array('skin/blog/periwinkle/less/pagination.less', array('b051f9c7ed684ea459b04a942e36bd2fa8ae00daf4eb0a1b3f96ca8c323903cb')),
	array('skin/blog/periwinkle/less/panels.less', array('ffb2c34ab7d322dc174bc4c99979349f24b669e694e8da4e23714953a5b51e3b')),
	array('skin/blog/periwinkle/less/popovers.less', array('462d813bd9777b5c4be8b7f3e7b511a0aee1493a9146a0d4d3ac24efc1ee4eab')),
	array('skin/blog/periwinkle/less/print.less', array('4fc22aca6f53040250839339e09513eed2b542e90a3ff55e52cc15533ba52408')),
	array('skin/blog/periwinkle/less/progress-bars.less', array('a6833cbe735a099d19b5be6601e49f8f191f095fca2800db83b98235cb63bb90')),
	array('skin/blog/periwinkle/less/responsive-utilities.less', array('b35b87e534e4be8770e6387bd653f62a2adbed183c13304848a8bfa61f230513')),
	array('skin/blog/periwinkle/less/scaffolding.less', array('610f9ff1c1a823bab74d5241c4809abbb26f3a5c5098ebd9119ee7aba21847ce')),
	array('skin/blog/periwinkle/less/tables.less', array('f4a51b27630a389194ae02926fa6544d04bc50aed832bc61b3ee87869938523e')),
	array('skin/blog/periwinkle/less/theme.less', array('948a2b35441a140f4c3a2ffa3039ac293273eeab108ceabe205d68f454f8595e')),
	array('skin/blog/periwinkle/less/thumbnails.less', array('d22ac9c6c676926db0bf9759b23d9f01aff48a4f6734bdbb40385d9cb4e55922')),
	array('skin/blog/periwinkle/less/tooltip.less', array('4b8883c83b3de5f7733db72650e2ac41aaf9df332431fdcbdfb4d6ecad1a38e3')),
	array('skin/blog/periwinkle/less/type.less', array('7b7ac1db7dfeb550d8095c0b28b4c93f3994841f2c3cee7da1e4b2a54736be39')),
	array('skin/blog/periwinkle/less/utilities.less', array('34ef55730b4ac9137ba9f15fd641912e6c857568adefca9cdd46308f5769be38')),
	array('skin/blog/periwinkle/less/variables.less', array('8628aa415317634c26874cf0cf6cf998ba15357198c1b2fafd5ba7517e9e0830')),
	array('skin/blog/periwinkle/less/wells.less', array('71116d7fe04f7544ee257b9231a33f5d16ab0ffba8c7f70e97e3e5451bb874a2')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/COPYRIGHT', array('87955454dc17967e6cb6d524c44e711460a7230b54240812c6a799243d05f4db')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/content.inline.min.css', array('89a64bd1f253460c7feddb9aff0fcacb4be73aea2c55fb55003194eb57d2b95c')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/content.min.css', array('4ec97e9fb206dc1580dd7153c5eae3f53332bc1b177ff65bac7fa2b3420b8072')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce-small.eot', array('1b2a0219c27f8d06a15a9e63a38b2f0e538b6704e9a92baf32a0a8857946d3fe')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce-small.json', array('69e1618de2c43f7c67e36a6ac86573a4e345e4d123627d896209b06eb061a282')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce-small.svg', array('5a962547281b0a22a9c2dc7560df57775a346d2c3edf1f3734066618d1da6834')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce-small.ttf', array('40f04be3d2eb7f12ce3583a9836477aaec3fc2db5ecc3e20b1af992e8e9f45bf')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce-small.woff', array('591d15a5255dd22a711cf9021db151204592dd9a5c4c785c1213f93f985c8966')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce.eot', array('73852de780d66c54e139acc52ac70318762b20dcf5b7a2d98df915d0a4eaa743')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce.json', array('a2973d3655b1ce95e9c713c754b808d2f044ec2f0a1c12360f1461e0783a864f')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce.svg', array('b0afeb536203d3b8859b460832666706848fa5ebe6f810879391830208a3adab')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce.ttf', array('b222b037badfbe835f23537133bff2975a31ad206c81337070a6c1ea13b00c75')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/fonts/tinymce.woff', array('a3af4bd706a838f1e4aa08a16e97e34ffa9307c3c9b1925e25347b68bf081fc3')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/img/anchor.gif', array('2861666fd107d278d4449970615136d06d7f746be9bb19072cf9c8f30e565e1e')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/img/loader.gif', array('eb7cfd3d959b2e09c170f532e29f8b825f9bc770b2279fde58e595617753e244')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/img/object.gif', array('e6a15e52bc4a17b085073ba8debd4708ead6ae3d4cbeb3880c65cb7afc489777')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/img/trans.gif', array('9cf020d7c3bba7f5ab10cda54aabef934f906d4f9a3acf99e9e7dc6c98579635')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/skin.ie7.min.css', array('49de15b07568ff4a5cc7cb2e9e996854254964e193836104f0772537acf4157b')),
	array('plugins/ED_tinyMCE/tinymce/skins/light/skin.min.css', array('4657bbe7c591a83496059f79d8ba08ca7bb4e72867260690edb9ca0eeacff3a9')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/.gitignore', array('88fe28193bb110d584963caa800953ecb9d7bf9fd168d9332202062426b03878')),
	array('resources/script/gallery/iMazing/net_imazing_frame_none/config.xml', array('90ad7d8cf64f8161b5d6f20c47aa1ffd92f7619ff8dcbb776d7a4c62a7fe7d65')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_none/config.xml', array('459a667a6ff3a88f5fb8e43a0337bee01a64bea1f111ca9f489036a02d2b375c')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_none/preview', array('6976f9d0b86a084fe6cad62a74829dfe3d1d9b44a3109c5ff84aa17ab299dfeb')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_showroom/config.xml', array('f901072cfd0a205ae7e14b5611b82b4533ec1fda2bb6073f62da5af78d5cd1c3')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_showroom/preview', array('6976f9d0b86a084fe6cad62a74829dfe3d1d9b44a3109c5ff84aa17ab299dfeb')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_sidebar/config.xml', array('6db9110b4b3dd2df78d5211ef9b79dd259530c913023156406eebaf7b13b4b34')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_sidebar/preview', array('ef844902d3de2f4c87f14fcd4251a1af7c37e358971787a97331ffd9d2208117')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_simple/config.xml', array('b37225e5fe252ce347982dc8ea9c67086461c58bd60c602bdcf5947cfc879c56')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_navigation_simple/preview', array('6976f9d0b86a084fe6cad62a74829dfe3d1d9b44a3109c5ff84aa17ab299dfeb')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_alpha/config.xml', array('c88e4b207c5b334f1fbaefd62aa24a4a77ff68d14417d4cb6d13dbb9ea70c92b')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_alpha/preview', array('ea18d9b876c16783c46941b1836f781df5ae00a056ff64601b70a665cb85fb51')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_contrast/config.xml', array('db1e6a6360ff501427dfb1c5f95e8dc9aaf5ebb2ff8034d5ecf9d4352183a91d')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_contrast/preview', array('ea18d9b876c16783c46941b1836f781df5ae00a056ff64601b70a665cb85fb51')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_none/config.xml', array('69efb64b799d65979aadbe8561a1c74924b6f6c9f315b4f849bcae08c91a29d9')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_none/preview', array('ea18d9b876c16783c46941b1836f781df5ae00a056ff64601b70a665cb85fb51')),
	array('resources/script/gallery/iMazing/net_imazing_show_window_transition_sliding/config.xml', array('37ee6ddf34e3f46675ca77d08d2f1d3804f201c1146bef0bba752422273972f8')),
	array('resources/script/detectFlash.inc', array('a9979b90531cfb2f76544771020673b3a9f7eb57fb5ef2b98e83bd8488cb29f9')),
	array('resources/script/flash.js', array('341b8da3ba6e6c9460ab9ac50ba8d2bae0d324d3c7eb9dc85269bac864db2bed')),
	// Textcube-NG: v3.175 에서 뺀 Dojo 0.4.3·MooTools 1.0/1.2b2·helpdialog.js 29개. iframe_history.html 은 v3.126~v3.174 판과 원본 판(DOM XSS) 두 내용(v3.197)
	array('resources/script/dojo/LICENSE', array('83e8eb315eac86c1cfc32eeaf9bf635656b9048b29b3edded9b0cd5ee4a67ee8')),
	array('resources/script/dojo/dojo.js', array('66fafeebf390f8d53193659fe76c13906fac206efac471ce7d2e29ec601850fb')),
	array('resources/script/dojo/iframe_history.html', array('5a66a6f2335fa8d5bd0a585bac73c0616bd7080395b1cb6d319251b8a400071f', 'be05f57584421471abca541c94a08ca6be90ab9bc5b604b7da8addf68010af06')),
	array('resources/script/dojo/src/a11y.js', array('963a8520ed1500e806d41b0bc646f8bcb579efbb5048e1a06206bf75a2a3399f')),
	array('resources/script/dojo/src/dnd/DragAndDrop.js', array('59992bf3ae15597d350c7ca7619f7c68abba000cbe3b57b0124f6d2629344704')),
	array('resources/script/dojo/src/dnd/HtmlDragAndDrop.js', array('8378fbee998ed6dbad2ec0d737e064cf8c03e59e2142b3202d56ed810de322c4')),
	array('resources/script/dojo/src/dnd/HtmlDragManager.js', array('fdfa859354eb8ffad6c8046259170ec9a9df43b958ba81b6a4eff88cbcd49735')),
	array('resources/script/dojo/src/html/__package__.js', array('f65fc870845bab8508ddb8e8b4fdadc2bc1a64336b09c8d7dcfab06e1d533682')),
	array('resources/script/dojo/src/html/iframe.js', array('da8596423379d75f0f89b0a7edc811127ce80d7f89cd5b49622e92c4c13b261b')),
	array('resources/script/dojo/src/html/selection.js', array('b34ba236b373e8b3a1e4d15203083c7849c34e70f0de0e99717b4e2f87fcc3d4')),
	array('resources/script/dojo/src/html/util.js', array('51513de4755d8f8a7847b09ae7dbe16b4d1f0e33f2d8e2aef8b8dd5a7d552d00')),
	array('resources/script/dojo/src/lang/declare.js', array('fb24a0573eb1269169a83d93c196d724aa0b9c82527d2de8943f833e2fb130ec')),
	array('resources/script/dojo/src/lfx/toggle.js', array('12da4c423f758271a8ab34b93482ccb17b14364519c244d70719eb7783ccbbf3')),
	array('resources/script/dojo/src/namespaces/dojo.js', array('5e00a552f3e42ff6282dd1b84b33b403691e00658ee9f835df04c77ae89a945a')),
	array('resources/script/dojo/src/ns.js', array('9892da7b1af806400ac54c7643f265a20d4f42249a74d7a6a4d468316d32ecc5')),
	array('resources/script/dojo/src/uri/__package__.js', array('61203cbbf1b48847ab9de9338f49628401b3f433ee0ed39436a8e1c796896704')),
	array('resources/script/dojo/src/widget/ContentPane.js', array('942b0b903673718c510cae3908c9e7565fba84e071f66c5e4dc0d8cf898e99e8')),
	array('resources/script/dojo/src/widget/Dialog.js', array('210ff477413928e7d3d7d8074658223f5dabdf76a1b2c0b76f3a30ae572a84e0')),
	array('resources/script/dojo/src/widget/DomWidget.js', array('a2139a10737a329316e9d10825be2abb4087bbaf03ba70c2e526b690da957c2e')),
	array('resources/script/dojo/src/widget/HtmlWidget.js', array('19e51a37d962ac5b65bc04b94c58e188635eb7c7cf1bbaa2dd74211686b03303')),
	array('resources/script/dojo/src/widget/Manager.js', array('1ecdfee4ef9a2fa243eb58ada431b6ddcb3376fbaae6807e24fea5075351461c')),
	array('resources/script/dojo/src/widget/Parse.js', array('50c0f8bf562522ef734ca353053b7eaa6692c60b75cc20a53747b29269650ec2')),
	array('resources/script/dojo/src/widget/Widget.js', array('bf4598f6fcf4406f6d0c1037ffa43d34d8f400ffee823da9a9a9f2bcd20cdf49')),
	array('resources/script/dojo/src/widget/__package__.js', array('08e5e7dcb70313ba5529205e938a8c16d4bf737c5a409999ac787b7887563356')),
	array('resources/script/dojo/src/widget/templates/images/tab_close.gif', array('65b5e4e6f483de131071111dedf7373e834f55c27fa0cdcffb174e9847efe119')),
	array('resources/script/dojo/src/xml/Parse.js', array('a410caa35a9efde0406ac144381dd92679d3b2b35090f2adbe4960da010ffabb')),
	array('resources/script/mootools10.js', array('f85e5ebcc650c9d61c1d29d0a83b9120e9443d78bc4def99644cbdf704943e09')),
	array('resources/script/mootools.js', array('c97b0ee4295a0add9112e9b1c60a3ae36f09a737e1fad13863143db10e702f81')),
	array('resources/script/helpdialog.js', array('a5015aae6498fdea33484aee680626e804903b5cd00e486ee0f3712f129ebc16')),
	// Textcube-NG: r3 에서 뺀 PHPMailer 5.x 6개. 원본 판과 이전 Textcube-NG 판 내용(class.pop3·class.smtp 4가지, class.phpmailer 2가지)
	array('library/contrib/phpmailer/ChangeLog.txt', array('a7d73fec80cc829d0bc2e318edb49320fe323244713b9e132d6044b739314ed2')),
	array('library/contrib/phpmailer/README', array('4431f5385c899111a4f03deb879d618cba99ec9749fc2c2570f4e4d5dbac00e5')),
	array('library/contrib/phpmailer/class.phpmailer.php', array('b5423e8d323b26b9c34bd669be114ac61e5e9f7192616935da18f7047aac8811', '69d4855315ecf9ea06280b26dd896f1c227758f2e86dc1ec96133ccc9f920793')),
	array('library/contrib/phpmailer/class.pop3.php', array('22c56df9cd12ffcc3fcf8879ccac46a5f62151d0838bba7b1934803f0806bb06', '1d0b01d3d2cbf1b9d29145445e6b882ace55a06387377451cca6669a341720b4', '37baf6c68d8243083bbcd426f3fd388f3731e7127a400dba57878fc64e918ff8', '1cc47595b741aa760a17d3163c0e9fa32665bea73221856a74d0d1f7806c71a2')),
	array('library/contrib/phpmailer/class.smtp.php', array('8f904915e60eb4625e13f5df2d6b2d8728b7726ef1f8c10dee6829c5815472fb', 'c44c6d86e31bf906557676c820f1c86d7d5afaab429e24e0c47d67ded2de2e8b', '3dfc0abb186eaf495802fc9d03c0144db8581c95d005251174157f058bdb92ab', '5ac98ce7e6fc305ae2edc5a29694fe4d5e8cc0d40daa41b37fc4c748fb4a04a4')),
	array('library/contrib/phpmailer/codeworxtech.html', array('273542d6b6d4b8fb732b1c5aa718e1e3ce4bcd1c8ee393ac5a9bc22b74ef198a')),
	// Textcube-NG: 원본에 있던 OpenID 2.0 라이브러리 library/contrib/phpopenid/(v3.53 에서 제거)와 jpgraph 그래프 plugins/StatGraph/count/(v3.52 에서 SVG 로 대체). 원본 판과 이전 Textcube-NG 판 내용
	array('library/contrib/phpopenid/Auth/OpenID.php', array('80e6f2f73551068425d4604776bb50ccf83cad1faf256a91fd1bf4c9e3d2fff8')),
	array('library/contrib/phpopenid/Auth/OpenID/AX.php', array('7d15d21a86fbef49f5cd0e9fadeb7a27dea8bdc754f9306640f405b253659312', 'f8b49079b5cf34e61eb50484afb457b75312cc8a43d3fb07bb1d825a6ec36b1f')),
	array('library/contrib/phpopenid/Auth/OpenID/Association.php', array('6d56d02ac49d940ac739b793be0f9ec2cd973e5c0a70bb6fa62d63919bb518bc', 'ab2ac86f7661939248e8256e65e585e3ce0e5566eb24b168982baa6ebb4fbb92')),
	array('library/contrib/phpopenid/Auth/OpenID/BigMath.php', array('3acc7226d24cdcfae17d1fab6af5a0636906f69e5e807f015a690481d69b0a6c')),
	array('library/contrib/phpopenid/Auth/OpenID/Consumer.php', array('05cfd798b24e40990d7a5085d7c48eeec69a340df78b0f5e5f97f02b8b184e63', '1c0dfe01ac9a45d29e91c7d430d2edc3fdcb54d0aaad2005e9b6ed57d19877ec')),
	array('library/contrib/phpopenid/Auth/OpenID/CryptUtil.php', array('0c61b0694500a823d9810f0aa33affaa01db48032e1161304ae2f7937fbf31bb', 'a31f5720f42c70dce6dec1be5c20abaf2d71fdced3f5ad34461078ec50f32073')),
	array('library/contrib/phpopenid/Auth/OpenID/DatabaseConnection.php', array('108cc4edd25e58c0752eb93921f761a4013b80083cce5bf381fddb63d59ccc73')),
	array('library/contrib/phpopenid/Auth/OpenID/DiffieHellman.php', array('b05fa4bca2e4e610a6be8925d90946bc62801e65c4deb4d8bf38c2e0af0e2ff0', 'ce7445c794fd3964f64e0a4e17870bf5156be2dbd5ac421e2b2c9da4ec7b0678')),
	array('library/contrib/phpopenid/Auth/OpenID/Discover.php', array('029fdb23131a628b30a10c92b44610a9bddf78932ad606212b8efdc5714bc087', '57cffda51d0dfbfdfdd9f8f0b28449bcf5b8f69915e4f2f9b2bb27e9244bdd56')),
	array('library/contrib/phpopenid/Auth/OpenID/DumbStore.php', array('6794696d29d925460fe73833418999095fe9f361e549536de3bfb54bfa663fbd', 'ddeecd6da5839df068ec993e18a398bda2569c710b4e222a2039554d5966fd3e')),
	array('library/contrib/phpopenid/Auth/OpenID/Extension.php', array('63c3ed5f3fe9ba8c5edc30419fcda9fc4bdf9e79c30fdb03d8f1ac97fbd29856')),
	array('library/contrib/phpopenid/Auth/OpenID/FileStore.php', array('12aa03592287c640d35984a663788dee92c976b2c59818deba08d5695a76e06a', '1ac90f452c33d0a6f0fc1b4d7d7d6232f58f61dc7d9837388c372144e4b5e165')),
	array('library/contrib/phpopenid/Auth/OpenID/HMAC.php', array('06b36cfb815074e81e9b7de7f649f897419c86a6029b7b7f9bc18445f12a6623')),
	array('library/contrib/phpopenid/Auth/OpenID/Interface.php', array('c64a4c483febf06134bd5cd2e165d090d0d5c92bbe43c2f0231d1f5357feb6f9')),
	array('library/contrib/phpopenid/Auth/OpenID/KVForm.php', array('70e77a06452d2301f3a2325f522ef506760998271adb2145327e316c1d4e1c41')),
	array('library/contrib/phpopenid/Auth/OpenID/MDB2Store.php', array('23b5f4eeb2c5c40ff81d0e99c22ad1fb0ea3c6c0d9644b18f31aa58a26c57a83', '8b4fdca0e993769cb712cc98e451d8585295941214faa5d5143999f348a5548b')),
	array('library/contrib/phpopenid/Auth/OpenID/MemcachedStore.php', array('2b8b3c247c301a1d98a4e89546e83f0506c82bf231b177b4d54ee9e5bb1597b0', '4b55653324435dcfbf4bacbff0e4cdfb2a67f5649a5235231f77ab4a571709b5')),
	array('library/contrib/phpopenid/Auth/OpenID/Message.php', array('371de593341b27ada3dcbf4a1d901bfb5f344cc7dd2c248095a250e5bf8e60dc', '3773df1559966488ab5e34a082204c586ec648fc33eda196e9369638d863b363')),
	array('library/contrib/phpopenid/Auth/OpenID/MySQLStore.php', array('fd5c3ad28bb96df8b72cbb168632e71ad7cf312738b2106e5598f4c9f1712a37')),
	array('library/contrib/phpopenid/Auth/OpenID/Nonce.php', array('f1dfe0cd4af42428faae9eff2c80e285a1f805d50360329570915319fc6b1409', 'f816e14668870237f076c31d0c8382ae33538a1313e6aa1f9a209328d8c84205')),
	array('library/contrib/phpopenid/Auth/OpenID/PAPE.php', array('70bb56f207fe5f89a4fa8d7cf47401bc41072f903e84d51ec6afe2f7c0d08cac', 'f679d22604799edb88f06bc00fa17e2d92a0c906fe8e001f6da9d659bd7052b0')),
	array('library/contrib/phpopenid/Auth/OpenID/Parse.php', array('bc81e1434b41856e6d62b23cfc40a764cb11e79cafb6735c36f64a182232bf60', 'c9a8cfcc13dfaa704e997d2a25d21eb7147de981accea43eb746b7424f80c172')),
	array('library/contrib/phpopenid/Auth/OpenID/PostgreSQLStore.php', array('e4022b42d9752430cba51ad64dd783a5cfede1a8fc7993871d13126b8c2c755f')),
	array('library/contrib/phpopenid/Auth/OpenID/PredisStore.php', array('926dda74cd76bab2ff0f03a886463231561579c71d41108bc4beecc30b88da5b', 'de2ab74bd217dbe91c39324609abe1cc0b5e506614a74a8bca56f18d5f14e62c')),
	array('library/contrib/phpopenid/Auth/OpenID/SQLStore.php', array('4c1a6f3e5e34da1a36ed359b6ba5bee3cf1736c6730a78b231aedf9c005799ad', '707a89da0f17aae95295871cde2aeaf95ce5a63d1cd62a4de3cdae7f8061316f')),
	array('library/contrib/phpopenid/Auth/OpenID/SQLiteStore.php', array('3fbbfc2ab8ea82eb98f86aa7ace64860721e2bdbd0ceea5b085cd9a2588ba75c')),
	array('library/contrib/phpopenid/Auth/OpenID/SReg.php', array('9f9dd3ca17372c5b3932bd7666fa6bd427b427cdf57ac39d890b213d9a24a367', 'baeb2ff4ce2cb7c07ffe05a2d39e2e294ea3d6f5dfe932f011086af50917ca1c')),
	array('library/contrib/phpopenid/Auth/OpenID/Server.php', array('0d2bb668e9a8337054600fe0584d9106ecaf5480540f5b38df6dac14adf5821f', 'f437f11459147ff59eea2b188a6c57c5e455453a9056f02e17bc71a3ad51bf4a')),
	array('library/contrib/phpopenid/Auth/OpenID/ServerRequest.php', array('09a23b19e61673b05f4072aa559a6f37862626e0eafeb49c61d2288094730fb6', '74e1c75cd69ada16abde338b69a07b4925ccd680f44cf83cfe8ffc084d03e60b')),
	array('library/contrib/phpopenid/Auth/OpenID/TrustRoot.php', array('07269ee562a9a8f9992e9847997ab844cd508ff9ade96a5197ff37b7c49eb65d')),
	array('library/contrib/phpopenid/Auth/OpenID/URINorm.php', array('2463a31a3c85e4c6e900f8d00b79d35588874963505a200bdf4a52d1b57ce766')),
	array('library/contrib/phpopenid/Auth/Yadis/HTTPFetcher.php', array('0dd1758834b9a907ed414c17d87a9b9e91861b8c0a27f8cdbba79e00f269efaa', '3871e659ee8d2c815ec94efbb7b812adebc00dd5cbd1d4b38e12e0a64c4d36b2')),
	array('library/contrib/phpopenid/Auth/Yadis/Manager.php', array('78f8a78b39249e8de0d48ab73fe4db55f528db93d8b63c196af8de7d5a3256f9', 'fd296e87506b6ee55bdffb17c3d3a6b09e8f664c107fa029b6c05277453decc9')),
	array('library/contrib/phpopenid/Auth/Yadis/Misc.php', array('9ea01ed275c51a62a0d752349298a936c323548f681684acf955d42afd3cdf45')),
	array('library/contrib/phpopenid/Auth/Yadis/ParanoidHTTPFetcher.php', array('7764a336322c7ddf9d303c1384e8265a27c95bf67d66ccfad49db129da76ed13', 'd9a4e13cd390195d73455329ebcf1da31e4f13dec9e4092d000598366aedf073')),
	array('library/contrib/phpopenid/Auth/Yadis/ParseHTML.php', array('b9690a687268c696c42f29e8435ea751ca7396ecf6f93fd9bd4e9821911a9569', 'fb7331c657c6f278a5faf8cbb23c6b476ed741b9287c3c75ae49febb0df81db6')),
	array('library/contrib/phpopenid/Auth/Yadis/PlainHTTPFetcher.php', array('6f747d0efc467ab69671721ec09f4e2a53b321e4cadd73d27d45bd9d82687323')),
	array('library/contrib/phpopenid/Auth/Yadis/XML.php', array('1fb0d7ed61c82ad5414aaa89e0668df3f0a4fab8b8e820367582c77a908357a3', '51e7c0ff52e643939e17bfbaaa82ee4ba25dabcb518c6931cbcf08054e20a86f', 'a1cc291b75abe6b0c34e6e99e026b8a9a58617711ce5a737ae4da980e566958e')),
	array('library/contrib/phpopenid/Auth/Yadis/XRDS.php', array('638d67cde82c03b5a93f408785aa8d9deac26790d674d158fcd57dcc4c6391ae', '6fdc481ae3af7ea909a65d71231a24065b43f4800e1154cd78a24d93bac6c75e')),
	array('library/contrib/phpopenid/Auth/Yadis/XRI.php', array('be636952c347bd2c630a36fde32e916035432956d1acb06c08c014244633dfd8')),
	array('library/contrib/phpopenid/Auth/Yadis/XRIRes.php', array('3215902403ba8bf7adcd226f927d86bdd682b2ce37337730c8f1004f923be9ff', '7a760d388ed9f67f9b28e0ba6857b4b9f348747bdb2df2d0a574f717370de648')),
	array('library/contrib/phpopenid/Auth/Yadis/Yadis.php', array('7c2303e757c6b256b76b4cd1473421189e4e75fc92127f2ad45b9dda9ba72189', 'c20c6f363024b9d3e4e43b77facd3668fe56a4daf41f687466ac8f3f803a95c1')),
	array('library/contrib/phpopenid/CHANGES-2.1.0', array('d368d342d694e69794ec3ad3cc5d2c657ec6d02c469104e9dcedfb0f42453ba5')),
	array('library/contrib/phpopenid/COPYING', array('cfc7749b96f63bd31c3c42b5c471bf756814053e847c10f3eb003417bc523d30')),
	array('library/contrib/phpopenid/NEWS', array('0dcbe003da86ac3a1ce7c88b25cbe842614e6e38049c583d2f11f6799bce932d')),
	array('library/contrib/phpopenid/README', array('be59d81312346d96cf58cf471b9ef1a9780caea2a85f8793d043e98ac13d00c1')),
	array('library/contrib/phpopenid/README.Debian', array('3efe27b448cca0cc96e9a4c2e921eed5564ca960a088f91542efb85133e72895')),
	array('library/contrib/phpopenid/README.git', array('4e6c191ac651f12de370bd1111137997c8dcb02ba95ce04ea3b328182d5b85c2')),
	array('library/contrib/phpopenid/composer.json', array('527665dbe4b94943bd98abceac673b6537cad71f3b60394056b94f695f4e7119')),
	array('library/contrib/phpopenid/contrib/google/php-openid-apps-discover-1.0.1.tar.gz', array('9aed5c31a8a0c378e4ae44463ec5e199d229a85e362ab575e1826813cc40251b')),
	array('library/contrib/phpopenid/contrib/signed_assertions/AP.php', array('672e1f1c2300dc7ebe8cb12e09301fa163c57d0e5b2750ffb0c1ed1a14ce2ad0', 'b95ef51f2709fb727a235d0cc50ac26518e80575194a8113d8ced5a7c31a9e22')),
	array('library/contrib/phpopenid/contrib/signed_assertions/SAML.php', array('53d7fae11fac4b3986246efef5a5d1d428330601a2970a117d432afd0ac9531c', '97c47d5ca2d321bdaf7612c007bbf6b45e91e6be327e1ededf213f14422b5666')),
	array('library/contrib/phpopenid/contrib/upgrade-store-1.1-to-2.0', array('1fa9c18e162de6ba14e4f86e729acab4cfdd187aa5a683136cbb5103d4d4a1a9')),
	array('plugins/StatGraph/count/count.php', array('a123668a213093925715fc551f57df3eaec6af09e198df47bd1eb9a5ebe1f5df')),
	array('plugins/StatGraph/count/src/jpg-config.inc', array('10800704533307a7b53f2cc952d858b2584613f04f1aea59b3592e5564ca36eb')),
	array('plugins/StatGraph/count/src/jpgraph.php', array('07ca66ce76ce577c15ceac31e5048a643813ce889c1d8f8a2326cf9ee7db7adc', '2bdd8abf6b69d7603332cced579777c3ce1facfa57d369d5bd5328cc9c4e75c1', '7b04d8200944ccec165fa1a3349434db418d9c447d324d9b593bd949dd7011e2')),
	array('plugins/StatGraph/count/src/jpgraph_gradient.php', array('1765c7bd1a41677b5e516f749644a1b6a695fe924a512793bfae222816f2adcb', '2d6910181638ecd92321df69b6caec15be5418beff973d06599dd42e78179dfc')),
	array('plugins/StatGraph/count/src/jpgraph_line.php', array('05669e6c558ef4121d994d910c6ee67de0fb3ee5873adbd3168d71ec1d598543', '704ddee4966dbe70c6a8b1a0b06b828ca9aee5e925257eb65e0d5f012df2a63a')),
	array('plugins/StatGraph/count/src/jpgraph_plotmark.inc', array('051b2ab2ec745e55d3d2d5d47bd7a5c3c70c37d9fe735503a19b1b4de9f2c7d1')),
	array('plugins/StatGraph/count/src/jpgraph_scatter.php', array('2cfee98e8da5d131f1c0291f42773e876cee0b439645ba87a388dc24a69bfd9e', 'c8e87a8fc5b580bb404239d2c7e09c4e4d8552a928c996eeb2c6faa870a92cc4')),
	// Textcube-NG: r4-5 에서 뺀 CodeMirror node.js 전용 파일(편집기는 쓰지 않음, 저장소 의존성 경고 대상). 원본·r1·r2 판과 r3 판 내용
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/package.json', array('a00803e2afcdf664720bc23494c7fb5038aa998dddb7611adf6fc4404eba6260', '95a808668722f226219643949ae753d88d730b370c8b3b6f5decdc802fb056c0')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/bin/source-highlight', array('94aa1e0c0b0affbf13565a8bf861605b4dee99cca90890ece803c6013c32e517')),
	array('plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/addon/runmode/runmode.node.js', array('d02fc2ed8e4981f89ba1807f9d1e9de27a3ddb8ced286f825f87afb4bada1fc0', 'e42b873d75f330a7a30bc325795827a883a18568a3f94be4fcd18a4125645089')),
);

// 비게 되면 지울 디렉토리(깊은 것부터). 다른 파일이 있으면 남는다.
$dirs = array(
	'plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/jade',
	'plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/kotlin',
	'plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/mode/rpm/changes',
	'plugins/ED_tinyMCE/tinymce/plugins/codemirror/CodeMirror/doc',
	'plugins/ED_tinyMCE/tinymce/skins/light/fonts',
	'plugins/ED_tinyMCE/tinymce/skins/light/img',
	'plugins/ED_tinyMCE/tinymce/plugins/layer',
	'plugins/ED_tinyMCE/tinymce/skins/light',
	'resources/script/gallery/iMazing/net_imazing_frame_none',
	'resources/script/gallery/iMazing/net_imazing_show_window_navigation_none',
	'resources/script/gallery/iMazing/net_imazing_show_window_navigation_showroom',
	'resources/script/gallery/iMazing/net_imazing_show_window_navigation_sidebar',
	'resources/script/gallery/iMazing/net_imazing_show_window_navigation_simple',
	'resources/script/gallery/iMazing/net_imazing_show_window_transition_alpha',
	'resources/script/gallery/iMazing/net_imazing_show_window_transition_contrast',
	'resources/script/gallery/iMazing/net_imazing_show_window_transition_none',
	'resources/script/gallery/iMazing/net_imazing_show_window_transition_sliding',
	'resources/script/gallery/iMazing',
	'resources/script/jukebox/flash',
	'skin/blog/periwinkle/less',
	'resources/script/clipboardPoter',
	'resources/script/gallery',
	'resources/script/jukebox',
	'resources/script/uploader',
	// Textcube-NG: v3.175 에서 뺀 resources/script/dojo/ 아래(깊은 것부터)(v3.197)
	'resources/script/dojo/src/widget/templates/images',
	'resources/script/dojo/src/widget/templates',
	'resources/script/dojo/src/dnd',
	'resources/script/dojo/src/html',
	'resources/script/dojo/src/lang',
	'resources/script/dojo/src/lfx',
	'resources/script/dojo/src/namespaces',
	'resources/script/dojo/src/uri',
	'resources/script/dojo/src/widget',
	'resources/script/dojo/src/xml',
	'resources/script/dojo/src',
	'resources/script/dojo',
	// Textcube-NG: library/contrib/phpopenid/·plugins/StatGraph/count/ 아래(깊은 것부터)
	'library/contrib/phpopenid/Auth/OpenID',
	'library/contrib/phpopenid/Auth/Yadis',
	'library/contrib/phpopenid/contrib/google',
	'library/contrib/phpopenid/contrib/signed_assertions',
	'library/contrib/phpopenid/Auth',
	'library/contrib/phpopenid/contrib',
	'plugins/StatGraph/count/src',
	'library/contrib/phpopenid',
	'plugins/StatGraph/count',
);

function tcCleanupOut($s) { fwrite(STDOUT, $s . PHP_EOL); }
function tcCleanupErr($s) { fwrite(STDERR, $s . PHP_EOL); }
function tcCleanupUsage() {
	tcCleanupOut('사용: php cleanup_legacy_assets.php [--apply] [--backup=DIR]');
	tcCleanupOut('  인자 없음      미리보기(지우지 않음)');
	tcCleanupOut('  --apply        배포 내용과 같은 파일만 지우고, 비게 된 디렉토리를 지움');
	tcCleanupOut('  --backup=DIR   지우기 전에 DIR 에 복사(DIR 은 미리 만들고, 웹 루트 밖이어야 함)');
}

$apply = false;
$backup = null;
foreach (array_slice($argv, 1) as $arg) {
	if ($arg === '--apply') {
		$apply = true;
	} else if (strncmp($arg, '--backup=', 9) === 0 && strlen($arg) > 9) {
		$backup = substr($arg, 9);
	} else if ($arg === '--help' || $arg === '-h') {
		tcCleanupUsage();
		exit(0);
	} else {
		tcCleanupErr('알 수 없는 인자: ' . $arg);
		tcCleanupUsage();
		exit(2);
	}
}
if ($backup !== null && !$apply) {
	tcCleanupErr('--backup 은 --apply 와 함께 써야 합니다.');
	exit(2);
}

$root = realpath(__DIR__);
if ($root === false || !is_file($root . '/framework/id/textcube/config.default.php') || !is_file($root . '/rewrite.php')) {
	tcCleanupErr('Textcube 루트가 아닙니다: ' . __DIR__);
	exit(2);
}
$sep = DIRECTORY_SEPARATOR;

$backupReal = null;
if ($backup !== null) {
	$backupReal = realpath($backup);
	if ($backupReal === false || !is_dir($backupReal) || !is_writable($backupReal)) {
		tcCleanupErr('백업 디렉토리가 없거나 쓸 수 없습니다(미리 만들 것): ' . $backup);
		exit(2);
	}
	if ($backupReal === $root || strpos($backupReal . $sep, $root . $sep) === 0) {
		tcCleanupErr('백업 디렉토리는 웹 루트 밖이어야 합니다(옛 판이 웹에 그대로 노출됨): ' . $backupReal);
		exit(2);
	}
}

$targets = array();
$absent = 0;
$skipped = array();
foreach ($files as $f) {
	$rel = $f[0];
	$path = $root . $sep . str_replace('/', $sep, $rel);
	if (!file_exists($path) && !is_link($path)) {
		$absent++;
		continue;
	}
	if (is_link($path) || !is_file($path)) {
		$skipped[] = $rel . '  (일반 파일이 아님)';
		continue;
	}
	$real = realpath($path);
	if ($real === false || strpos($real, $root . $sep) !== 0) {
		$skipped[] = $rel . '  (루트 밖을 가리킴)';
		continue;
	}
	$hash = hash_file('sha256', $real);
	if ($hash === false || !in_array($hash, $f[1], true)) {
		$skipped[] = $rel . '  (배포 내용과 다름 — 확인 후 직접 정리)';
		continue;
	}
	$targets[] = array($rel, $real, $hash);
}

tcCleanupOut('Textcube 루트: ' . $root);
tcCleanupOut(sprintf('목록 %d개 — 지울 파일 %d개, 이미 없음 %d개, 건너뜀 %d개', count($files), count($targets), $absent, count($skipped)));
foreach ($skipped as $s) tcCleanupOut('  건너뜀: ' . $s);

if (!$apply) {
	foreach ($targets as $t) tcCleanupOut('  지울 파일: ' . $t[0]);
	tcCleanupOut('미리보기입니다. 지우려면 --apply 를 붙여 다시 실행하세요.');
	exit(0);
}

$deleted = 0;
$errors = array();
foreach ($targets as $t) {
	list($rel, $real, $hash) = $t;
	if ($backupReal !== null) {
		$dest = $backupReal . $sep . str_replace('/', $sep, $rel);
		$destDir = dirname($dest);
		if ((!is_dir($destDir) && !@mkdir($destDir, 0700, true)) || !@copy($real, $dest) || hash_file('sha256', $dest) !== $hash) {
			$errors[] = $rel . '  (백업 실패 — 지우지 않음)';
			continue;
		}
	}
	if (@unlink($real)) {
		$deleted++;
	} else {
		$errors[] = $rel . '  (지우기 실패 — 권한 확인)';
	}
}
$removedDirs = 0;
foreach ($dirs as $d) {
	$p = $root . $sep . str_replace('/', $sep, $d);
	if (is_dir($p) && !is_link($p)) {
		$entries = @scandir($p);
		if ($entries !== false && count($entries) === 2 && @rmdir($p)) $removedDirs++;
	}
}
foreach ($errors as $e) tcCleanupErr('  오류: ' . $e);
tcCleanupOut(sprintf('지운 파일 %d개, 지운 빈 디렉토리 %d개, 오류 %d개', $deleted, $removedDirs, count($errors)));
exit(count($errors) ? 1 : 0);
