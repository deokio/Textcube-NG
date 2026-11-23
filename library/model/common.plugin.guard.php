<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)
/* Textcube-NG: 플러그인 안전 검사(v3.211).
   - PDO 호환성: token_get_all로 플러그인 PHP 파일을 읽어 직접 SQL(POD 문자열 SQL)과 Textcube가 제공하지 않는 DB 연결 요소를 센다.
     PDO 모드(POD::__isPDO())에서는 활성화를 거부하고 적재에서 뺀다. native 모드에서는 켜고 경고한다.
   - 판정 불가 호출(dyn)도 PDO 모드에서는 활성화 거부·적재 제외, native 모드는 경고만 한다. 검토한 번들(pluginGuardReviewedExceptions,
     파일 sha256 고정)은 dyn 판정에서만 예외로 본다. 파일이 하나라도 다르면 예외가 풀린다.
   - 허용 목록(서비스 설정 pluginAllowlistEnabled·pluginAllowlist): 켜져 있으면 목록 밖 플러그인은 활성화 거부·적재 제외.
   - 적재 재검증: 이름 규칙·활성 목록·권한 기록·PDO 검사·허용 목록·파일 무결성을 바인딩(plugins.php)과 include(fireEvent 등) 직전에 다시 본다.
   - 파일 무결성: 활성화 때 플러그인 폴더 안 모든 파일(확장자와 관계없이)의 sha256 묶음값(블로그 설정 pgi_*)을 남기고, 적재 때 mtime·크기가 바뀌면 다시 해시해 다르면 막는다.
     정적 분석은 PHP 계열 파일과 index.xml만 본다. 파일 링크·일반 파일이 아닌 것·읽지 못한 파일이 있으면 기준값을 남기지 않고 막는다.
     v2 기록(PHP 계열 파일과 index.xml만 묶음)은 같은 규칙으로 다시 묶어 같을 때만 폴더 전체로 기준값을 새로 남긴다(엄격 모드에서는 막는다).
     코어(FM_TTML·ED_tinyMCE)도 같다. 끄고 다시 켜면(activatePlugin) 기준값을 새로 남겨 풀린다.
   - 엄격 모드(서비스 설정 pluginIntegrityStrictEnabled, 기본 꺼짐): 꺼져 있으면 기록이 없는 플러그인은 처음 본 상태를 기준값으로 삼는다(TOFU).
     켜져 있으면 기록이 없는 플러그인(기록 저장 실패 포함)을 막는다. activatePlugin 밖에서 켠 플러그인도 적재 때 이 규칙으로 본다.
   - 실패하면 바인딩만 뺀다. Plugins 테이블 행은 그대로 둔다. */

if (!defined('PLUGIN_GUARD_VERSION')) {
    define('PLUGIN_GUARD_VERSION', 3);
}
if (!defined('PLUGIN_GUARD_MAX_ITEMS')) {
    define('PLUGIN_GUARD_MAX_ITEMS', 10);
}
if (!defined('PLUGIN_GUARD_MAX_FILE')) {
    define('PLUGIN_GUARD_MAX_FILE', 2097152);
}
if (!defined('PLUGIN_GUARD_MAX_ENTRIES')) {
    define('PLUGIN_GUARD_MAX_ENTRIES', 2000);
}
if (!defined('PLUGIN_GUARD_MAX_VALUE')) {
    define('PLUGIN_GUARD_MAX_VALUE', 60000);
}

/***** 기본 판정 *****/
function pluginGuardIsPDO() {
    return method_exists('POD', '__isPDO') && POD::__isPDO();
}

function pluginGuardPrepMode() {
    return method_exists('POD', 'prepare') ? 'p' : 'n';
}

function pluginGuardNameIsValid($name) {
    static $memo = array();
    if (!is_string($name) || $name === '') {
        return false;
    }
    if (!isset($memo[$name])) {
        $memo[$name] = (preg_match('/^[-a-zA-Z0-9_ ]+$/D', $name) === 1);	// Textcube-NG: D — 끝 줄바꿈이 붙은 이름은 받지 않는다(r3-15, R-370)
    }
    return $memo[$name];
}

function pluginGuardCorePlugins() {
    return array('FM_TTML', 'ED_tinyMCE');
}

function pluginGuardAllowlist($reload = false) {
    static $memo = null;
    if ($memo !== null && !$reload) {
        return $memo;
    }
    $enabled = ((string)Setting::getServiceSettingGlobal('pluginAllowlistEnabled', '0') === '1');
    $list = array();
    $decoded = json_decode((string)Setting::getServiceSettingGlobal('pluginAllowlist', ''), true);
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (is_string($item) && pluginGuardNameIsValid($item)) {
                $list[] = $item;
            }
        }
    }
    $list = array_values(array_unique($list));
    sort($list, SORT_STRING);
    $memo = array($enabled, $list);
    return $memo;
}

function pluginGuardIsAllowed($name, $allow = null) {
    if (!is_array($allow)) {
        $allow = pluginGuardAllowlist();
    }
    if (!$allow[0]) {
        return true;
    }
    if (in_array($name, pluginGuardCorePlugins(), true)) {
        return true;
    }
    return in_array($name, $allow[1], true);
}

function pluginGuardStrictEnabled($reload = false) {
    static $memo = null;
    if ($memo === null || $reload) {
        $memo = ((string)Setting::getServiceSettingGlobal('pluginIntegrityStrictEnabled', '0') === '1');
    }
    return $memo;
}

// Textcube-NG: 무결성 검사 면제(r3-14). 플러그인 목록(owner/plugin)에서 서비스 관리자가 '무결성 검사 없이 항상 허용'을 고른 플러그인 이름 목록.
//   서비스 설정 pluginIntegrityExempt 에 JSON 배열로 둔다(서비스 전체, 모든 블로그에 같이 적용). 바꾸는 곳은 control/plugin/exempt(group.creators)뿐이다.
//   면제한 플러그인은 무결성 판정(기록 비교·엄격 모드의 기록 없음)만 건너뛴다. 엄격 모드에서도 면제를 쓴다.
//   이름 규칙·허용 목록·서비스 관리자 전용(privilege)·PDO 판정은 지금 파일로 그대로 한다.
//   저장할 때와 읽을 때 모두 이름 규칙(줄 끝 포함 /D)·plugins 폴더·index.xml 을 다시 확인하고, 통과한 이름만 쓴다.
function pluginGuardExemptKey() {
    return 'pluginIntegrityExempt';
}

function pluginGuardExemptNameOk($name) {
    return is_string($name) && preg_match('/^[-a-zA-Z0-9_ ]+$/D', $name) === 1 && pluginGuardNameIsValid($name)
        && is_dir(ROOT . '/plugins/' . $name) && is_file(ROOT . '/plugins/' . $name . '/index.xml');
}

function pluginGuardExemptList($reload = false) {
    static $memo = null;
    if ($memo !== null && !$reload) {
        return $memo;
    }
    $list = array();
    $raw = Setting::getServiceSettingGlobal(pluginGuardExemptKey(), '');
    $decoded = (is_string($raw) && $raw !== '') ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (pluginGuardExemptNameOk($item)) {
                $list[] = $item;
            }
        }
    }
    $list = array_values(array_unique($list));
    sort($list, SORT_STRING);
    $memo = $list;
    return $memo;
}

function pluginGuardIsExempt($name, $exempt = null) {
    if (!is_array($exempt)) {
        $exempt = pluginGuardExemptList();
    }
    return in_array((string)$name, $exempt, true);
}

function pluginGuardSetExempt($name, $on) {
    if (!pluginGuardExemptNameOk($name)) {
        return false;
    }
    $list = array();
    foreach (pluginGuardExemptList(true) as $item) {
        if ($item !== $name) {
            $list[] = $item;
        }
    }
    if ($on) {
        $list[] = $name;
    }
    sort($list, SORT_STRING);
    $json = jsonEncodeCompat($list, pluginGuardJsonFlags());	// Textcube-NG: JSON_INVALID_UTF8_SUBSTITUTE(7.2) 대신 jsonEncodeCompat()(00-Compat.php, r4-4)
    if (!is_string($json) || strlen($json) > PLUGIN_GUARD_MAX_VALUE) {
        return false;
    }
    $result = Setting::setServiceSettingGlobal(pluginGuardExemptKey(), $json);
    pluginGuardExemptList(true);
    return $result;
}

// 검토한 번들의 판정 불가 호출(dyn) 예외: 플러그인 이름 → {상대 경로 → sha256}. dyn 판정에만 쓴다(sql·conn에는 쓰지 않는다).
// 플러그인 폴더 안 모든 파일(pluginGuardFileList 무결성 목록, 확장자와 관계없이)이 이 목록과 같고 모두 이 해시와 같을 때만 예외다. 트리마다 값이 다르면 트리별 값을 둔다.
function pluginGuardReviewedExceptions() {
    return array(
        'FM_Markdown' => array(
            'License.text' => '8c565d67169154397e56b2f48b3082902fa2bc882ffb24a3a55e22b3693fec49',
            'images/icon_plugin.png' => '5e00fb7fa3f6874c6cdf8650d7a19a75693794330cc637e570ef1721764769ef',
            'images/icon_plugin_off.png' => '41a7f8620563d55657252bc04ca6c3f4698e77fdee973f32f62fc59d17dac5cb',
            'images/icon_plugin_on.png' => '6e08f4d60f7083f6d1be4770179b49a9e6d839c3db137a846740ecc505e351fd',
            'index.php' => 'ca93cd55a412c18327cddbc2fafded6aabde37cd084688d2f8a5eda797f4bb87',
            'index.xml' => '1688dba92356ce526e0a5e331aa4fd657b50142c2c9454c9897efb4b79600e50',
            'locale/en.php' => 'cb711005b8b36f632a4e4412ab1dd26e29a4da9877d04a67856108ba8899663c',
            'locale/ja.php' => '6c15ba5a58dde86abf15cbfa7afd14211a47b951804f048aa84e5a7e44095ee9',
            'locale/ko.php' => 'aaa28e449c6ec26b8a7b2fc9c85d7deefd1457fb5785849ccf4dc8f0f10c9d77',
            'locale/vi.php' => '2c9839bdb58fd1e76d63cbb02788a6ad9ec56aa7fb9f61f9d85cb0040b3ece9f',
            'locale/zh-CN.php' => 'f1f96ab53b83fe966dd9cc7a7d11f5c324b2889f73f2c88b26813db3ce6e3c22',
            'locale/zh-TW.php' => '21613cb144fa482b3714babcfb2cbae68ef337b8aec7d2a34646f9389b61f069',
            'markdown.php' => '374517d71282c9b2b575075575beffb5c505659e7e1209632fb2667fadf89ee5',
            'michelf/License.md' => 'af723e50680158166b98527005665d0bb46fb7994df15b7798f4f37715fdfec7',
            'michelf/Markdown.php' => 'a1eca34e48f44dc0433d3b04f2c02b914715c8596e6d1e65390a69fd93ab44f7',
            'michelf/MarkdownExtra.php' => '9791620444869c1ee4db88d3f86d0da97c291725c25d285ff19ba138d5cc1cb6', // Textcube-NG: r4-1 php7.2 이식본은 michelf 1.9.1+3커밋(fbc3604077·dbb995733d·98b12b84)이라 index.xml·markdown.php·michelf 4파일 해시를 이 트리 값으로 둔다(다른 트리는 2.0.0 HEAD 값) · r4-4 php5.5 이식본은 ??·JSON·타입 수정으로 markdown.php·MarkdownExtra.php·ttml.php 해시를 다시 계산
            'michelf/MarkdownInterface.php' => 'dd2664f04b3cbb246a70d5b2382e36ecd3f3d1e4f2e0679a7b0d8b1cd31f12a0',
            'ttml.php' => '4f45f167151423b835a03988ec2ef7da340a9b14d53036951406a1930954cedc', // Textcube-NG: r3-42 ttml.php 새 해시(r3-31·r3-32·r3-34·r3-38 에서 ttml.php 를 고치고 갱신하지 않은 것을 보완, dyn 출처는 michelf 2파일 그대로)
        ),
    );
}

function pluginGuardDynExempt($name, $scan) {
    static $memo = array();
    $dyn = (int)(isset($scan['dyn']) ? $scan['dyn'] : 0);
    $df = (isset($scan['df']) && is_array($scan['df'])) ? $scan['df'] : array();
    if ($dyn <= 0 || !pluginGuardNameIsValid($name) || (int)array_sum(array_map('intval', $df)) !== $dyn) {
        return false;
    }
    $reviewed = pluginGuardReviewedExceptions();
    if (!isset($reviewed[$name]) || !is_array($reviewed[$name]) || empty($reviewed[$name])) {
        return false;
    }
    foreach ($df as $rel => $count) {
        if (!isset($reviewed[$name][(string)$rel])) {
            return false; // 목록 밖 파일(또는 검사 한도·토큰화 실패 '-')에서 나온 dyn
        }
    }
    if (!isset($memo[$name])) {
        $ok = false;
        $list = pluginGuardFileList($name, true); // Textcube-NG: 폴더 안 모든 파일이 검토 목록과 같아야 예외다(v3.211)
        if (!$list[2] && count($list[0]) === count($reviewed[$name])) {
            $ok = true;
            $base = ROOT . '/plugins/' . $name . '/';
            foreach ($reviewed[$name] as $rel => $hash) {
                $actual = isset($list[0][$rel]) ? @hash_file('sha256', $base . $rel) : false;
                if (!is_string($actual) || !hash_equals((string)$hash, $actual)) {
                    $ok = false;
                    break;
                }
            }
        }
        $memo[$name] = $ok;
    }
    return $memo[$name];
}

function pluginGuardRecordKey($name) {
    return 'pgi_' . substr(md5((string)$name), 0, 28);
}

function pluginGuardScanKey($name) {
    return 'pgs_' . substr(md5((string)$name), 0, 28);
}

/***** 블로그 설정 읽기·쓰기(Setting API) *****/
function pluginGuardCacheStorage() {
    global $gCacheStorage;
    if (!is_object($gCacheStorage)) {
        $gCacheStorage = globalCacheStorage::getInstance();
    }
    return $gCacheStorage;
}

function pluginGuardSyncBlogCache($blogid) {
    global $__gCacheBlogSettings;
    // purge()는 PageCacheLog 행만 지우므로 메모리의 BlogSettings 묶음도 맞춰 둔다(종료 때 save가 옛 값을 다시 쓰지 않게)
    if (is_array($__gCacheBlogSettings) && isset($__gCacheBlogSettings[$blogid]) && is_array($__gCacheBlogSettings[$blogid]) && $blogid == getBlogId()) {
        pluginGuardCacheStorage()->setContent('BlogSettings', $__gCacheBlogSettings[$blogid]);
    }
}

function pluginGuardGetBlogValue($key) {
    $blogid = getBlogId();
    if (!is_numeric($blogid)) {
        return null;
    }
    pluginGuardCacheStorage();
    $settings = Setting::getBlogSettingsGlobal($blogid);
    if (is_array($settings) && array_key_exists($key, $settings)) {
        return $settings[$key];
    }
    return null;
}

function pluginGuardSetBlogValue($key, $value) {
    global $__gCacheBlogSettings;
    $blogid = getBlogId();
    if (!is_numeric($blogid)) {
        return false;
    }
    pluginGuardCacheStorage();
    if (!is_array(Setting::getBlogSettingsGlobal($blogid))) {
        return false;
    }
    // 메모리 묶음에 키가 없으면 setBlogSettingGlobal이 INSERT로 가는데, 묶음이 묵어 DB에 같은 키가 있으면 실패한다.
    // 자리값을 두어 replace(덮어쓰기) 경로로 보낸다
    if (isset($__gCacheBlogSettings[$blogid]) && is_array($__gCacheBlogSettings[$blogid]) && !array_key_exists($key, $__gCacheBlogSettings[$blogid])) {
        $__gCacheBlogSettings[$blogid][$key] = '';
    }
    $result = Setting::setBlogSettingGlobal($key, $value, $blogid);
    pluginGuardSyncBlogCache($blogid);
    return ($result !== false && $result !== null);
}

function pluginGuardRemoveBaseline($name) {
    global $__gCacheBlogSettings;
    $blogid = getBlogId();
    if (!is_numeric($blogid) || !pluginGuardNameIsValid($name)) {
        return true;
    }
    pluginGuardCacheStorage();
    if (!is_array(Setting::getBlogSettingsGlobal($blogid))) {
        return false;
    }
    $key = pluginGuardRecordKey($name);
    // 묶음이 묵어 키가 빠져 있어도 DB 행을 지우도록 자리값을 둔다
    if (isset($__gCacheBlogSettings[$blogid]) && is_array($__gCacheBlogSettings[$blogid]) && !array_key_exists($key, $__gCacheBlogSettings[$blogid])) {
        $__gCacheBlogSettings[$blogid][$key] = '';
    }
    $result = Setting::removeBlogSettingGlobal($key, $blogid);
    pluginGuardSyncBlogCache($blogid);
    return ($result !== false && $result !== null);
}

function pluginGuardLoadRecord($name) {
    $raw = pluginGuardGetBlogValue(pluginGuardRecordKey($name));
    if (!is_string($raw) || $raw === '') {
        return null;
    }
    $record = json_decode($raw, true);
    if (!is_array($record) || !isset($record['d']) || !is_string($record['d'])) {
        return null;
    }
    if (!isset($record['f']) || !is_array($record['f'])) {
        $record['f'] = array();
    }
    if (!isset($record['w']) || !is_array($record['w'])) {
        $record['w'] = array();
    }
    return $record;
}

function pluginGuardJsonFlags() {
    return JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE;
}

function pluginGuardSaveRecord($name, $record, $refreshOnly = false) {
    $json = jsonEncodeCompat($record, pluginGuardJsonFlags());
    if (!is_string($json)) {
        return false;
    }
    if (strlen($json) > PLUGIN_GUARD_MAX_VALUE) {
        if ($refreshOnly) {
            return true;
        }
        // 파일 목록이 너무 크면 묶음값만 남긴다(적재 때마다 다시 해시한다)
        $record['f'] = array();
        $record['w'] = array();
        $json = jsonEncodeCompat($record, pluginGuardJsonFlags());
        if (!is_string($json) || strlen($json) > PLUGIN_GUARD_MAX_VALUE) {
            return false;
        }
    }
    return pluginGuardSetBlogValue(pluginGuardRecordKey($name), $json);
}

function pluginGuardPrivilege($name) {
    if (!pluginGuardNameIsValid($name)) {
        return '';
    }
    $manifest = @file_get_contents(ROOT . '/plugins/' . $name . '/index.xml');
    if (!is_string($manifest) || $manifest === '') {
        return '';
    }
    $xmls = new XMLStruct();
    if (!$xmls->open($manifest)) {
        return '';
    }
    return (string)$xmls->getValue('/plugin/requirements/privilege');
}

/***** 파일 목록·묶음값 *****/
// Textcube-NG: $all이 거짓이면 정적 분석 목록(PHP 계열 확장자와 index.xml), 참이면 무결성 목록(폴더 안 모든 파일)이다. 세 번째 반환값이 참이면 목록을 끝까지 읽지 못한 것이다(v3.211)
function pluginGuardFileList($name, $all = false) {
    if (!pluginGuardNameIsValid($name)) {
        return array(array(), array(), true);
    }
    $base = ROOT . '/plugins/' . $name;
    if (!is_dir($base)) {
        return array(array(), array(), true);
    }
    $files = array();
    $dirs = array();
    $over = false;
    $stack = array(array('', 0));
    while (!empty($stack)) {
        $top = array_pop($stack);
        $rel = $top[0];
        $depth = $top[1];
        $path = ($rel === '') ? $base : $base . '/' . $rel;
        $mtime = @filemtime($path);
        $dirs[($rel === '') ? '.' : $rel] = ($mtime === false) ? -1 : (int)$mtime;
        if (count($dirs) > PLUGIN_GUARD_MAX_ENTRIES) {
            $over = true;
            break;
        }
        $entries = @scandir($path);
        if (!is_array($entries)) {
            $over = true;
            continue;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $childRel = ($rel === '') ? $entry : $rel . '/' . $entry;
            $child = $base . '/' . $childRel;
            if (is_dir($child)) {
                if (is_link($child) || $depth + 1 >= 8) {
                    $over = true;
                    continue;
                }
                $stack[] = array($childRel, $depth + 1);
                continue;
            }
            if (!$all && $childRel !== 'index.xml' && !preg_match('/\.(php|php3|php4|php5|php7|phtml|pht|inc|module)$/i', $entry)) {
                continue;
            }
            // Textcube-NG: 무결성 목록은 확장자와 관계없이 모든 파일을 본다. 파일 링크·일반 파일이 아닌 것·stat 실패는 끝까지 읽지 못한 것으로 본다(v3.211).
            // 확장자 목록(TOFU Scan)도 고른 파일이 링크·일반 파일이 아닌 것(fifo 등)이면 읽지 않고 끝까지 읽지 못한 것으로 본다(r3-3)
            if (is_link($child) || !is_file($child)) {
                $over = true;
                continue;
            }
            $stat = @stat($child);
            if ($all && !is_array($stat)) {
                $over = true;
                continue;
            }
            $files[$childRel] = is_array($stat) ? array((int)$stat['mtime'], (int)$stat['size']) : array(-1, -1);
            if (count($files) > PLUGIN_GUARD_MAX_ENTRIES) {
                $over = true;
                break 2;
            }
        }
    }
    ksort($files, SORT_STRING);
    ksort($dirs, SORT_STRING);
    return array($files, $dirs, $over);
}

function pluginGuardDigest($name, $files) {
    $base = ROOT . '/plugins/' . $name . '/';
    $buffer = '';
    foreach ($files as $rel => $stat) {
        $hash = @hash_file('sha256', $base . $rel);
        if (!is_string($hash)) {
            return ''; // Textcube-NG: 읽지 못한 파일이 있으면 묶음값을 내지 않는다. 빈 값은 무결성 실패로 본다(v3.211)
        }
        $buffer .= $rel . "\0" . $hash . "\n";
    }
    return hash('sha256', $buffer);
}

function pluginGuardStatMatches($name, $record) {
    if (empty($record['f'])) {
        return false;
    }
    $base = ROOT . '/plugins/' . $name;
    foreach ($record['f'] as $rel => $saved) {
        if (!is_array($saved) || count($saved) < 2) {
            return false;
        }
        $stat = @stat($base . '/' . $rel);
        if (!is_array($stat) || (int)$stat['mtime'] !== (int)$saved[0] || (int)$stat['size'] !== (int)$saved[1]) {
            return false;
        }
    }
    foreach ($record['w'] as $rel => $saved) {
        $mtime = @filemtime(((string)$rel === '.') ? $base : $base . '/' . $rel);
        if ((($mtime === false) ? -1 : (int)$mtime) !== (int)$saved) {
            return false;
        }
    }
    return true;
}

/***** 토큰화 *****/
// 결과: array(종류, 값, 줄)의 목록. 종류는 name(소문자 식별자)·var·lit(따옴표 안 값)·str(값을 알 수 없는 문자열)·num·kw·ch·op.
// 주석·doc·inline HTML·문자열 안의 내용은 건너뛴다(보간 {…} 안의 코드는 그대로 본다)
function pluginGuardTokens($code) {
    static $skip = null;
    static $keywords = array();
    static $qualified = array();
    static $arrows = array();
    static $amps = array();
    if (!function_exists('token_get_all')) {
        return null;
    }
    if ($skip === null) {
        $skip = array();
        foreach (array('T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT', 'T_INLINE_HTML', 'T_OPEN_TAG', 'T_OPEN_TAG_WITH_ECHO') as $c) {
            if (defined($c)) {
                $skip[constant($c)] = true;
            }
        }
        $kw = array('T_FUNCTION' => 'function', 'T_NEW' => 'new', 'T_CLASS' => 'class', 'T_INTERFACE' => 'interface',
            'T_TRAIT' => 'trait', 'T_EXTENDS' => 'extends', 'T_IMPLEMENTS' => 'implements', 'T_USE' => 'use',
            'T_CONST' => 'const', 'T_EVAL' => 'eval', 'T_NAMESPACE' => 'namespace', 'T_ARRAY' => 'array', 'T_FN' => 'fn');
        foreach ($kw as $c => $v) {
            if (defined($c)) {
                $keywords[constant($c)] = $v;
            }
        }
        foreach (array('T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED', 'T_NAME_RELATIVE') as $c) {
            if (defined($c)) {
                $qualified[constant($c)] = true;
            }
        }
        foreach (array('T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR') as $c) {
            if (defined($c)) {
                $arrows[constant($c)] = true;
            }
        }
        foreach (array('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG', 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG') as $c) {
            if (defined($c)) {
                $amps[constant($c)] = true;
            }
        }
    }
    try {
        $raw = token_get_all($code);
    } catch (Exception $e) {	// Textcube-NG: PHP 5.x 에는 Throwable 이 없어 Exception 을 먼저 잡는다(r4-4)
        return null;
    } catch (Throwable $e) {
        return null;
    }
    if (!is_array($raw)) {
        return null;
    }
    $out = array();
    $strings = array(); // array(닫는 표시, 보간 깊이)
    $line = 1;
    foreach ($raw as $t) {
        if (is_array($t)) {
            $id = $t[0];
            $text = $t[1];
            $line = $t[2];
        } else {
            $id = null;
            $text = $t;
        }
        $top = count($strings) - 1;
        if ($top >= 0 && $strings[$top][1] === 0) {
            if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $strings[$top][1] = 1;
            } elseif ($strings[$top][0] === 'h' ? ($id === T_END_HEREDOC) : ($id === null && $text === $strings[$top][0])) {
                array_pop($strings);
            }
            continue;
        }
        if ($top >= 0) {
            if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES || ($id === null && $text === '{')) {
                $strings[$top][1]++;
                $out[] = array('ch', '{', $line);
                continue;
            }
            if ($id === null && $text === '}') {
                $strings[$top][1]--;
                if ($strings[$top][1] > 0) {
                    $out[] = array('ch', '}', $line);
                }
                continue;
            }
        }
        if ($id === null) {
            if ($text === '"' || $text === '`') {
                $out[] = array('str', '', $line);
                $strings[] = array($text, 0);
                continue;
            }
            $out[] = array('ch', $text, $line);
            continue;
        }
        if (isset($skip[$id])) {
            continue;
        }
        if ($id === T_CLOSE_TAG) {
            $out[] = array('ch', ';', $line);
            continue;
        }
        if ($id === T_CONSTANT_ENCAPSED_STRING) {
            $s = $text;
            if ($s !== '' && ($s[0] === 'b' || $s[0] === 'B')) {
                $s = substr($s, 1);
            }
            $quote = substr($s, 0, 1);
            $body = (string)substr($s, 1, -1);
            if ($quote === "'") {
                $out[] = array('lit', strtr($body, array('\\\\' => '\\', "\\'" => "'")), $line);
            } elseif (strpos($body, '\\') === false) {
                $out[] = array('lit', $body, $line);
            } else {
                $out[] = array('str', '', $line);
            }
            continue;
        }
        if ($id === T_START_HEREDOC) {
            $out[] = array('str', '', $line);
            $strings[] = array('h', 0);
            continue;
        }
        if ($id === T_VARIABLE) {
            $out[] = array('var', strtolower(substr($text, 1)), $line);
            continue;
        }
        if ($id === T_LNUMBER || $id === T_DNUMBER) {
            $out[] = array('num', $text, $line);
            continue;
        }
        if ($id === T_STRING || $id === T_STATIC) {
            $out[] = array('name', strtolower($text), $line);
            continue;
        }
        if (isset($qualified[$id])) {
            $pos = strrpos($text, '\\');
            $out[] = array('name', strtolower(($pos === false) ? $text : substr($text, $pos + 1)), $line);
            continue;
        }
        if ($id === T_NS_SEPARATOR) {
            // PHP 7.4: Foo\bar 는 T_STRING T_NS_SEPARATOR T_STRING 이다. 마지막 조각만 남긴다
            $last = count($out) - 1;
            if ($last >= 0 && ($out[$last][0] === 'name' || ($out[$last][0] === 'kw' && $out[$last][1] === 'namespace'))) {
                array_pop($out);
            }
            continue;
        }
        if (isset($keywords[$id])) {
            $out[] = array('kw', $keywords[$id], $line);
            continue;
        }
        if ($id === T_DOUBLE_COLON) {
            $out[] = array('ch', '::', $line);
            continue;
        }
        if (isset($arrows[$id])) {
            $out[] = array('ch', '->', $line);
            continue;
        }
        if ($id === T_DOUBLE_ARROW) {
            $out[] = array('ch', '=>', $line);
            continue;
        }
        if (isset($amps[$id])) {
            $out[] = array('ch', '&', $line);
            continue;
        }
        $out[] = array('op', strtolower($text), $line);
    }
    return $out;
}

/***** 분석 *****/
function pluginGuardTables() {
    static $tables = null;
    if ($tables === null) {
        $tables = array(
            'pod' => array_fill_keys(array('pod', 'dbadapter', 'dbquery'), true),
            'conn' => array_fill_keys(array('pdo', 'pdostatement', 'mysqli', 'mysqli_result', 'mysqli_stmt', 'mysqli_driver',
                'mysqli_warning', 'sqlite3', 'sqlite3stmt', 'sqlite3result', 'dbadapterstatement', 'dbadapterwriteresult',
                'dbadapterpdostatement', 'iadapter'), true),
            'cuf' => array_fill_keys(array('call_user_func', 'call_user_func_array', 'forward_static_call', 'forward_static_call_array'), true),
            'escape' => array_fill_keys(array('mysql_tt_escape_string', 'mysql_tc_escape_string', 'tc_escape_string'), true),
            'sql' => array_fill_keys(array('query', 'queryexistence', 'querycount', 'querycell', 'queryrow', 'querycolumn', 'queryall',
                'queryallwithoutcache', 'queryallwithcache', 'execute', 'multiquery', 'querywithdbcache', 'queryallwithdbcache',
                'queryrowwithdbcache', 'querycolumnwithdbcache', 'num_rows', 'free', 'fetch', '__compatquery'), true),
            'prep' => array_fill_keys(array('prepare', 'bindandexecute', 'fetchallstmt'), true),
            'allowed' => array_fill_keys(array('escapestring', 'insertid', 'dbms', 'version', 'charset', 'clearcache', 'commit',
                'rollback', 'reservedfieldnames', 'reservedfunctionnames', 'tablelist', 'fieldtype', 'settimezone', 'error', 'stat',
                'cacheload', 'cachesave', '__ispdo'), true),
        );
    }
    return $tables;
}

function pluginGuardIsDbFunction($name) {
    return preg_match('/^(mysql_|mysqli_|pg_|cubrid_|sqlite_|sqlite3_|sqlsrv_|oci_|odbc_|mssql_|ibase_|fbird_|db2_)/', $name) === 1;
}

function pluginGuardCount(&$r, $kind, $rel, $line, $what) {
    if ($kind === '') {
        return;
    }
    $r[$kind]++;
    if ($kind === 'dyn') {
        $key = (string)$rel;
        $r['df'][$key] = (isset($r['df'][$key]) ? (int)$r['df'][$key] : 0) + 1; // dyn이 나온 파일별 개수(검토 번들 예외 판정)
    }
    if (count($r['i']) < PLUGIN_GUARD_MAX_ITEMS) {
        $r['i'][] = $kind . '|' . str_replace('|', '_', (string)$rel) . '|' . (int)$line . '|' . substr(str_replace('|', '_', (string)$what), 0, 64);
    }
}

function pluginGuardMethodKind($method) {
    $t = pluginGuardTables();
    $method = strtolower((string)$method);
    if (isset($t['sql'][$method])) {
        return 'sql';
    }
    if (isset($t['prep'][$method])) {
        return (pluginGuardPrepMode() === 'p') ? '' : 'conn';
    }
    if (isset($t['allowed'][$method])) {
        return '';
    }
    return 'conn';
}

// call_user_func 류의 첫 인자: 리터럴·배열 리터럴·익명 함수는 리터럴 규칙이 따로 본다. 그 밖은 판정 불가로 센다
function pluginGuardCallback(&$r, $rel, $tk, $j, $line, $fn) {
    $n = count($tk);
    $at = function ($k) use ($tk, $n) {
        return ($k >= 0 && $k < $n) ? $tk[$k] : array('eof', '', 0);
    };
    $a = $at($j);
    if ($a[0] === 'lit') {
        $b = $at($j + 1);
        if ($b[0] === 'ch' && $b[1] === '.') {
            pluginGuardCount($r, 'dyn', $rel, $line, $fn . '(lit.)');
        }
        return;
    }
    if ($a[0] === 'kw' && ($a[1] === 'function' || $a[1] === 'fn')) {
        return;
    }
    if ($a[0] === 'name' && $a[1] === 'static') {
        $b = $at($j + 1);
        if ($b[0] === 'kw' && ($b[1] === 'function' || $b[1] === 'fn')) {
            return;
        }
    }
    $k = -1;
    if ($a[0] === 'kw' && $a[1] === 'array') {
        $b = $at($j + 1);
        if ($b[0] === 'ch' && $b[1] === '(') {
            $k = $j + 2;
            $close = ')';
        }
    } elseif ($a[0] === 'ch' && $a[1] === '[') {
        $k = $j + 1;
        $close = ']';
    }
    if ($k >= 0) {
        $e1 = $at($k);
        $e2 = $at($k + 1);
        $e3 = $at($k + 2);
        $e4 = $at($k + 3);
        if (in_array($e1[0], array('var', 'lit', 'name'), true) && $e2[0] === 'ch' && $e2[1] === ',' && $e3[0] === 'lit' && $e4[0] === 'ch' && $e4[1] === $close) {
            return;
        }
    }
    pluginGuardCount($r, 'dyn', $rel, $line, $fn . '(?)');
}

function pluginGuardClassify(&$r, $rel, $tk, $defined) {
    $t = pluginGuardTables();
    $E = array('eof', '', 0);
    $n = count($tk);
    for ($i = 0; $i < $n; $i++) {
        $type = $tk[$i][0];
        $x = $tk[$i][1];
        $line = $tk[$i][2];
        $p1 = ($i > 0) ? $tk[$i - 1] : $E;
        $p2 = ($i > 1) ? $tk[$i - 2] : $E;
        $n1 = ($i + 1 < $n) ? $tk[$i + 1] : $E;
        $n2 = ($i + 2 < $n) ? $tk[$i + 2] : $E;
        $n3 = ($i + 3 < $n) ? $tk[$i + 3] : $E;
        $n1ch = ($n1[0] === 'ch') ? $n1[1] : '';
        $p1ch = ($p1[0] === 'ch') ? $p1[1] : '';
        $n3paren = ($n3[0] === 'ch' && $n3[1] === '(');
        if ($type === 'name') {
            if ($n1ch === '::') {
                if (isset($t['pod'][$x])) {
                    if ($n2[0] === 'name' && $n3paren) {
                        pluginGuardCount($r, pluginGuardMethodKind($n2[1]), $rel, $line, $x . '::' . $n2[1]);
                    } elseif ($n2[0] === 'var' && $n3paren) {
                        pluginGuardCount($r, 'sql', $rel, $line, $x . '::$' . $n2[1]);
                    } elseif ($n2[0] === 'var') {
                        pluginGuardCount($r, 'conn', $rel, $line, $x . '::$' . $n2[1]);
                    }
                } elseif (isset($t['conn'][$x]) && ($n2[0] === 'name' || $n2[0] === 'var') && $n3paren) {
                    pluginGuardCount($r, 'conn', $rel, $line, $x . '::' . $n2[1]);
                } elseif ($x === 'paging' && $n2[0] === 'name' && ($n2[1] === 'fetch' || $n2[1] === 'fetchwithpaging') && $n3paren) {
                    pluginGuardCount($r, 'sql', $rel, $line, 'paging::' . $n2[1]);
                }
                continue;
            }
            if ($n1ch !== '(') {
                continue;
            }
            if ($p1ch === '->') {
                if (isset($t['sql'][$x])) {
                    $r['inst']++;
                }
                continue;
            }
            if ($p1ch === '::') {
                continue;
            }
            if ($p1[0] === 'kw' && in_array($p1[1], array('function', 'fn', 'new', 'const'), true)) {
                continue;
            }
            if ($p1ch === '&' && $p2[0] === 'kw' && $p2[1] === 'function') {
                continue;
            }
            if ($x === 'fetchwithpaging' && !isset($defined[$x])) {
                pluginGuardCount($r, 'sql', $rel, $line, $x);
            } elseif (isset($t['escape'][$x])) {
                continue;
            } elseif (pluginGuardIsDbFunction($x) && (!isset($defined[$x]) || function_exists($x))) {
                pluginGuardCount($r, 'conn', $rel, $line, $x);
            } elseif ($x === 'create_function') {
                pluginGuardCount($r, 'dyn', $rel, $line, $x);
            } elseif (isset($t['cuf'][$x])) {
                pluginGuardCallback($r, $rel, $tk, $i + 2, $line, $x);
            }
            continue;
        }
        if ($type === 'var') {
            if ($n1ch === '(') {
                if ($p1ch === '::' && ($p2[0] === 'var' || ($p2[0] === 'name' && (isset($t['pod'][$p2[1]]) || isset($t['conn'][$p2[1]]))))) {
                    continue;
                }
                pluginGuardCount($r, 'dyn', $rel, $line, '$' . $x . '(');
            } elseif ($n1ch === '::' && ($n2[0] === 'name' || $n2[0] === 'var') && $n3paren) {
                pluginGuardCount($r, 'dyn', $rel, $line, '$' . $x . '::' . $n2[1]);
            }
            continue;
        }
        if ($type === 'ch') {
            if ($x === ']' && $n1ch === '(') {
                pluginGuardCount($r, 'dyn', $rel, $line, '](');
            }
            continue;
        }
        if ($type === 'kw') {
            if ($x === 'eval') {
                pluginGuardCount($r, 'dyn', $rel, $line, 'eval');
            } elseif ($x === 'new') {
                if ($n1[0] === 'name' && (isset($t['conn'][$n1[1]]) || isset($t['pod'][$n1[1]]))) {
                    pluginGuardCount($r, 'conn', $rel, $line, 'new ' . $n1[1]);
                } elseif ($n1[0] === 'var' || $n1ch === '(') {
                    pluginGuardCount($r, 'dyn', $rel, $line, 'new $');
                }
            } elseif (($x === 'extends' || $x === 'implements' || $x === 'use') && $n1ch !== '(') {
                $limit = min($n, $i + 65);
                for ($k = $i + 1; $k < $limit; $k++) {
                    $c = $tk[$k];
                    if ($c[0] === 'name') {
                        if (isset($t['conn'][$c[1]]) || isset($t['pod'][$c[1]]) || ($x === 'use' && pluginGuardIsDbFunction($c[1]))) {
                            pluginGuardCount($r, 'conn', $rel, $c[2], $x . ' ' . $c[1]);
                            break;
                        }
                        continue;
                    }
                    if (($c[0] === 'ch' && in_array($c[1], array(',', '{', '}'), true)) || ($c[0] === 'kw' && ($c[1] === 'function' || $c[1] === 'const'))) {
                        continue;
                    }
                    if ($c[0] === 'op' && $c[1] === 'as') {
                        $k++;
                        continue;
                    }
                    break;
                }
            }
            continue;
        }
        if ($type === 'lit') {
            $m = array();
            $lower = strtolower(ltrim($x, '\\'));
            if (preg_match('/^(pod|dbadapter|dbquery)::(\w+)$/', $lower, $m)) {
                pluginGuardCount($r, pluginGuardMethodKind($m[2]), $rel, $line, "'" . $m[1] . '::' . $m[2] . "'");
            } elseif (isset($t['pod'][$lower]) && ($p1ch === '(' || $p1ch === '[') && $n1ch === ',') {
                if ($n2[0] === 'lit') {
                    pluginGuardCount($r, pluginGuardMethodKind($n2[1]), $rel, $line, "'" . $lower . "','" . $n2[1] . "'");
                } else {
                    pluginGuardCount($r, 'dyn', $rel, $line, "'" . $lower . "',?");
                }
            } elseif (preg_match('/^\w+$/', $lower) && pluginGuardIsDbFunction($lower) && !isset($t['escape'][$lower]) && (!isset($defined[$lower]) || function_exists($lower))) {
                if (($p1ch === '[' && $n1ch === ']') || $n1ch === '=>') {
                    continue;
                }
                if (($p1ch === '(' && $p2[0] === 'name' && isset($t['cuf'][$p2[1]])) || function_exists($lower)) {
                    pluginGuardCount($r, 'conn', $rel, $line, "'" . $lower . "'");
                }
            } elseif (isset($t['conn'][$lower]) && ($p1ch === '(' || $p1ch === '[' || $p1ch === ',')) {
                pluginGuardCount($r, 'dyn', $rel, $line, "'" . $lower . "'");
            }
            continue;
        }
    }
}

function pluginGuardAnalyzeFiles($name, $files) {
    $r = array('g' => 1, 'sql' => 0, 'conn' => 0, 'dyn' => 0, 'inst' => 0, 'i' => array(), 'df' => array());
    if (!function_exists('token_get_all')) {
        $r['g'] = 0;
        pluginGuardCount($r, 'dyn', '-', 0, 'tokenizer');
        return $r;
    }
    $base = ROOT . '/plugins/' . $name . '/';
    $defined = array();
    $failed = array();
    // 1차: 플러그인이 정의한 함수 이름을 모은다
    foreach ($files as $rel => $stat) {
        $rel = (string)$rel;
        if ($rel === 'index.xml') {
            continue;
        }
        $size = @filesize($base . $rel);
        if ($size === false) {
            $failed[$rel] = 'read';
        } elseif ($size > PLUGIN_GUARD_MAX_FILE) {
            $failed[$rel] = 'size';
        } else {
            $code = @file_get_contents($base . $rel);
            $tk = is_string($code) ? pluginGuardTokens($code) : null;
            if (!is_string($code)) {
                $failed[$rel] = 'read';
            } elseif (!is_array($tk)) {
                $failed[$rel] = 'token';
            } else {
                $count = count($tk);
                for ($k = 0; $k < $count; $k++) {
                    if ($tk[$k][0] !== 'kw' || $tk[$k][1] !== 'function') {
                        continue;
                    }
                    $next = $k + 1;
                    if ($next < $count && $tk[$next][0] === 'ch' && $tk[$next][1] === '&') {
                        $next++;
                    }
                    if ($next < $count && $tk[$next][0] === 'name') {
                        $defined[$tk[$next][1]] = true;
                    }
                }
            }
            unset($code, $tk);
        }
        if (isset($failed[$rel])) {
            $r['g'] = 0;
            pluginGuardCount($r, 'dyn', $rel, 0, $failed[$rel]);
        }
    }
    // 2차: 분류
    foreach ($files as $rel => $stat) {
        $rel = (string)$rel;
        if ($rel === 'index.xml' || isset($failed[$rel])) {
            continue;
        }
        $code = @file_get_contents($base . $rel);
        $tk = is_string($code) ? pluginGuardTokens($code) : null;
        if (!is_array($tk)) {
            $r['g'] = 0;
            pluginGuardCount($r, 'dyn', $rel, 0, 'read');
            continue;
        }
        pluginGuardClassify($r, $rel, $tk, $defined);
        unset($code, $tk);
    }
    return $r;
}

/***** 검사 결과 캐시(서비스 설정 pgs_*)·무결성 기록(블로그 설정 pgi_*) *****/
function pluginGuardScan($name, $list = null, $force = false) {
    static $memo = array();
    if (isset($memo[$name]) && (!$force || $memo[$name][1])) {
        return $memo[$name][0];
    }
    if (!pluginGuardNameIsValid($name)) {
        return array('g' => 0, 'sql' => 0, 'conn' => 0, 'dyn' => 1, 'inst' => 0, 'i' => array('dyn|-|0|name'), 'df' => array('-' => 1));
    }
    if (!is_array($list)) {
        $list = pluginGuardFileList($name);
    }
    $sig = md5(serialize(array(PLUGIN_GUARD_VERSION, pluginGuardPrepMode(), $list[0], $list[2])));
    $key = pluginGuardScanKey($name);
    if (!$force) {
        $cached = json_decode((string)Setting::getServiceSettingGlobal($key, ''), true);
        if (is_array($cached) && isset($cached['s'], $cached['sql'], $cached['conn'], $cached['dyn']) && $cached['s'] === $sig) {
            $scan = array('g' => (int)(isset($cached['g']) ? $cached['g'] : 0), 'sql' => (int)$cached['sql'], 'conn' => (int)$cached['conn'],
                'dyn' => (int)$cached['dyn'], 'inst' => (int)(isset($cached['inst']) ? $cached['inst'] : 0),
                'i' => (isset($cached['i']) && is_array($cached['i'])) ? $cached['i'] : array(),
                'df' => (isset($cached['df']) && is_array($cached['df'])) ? $cached['df'] : array());
            $memo[$name] = array($scan, false);
            return $scan;
        }
    }
    $scan = pluginGuardAnalyzeFiles($name, $list[0]);
    if ($list[2]) {
        $scan['g'] = 0;
        pluginGuardCount($scan, 'dyn', '-', 0, 'limit');
    }
    $stored = $scan;
    $stored['s'] = $sig;
    $stored['m'] = pluginGuardPrepMode();
    $json = jsonEncodeCompat($stored, pluginGuardJsonFlags());
    if (is_string($json) && strlen($json) < PLUGIN_GUARD_MAX_VALUE) {
        Setting::setServiceSettingGlobal($key, $json);
    }
    $memo[$name] = array($scan, true);
    return $scan;
}

function pluginGuardBuildRecord($name, $by, &$saved = null) {
    $list = pluginGuardFileList($name);
    $scan = pluginGuardScan($name, $list, true);
    $all = pluginGuardFileList($name, true); // Textcube-NG: 묶음값·stat 기록은 무결성 목록(폴더 안 모든 파일)으로, 정적 분석(scan)은 위 목록으로 한다(v3.211)
    $record = array(
        'v' => PLUGIN_GUARD_VERSION,
        'pm' => pluginGuardPrepMode(),
        'd' => $all[2] ? '' : pluginGuardDigest($name, $all[0]),
        'f' => $all[0],
        'w' => $all[1],
        'by' => (string)$by,
        'p' => pluginGuardPrivilege($name),
        't' => time(),
        'sql' => (int)$scan['sql'],
        'conn' => (int)$scan['conn'],
        'dyn' => (int)$scan['dyn'],
        'df' => $scan['df']
    );
    if ($record['d'] === '') {
        // Textcube-NG: 목록을 끝까지 읽지 못했거나 읽지 못한 파일이 있으면 기준값을 남기지 않는다. 돌려준 기록(d 빈 값)은 적재 때 'integrity'로 막는다(v3.211)
        $saved = false;
        return $record;
    }
    // 엄격 모드가 꺼져 있으면 기록을 쓰지 못해도 막지 않는다(다음 적재 때 다시 만든다). 켜져 있으면 다음 적재 때 'strict'로 막힌다
    $saved = pluginGuardSaveRecord($name, $record);
    return $record;
}

function pluginGuardCheckRecord($name, &$record, $strict = null) {
    $changed = false;
    $refreshOnly = true;
    $version = (int)(isset($record['v']) ? $record['v'] : 0);
    if ((string)$record['d'] === '') {
        return false; // Textcube-NG: 빈 묶음값은 기준값이 아니다(hash_equals('', '') 통과 방지)(v3.211)
    }
    if ($version === 2) {
        // Textcube-NG: v2 기록은 PHP 계열 파일과 index.xml만 묶었다. 같은 규칙으로 다시 묶어 같을 때만 무결성 목록(폴더 안 모든 파일)으로 기준값을 새로 남긴다.
        // 그 밖의 파일은 이때 처음 본 상태를 믿는다. 엄격 모드에서는 막는다(끄고 다시 켜면 풀린다)(v3.211)
        if ($strict === null) {
            $strict = pluginGuardStrictEnabled();
        }
        if ($strict) {
            return false;
        }
        $list = pluginGuardFileList($name);
        if ($list[2] || !hash_equals((string)$record['d'], pluginGuardDigest($name, $list[0]))) {
            return false;
        }
        $list = pluginGuardFileList($name, true);
        $digest = $list[2] ? '' : pluginGuardDigest($name, $list[0]);
        if ($digest === '') {
            return false;
        }
        $record['d'] = $digest;
        $record['f'] = $list[0];
        $record['w'] = $list[1];
        $changed = true;
    } elseif ($version !== PLUGIN_GUARD_VERSION) {
        return false; // Textcube-NG: 형식을 모르는 기록은 막는다(끄고 다시 켜면 풀린다)(v3.211)
    } elseif (!pluginGuardStatMatches($name, $record)) {
        $list = pluginGuardFileList($name, true); // Textcube-NG: 무결성 목록으로 다시 묶는다. 목록을 끝까지 읽지 못하면 막는다(v3.211)
        if ($list[2] || !hash_equals((string)$record['d'], pluginGuardDigest($name, $list[0]))) {
            return false;
        }
        $record['f'] = $list[0];
        $record['w'] = $list[1];
        $changed = true;
    }
    if ((int)(isset($record['v']) ? $record['v'] : 0) !== PLUGIN_GUARD_VERSION || (string)(isset($record['pm']) ? $record['pm'] : '') !== pluginGuardPrepMode()) {
        $scan = pluginGuardScan($name, null, true);
        $record['v'] = PLUGIN_GUARD_VERSION;
        $record['pm'] = pluginGuardPrepMode();
        $record['sql'] = (int)$scan['sql'];
        $record['conn'] = (int)$scan['conn'];
        $record['dyn'] = (int)$scan['dyn'];
        $record['df'] = $scan['df'];
        $changed = true;
        $refreshOnly = false;
    }
    if ($changed) {
        pluginGuardSaveRecord($name, $record, $refreshOnly);
    }
    return true;
}

/***** 적재 재검증 *****/
function pluginGuardEvaluateOne($name, $isPDO, $allow, $strict = null, $exempt = null) {
    if (!pluginGuardNameIsValid($name)) {
        return 'regex';
    }
    if (!is_file(ROOT . '/plugins/' . $name . '/index.xml')) {
        return ''; // plugins.php의 기존 정리(deactivatePlugin) 경로가 돌게 둔다
    }
    if (!pluginGuardIsAllowed($name, $allow)) {
        return 'allowlist';
    }
    if ($strict === null) {
        $strict = pluginGuardStrictEnabled();
    }
    $record = pluginGuardLoadRecord($name);
    if (pluginGuardIsExempt($name, $exempt)) {
        return pluginGuardEvaluateExempt($name, $isPDO, $record); // Textcube-NG: 무결성 검사 면제(r3-14). 엄격 모드에서도 무결성 기록과 비교하지 않는다
    }
    if ($record === null) {
        if ($strict) {
            return 'strict'; // 엄격 모드: 기록이 없으면(activatePlugin 밖에서 켰거나 기록 저장 실패) 막는다. 다시 켜면 기록이 생긴다
        }
        $record = pluginGuardBuildRecord($name, 'tofu'); // 이 판 이전에 켠 플러그인: 처음 본 상태를 기준값으로 삼는다
        if ((string)$record['d'] === '') {
            return 'integrity'; // Textcube-NG: 목록을 끝까지 읽지 못했거나 읽지 못한 파일이 있어 기준값을 만들지 못했다(v3.211)
        }
    } elseif (!pluginGuardCheckRecord($name, $record, $strict)) {
        return 'integrity'; // 코어(FM_TTML·ED_tinyMCE)도 막는다
    }
    if ((string)(isset($record['p']) ? $record['p'] : '') === 'administrator' && (string)(isset($record['by']) ? $record['by'] : '') === 'user') {
        return 'privilege';
    }
    if ($isPDO && ((int)(isset($record['sql']) ? $record['sql'] : 0) + (int)(isset($record['conn']) ? $record['conn'] : 0)) > 0) {
        return 'pdo';
    }
    if ($isPDO && (int)(isset($record['dyn']) ? $record['dyn'] : 0) > 0 && !pluginGuardDynExempt($name, $record)) {
        return 'pdo'; // 판정 불가 호출도 PDO 모드에서는 막는다(검토 번들 예외만 통과)
    }
    return '';
}

// Textcube-NG: 무결성 검사 면제 플러그인 판정(r3-14). 무결성 기록과 비교하지 않고(엄격 모드 포함), 지금 파일을 검사한 값으로 서비스 관리자 전용·PDO 판정을 한다.
//   기록은 바꾸지 않는다(면제를 풀면 원래 기록과 다시 비교한다). 기록이 없으면 누가 켰는지(by) 알 수 없어 서비스 관리자 전용 판정은 하지 않는다(TOFU 와 같음).
//   목록을 끝까지 읽지 못하면 pluginGuardScan 이 dyn(limit)으로 세어 PDO 모드에서 막힌다
function pluginGuardEvaluateExempt($name, $isPDO, $record) {
    $by = is_array($record) ? (string)(isset($record['by']) ? $record['by'] : '') : '';
    if ($by === 'user' && pluginGuardPrivilege($name) === 'administrator') {
        return 'privilege';
    }
    if (!$isPDO) {
        return '';
    }
    $scan = pluginGuardScan($name);
    if (((int)$scan['sql'] + (int)$scan['conn']) > 0) {
        return 'pdo';
    }
    if ((int)$scan['dyn'] > 0 && !pluginGuardDynExempt($name, $scan)) {
        return 'pdo';
    }
    return '';
}

function pluginGuardEvaluate($activePlugins) {
    global $pluginGuardBlocked, $pluginGuardEvaluated;
    $isPDO = pluginGuardIsPDO();
    $allow = pluginGuardAllowlist();
    $strict = pluginGuardStrictEnabled();
    $exempt = pluginGuardExemptList(); // Textcube-NG: 무결성 검사 면제(r3-14, 서비스 설정 pluginIntegrityExempt)
    $blocked = array();
    if (is_array($activePlugins)) {
        foreach ($activePlugins as $name) {
            $name = (string)$name;
            $reason = pluginGuardEvaluateOne($name, $isPDO, $allow, $strict, $exempt);
            if ($reason !== '') {
                $blocked[$name] = $reason;
            }
        }
    }
    ksort($blocked, SORT_STRING);
    $pluginGuardBlocked = $blocked;
    $pluginGuardEvaluated = true;
    return md5(serialize(array(PLUGIN_GUARD_VERSION, $isPDO, $allow, $strict, $exempt, $blocked))); // Textcube-NG: 면제를 바꾸면 다시 바인딩한다(r3-14)
}

function pluginGuardIsLoadable($name) {
    global $activePlugins, $pluginGuardBlocked, $pluginGuardEvaluated;
    if (!pluginGuardNameIsValid($name)) {
        return false;
    }
    if (!is_array($activePlugins) || !in_array($name, $activePlugins, true)) {
        return false;
    }
    if (empty($pluginGuardEvaluated)) {
        pluginGuardEvaluate($activePlugins);
    }
    return !isset($pluginGuardBlocked[$name]);
}

function pluginGuardFilterMap($map, $blocked, $reindex) {
    if (!is_array($map)) {
        return $map;
    }
    foreach ($map as $k => $item) {
        if (is_array($item) && isset($item['plugin']) && isset($blocked[(string)$item['plugin']])) {
            unset($map[$k]);
        }
    }
    return $reindex ? array_values($map) : $map;
}

// 바인딩 캐시(PluginSettings)에서 복원한 매핑에서도 막힌 플러그인을 뺀다
function pluginGuardFilterMappings() {
    global $pluginGuardBlocked, $eventMappings, $tagMappings, $centerMappings, $sidebarMappings, $coverpageMappings,
        $formatterMappings, $editorMappings, $adminMenuMappings, $adminHandlerMappings, $configMappings;
    if (empty($pluginGuardBlocked) || !is_array($pluginGuardBlocked)) {
        return;
    }
    $blocked = $pluginGuardBlocked;
    if (is_array($eventMappings)) {
        foreach ($eventMappings as $k => $group) {
            $eventMappings[$k] = pluginGuardFilterMap($group, $blocked, true);
            if (empty($eventMappings[$k])) {
                unset($eventMappings[$k]);
            }
        }
    }
    if (is_array($tagMappings)) {
        foreach ($tagMappings as $k => $group) {
            $tagMappings[$k] = pluginGuardFilterMap($group, $blocked, true);
            if (empty($tagMappings[$k])) {
                unset($tagMappings[$k]);
            }
        }
    }
    $centerMappings = pluginGuardFilterMap($centerMappings, $blocked, true);
    $sidebarMappings = pluginGuardFilterMap($sidebarMappings, $blocked, true);
    $coverpageMappings = pluginGuardFilterMap($coverpageMappings, $blocked, true);
    $formatterMappings = pluginGuardFilterMap($formatterMappings, $blocked, false);
    $editorMappings = pluginGuardFilterMap($editorMappings, $blocked, false);
    foreach (array('adminMenuMappings', 'adminHandlerMappings') as $var) {
        if (!isset($GLOBALS[$var]) || !is_array($GLOBALS[$var])) {
            continue;
        }
        foreach ($GLOBALS[$var] as $k => $item) {
            $owner = (is_array($item) && isset($item['plugin'])) ? (string)$item['plugin'] : strstr((string)$k, '/', true);
            if ($owner !== false && isset($blocked[$owner])) {
                unset($GLOBALS[$var][$k]);
            }
        }
    }
    if (is_array($configMappings)) {
        foreach ($blocked as $name => $reason) {
            unset($configMappings[$name]);
        }
    }
    if (is_array($formatterMappings) && is_array($editorMappings)) {
        foreach ($formatterMappings as $id => $formatter) {
            if (!isset($formatter['editors']) || !is_array($formatter['editors'])) {
                continue;
            }
            foreach ($formatter['editors'] as $editorId => $unused) {
                if (!isset($editorMappings[$editorId])) {
                    unset($formatterMappings[$id]['editors'][$editorId]);
                }
            }
        }
    }
}

/***** 활성화 검사·표시 *****/
function pluginGuardReasonText($reason) {
    switch ($reason) {
        case 'regex':
            return _t('이름 규칙 위반');
        case 'allowlist':
            return _t('허용 목록 밖');
        case 'integrity':
            return _t('무결성');
        case 'strict':
            return _t('무결성 기록 없음(엄격 모드)');
        case 'privilege':
            return _t('서비스 관리자 전용');
        case 'pdo':
            return _t('PDO 모드에서 쓸 수 없음');
    }
    return (string)$reason;
}

function pluginGuardScanSummary($scan, $kinds) {
    $items = array();
    if (isset($scan['i']) && is_array($scan['i'])) {
        foreach ($scan['i'] as $item) {
            $part = explode('|', (string)$item, 4);
            if (count($part) === 4 && in_array($part[0], $kinds, true)) {
                $items[] = $part[1] . ':' . $part[2] . ' ' . $part[3];
                if (count($items) >= 3) {
                    break;
                }
            }
        }
    }
    if (empty($items)) {
        return '';
    }
    return ' (' . implode(', ', $items) . ')';
}

function pluginGuardCheckActivation($name) {
    if (!pluginGuardNameIsValid($name)) {
        return array(false, _t('플러그인 이름이 올바르지 않습니다.'));
    }
    if (!pluginGuardIsAllowed($name)) {
        return array(false, _t('서비스 관리자가 허용하지 않은 플러그인입니다.'));
    }
    $all = pluginGuardFileList($name, true);
    if ($all[2] || pluginGuardDigest($name, $all[0]) === '') {
        // Textcube-NG: 무결성 목록(폴더 안 모든 파일)을 끝까지 읽지 못하면 기준값을 남길 수 없으므로 켜지 않는다(v3.211)
        // Textcube-NG: 목록은 끝까지 읽었어도 읽지 못한 파일(hash_file 실패·읽기 권한 없음)이 있어 묶음값이 빈 값이면 켜지 않는다(v3.211)
        return array(false, _t('플러그인 폴더의 파일 목록을 끝까지 읽지 못했습니다(심볼릭 링크·특수 파일·깊이·개수 한도·읽기 실패).'));
    }
    $scan = pluginGuardScan($name, null, true);
    $messages = array();
    if (((int)$scan['sql'] + (int)$scan['conn']) > 0) {
        if (pluginGuardIsPDO()) {
            return array(false, _t('이 플러그인은 PDO 모드에서 쓸 수 없습니다.') . pluginGuardScanSummary($scan, array('sql', 'conn')));
        }
        $messages[] = _t('이 플러그인은 직접 SQL 또는 Textcube가 제공하지 않는 DB 연결 요소를 씁니다. PDO 모드로 바꾸면 쓸 수 없습니다.') . pluginGuardScanSummary($scan, array('sql', 'conn'));
    }
    if ((int)$scan['dyn'] > 0 && !pluginGuardDynExempt($name, $scan)) {
        if (pluginGuardIsPDO()) {
            return array(false, _t('이 플러그인은 정적으로 판정할 수 없는 호출을 써서 PDO 모드에서 쓸 수 없습니다.') . pluginGuardScanSummary($scan, array('dyn')));
        }
        $messages[] = _t('정적으로 판정할 수 없는 호출') . ': ' . (int)$scan['dyn'] . pluginGuardScanSummary($scan, array('dyn')) . ' ' . _t('PDO 모드로 바꾸면 쓸 수 없습니다.');
    }
    return array(true, implode("\n", $messages));
}

function pluginGuardLastMessage() {
    global $pluginGuardMessage;
    return is_string($pluginGuardMessage) ? $pluginGuardMessage : '';
}

function pluginGuardResultCode($code) {
    $message = pluginGuardLastMessage();
    if ($message === '') {
        return $code;
    }
    return array($code, str_replace(']]>', ']] >', $message)); // Respond::ResultPage는 메시지를 CDATA로 낸다
}

function pluginGuardListLabel($name, $active) {
    global $pluginGuardBlocked;
    $labels = array();
    if ($active && is_array($pluginGuardBlocked) && isset($pluginGuardBlocked[$name])) {
        $label = _t('차단') . '(' . pluginGuardReasonText($pluginGuardBlocked[$name]) . ')';
        if ($pluginGuardBlocked[$name] === 'integrity' || $pluginGuardBlocked[$name] === 'strict') {
            $label .= ' ' . _t('끄고 다시 켜면 풀립니다');
        }
        $labels[] = $label;
    } elseif (!$active && !pluginGuardIsAllowed($name)) {
        $labels[] = _t('사용 불가') . '(' . _t('허용 목록 밖') . ')';
    }
    if (pluginGuardIsExempt($name)) {
        $labels[] = _t('무결성 검사 면제'); // Textcube-NG(r3-14)
    }
    $scan = pluginGuardScan($name);
    if (((int)$scan['sql'] + (int)$scan['conn']) > 0) {
        $labels[] = pluginGuardIsPDO() ? _t('사용 불가') . '(PDO)' : _t('직접 SQL');
    }
    if ((int)$scan['dyn'] > 0) {
        if (pluginGuardDynExempt($name, $scan)) {
            $labels[] = _t('검토된 번들 예외');
        } elseif (pluginGuardIsPDO()) {
            $labels[] = _t('사용 불가') . '(PDO) ' . _t('판정 불가 호출') . ' ' . (int)$scan['dyn'];
        } else {
            $labels[] = _t('판정 불가 호출') . ' ' . (int)$scan['dyn'];
        }
    }
    return implode(', ', $labels);
}

// Textcube-NG: 차단 화면 개선(r3-13). 플러그인 목록(owner/plugin)의 차단 배지·경고 상자에 쓸 사유와 풀기 방법. 막히지 않았으면 null
//   켜짐: library/plugins.php 가 채운 $pluginGuardBlocked 의 사유. 꺼짐: 켤 때 거부되는 사유(이름 규칙·허용 목록·PDO, pluginGuardCheckActivation 과 같은 조건)
//   action: recheck(무결성·엄격 — 끄고 다시 켜기) | allow(허용 목록에 추가, 서비스 관리자만) | privilege(안내·링크) | info(사유·고치는 법만)
function pluginGuardBlockInfo($name, $active) {
    global $pluginGuardBlocked;
    $name = (string)$name;
    $reason = null;
    if ($active) {
        if (is_array($pluginGuardBlocked) && isset($pluginGuardBlocked[$name])) {
            $reason = (string)$pluginGuardBlocked[$name];
        }
    } elseif (!pluginGuardNameIsValid($name)) {
        $reason = 'regex';
    } elseif (!pluginGuardIsAllowed($name)) {
        $reason = 'allowlist';
    } elseif (pluginGuardIsPDO()) {
        $scan = pluginGuardScan($name);
        if (((int)$scan['sql'] + (int)$scan['conn']) > 0 || ((int)$scan['dyn'] > 0 && !pluginGuardDynExempt($name, $scan))) {
            $reason = 'pdo';
        }
    }
    if ($reason === null) {
        return null;
    }
    $action = 'info';
    $text = '';
    switch ($reason) {
        case 'integrity':
            $action = 'recheck';
            $text = _t('플러그인 파일이 켤 때 남긴 무결성 기록과 달라 불러오지 않습니다. 파일을 바꾼 사람이 본인이거나 믿을 수 있는 사람이면 [다시 검사하고 풀기]를 누르십시오. 모르는 변경이면 플러그인 폴더를 먼저 확인하십시오.');
            break;
        case 'strict':
            $action = 'recheck';
            $text = _t('엄격 모드에서는 무결성 기록이 없는 플러그인을 불러오지 않습니다. [다시 검사하고 풀기]를 누르면 지금 파일로 기록을 새로 남깁니다.');
            break;
        case 'allowlist':
            $action = 'allow';
            $text = Acl::check('group.creators') ? _t('서비스 허용 목록을 쓰고 있어 목록에 없는 플러그인은 켜지도 불러오지도 않습니다. [허용 목록에 추가]를 누르면 목록에 넣습니다.') : _t('서비스 허용 목록을 쓰고 있어 목록에 없는 플러그인은 켜지도 불러오지도 않습니다. 쓰려면 서비스 관리자에게 허용 목록에 넣어 달라고 요청하십시오.');
            break;
        case 'privilege':
            $action = 'privilege';
            $text = _t('서비스 관리자가 아닌 사용자가 켠 서비스 관리자 전용 플러그인이라 불러오지 않습니다. 서비스 관리자가 끄고 다시 켜면 풀립니다.');
            break;
        case 'regex':
            $text = _t('플러그인 폴더 이름에 쓸 수 없는 글자가 있어 켜지도 불러오지도 않습니다. 폴더 이름을 영문·숫자·-·_·공백으로만 바꾸십시오.');
            break;
        case 'pdo':
            $text = _t('PDO 모드에서는 직접 SQL이나 정적으로 판정할 수 없는 호출을 쓰는 플러그인을 불러오지 않습니다. PDO 호환 판으로 바꾸거나, 서비스 관리자가 DB 연결 방식을 native로 두어야 쓸 수 있습니다.');
            break;
    }
    $badge = $active ? _t('차단') : _t('사용 불가');
    return array(
        'reason' => $reason,
        'action' => $action,
        'badge' => $badge,
        'title' => $badge . '(' . pluginGuardReasonText($reason) . ')',
        'text' => $text
    );
}

/***** 알림(대시보드) *****/
// Textcube-NG: 플러그인 차단·자동 꺼짐 알림(v3.219). 블로그 설정 pgn_notices 에 JSON 으로 둔다.
//   {"v":1,"s":{키:사유},"n":[{"k":키,"p":이름,"r":사유,"t0":처음,"t":최근,"c":횟수,"u":미확인 1/확인 0}]}
//   키 = md5(이름 . "\n" . 사유)(자르기 전 이름 전체). s 는 지금 막힌 것(바뀔 때만 쓴다), n 은 이름+사유마다 알림 1개.
//   개수 한도는 서비스 설정 pluginNoticeLimit(1~200, 기본 20). 넘치면 확인한 알림 가운데 오래된 것부터 지우고,
//   확인하지 않은 알림은 저장 크기가 PLUGIN_GUARD_MAX_VALUE 를 넘을 때만 지운다. 풀림 알림은 남기지 않는다.
function pluginNoticeSettingKey() {
    return 'pgn_notices';
}

function pluginNoticeLimit($reload = false) {
    static $limit = null;
    if ($limit === null || $reload) {
        $value = Setting::getServiceSettingGlobal('pluginNoticeLimit', '20');
        $limit = (is_scalar($value) && preg_match('/^[1-9][0-9]{0,2}$/D', (string)$value) && (int)$value <= 200) ? (int)$value : 20;
    }
    return $limit;
}

function pluginNoticeName($name) {
    // 표시용 이름: 깨진 UTF-8·탭·줄바꿈은 ?, 64바이트까지(키는 자르기 전 이름으로 만든다)
    return UTF8::lessenAsByte(str_replace(array("\t", "\n", "\r"), '?', UTF8::correct((string)$name, '?')), 64, '');
}

function pluginNoticeKey($name, $reason) {
    return md5((string)$name . "\n" . (string)$reason);
}

function pluginNoticeEscape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pluginNoticeBlockedMap($blocked) {
    $map = array();
    if (is_array($blocked)) {
        foreach ($blocked as $name => $reason) {
            $name = (string)$name;
            $reason = (string)$reason;
            $map[pluginNoticeKey($name, $reason)] = array($name, $reason);
        }
    }
    ksort($map, SORT_STRING);
    return $map;
}

function pluginNoticeState($map) {
    $state = array();
    foreach ($map as $key => $item) {
        $state[$key] = $item[1];
    }
    return $state;
}

function pluginNoticeEmpty() {
    return array('v' => 1, 's' => array(), 'n' => array());
}

function pluginNoticeLoad() {
    $out = pluginNoticeEmpty();
    $raw = pluginGuardGetBlogValue(pluginNoticeSettingKey());
    if (!is_string($raw) || $raw === '' || strlen($raw) > PLUGIN_GUARD_MAX_VALUE) {
        return $out;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['v'], $data['s'], $data['n']) || $data['v'] !== 1 || !is_array($data['s']) || !is_array($data['n'])) {
        return $out;
    }
    foreach ($data['s'] as $key => $reason) {
        if (is_string($key) && preg_match('/^[0-9a-f]{32}$/D', $key) && is_string($reason)) {
            $out['s'][$key] = $reason;
        }
    }
    $seen = array();
    foreach ($data['n'] as $item) {
        if (!is_array($item) || !isset($item['k'], $item['p'], $item['r'], $item['t0'], $item['t'], $item['c'], $item['u'])) {
            continue;
        }
        if (!is_string($item['k']) || !preg_match('/^[0-9a-f]{32}$/D', $item['k']) || isset($seen[$item['k']])
            || !is_string($item['p']) || !is_string($item['r'])
            || !is_int($item['t0']) || !is_int($item['t']) || !is_int($item['c'])
            || ($item['u'] !== 0 && $item['u'] !== 1)) {
            continue;
        }
        $seen[$item['k']] = true;
        $out['n'][] = array('k' => $item['k'], 'p' => $item['p'], 'r' => $item['r'], 't0' => $item['t0'], 't' => $item['t'], 'c' => $item['c'], 'u' => $item['u']);
    }
    return $out;
}

function pluginNoticeEncode($data) {
    return jsonEncodeCompat(array('v' => 1, 's' => (object)$data['s'], 'n' => array_values($data['n'])), pluginGuardJsonFlags());
}

function pluginNoticeAdd(&$data, $name, $reason, $now) {
    $name = (string)$name;
    $reason = (string)$reason;
    $key = pluginNoticeKey($name, $reason);
    foreach ($data['n'] as $i => $item) {
        if ($item['k'] === $key) {
            // 같은 이름+사유가 다시 생기면 최근 시각·횟수를 갱신하고 미확인으로 되돌린다
            $data['n'][$i]['t'] = $now;
            if ($item['c'] < 999999999) {
                $data['n'][$i]['c'] = $item['c'] + 1;
            }
            $data['n'][$i]['u'] = 1;
            return;
        }
    }
    $data['n'][] = array('k' => $key, 'p' => pluginNoticeName($name), 'r' => $reason, 't0' => $now, 't' => $now, 'c' => 1, 'u' => 1);
}

function pluginNoticeOldest($items, $unread) {
    $found = -1;
    foreach ($items as $i => $item) {
        if ($item['u'] === $unread && ($found < 0 || $item['t'] < $items[$found]['t'])) {
            $found = $i;
        }
    }
    return $found;
}

function pluginNoticeTrim(&$data) {
    $data['n'] = array_values($data['n']);
    $limit = pluginNoticeLimit();
    while (count($data['n']) > $limit) {
        $i = pluginNoticeOldest($data['n'], 0);
        if ($i < 0) {
            break;
        }
        unset($data['n'][$i]);
    }
    while (count($data['n']) > 0) {
        $json = pluginNoticeEncode($data);
        if (is_string($json) && strlen($json) <= PLUGIN_GUARD_MAX_VALUE) {
            break;
        }
        $i = pluginNoticeOldest($data['n'], 0);
        if ($i < 0) {
            $i = pluginNoticeOldest($data['n'], 1);
        }
        if ($i < 0) {
            break;
        }
        unset($data['n'][$i]);
    }
    $data['n'] = array_values($data['n']);
}

function pluginNoticeSave($data) {
    $json = pluginNoticeEncode($data);
    if (!is_string($json) || strlen($json) > PLUGIN_GUARD_MAX_VALUE) {
        return false;
    }
    return pluginGuardSetBlogValue(pluginNoticeSettingKey(), $json);
}

function pluginNoticeSync($blocked) {
    $map = pluginNoticeBlockedMap($blocked);
    $state = pluginNoticeState($map);
    $raw = pluginGuardGetBlogValue(pluginNoticeSettingKey());
    if (!is_string($raw) || $raw === '') {
        if (empty($state)) {
            return true;
        }
    } else {
        // 빠른 길: 저장된 JSON 앞부분 {"v":1,"s":{…}, 가 지금 막힌 것과 같으면 바뀐 것이 없다(읽기·쓰기 없음)
        $head = jsonEncodeCompat(array('v' => 1, 's' => (object)$state), pluginGuardJsonFlags());
        if (is_string($head) && strncmp($raw, substr($head, 0, -1) . ',', strlen($head)) === 0) {
            return true;
        }
    }
    $data = pluginNoticeLoad();
    $now = (int)Timestamp::getUNIXtime();
    $changed = false;
    foreach ($map as $key => $item) {
        if (!array_key_exists($key, $data['s'])) {
            pluginNoticeAdd($data, $item[0], $item[1], $now);
            $changed = true;
        }
    }
    if (!$changed && $data['s'] === $state) {
        return true;
    }
    $data['s'] = $state;
    pluginNoticeTrim($data);
    return pluginNoticeSave($data);
}

function pluginNoticeJustDisabled($name = null) {
    // 이번 요청에서 자동으로 끈 플러그인(deactivatePlugin 은 $activePlugins 에서 빼지 않는다)
    static $names = array();
    if ($name !== null) {
        $names[(string)$name] = true;
    }
    return $names;
}

function pluginNoticeRecordDisable($name, $reason) {
    pluginNoticeJustDisabled($name);
    $data = pluginNoticeLoad();
    pluginNoticeAdd($data, $name, $reason, (int)Timestamp::getUNIXtime());
    pluginNoticeTrim($data);
    return pluginNoticeSave($data);
}

function pluginNoticeAck($key) {
    $key = (string)$key;
    $data = pluginNoticeLoad();
    $changed = false;
    foreach ($data['n'] as $i => $item) {
        if ($item['u'] === 1 && ($key === 'all' || $item['k'] === $key)) {
            $data['n'][$i]['u'] = 0;
            $changed = true;
        }
    }
    if (!$changed) {
        return true;
    }
    pluginNoticeTrim($data);
    return pluginNoticeSave($data);
}

function pluginNoticeReasonText($reason) {
    switch ((string)$reason) {
        case 'version':
            return _t('요구 버전이 맞지 않아 자동으로 꺼짐');
        case 'manifest':
            return _t('index.xml 을 읽지 못해 자동으로 꺼짐');
    }
    return pluginGuardReasonText($reason);
}

function pluginNoticeRender($hidden = false) {
    global $pluginGuardBlocked, $activePlugins;
    $data = pluginNoticeLoad();
    if (empty($data['n'])) {
        return '';
    }
    $items = $data['n'];
    usort($items, function ($a, $b) {
        if ($a['t'] !== $b['t']) {
            return ($a['t'] > $b['t']) ? -1 : 1;
        }
        return strcmp($a['k'], $b['k']);
    });
    $blockedNow = pluginNoticeState(pluginNoticeBlockedMap($pluginGuardBlocked));
    $justDisabled = pluginNoticeJustDisabled();
    $activeNow = array();
    if (is_array($activePlugins)) {
        foreach ($activePlugins as $name) {
            $name = (string)$name;
            if (isset($justDisabled[$name])) {
                continue;
            }
            $activeNow[pluginNoticeKey($name, 'version')] = true;
            $activeNow[pluginNoticeKey($name, 'manifest')] = true;
        }
    }
    $unread = 0;
    foreach ($items as $item) {
        $unread += $item['u'];
    }
    $blogURL = (string)Model_Context::getInstance()->getProperty('uri.blog');
    // Textcube-NG: r3-13 대시보드에서는 평소 접어 두고 '플러그인 알림 (n)' 탭으로 연다($hidden 이면 display: none)
    $html = '<div id="plugin-notice-panel" class="main-explain-box"' . ($hidden ? ' style="display: none;"' : '') . '>' . "\n"
        . '<p class="explain"><strong>' . pluginNoticeEscape(_t('플러그인 알림')) . '</strong> '
        . pluginNoticeEscape(_f('플러그인이 막히거나 자동으로 꺼지면 여기에 남깁니다. 확인하지 않은 알림: %1개', $unread)) . ' '
        . '<a href="' . pluginNoticeEscape($blogURL . '/owner/plugin') . '">' . pluginNoticeEscape(_t('플러그인 화면')) . '</a></p>' . "\n"
        . '<table class="data-inbox" cellspacing="0" cellpadding="0">' . "\n"
        . '<thead><tr>';
    foreach (array('플러그인', '사유', '현재 상태', '처음', '최근', '횟수', '확인') as $label) {
        $html .= '<th><span class="text">' . pluginNoticeEscape(_t($label)) . '</span></th>';
    }
    $html .= '</tr></thead>' . "\n" . '<tbody>' . "\n";
    foreach ($items as $item) {
        $reason = $item['r'];
        if ($reason === 'version' || $reason === 'manifest') {
            $status = isset($activeNow[$item['k']]) ? _t('다시 켜짐') : _t('꺼짐');
        } else if (isset($blockedNow[$item['k']])) {
            $status = _t('차단 중');
            if ($reason === 'integrity' || $reason === 'strict') {
                $status .= ' ' . _t('끄고 다시 켜면 풀립니다');
            }
        } else {
            $status = _t('풀림');
        }
        $ack = '';
        if ($item['u'] === 1) {
            $ack = '<a href="#" onclick="ackPluginNotice(\'' . pluginNoticeEscape($item['k']) . '\'); return false;">' . pluginNoticeEscape(_t('확인')) . '</a>';
        }
        $html .= '<tr' . ($item['u'] === 1 ? ' class="active-class"' : '') . '>'
            . '<td>' . pluginNoticeEscape($item['p']) . '</td>'
            . '<td>' . pluginNoticeEscape(pluginNoticeReasonText($reason)) . '</td>'
            . '<td>' . pluginNoticeEscape($status) . '</td>'
            . '<td>' . pluginNoticeEscape(Timestamp::format5((int)$item['t0'])) . '</td>'
            . '<td>' . pluginNoticeEscape(Timestamp::format5((int)$item['t'])) . '</td>'
            . '<td>' . (int)$item['c'] . '</td>'
            . '<td>' . $ack . '</td>'
            . '</tr>' . "\n";
    }
    $html .= '</tbody>' . "\n" . '</table>' . "\n";
    if ($unread > 0) {
        $html .= '<div class="button-box"><input type="button" class="input-button" value="' . pluginNoticeEscape(_t('모두 확인')) . '" onclick="ackPluginNotice(\'all\'); return false;" /></div>' . "\n";
    }
    $html .= '</div>' . "\n";
    return $html;
}

// Textcube-NG: 대시보드 '플러그인 알림 (n)' 탭(r3-13). array(알림 개수, 미확인 개수)
function pluginNoticeSummary() {
    $data = pluginNoticeLoad();
    $total = 0;
    $unread = 0;
    if (!empty($data['n']) && is_array($data['n'])) {
        foreach ($data['n'] as $item) {
            $total++;
            $unread += isset($item['u']) ? (int)$item['u'] : 0;
        }
    }
    return array($total, $unread);
}

// Textcube-NG: 대시보드 탭 줄(ul.tabs-box)에 넣을 '플러그인 알림 (n)' 탭(r3-13). 알림이 없으면 빈 문자열, n 은 미확인 개수.
//   누르면 togglePluginNotice()(dashboard/index.php)가 #plugin-notice-panel 을 펼치거나 접는다. $open 이면 펼친 상태 표시
function pluginNoticeTab($open = false) {
    $summary = pluginNoticeSummary();
    if ($summary[0] === 0) {
        return '';
    }
    $class = 'plugin-notice';
    if ($summary[1] > 0) {
        $class .= ' plugin-notice-unread';
    }
    if ($open) {
        $class .= ' plugin-notice-open';
    }
    return '<li id="plugin-notice-tab" class="' . $class . '"><a href="#plugin-notice-panel" onclick="return togglePluginNotice();">'
        . pluginNoticeEscape(_f('플러그인 알림 (%1)', $summary[1])) . '</a></li>' . "\n";
}

function pluginNoticeCheckup() {
    // checkup 용: plugins.php 가 돌지 않으므로 켜진 플러그인을 DB 에서 읽어 다시 판정하고 알림과 맞춘다
    global $pluginGuardBlocked;
    $blogid = getBlogId();
    if (!is_numeric($blogid)) {
        return array();
    }
    $pool = DBModel::getInstance();
    $pool->reset('Plugins');
    $pool->setQualifier('blogid', 'eq', intval($blogid));
    $names = $pool->getColumn('name');
    if (!is_array($names)) {
        $names = array();
    }
    pluginGuardEvaluate($names);
    $blocked = is_array($pluginGuardBlocked) ? $pluginGuardBlocked : array();
    pluginNoticeSync($blocked);
    return $blocked;
}
?>
