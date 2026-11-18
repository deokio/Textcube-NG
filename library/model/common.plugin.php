<?php
/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

global $pluginSetting;
$pluginSetting = array();
// Textcube-NG: 플러그인 안전 검사(PDO 호환성·허용 목록·적재 재검증·파일 무결성)(v3.211)
require_once ROOT . '/library/model/common.plugin.guard.php';

/***** Plugin data manipulation *****/
function clearPluginSettingCache() {
    global $pluginSetting;
    if (!empty($pluginSetting)) {
        $pluginSetting = array();
    }
    $gCacheStorage = globalCacheStorage::getInstance();
    $gCacheStorage->purge();
    $pageCache = pageCache::getInstance();
    $pageCache->reset('PluginSettings');
    $pageCache->purge();
}

function activatePlugin($name) {
    global $database, $activePlugins;
    global $pluginGuardMessage;
    $pluginGuardMessage = ''; // Textcube-NG: 활성화 결과 메시지(native 경고·거부 사유)(v3.211)
    if (in_array($name, $activePlugins)) {
        return true;
    }
    // Textcube-NG: r3-42 켜기 검사는 pluginActivationGate 로 옮겼다(다시 검사 recheckPlugin 과 같이 쓴다)
    $guard = pluginActivationGate($name);
    if (!$guard[0]) {
        $pluginGuardMessage = $guard[1];
        return false;
    }
    $pluginName = $name;
    $pluginOpLock = pluginOpLock(true); // Textcube-NG: r3-42 블로그별 플러그인 작업 잠금(같은 요청 안의 잠금은 겹쳐도 막히지 않는다)
    $name = POD::escapeString(UTF8::lessenAsEncoding($name, 255));
    $result = POD::queryCount("INSERT INTO {$database['prefix']}Plugins VALUES (" . getBlogId() . ", '$name', null)");
    if ($result == 1) {
        $activePlugins[] = $pluginName; // Textcube-NG: r3-30 R-491 같은 요청 안에서 다시 켜기 요청이 오면(plugins.php 자동 켜기 뒤) 이미 켜진 것으로 본다(원본은 같은 키를 다시 INSERT 하다 실패)
        pluginKeptSettingsRestore($pluginName); // Textcube-NG: r3-42 끌 때 보관해 둔 설정을 되돌린다(보관한 것이 없으면 아무 것도 하지 않는다)
        // Textcube-NG: 파일 무결성 기준값(sha256)과 검사 결과를 남기고, native 경고를 활성화 결과로 돌려준다(v3.211)
        $pluginGuardSaved = false;
        pluginGuardBuildRecord($pluginName, Acl::check('group.creators') ? 'creator' : 'user', $pluginGuardSaved);
        $pluginGuardMessage = $guard[1];
        if (!$pluginGuardSaved && pluginGuardStrictEnabled()) {
            // 엄격 모드: 기록을 남기지 못하면 적재 때 막힌다
            $pluginGuardMessage = ltrim($pluginGuardMessage . "\n" . _t('무결성 기록을 저장하지 못했습니다. 엄격 모드에서는 이 플러그인을 불러오지 않습니다. 끄고 다시 켜 보십시오.'), "\n");
        }
    }
    clearPluginSettingCache();
    CacheControl::flushItemsByPlugin($pluginName);
    pluginOpUnlock($pluginOpLock); // Textcube-NG: r3-42
    return ($result == 1);
}

// Textcube-NG: r3-42 $refuseKeptOverflow — true 면 설정 보관 한도를 넘거나 보관 저장이 실패할 때 끄지 않는다(관리 화면 owner/plugin/deactivate 만 true).
// 기본값 false 는 원본과 같이 끈다(자동 끄기 library/plugins.php, 데이터 삭제, 다른 플러그인의 호출)
function deactivatePlugin($name, $refuseKeptOverflow = false) {
    global $database, $activePlugins;
    if (!in_array($name, $activePlugins)) {
        return false;
    }
    $pluginName = $name;
    // Textcube-NG: r3-42 끄기 전에 이 플러그인의 설정을 블로그 설정 pluginSettingsKept 에 보관한다(다시 켜면 activatePlugin 이 되돌린다). 블로그별 잠금 안에서 한다
    $pluginOpLock = pluginOpLock(true);
    $keptStore = pluginKeptSettingsStore($pluginName, $refuseKeptOverflow);
    if ($refuseKeptOverflow && ($keptStore === 'overflow' || $keptStore === 'failed')) {
        pluginOpUnlock($pluginOpLock);
        return false;
    }
    $name = POD::escapeString($name);
    POD::query("DELETE FROM {$database['prefix']}Plugins
			WHERE blogid = " . getBlogId() . "
				AND name = '$name'");
    clearPluginSettingCache();
    CacheControl::flushItemsByPlugin($pluginName);
    pluginGuardRemoveBaseline($pluginName); // Textcube-NG: 파일 무결성 기준값 삭제(다시 켜면 새로 남긴다)(v3.211)
    pluginOpUnlock($pluginOpLock); // Textcube-NG: r3-42
    return true;
}

// Textcube-NG: r3-42 켜기 검사(이름·폴더·index.xml/index.php·요구 판번호·서비스 관리자 전용·PDO 호환성·허용 목록)를 activatePlugin 에서 떼어
// 다시 검사(recheckPlugin)와 같이 쓴다. 돌려주는 값은 pluginGuardCheckActivation 과 같은 array(통과 여부, 거부 사유 또는 경고)
function pluginActivationGate($name) {
    if (!preg_match('/^[-a-zA-Z0-9_ ]+$/', $name)) {
        return array(false, '');
    }
    if (!is_dir(ROOT . "/plugins/$name")) {
        return array(false, '');
    }
    if (!file_exists(ROOT . "/plugins/$name/index.xml") || !file_exists(ROOT . "/plugins/$name/index.php")) {
        return array(false, '');
    }
    $xmls = new XMLStruct();
    $manifest = @file_get_contents(ROOT . "/plugins/$name/index.xml");
    if ($xmls->open($manifest)) {
        list($currentTextcubeVersion) = explode(' ', TEXTCUBE_VERSION, 2);

        $requiredTattertoolsVersion = $xmls->getValue('/plugin/requirements/tattertools');
        $requiredTextcubeVersion = $xmls->getValue('/plugin/requirements/textcube');
        if (is_null($requiredTextcubeVersion) && !is_null($requiredTattertoolsVersion)) {
            $requiredTextcubeVersion = $requiredTattertoolsVersion;
        }
        $requiredMinVersion = $xmls->getValue('/plugin/requirements/textcube/minVersion');
        $requiredMaxVersion = $xmls->getValue('/plugin/requirements/textcube/maxVersion');
        if (!is_null($requiredMinVersion)) {
            if (version_compare($currentTextcubeVersion, $requiredMinVersion) < 0) {
                return array(false, '');
            }
        }
        if (!is_null($requiredMaxVersion)) {
            if (version_compare($currentTextcubeVersion, $requiredMaxVersion) > 0) {
                return array(false, '');
            }
        }
        if (!is_null($requiredTextcubeVersion)) {
            if (version_compare($currentTextcubeVersion, $requiredTextcubeVersion) < 0) {
                return array(false, '');
            }
        }
        /* [보안] 서비스 관리자 전용 플러그인(<privilege>administrator</privilege>)은 서비스 관리자만 켠다 — 목록(owner/plugin)만
           숨기고 activate 요청은 막지 않았다 (v3.151) */
        if ($xmls->getValue('/plugin/requirements/privilege') == 'administrator' && !Acl::check('group.creators')) {
            return array(false, '');
        }
    } else {
        return array(false, '');
    }
    // Textcube-NG: PDO 호환성·허용 목록 검사. PDO 모드에서 직접 SQL·미제공 연결 요소·판정 불가 호출(검토 예외 밖)을 쓰면 거부하고, native 모드는 켜고 경고한다(v3.211)
    return pluginGuardCheckActivation($name);
}

// Textcube-NG: r3-42 플러그인 작업 잠금(블로그별). 켜기·끄기·다시 검사·설정 초기화·보관 설정 읽고 쓰기를 한 번에 하나만 한다.
// cache/pluginlock/<blogid>.lock.cache 에 flock(blog.skin.php 의 편집기 CSS 잠금과 같은 모양: mkdir 0700, fopen 'c', chmod 0600).
// 같은 요청 안에서는 다시 잠가도 막히지 않는다(깊이 계산). $wait 가 false 면 기다리지 않는다.
// 돌려주는 값: true(잠금) / false(다른 요청이 잠그고 있음) / null(잠글 수 없음 — 캐시 폴더 없음·열기 실패 등, 잠그지 않고 진행)
function pluginOpLock($wait = true) {
    global $pluginOpLockHandle, $pluginOpLockDepth;
    if (is_resource($pluginOpLockHandle)) {
        $pluginOpLockDepth = intval($pluginOpLockDepth) + 1;
        return true;
    }
    if (!defined('__TEXTCUBE_CACHE_DIR__')) {
        return null;
    }
    $blogid = intval(getBlogId());
    if ($blogid <= 0) {
        return null;
    }
    $dir = __TEXTCUBE_CACHE_DIR__ . '/pluginlock';
    if (!is_dir($dir) && !@mkdir($dir, 0700) && !is_dir($dir)) {
        return null;
    }
    $lockPath = $dir . '/' . $blogid . '.lock.cache';
    $lock = @fopen($lockPath, 'c');
    if ($lock === false) {
        return null;
    }
    @chmod($lockPath, 0600);	// 다른 캐시 파일과 같은 권한(실패는 무시)
    $wouldBlock = 0;
    if (!@flock($lock, $wait ? LOCK_EX : (LOCK_EX | LOCK_NB), $wouldBlock)) {
        fclose($lock);
        return (!$wait && $wouldBlock) ? false : null;
    }
    $pluginOpLockHandle = $lock;
    $pluginOpLockDepth = 1;
    return true;
}

// Textcube-NG: r3-42 pluginOpLock 이 true 를 돌려준 만큼 푼다. 가장 바깥에서 풀 때 flock 을 풀고 닫는다(요청이 끝나면 PHP 가 닫으므로 그때도 풀린다)
function pluginOpUnlock($token) {
    global $pluginOpLockHandle, $pluginOpLockDepth;
    if ($token !== true || !is_resource($pluginOpLockHandle)) {
        return;
    }
    $pluginOpLockDepth = intval($pluginOpLockDepth) - 1;
    if ($pluginOpLockDepth > 0) {
        return;
    }
    @flock($pluginOpLockHandle, LOCK_UN);
    fclose($pluginOpLockHandle);
    $pluginOpLockHandle = null;
    $pluginOpLockDepth = 0;
}

// Textcube-NG: r3-42 다른 플러그인 작업이 처리 중일 때의 응답 메시지(owner/plugin/activate·deactivate·recheck·resetSetting)
function pluginOpBusyMessage() {
    return _t('다른 플러그인 작업을 처리하고 있습니다. 잠시 뒤 다시 시도하십시오.');
}

// Textcube-NG: r3-42 잠근 뒤 켜진 플러그인 목록을 DB 에서 다시 읽는다(library/plugins.php 와 같은 질의). 요청 처음에 읽은 목록은
// 앞선 요청이 끝나기 전에 읽었을 수 있다. 같은 요청의 SELECT 결과 캐시(POD)를 먼저 비운다
function pluginOpRefreshActive() {
    global $activePlugins;
    POD::clearCache();
    $pool = DBModel::getInstance();
    $pool->reset('Plugins');
    $pool->setQualifier('blogid', 'eq', getBlogId());
    $names = $pool->getColumn('name');
    $activePlugins = is_array($names) ? $names : array();
}

// Textcube-NG: r3-42 끌 때 보관한 설정 묶음(블로그 설정 pluginSettingsKept, serialize 한 [플러그인 이름 => Plugins.settings 값])의 크기 한도(바이트).
// BlogSettings.value 가 MySQL TEXT(65535 바이트)이므로 그보다 작게 둔다. 넘으면 그 플러그인의 설정은 보관하지 않는다(원래처럼 지워짐)
function pluginKeptSettingsMaxBytes() {
    return 65000;
}

// Textcube-NG: r3-42 보관 설정 묶음을 DB 에서 바로 읽는다(메모리·캐시 묶음은 다른 요청이 바꾼 값을 모를 수 있다).
// 클래스는 풀지 않고(allowed_classes false), 배열이 아니면 빈 묶음, 이름이 올바르지 않거나 값이 문자열이 아닌 항목은 버린다
function pluginKeptSettingsLoad() {
    POD::clearCache();
    $raw = Setting::getBlogSettingGlobal('pluginSettingsKept', null, null, true);
    if (!is_string($raw) || $raw === '') {
        return array();
    }
    $map = @unserialize($raw, array('allowed_classes' => false));
    if (!is_array($map)) {
        return array();
    }
    $result = array();
    foreach ($map as $key => $value) {
        $key = (string)$key;
        if (pluginGuardNameIsValid($key) && is_string($value)) {
            $result[$key] = $value;
        }
    }
    return $result;
}

// Textcube-NG: r3-42 보관 설정 묶음을 쓴다. 빈 묶음이면 설정 행을 지운다. 묵은 메모리 묶음 때문에 INSERT 로 가서 실패하지 않게
// pluginGuardSetBlogValue·pluginGuardRemoveBaseline 과 같이 자리값을 두어 덮어쓰기(replace)·삭제 경로로 보낸다
function pluginKeptSettingsSave($map) {
    global $__gCacheBlogSettings;
    POD::clearCache();
    if (empty($map)) {
        $blogid = getBlogId();
        if (!is_numeric($blogid)) {
            return false;
        }
        pluginGuardCacheStorage();
        if (!is_array(Setting::getBlogSettingsGlobal($blogid))) {
            return false;
        }
        if (isset($__gCacheBlogSettings[$blogid]) && is_array($__gCacheBlogSettings[$blogid]) && !array_key_exists('pluginSettingsKept', $__gCacheBlogSettings[$blogid])) {
            $__gCacheBlogSettings[$blogid]['pluginSettingsKept'] = '';
        }
        $result = Setting::removeBlogSettingGlobal('pluginSettingsKept', $blogid);
        pluginGuardSyncBlogCache($blogid);
        return ($result !== false && $result !== null);
    }
    $value = serialize($map);
    if (strlen($value) > pluginKeptSettingsMaxBytes()) {
        return false;
    }
    return pluginGuardSetBlogValue('pluginSettingsKept', $value);
}

// Textcube-NG: r3-42 관리 화면 끄기를 거절할 때의 안내(owner/plugin/deactivate). $reason 은 $pluginKeptRefusal 값('overflow' 보관 한도 초과, 'failed' 보관 저장 실패)
function pluginKeptRefusalMessage($reason) {
    if ($reason === 'overflow') {
        return _t('설정을 보관할 공간이 부족해 끌 수 없습니다. 설정을 초기화한 뒤 끄십시오.');
    }
    return _t('설정을 보관하지 못해 끄지 않았습니다. 잠시 뒤 다시 시도하십시오.');
}

// Textcube-NG: r3-42 끄기 전에 이 플러그인의 Plugins.settings 를 보관 묶음에 넣는다. 지금 설정이 없으면(NULL·빈 값) 이미 보관한 항목을 그대로 두고
// 한도와 무관하게 끈다(보관 항목을 지우는 것은 [초기화]·[보관 설정 지우기]·데이터 삭제뿐이다).
// $refuse 가 true 일 때(관리 화면의 끄기) 한도를 넘으면 'overflow', 보관 저장이 실패하면 'failed' 를 돌려주고 $pluginKeptRefusal 에 같은 값을 두어 끄기를 거절하게 한다.
// false 일 때(자동 끄기 library/plugins.php·데이터 삭제·다른 호출) 한도를 넘으면 보관하지 않고, 저장이 실패해도 원본처럼 설정을 지우며 끈다
function pluginKeptSettingsStore($name, $refuse = false) {
    global $pluginKeptRefusal;
    $pluginKeptRefusal = '';
    if (!pluginGuardNameIsValid($name)) {
        return false;
    }
    $lock = pluginOpLock(true);
    POD::clearCache();
    $pool = DBModel::getInstance();
    $pool->reset('Plugins');
    $pool->setQualifier('blogid', 'eq', getBlogId());
    $pool->setQualifier('name', 'eq', UTF8::lessenAsEncoding($name, 255), true);
    $settings = $pool->getCell('settings');
    $map = pluginKeptSettingsLoad();
    $before = $map;
    if (is_string($settings) && $settings !== '') {
        $map[$name] = $settings;
        if (strlen(serialize($map)) > pluginKeptSettingsMaxBytes()) {
            if ($refuse) {
                $pluginKeptRefusal = 'overflow';
                pluginOpUnlock($lock);
                return 'overflow';
            }
            unset($map[$name]);
        }
    }
    $result = ($map === $before) ? true : pluginKeptSettingsSave($map);
    if ($refuse && !$result) {
        $pluginKeptRefusal = 'failed';
        $result = 'failed';
    }
    pluginOpUnlock($lock);
    return $result;
}

// Textcube-NG: r3-42 다시 켠 뒤(INSERT 성공) 보관해 둔 설정을 Plugins.settings 로 되돌리고 묶음에서 뺀다. 보관한 것이 없으면 아무 것도 하지 않는다
function pluginKeptSettingsRestore($name) {
    if (!pluginGuardNameIsValid($name)) {
        return false;
    }
    $lock = pluginOpLock(true);
    $map = pluginKeptSettingsLoad();
    $result = true;
    if (isset($map[$name])) {
        $pool = DBModel::getInstance();
        $pool->reset('Plugins');
        $pool->setQualifier('blogid', 'eq', getBlogId());
        $pool->setQualifier('name', 'eq', UTF8::lessenAsEncoding($name, 255), true);
        $pool->setAttribute('settings', $map[$name], true);
        if ($pool->update()) {
            unset($map[$name]);
            $result = pluginKeptSettingsSave($map);
        } else {
            $result = false;
        }
    }
    pluginOpUnlock($lock);
    return $result;
}

// Textcube-NG: r3-42 이 플러그인의 보관 설정을 지운다(꺼진 플러그인의 [보관 설정 지우기], 플러그인 데이터 삭제). 없으면 그대로 성공
function pluginKeptSettingsRemove($name) {
    if (!is_string($name) || $name === '') {
        return false;
    }
    $lock = pluginOpLock(true);
    $map = pluginKeptSettingsLoad();
    $result = true;
    if (array_key_exists($name, $map)) {
        unset($map[$name]);
        $result = pluginKeptSettingsSave($map);
    }
    pluginOpUnlock($lock);
    return $result;
}

// Textcube-NG: r3-42 이 블로그의 보관 설정을 모두 지운다(데이터 모두 지우기 — DataMaintenance::removeAll 이 Plugins 행을 모두 지울 때)
function pluginKeptSettingsClear() {
    $lock = pluginOpLock(true);
    $result = pluginKeptSettingsSave(array());
    pluginOpUnlock($lock);
    return $result;
}

// Textcube-NG: r3-42 다시 검사(켜진 플러그인만). 끄지 않고 켜기와 같은 검사(pluginActivationGate)를 거친 뒤, 무결성 기준값을 지우고 지금 파일로 새로 남긴다.
// Plugins 행·설정은 건드리지 않는다. 결과 메시지(native 경고·거부 사유·엄격 모드 기록 실패)는 activatePlugin 과 같이 $pluginGuardMessage 에 둔다
function recheckPlugin($name) {
    global $activePlugins;
    global $pluginGuardMessage;
    $pluginGuardMessage = '';
    if (!is_array($activePlugins) || !in_array($name, $activePlugins)) {
        return false;
    }
    $guard = pluginActivationGate($name);
    if (!$guard[0]) {
        $pluginGuardMessage = $guard[1];
        return false;
    }
    $lock = pluginOpLock(true);
    pluginGuardRemoveBaseline($name);
    $pluginGuardSaved = false;
    pluginGuardBuildRecord($name, Acl::check('group.creators') ? 'creator' : 'user', $pluginGuardSaved);
    $pluginGuardMessage = $guard[1];
    if (!$pluginGuardSaved && pluginGuardStrictEnabled()) {
        // 엄격 모드: 기록을 남기지 못하면 적재 때 막힌다(activatePlugin 과 같은 안내)
        $pluginGuardMessage = ltrim($pluginGuardMessage . "\n" . _t('무결성 기록을 저장하지 못했습니다. 엄격 모드에서는 이 플러그인을 불러오지 않습니다. 끄고 다시 켜 보십시오.'), "\n");
    }
    clearPluginSettingCache();
    CacheControl::flushItemsByPlugin($name);
    pluginOpUnlock($lock);
    return true;
}

// Textcube-NG: r3-42 설정 초기화. scope 'active' — 켜진 플러그인의 Plugins.settings 를 NULL 로(기본값) 하고 보관 항목도 지운다. scope 'kept' — 꺼진 플러그인의 보관 설정을 지운다.
// 범위와 지금 상태가 맞지 않으면(예: 오래된 화면의 [보관 설정 지우기]를 켜진 플러그인에 누름) 아무 것도 하지 않고 실패한다
function resetPluginConfig($name, $scope) {
    global $activePlugins;
    if (!pluginGuardNameIsValid($name)) {
        return false;
    }
    $active = is_array($activePlugins) && in_array($name, $activePlugins);
    $lock = pluginOpLock(true);
    $result = false;
    if ($scope === 'active' && $active) {
        $pool = DBModel::getInstance();
        $pool->reset('Plugins');
        $pool->setQualifier('blogid', 'eq', getBlogId());
        $pool->setQualifier('name', 'eq', UTF8::lessenAsEncoding($name, 255), true);
        $pool->setAttribute('settings', null);
        $result = $pool->update() ? true : false;
        if ($result) {
            $result = pluginKeptSettingsRemove($name); // 끌 때 지금 설정이 없으면 보관 항목을 남겨 두므로 초기화는 보관 항목도 지운다
        }
        clearPluginSettingCache();
        CacheControl::flushItemsByPlugin($name);
    } else if ($scope === 'kept' && !$active) {
        $result = pluginKeptSettingsRemove($name);
    }
    pluginOpUnlock($lock);
    return $result;
}

function getCurrentSetting($name) {
    global $database, $activePlugins;
    global $pluginSetting;
    if (!in_array($name, $activePlugins)) {
        return false;
    }
    if (empty($pluginSetting)) {
        $settings = POD::queryAllWithCache("SELECT name, settings
				FROM {$database['prefix']}Plugins 
				WHERE blogid = " . getBlogId(), 'num');
        foreach ($settings as $k => $v) {
            $pluginSetting[$v[0]] = $v[1];
        }
    }
    if (isset($pluginSetting[$name])) {
        return $pluginSetting[$name];
    }
    return null;
}

function isActivePlugin($name) {
    global $activePlugins;
    return in_array($name, $activePlugins);
}

function updatePluginConfig($name, $setVal) {
    global $activePlugins;
    if (!in_array($name, $activePlugins)) {
        return false;
    }
    $pluginName = $name;
    $name = UTF8::lessenAsEncoding($name, 255);
    $setting = serialize(Setting::fetchConfigXML($setVal));
    $pool = DBModel::getInstance();
    $pool->reset('Plugins');
    $pool->setQualifier('blogid', 'eq', getBlogId());
    $pool->setQualifier('name', 'eq', $name, true);
    $pool->setAttribute('settings', $setting, true);

    if ($pool->update()) {
        $result = '0';
    } else {
        $result = '1';
    }
    clearPluginSettingCache();
    CacheControl::flushItemsByPlugin($pluginName);
    return $result;
}

function getPluginInformation($plugin) {
    $xmls = new XMLStruct();
    // Error checking routine
    if (!preg_match('@^[A-Za-z0-9 _-]+$@', $plugin)) {
        return false;
    }
    if (!file_exists(ROOT . "/plugins/$plugin/index.xml")) {
        return false;
    }
    if (!$xmls->open(file_get_contents(ROOT . "/plugins/$plugin/index.xml"))) {
        error_log("PLUGIN XML_PARSE_ERROR: " . $plugin . ": " .
            xml_error_string($xmls->error['code']));
        return false;
    } else {
        // Determine plugin scopes.
        $scopeByXMLPath = array(
            'admin' => '/plugin/binding/adminMenu',
            'blog' => '/plugin/binding/tag',
            'center' => '/plugin/binding/center',
            'coverpage' => '/plugin/binding/coverpage',
            'global' => '/plugin/binding/listener',
            'sidebar' => '/plugin/binding/sidebar',
            'editor' => '/plugin/binding/editor',
            'formatter' => '/plugin/binding/formatter'
        );
        $pluginScope = array();
        $scopeCount = 0;
        foreach ($scopeByXMLPath as $key => $value) {
            if ($xmls->doesExist($value)) {
                array_push($pluginScope, $key);
                $scopeCount = $scopeCount + 1;
            }
        }
        if ($scopeCount == 0) {
            array_push($pluginScope, 'none');
        }
        // load plugin information.
        $maxVersion = max($xmls->getValue('/plugin/requirements/tattertools'), $xmls->getValue('/plugin/requirements/textcube'));
        $requiredVersion = empty($maxVersion) ? 0 : $maxVersion;

        $pluginInformation = array(
            'link' => $xmls->getValue('/plugin/link[lang()]'),
            'title' => $xmls->getValue('/plugin/title[lang()]'),
            'version' => $xmls->getValue('/plugin/version[lang()]'),
            'requirements' => $requiredVersion,
            'scope' => $pluginScope,
            'description' => $xmls->getValue('/plugin/description[lang()]'),
            'authorLink' => $xmls->getAttribute('/plugin/author[lang()]', 'link'),
            'author' => $xmls->getValue('/plugin/author[lang()]'),
            'config' => $xmls->doesExist('/plugin/binding/config'),
            'directory' => trim($plugin),
            'width' => $xmls->getAttribute('/plugin/binding/config/window', 'width'),
            'height' => $xmls->getAttribute('/plugin/binding/config/window', 'height'),
            'privilege' => $xmls->getValue('/plugin/requirements/privilege')
        );
        return $pluginInformation;
    }
    return null;
}

// Textcube-NG: $indexes 는 plugins.php 가 읽은 <index> 값들 — 처음 만들 때와 version 이 바뀐 UpdateDB 뒤에 색인을 만든다(createPluginTableIndexes)(v3.210)
function treatPluginTable($plugin, $name, $fields, $keys, $version, $indexes = array()) {
    global $database;
    if (doesExistTable($database['prefix'] . $name)) {
        $keyname = 'Database_' . $name;
        $value = $plugin;
        $result = getServiceSetting($keyname, null);
        if (is_null($result)) {
            $keyname = UTF8::lessenAsEncoding($keyname, 32);
            $value = UTF8::lessenAsEncoding($plugin . '/' . $version, 255);
            $query = DBModel::getInstance();
            $query->reset('ServiceSettings');
            $query->setAttribute('name', $keyname, true);
            $query->setAttribute('value', $value, true);
            $query->insert();
        } else {
            $keyname = UTF8::lessenAsEncoding($keyname, 32);
            $value = UTF8::lessenAsEncoding($plugin . '/' . $version, 255);
            $values = explode('/', $result, 2);
            if (strcmp($plugin, $values[0]) != 0) { // diff plugin
                return false; // nothing can be done
            } else {
                if (strcmp($version, $values[1]) != 0) {
                    $query = DBModel::getInstance();
                    $query->reset('ServiceSettings');
                    $query->setQualifier('name', 'equals', $keyname, true);
                    $query->setAttribute('value', $value, true);
                    $query->update();
                    $eventName = 'UpdateDB_' . $name;
                    fireEvent($eventName, $values[1]);
                    // Textcube-NG: 플러그인의 UpdateDB 처리(열 추가 등) 뒤, 같은 이름의 색인이 없을 때만 만든다(v3.210)
                    createPluginTableIndexes($name, $fields, $indexes, true);
                }
            }
        }
        return true;
    } else {
        // Textcube-NG: PostgreSQL·SQLite3·Cubrid 는 DBModel::create() 와 같은 꼴로 만든다 — 아래 원본 CREATE 문(int(11)·AUTO_INCREMENT·KEY()·DEFAULT CHARSET)은 MySQL 전용이라 그 밖에서는 문법 오류였다(v3.210)
        if (!in_array(POD::dbms(), array('MySQL', 'MySQLi'))) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                return false;
            }
            $reserved = POD::reservedFieldNames();
            if (!is_array($reserved)) {
                $reserved = array();
            }
            $columns = array('blogid ' . POD::fieldType('int') . ' DEFAULT 0 NOT NULL');
            $isaiExists = false;
            foreach ($fields as $field) {
                $attribute = strtolower($field['attribute']);
                $type = POD::fieldType($attribute);
                if (is_null($type) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field['name'])) {
                    return false;
                }
                // Textcube-NG: 자동 증가는 MySQL 분기와 같이 'int' 이고 autoincrement 인 첫 필드만 — PostgreSQL 은 SERIAL, Cubrid 는 AUTO_INCREMENT(둘 다 DEFAULT 없이 NOT NULL).
                // SQLite3 의 AUTOINCREMENT 는 단일 INTEGER PRIMARY KEY 에만 붙으므로 그 열을 기본 키로 하고, (blogid, 키…)는 아래에서 UNIQUE 제약으로 둔다 — 번호는 블로그와 관계없이 표 전체에서 유일하다(v3.210)
                $ai = ($attribute == 'int' && $field['autoincrement'] == 1 && !$isaiExists);
                if ($ai) {
                    if (POD::dbms() == 'PostgreSQL') {
                        $type = 'SERIAL';
                    } else if (POD::dbms() == 'Cubrid') {
                        $type .= ' AUTO_INCREMENT';
                    } else if (POD::dbms() == 'SQLite3') {
                        $type = 'INTEGER PRIMARY KEY AUTOINCREMENT';
                    } else {
                        return false;
                    }
                    $isaiExists = true;
                }
                if ($field['length'] >= 0 && in_array($attribute, array('varchar', 'vartext'))) {
                    $type .= '(' . intval($field['length']) . ')';
                }
                $column = (in_array($field['name'], $reserved) ? '"' . $field['name'] . '"' : $field['name']) . ' ' . $type;
                if (!$ai && !is_null($field['default'])) {
                    if (in_array($attribute, array('integer', 'int', 'timestamp', 'float', 'double'))) {
                        if (!is_numeric($field['default'])) {
                            return false;
                        }
                        $column .= ' DEFAULT ' . ($field['default'] + 0);
                    } else {
                        $column .= " DEFAULT '" . POD::escapeString($field['default']) . "'";
                    }
                }
                $column .= ($ai || $field['isnull'] == 0 || in_array($field['name'], $keys)) ? ' NOT NULL' : ' NULL';
                array_push($columns, $column);
            }
            $primaryKeys = array('blogid');
            foreach ($keys as $key) {
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                    return false;
                }
                array_push($primaryKeys, in_array($key, $reserved) ? '"' . $key . '"' : $key);
            }
            // Textcube-NG: SQLite3 에서 자동 증가 열이 기본 키가 되었으면 (blogid, 키…)는 UNIQUE 제약 — 키 열은 모두 NOT NULL 이라 같은 묶음의 중복은 기본 키일 때처럼 거부된다(v3.210)
            $keyConstraint = (POD::dbms() == 'SQLite3' && $isaiExists) ? 'UNIQUE' : 'PRIMARY KEY';
            $query = "CREATE TABLE {$database['prefix']}{$name} (" . implode(', ', $columns) . ", {$keyConstraint} (" . implode(',', $primaryKeys) . "))";
        } else {
            $query = "CREATE TABLE {$database['prefix']}{$name} (blogid int(11) NOT NULL default 0,";
            $isaiExists = false;
            $index = '';
            foreach ($fields as $field) {
                $ai = '';
                if (strtolower($field['attribute']) == 'int' || strtolower($field['attribute']) == 'mediumint') {
                    if ($field['autoincrement'] == 1 && !$isaiExists) {
                        $ai = ' AUTO_INCREMENT ';
                        $isaiExists = true;
                        if (!in_array($field['name'], $keys)) {
                            $index = ", KEY({$field['name']})";
                        }
                    }
                }
                $isNull = ($field['isnull'] == 0) ? ' NOT NULL ' : ' NULL ';
                $noDefaultTypes = ['text','blob','longtext','mediumtext','tinytext','longblob','mediumblob','tinyblob','json'];
                $hasDefault = !is_null($field['default']) && !in_array(strtolower($field['attribute']), $noDefaultTypes);
                $defaultValue = $hasDefault ? " DEFAULT '" . POD::escapeString($field['default']) . "' " : '';
                $fieldLength = ($field['length'] >= 0) ? "(" . $field['length'] . ")" : '';
                $sentence = $field['name'] . " " . $field['attribute'] . $fieldLength . $isNull . $defaultValue . $ai . ",";
                $query .= $sentence;
            }

            array_unshift($keys, 'blogid');
            $query .= " PRIMARY KEY (" . implode(',', $keys) . ")";
            $query .= $index;
            $query .= ") ";
            $query .= (POD::charset() == 'utf8') ? 'DEFAULT CHARSET=utf8' : '';
        }
        if (POD::execute($query)) {
            // Textcube-NG: 등록은 첫 분기와 같은 DBModel 꼴(VALUES·escape=true·Cubrid "value") — 원본 INSERT … SET 은 MySQL 전용. 만든 뒤 테이블 목록을 다시 읽고 색인을 만든다(v3.210)
            $keyname = UTF8::lessenAsEncoding('Database_' . $name, 32);
            $value = UTF8::lessenAsEncoding($plugin . '/' . $version, 255);
            $query = DBModel::getInstance();
            $query->reset('ServiceSettings');
            $query->setAttribute('name', $keyname, true);
            $query->setAttribute('value', $value, true);
            $query->insert();
            doesExistTable($database['prefix'] . $name, true);
            createPluginTableIndexes($name, $fields, $indexes, false);
            return true;
        } else {
            return false;
        }

    }
    return true;
}

// Textcube-NG: index.xml <storage><table><index>열,…</index>(여러 개 가능)를 색인 정의로 바꾼다(v3.210). 값은 SQL 에 그대로 넣지 않는다 —
// 열은 그 표의 <field> 이름(영숫자·밑줄)과 정확히 같은 것만 받고, 정의 안 된 이름·blogid·중복·빈 값이 하나라도 있으면 그 색인을 버린다. blogid 를 맨 앞에 붙인다.
// 이름은 {접두어}{표 이름}_{열…}_idx(resources/setup/initialize.*.sql 의 색인 이름 관례), _idx 까지 63바이트(PostgreSQL 한도, MySQL 64)를 넘으면 버린다. 반환 array(색인 이름 => array('blogid', 열…))
function getPluginTableIndexes($name, $fields, $indexes) {
    global $database;
    $result = array();
    if (!is_array($fields) || !is_array($indexes) || !is_string($name) || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        return $result;
    }
    $defined = array();
    foreach ($fields as $field) {
        if (isset($field['name']) && is_string($field['name']) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $field['name'])) {
            array_push($defined, $field['name']);
        }
    }
    foreach ($indexes as $index) {
        if (!is_string($index)) {
            continue;
        }
        $columns = array();
        $seen = array();
        foreach (explode(',', $index) as $column) {
            $column = trim($column);
            if (!in_array($column, $defined, true) || strtolower($column) == 'blogid' || in_array(strtolower($column), $seen, true)) {
                $columns = array();
                break;
            }
            array_push($columns, $column);
            array_push($seen, strtolower($column));
        }
        if (empty($columns)) {
            continue;
        }
        $indexName = $database['prefix'] . $name . '_' . implode('_', $columns) . '_idx';
        if (strlen($indexName) > 63 || !preg_match('/^[A-Za-z0-9_]+$/', $indexName) || array_key_exists($indexName, $result)) {
            continue;
        }
        $result[$indexName] = array_merge(array('blogid'), $columns);
    }
    return $result;
}

// Textcube-NG: 색인 $indexName 의 열 이름들(없으면 빈 배열, 조회 실패면 null) — interface/blog/checkup.php(v3.185)와 같은 DBMS별 조회(v3.210)
function getPluginTableIndexColumns($table, $indexName) {
    $columns = null;
    switch (POD::dbms()) {
        case 'MySQL':
        case 'MySQLi':
            $rows = POD::queryAll("SHOW INDEX FROM $table WHERE Key_name = '" . POD::escapeString($indexName) . "'", 'assoc');
            if (is_array($rows)) {
                $columns = array();
                foreach ($rows as $row) {
                    array_push($columns, $row['Column_name']);
                }
            }
            break;
        case 'PostgreSQL':
            $columns = POD::queryColumn("SELECT a.attname FROM pg_index i JOIN pg_class c ON c.oid = i.indexrelid"
                . " JOIN pg_namespace n ON n.oid = c.relnamespace JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)"
                . " WHERE n.nspname = current_schema() AND c.relname = '" . POD::escapeString(strtolower($indexName)) . "'", false);
            break;
        case 'SQLite3':
            $rows = POD::queryAll("PRAGMA index_info('" . POD::escapeString($indexName) . "')", 'assoc');
            if (is_array($rows)) {
                $columns = array();
                foreach ($rows as $row) {
                    array_push($columns, $row['name']);
                }
            }
            break;
        case 'Cubrid':
            $columns = POD::queryColumn("SELECT key_attr_name FROM db_index_key WHERE index_name = '" . POD::escapeString(strtolower($indexName))
                . "' AND class_name = '" . POD::escapeString(strtolower($table)) . "'", false);
            break;
    }
    return $columns;
}

// Textcube-NG: getPluginTableIndexes() 의 색인을 CREATE INDEX 이름 ON 테이블 (열…)(4 DBMS 공통 문법)로 만든다(v3.210). $onlyMissing 이면
// getPluginTableIndexColumns() 로 같은 이름의 색인이 있는지 먼저 보고 없을 때(빈 배열)만 만든다 — 조회에 실패(null)하면 건너뛴다.
// 예약어 열(Cubrid)은 테이블을 만들 때처럼 "로 감싼다. 색인은 최적화라 만들지 못해도 테이블 처리 결과는 바꾸지 않는다.
function createPluginTableIndexes($name, $fields, $indexes, $onlyMissing) {
    global $database;
    $table = $database['prefix'] . $name;
    $reserved = POD::reservedFieldNames();
    if (!is_array($reserved)) {
        $reserved = array();
    }
    foreach (getPluginTableIndexes($name, $fields, $indexes) as $indexName => $columns) {
        if ($onlyMissing) {
            $existing = getPluginTableIndexColumns($table, $indexName);
            if (!is_array($existing) || !empty($existing)) {
                continue;
            }
        }
        foreach ($columns as $i => $column) {
            if (in_array($column, $reserved)) {
                $columns[$i] = '"' . $column . '"';
            }
        }
        POD::execute("CREATE INDEX {$indexName} ON {$table} (" . implode(', ', $columns) . ")");
    }
    return true;
}

function clearPluginTable($name) {
    global $database;
    $name = POD::escapeString($name);
    $count = POD::queryCount("DELETE FROM {$database['prefix']}{$name} WHERE blogid = " . getBlogId());
    return ($count == 1);
}

function deletePluginTable($name) {
    global $database;
    if (getBlogId() !== 0) {
        return false;
    }
    // Textcube-NG: 이름은 getPluginTableIndexes·treatPluginTable·getPluginTableList 와 같이 영숫자·밑줄만 받고, 아니면 SQL 없이 false — 식별자는 바인딩할 수 없고
    // escapeString 은 따옴표만 다뤄, 'DROP TABLE' 로 바꾼 뒤 'PTY; DROP TABLE <접두어>Sentinel2' 같은 이름이 PostgreSQL·Cubrid·SQLite3 에서 둘째 문장까지 실행됐다(v3.210)
    if (!is_string($name) || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        return false;
    }
    $name = POD::escapeString($name);
    // Textcube-NG: 원본은 'DROP 이름'(TABLE 없음)으로 Cubrid 밖에서는 문법 오류였고 결과와 관계없이 true 를 돌려줬다(v3.210)
    $result = POD::execute("DROP TABLE {$database['prefix']}{$name}");
    if ($result) {
        doesExistTable($database['prefix'] . $name, true);
    }
    return $result;
}

// Textcube-NG: 같은 데이터베이스를 쓰는 다른 설치의 코어 테이블로 보이는 이름이면 true — '{접두어}<단어>_<코어 테이블 이름>'(예: tc_ 설치에서 tc_b2_Entries).
// 코어 이름은 getDefinedTableNames()(common.setting)에서 접두어를 뗀 것, <단어>는 영숫자·밑줄 1자 이상. 플러그인 데이터 관리 화면은 이런 테이블을
// '다른 설치로 보임'으로 표시만 하고, 삭제(/control/plugin/table/delete)는 같은 판정으로 거부한다(v3.210)
function isOtherInstallTableName($table, $caseInsensitive) {
    global $database;
    requireModel('common.setting');
    $prefix = $database['prefix'];
    $table = (string)$table;
    if (strncmp($table, $prefix, strlen($prefix)) != 0) {
        return false;
    }
    $suffix = (string)substr($table, strlen($prefix));
    foreach (getDefinedTableNames() as $definedTable) {
        $core = (string)substr($definedTable, strlen($prefix));
        if ($core !== '' && preg_match('/^[A-Za-z0-9_]+_' . preg_quote($core, '/') . '$/' . ($caseInsensitive ? 'i' : ''), $suffix)) {
            return true;
        }
    }
    return false;
}

// Textcube-NG: 플러그인이 만든 테이블 목록 — 서비스 관리 '플러그인 데이터 관리'(/control/plugin/table)의 목록과 삭제가 같은 기준을 쓴다(v3.210).
// 반환 plugins['플러그인/버전'] = array('plugin', 'version', 'settings' => 등록 이름들, 'tables' => 실제 테이블 이름들, 'blogs' => 사용 블로그 수),
// unknown = 등록과 이어지지 않은 실제 테이블 이름들, others = 다른 설치로 보이는 테이블 이름들(isOtherInstallTableName, 등록과 잇기 전에 뺀다),
// caseInsensitive = 테이블 이름 대소문자 무시 여부. 테이블은 접두어로 시작하고 나머지가 영숫자·밑줄이며 코어 테이블(getDefinedTableNames)이 아닌 것만.
// 등록 이름은 treatPluginTable 과 같이 UTF8::lessenAsEncoding('Database_' . 이름, 32)로 잇는다(잘린 이름도 이어진다).
function getPluginTableList() {
    global $database;
    requireModel('common.setting');
    $prefix = $database['prefix'];
    if (in_array(POD::dbms(), array('MySQL', 'MySQLi'))) {
        $result = POD::queryRow("SHOW VARIABLES LIKE 'lower_case_table_names'");
        $dbCaseInsensitive = (is_array($result) && isset($result['Value']) && $result['Value'] == 1);
    } else {
        $dbCaseInsensitive = true;
    }
    $definedTables = getDefinedTableNames();
    if ($dbCaseInsensitive) {
        $definedTables = array_map('strtolower', $definedTables);
    }
    $tables = array();
    $others = array();
    foreach (POD::tableList($prefix) as $table) {
        $table = (string)$table;
        if (strncmp($table, $prefix, strlen($prefix)) != 0) {
            continue;
        }
        $suffix = (string)substr($table, strlen($prefix));
        if (!preg_match('/^[A-Za-z0-9_]+$/', $suffix)) {
            continue;
        }
        if (in_array($dbCaseInsensitive ? strtolower($table) : $table, $definedTables)) {
            continue;
        }
        if (isOtherInstallTableName($table, $dbCaseInsensitive)) {
            array_push($others, $table);
            continue;
        }
        $settingName = UTF8::lessenAsEncoding('Database_' . $suffix, 32);
        array_push($tables, array($table, $dbCaseInsensitive ? strtolower($settingName) : $settingName));
    }

    $blogs = array();
    $query = DBModel::getInstance();
    $query->reset('Plugins');
    $rows = $query->getAll('name');
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $blogs[$row['name']] = isset($blogs[$row['name']]) ? $blogs[$row['name']] + 1 : 1;
        }
    }

    $plugins = array();
    $query->reset('ServiceSettings');
    $query->setQualifier('name', 'like', 'Database', true);
    $rows = $query->getAll('name,value');
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (strncmp($row['name'], 'Database_', 9) != 0) {
                continue;
            }
            $values = explode('/', $row['value'], 2);
            $version = isset($values[1]) ? $values[1] : '';
            $id = $values[0] . '/' . $version;
            if (!array_key_exists($id, $plugins)) {
                $plugins[$id] = array('plugin' => $values[0], 'version' => $version, 'settings' => array(), 'tables' => array(),
                    'blogs' => isset($blogs[$values[0]]) ? $blogs[$values[0]] : 0);
            }
            array_push($plugins[$id]['settings'], $row['name']);
            $settingName = $dbCaseInsensitive ? strtolower($row['name']) : $row['name'];
            foreach ($tables as $index => $table) {
                if ($table[1] === $settingName) {
                    array_push($plugins[$id]['tables'], $table[0]);
                    unset($tables[$index]);
                }
            }
        }
    }
    $unknown = array();
    foreach ($tables as $table) {
        array_push($unknown, $table[0]);
    }
    return array('plugins' => $plugins, 'unknown' => $unknown, 'others' => $others, 'caseInsensitive' => $dbCaseInsensitive);
}

function getPluginTableName() {
    requireModel('common.setting');

    global $database;

    // Textcube-NG: tableList() 는 접두어를 글자 그대로 앞에서 비교한다(어댑터 모두 strpos() === 0). 원본은 LIKE 용으로 escape 한 접두어(tc\_)를 넘겨 '_'·'%' 가 든 접두어(기본 tc_)에서 플러그인 테이블이 하나도 맞지 않았다(r3-15)
    $dbtables = POD::tableList($database['prefix']);

    $dbCaseInsensitive = getServiceSetting('lowercaseTableNames');
    if ($dbCaseInsensitive === null) {
        $result = POD::queryRow("SHOW VARIABLES LIKE 'lower_case_table_names'");
        $dbCaseInsensitive = ($result['Value'] == 1) ? 1 : 0;
        setServiceSetting('lowercaseTableNames', $dbCaseInsensitive);
    }

    $definedTables = getDefinedTableNames();

    $dbtables = array_values(array_diff($dbtables, $definedTables));
    if ($dbCaseInsensitive == 1) {
        $tempTables = $definedTables;
        $definedTables = array();
        foreach ($tempTables as $table) {
            $table = strtolower($table);
            array_push($definedTables, $table);
        }
        $tempTables = $dbtables;
        $dbtables = array();
        foreach ($tempTables as $table) {
            $table = strtolower($table);
            array_push($dbtables, $table);
        }
        $dbtables = array_values(array_diff($dbtables, $definedTables));
    }
    return $dbtables;
}


/***** Events and configuration handles *****/

function eventExists($event) {
    global $eventMappings;
    return isset($eventMappings[$event]);
}

function fireEvent($event, $target = null, $mother = null, $condition = true) {
    global $service, $eventMappings, $pluginURL, $pluginPath, $pluginName, $configMappings, $configVal;
    $context = Model_Context::getInstance();
    if (!$condition) {
        return $target;
    }
    if (!isset($eventMappings[$event])) {
        return $target;
    }
    foreach ($eventMappings[$event] as $mapping) {
        // Textcube-NG: include 직전 재검증(이름 규칙·활성 목록·허용 목록·권한·PDO·파일 무결성·엄격 모드)(v3.211)
        if (!pluginGuardIsLoadable($mapping['plugin'])) {
            continue;
        }
        include_once(ROOT . "/plugins/{$mapping['plugin']}/index.php");
        if (function_exists($mapping['listener'])) {
            if (!empty($configMappings[$mapping['plugin']]['config'])) {
                $configVal = getCurrentSetting($mapping['plugin']);
                $context->setProperty('plugin.config',Setting::fetchConfigVal($configVal));
            } else {
                $configVal = null;
                $context->setProperty('plugin.config',array());
            }
            $context->setProperty('plugin.uri', $context->getProperty('service.path') . "/plugins/{$mapping['plugin']}");
            $context->setProperty('plugin.path', ROOT . "/plugins/{$mapping['plugin']}");
            $context->setProperty('plugin.name', $mapping['plugin']);
            $pluginURL = $context->getProperty('plugin.uri'); // Legacy plugin support.
            $pluginPath = $context->getProperty('plugin.path');
            $pluginName = $context->getProperty('plugin.name');
            // Loading locale resource
            $languageDomain = null;
            if (is_dir($pluginPath . '/locale/')) {
                $locale = Locales::getInstance();
                $languageDomain = $locale->domain;
                // Textcube-NG: setDirectory() 로 바꾼 directory 도 domain 과 함께 되돌린다(common.module.php v3.188 과 같음)(r3-5)
                $languageDirectory = $locale->directory;

                // Event listener language setting as scope defined.
                if (strpos($event, "/plugin/") === 0) {
                    if ($mapping['scope'] == 'owner') {
                        $language = $context->getProperty('blog.language') !== null ? $context->getProperty('blog.language') : $context->getProperty('service.language');
                    } else {
                        $language = $context->getProperty('blog.blogLanguage') !== null ? $context->getProperty('blog.blogLanguage') : $context->getProperty('service.language');
                    }
                } else {
                    $language = $locale->defaultLanguage;
                }

                if (file_exists($pluginPath . '/locale/' . $language . '.php')) {
                    $locale->setDirectory($pluginPath . '/locale');
                    $locale->set($language, $mapping['plugin']);
                    $locale->domain = $mapping['plugin'];
                }
            }
            $target = call_user_func($mapping['listener'], $target, $mother);
            /// unload.
            if (!is_null($languageDomain)) {
                $locale->domain = $languageDomain;
                $locale->directory = $languageDirectory;
            }
            $pluginURL = $pluginPath = $pluginName = "";
            $context->unsetProperty('plugin.uri');
            $context->unsetProperty('plugin.path');
            $context->unsetProperty('plugin.name');
            $context->unsetProperty('plugin.config');
        }
    }
    return $target;
}

function handleTags(& $content) {
    global $tagMappings, $pluginURL, $pluginPath, $pluginName, $configMappings, $configVal;
    $context = Model_Context::getInstance();
    if (preg_match_all('/\[##_(\w+)_##\]/', $content, $matches)) {
        foreach ($matches[1] as $tag) {
            if (!isset($tagMappings[$tag])) {
                continue;
            }
            $target = '';
            foreach ($tagMappings[$tag] as $mapping) {
                // Textcube-NG: include 직전 재검증(v3.211)
                if (!pluginGuardIsLoadable($mapping['plugin'])) {
                    continue;
                }
                include_once(ROOT . "/plugins/{$mapping['plugin']}/index.php");
                if (function_exists($mapping['handler'])) {
                    if (!empty($configMappings[$mapping['plugin']]['config'])) {
                        $configVal = getCurrentSetting($mapping['plugin']);
                        $context->setProperty('plugin.config',Setting::fetchConfigVal($configVal));
                    } else {
                        $configVal = '';
                        $context->setProperty('plugin.config',array());
                    }
                    $context->setProperty('plugin.uri', $context->getProperty('service.path') . "/plugins/{$mapping['plugin']}");
                    $context->setProperty('plugin.path', ROOT . "/plugins/{$mapping['plugin']}");
                    $context->setProperty('plugin.name', $mapping['plugin']);
                    $pluginURL = $context->getProperty('plugin.uri'); // Legacy plugin support.
                    $pluginPath = $context->getProperty('plugin.path');
                    $pluginName = $context->getProperty('plugin.name');
                    // Loading locale resource
                    $languageDomain = null;
                    if (is_dir($pluginPath . '/locale/')) {
                        $locale = Locales::getInstance();
                        $languageDomain = $locale->domain;
                        // Textcube-NG: setDirectory() 로 바꾼 directory 도 domain 과 함께 되돌린다(common.module.php v3.188 과 같음)(r3-5)
                        $languageDirectory = $locale->directory;
                        if (file_exists($pluginPath . '/locale/' . $locale->defaultLanguage . '.php')) {
                            $locale->setDirectory($pluginPath . '/locale');
                            $locale->set($locale->defaultLanguage, $mapping['plugin']);
                            $locale->domain = $mapping['plugin'];
                        }
                    }
                    $target = call_user_func($mapping['handler'], $target);
                    if (!is_null($languageDomain)) {
                        $locale->domain = $languageDomain;
                        $locale->directory = $languageDirectory;
                    }
                    $pluginURL = $pluginPath = $pluginName = "";
                    $context->unsetProperty('plugin.uri');
                    $context->unsetProperty('plugin.path');
                    $context->unsetProperty('plugin.name');
                    $context->unsetProperty('plugin.config');
                }
            }
            dress($tag, $target, $content);
        }
    }
}

function handleCenters($mapping) {
    global $pluginURL, $pluginPath, $pluginName, $configMappings, $configVal;
    $context = Model_Context::getInstance();

    $target = '';

    // Textcube-NG: include 직전 재검증(v3.211)
    if (!pluginGuardIsLoadable($mapping['plugin'])) {
        return $target;
    }
    include_once(ROOT . "/plugins/{$mapping['plugin']}/index.php");
    if (function_exists($mapping['handler'])) {
        if (!empty($configMappings[$mapping['plugin']]['config'])) {
            $configVal = getCurrentSetting($mapping['plugin']);
            $context->setProperty('plugin.config',Setting::fetchConfigVal($configVal));
        } else {
            $configVal = '';
            $context->setProperty('plugin.config',array());
        }
        $context->setProperty('plugin.uri', $context->getProperty('service.path') . "/plugins/{$mapping['plugin']}");
        $context->setProperty('plugin.path', ROOT . "/plugins/{$mapping['plugin']}");
        $context->setProperty('plugin.name', $mapping['plugin']);
        $pluginURL = $context->getProperty('plugin.uri'); // Legacy plugin support.
        $pluginPath = $context->getProperty('plugin.path');
        $pluginName = $context->getProperty('plugin.name');
        // Loading locale resource
        $languageDomain = null;
        if (is_dir($pluginPath . '/locale/')) {
            $locale = Locales::getInstance();
            $languageDomain = $locale->domain;
            // Textcube-NG: setDirectory() 로 바꾼 directory 도 domain 과 함께 되돌린다(common.module.php v3.188 과 같음)(r3-5)
            $languageDirectory = $locale->directory;
            if (file_exists($pluginPath . '/locale/' . $locale->defaultLanguage . '.php')) {
                $locale->setDirectory($pluginPath . '/locale');
                $locale->set($locale->defaultLanguage, $pluginName);
                $locale->domain = $mapping['plugin'];
            }
        }
        $target = call_user_func($mapping['handler'], $target);
        if (!is_null($languageDomain)) {
            $locale->domain = $languageDomain;
            $locale->directory = $languageDirectory;
        }
        $pluginURL = $pluginPath = $pluginName = "";
        $context->unsetProperty('plugin.uri');
        $context->unsetProperty('plugin.path');
        $context->unsetProperty('plugin.name');
        $context->unsetProperty('plugin.config');
    }

    return $target;
}

// 저장된 사이드바 정렬 순서 정보를 가져온다.
function handleSidebars(& $sval, & $obj, $previewMode) {
    global $service, $pluginURL, $pluginPath, $pluginName, $configVal, $configMappings;
    requireModel('blog.sidebar');
    $newSidebarAllOrders = array();
    // [sidebar id][element id](type, id, parameters)
    // type : 1=skin text, 2=default handler, 3=plug-in
    // id : type1=sidebar i, type2=handler id, type3=plug-in handler name
    // parameters : type1=sidebar j, blah blah~

    $sidebarCount = count($obj->sidebarBasicModules);
    $sidebarAllOrders = getSidebarModuleOrderData($sidebarCount);
    if ($previewMode == true) {
        $sidebarAllOrders = null;
    } else {
        if (is_null($sidebarAllOrders)) {
            $sidebarAllOrders = array();
        }
    }

    for ($i = 0; $i < $sidebarCount; $i++) {
        $str = "";
        if ((!is_null($sidebarAllOrders)) && ((array_key_exists($i, $sidebarAllOrders)))) {
            $currentSidebarOrder = $sidebarAllOrders[$i];
            for ($j = 0; $j < count($currentSidebarOrder); $j++) {
                if ($currentSidebarOrder[$j]['type'] == 1) { // skin text
                    $skini = $currentSidebarOrder[$j]['id'];
                    $skinj = $currentSidebarOrder[$j]['parameters'];
                    if (isset($obj->sidebarBasicModules[$skini]) && isset($obj->sidebarBasicModules[$skini][$skinj])) {
                        $str .= $obj->sidebarBasicModules[$skini][$skinj]['body'];
                    }
                } else {
                    if ($currentSidebarOrder[$j]['type'] == 2) { // default handler
                        // TODO : implement
                    } else {
                        if ($currentSidebarOrder[$j]['type'] == 3) { // plugin
                            $plugin = $currentSidebarOrder[$j]['id']['plugin'];
                            $handler = $currentSidebarOrder[$j]['id']['handler'];
                            include_once(ROOT . "/plugins/{$plugin}/index.php");
                            if (function_exists($handler)) {
                                $str .= "[##_temp_sidebar_element_{$i}_{$j}_##]";
                                $parameter = $currentSidebarOrder[$j]['parameters'];
                                $obj->sidebarStorage["temp_sidebar_element_{$i}_{$j}"]['plugin'] = $plugin;
                                $obj->sidebarStorage["temp_sidebar_element_{$i}_{$j}"]['handler'] = $handler;
                                $obj->sidebarStorage["temp_sidebar_element_{$i}_{$j}"]['parameters'] = $parameter;
                            } else {
                                $obj->sidebarStorage["temp_sidebar_element_{$i}_{$j}"] = "";
                            }
                        } else {
                            // WHAT?
                        }
                    }
                }
            }
        } else {
            $newSidebarAllOrders[$i] = array();

            for ($j = 0; $j < count($obj->sidebarBasicModules[$i]); $j++) {
                $str .= $obj->sidebarBasicModules[$i][$j]['body'];
                array_push($newSidebarAllOrders[$i], array('type' => '1', 'id' => "$i", 'parameters' => "$j"));
            }

            if (!is_null($sidebarAllOrders)) {
                $sidebarAllOrders[$i] = $newSidebarAllOrders[$i];
            }
        }
        dress("sidebar_{$i}", $str, $sval);
    }

    if (count($newSidebarAllOrders) > 0) {
        if (($previewMode == false) && !is_null($sidebarAllOrders)) {
            setBlogSetting("sidebarOrder", serialize($sidebarAllOrders));
            CacheControl::flushSkin();
        }
    }
}

// 저장된 표지 정렬 순서 정보를 가져온다.
function handleCoverpages(& $obj, $previewMode = false) {
    global $service, $pluginURL, $pluginPath, $pluginName, $configVal, $configMappings;
    $context = Model_Context::getInstance();
    requireModel("blog.coverpage");
    // [coverpage id][element id](type, id, parameters)
    // type : 3=plug-in
    // id : type1=coverpage i, type2=handler id, type3=plug-in handler name
    // parameters : type1=coverpage j, blah blah~

    $coverpageAllOrders = getCoverpageModuleOrderData();
    if ($previewMode == true) {
        $coverpageAllOrders = null;
    }

    $i = 0;
    $obj->coverpageModule = array();
    if ((!is_null($coverpageAllOrders)) && ((array_key_exists($i, $coverpageAllOrders)))) {
        $currentCoverpageOrder = $coverpageAllOrders[$i];
        for ($j = 0; $j < count($currentCoverpageOrder); $j++) {
            if ($currentCoverpageOrder[$j]['type'] == 3) { // plugin
                $plugin = $currentCoverpageOrder[$j]['id']['plugin'];
                $handler = $currentCoverpageOrder[$j]['id']['handler'];
                include_once(ROOT . "/plugins/{$plugin}/index.php");
                if (function_exists($handler)) {
                    $obj->coverpageModule[$j] = "[##_temp_coverpage_element_{$i}_{$j}_##]";
                    $parameters = $currentCoverpageOrder[$j]['parameters'];

                    $context->setProperty('plugin.uri', $context->getProperty('service.path') . "/plugins/{$plugin}");
                    $context->setProperty('plugin.path', ROOT . "/plugins/{$plugin}");
                    $context->setProperty('plugin.name', $plugin);
                    $pluginURL = $context->getProperty('plugin.uri'); // Legacy plugin support.
                    $pluginPath = $context->getProperty('plugin.path');
                    $pluginName = $context->getProperty('plugin.name');

                    if (!empty($configMappings[$plugin]['config'])) {
                        $configVal = getCurrentSetting($plugin);
                        $context->setProperty('plugin.config',Setting::fetchConfigVal($configVal));
                    } else {
                        $configVal = '';
                        $context->setProperty('plugin.config',array());
                    }


                    if (function_exists($handler)) {
                        // Loading locale resource
                        $languageDomain = null;
                        if (is_dir($pluginPath . '/locale/')) {
                            $locale = Locales::getInstance();
                            $languageDomain = $locale->domain;
                            // Textcube-NG: setDirectory() 로 바꾼 directory 도 domain 과 함께 되돌린다(common.module.php v3.188 과 같음)(r3-5)
                            $languageDirectory = $locale->directory;
                            if (file_exists($pluginPath . '/locale/' . $locale->defaultLanguage . '.php')) {
                                $locale->setDirectory($pluginPath . '/locale');
                                $locale->set($locale->defaultLanguage, $plugin);
                                $locale->domain = $plugin;
                            }
                        }
                        $obj->coverpageStorage["temp_coverpage_element_{$i}_{$j}"] = call_user_func($handler, $parameters);
                        if (!is_null($languageDomain)) {
                            $locale->domain = $languageDomain;
                            $locale->directory = $languageDirectory;
                        }
                        $pluginURL = $pluginPath = $pluginName = "";
                        $context->unsetProperty('plugin.uri');
                        $context->unsetProperty('plugin.path');
                        $context->unsetProperty('plugin.name');
                        $context->unsetProperty('plugin.config');
                    } else {
                        $obj->coverpageStorage["temp_coverpage_element_{$i}_{$j}"] = "";
                    }
                }
            } else {
                // WHAT?
            }
        }
    }
}

function handleDataSet($plugin, $DATA) {
    global $configMappings, $activePlugins, $service, $pluginURL, $pluginPath, $pluginName, $configMapping, $configVal;
    $context = Model_Context::getInstance();

    $xmls = new XMLStruct();
    if (!$xmls->open($DATA)) {
        unset($xmls);
        return array('error' => '3', 'customError' => '');
    }
    unset($xmls);
    if (!in_array($plugin, $activePlugins)) {
        return array('error' => '9', 'customError' => _f('%1 : 플러그인이 활성화되어 있지 않아 설정을 저장하지 못했습니다.', $plugin));
    }
    $reSetting = true;
    if (!empty($configMappings[$plugin]['dataValHandler'])) {
        $context->setProperty('plugin.uri', $context->getProperty('service.path') . "/plugins/{$plugin}");
        $context->setProperty('plugin.path', ROOT . "/plugins/{$plugin}");
        $context->setProperty('plugin.name', $plugin);
        $pluginURL = $context->getProperty('plugin.uri'); // Legacy plugin support.
        $pluginPath = $context->getProperty('plugin.path');
        $pluginName = $context->getProperty('plugin.name');

        include_once(ROOT . "/plugins/{$plugin}/index.php");
        if (function_exists($configMappings[$plugin]['dataValHandler'])) {
            if (!empty($configMappings[$plugin]['config'])) {
                $configVal = getCurrentSetting($plugin);
                $context->setProperty('plugin.config',Setting::fetchConfigVal($configVal));
            } else {
                $configVal = '';
                $context->setProperty('plugin.config',array());
            }
            // Loading locale resource
            $languageDomain = null;
            if (is_dir($pluginPath . '/locale/')) {
                $locale = Locales::getInstance();
                $languageDomain = $locale->domain;
                // Textcube-NG: setDirectory() 로 바꾼 directory 도 domain 과 함께 되돌린다(common.module.php v3.188 과 같음)(r3-5)
                $languageDirectory = $locale->directory;
                if (file_exists($pluginPath . '/locale/' . $locale->defaultLanguage . '.php')) {
                    $locale->setDirectory($pluginPath . '/locale');
                    $locale->set($locale->defaultLanguage, $pluginName);
                    $locale->domain = $pluginName;
                }
            }

            $reSetting = call_user_func($configMappings[$plugin]['dataValHandler'], serialize(Setting::fetchConfigXML($DATA)));
            $pluginURL = $pluginPath = $pluginName = "";
            $context->unsetProperty('plugin.uri');
            $context->unsetProperty('plugin.path');
            $context->unsetProperty('plugin.name');
            $context->unsetProperty('plugin.config',array());
            if (!is_null($languageDomain)) {
                $locale->domain = $languageDomain;
                $locale->directory = $languageDirectory;
            }
        }
        if (true !== $reSetting) {
            return array('error' => '9', 'customError' => $reSetting);
        }
    }
    $result = updatePluginConfig($plugin, $DATA);
    return array('error' => $result, 'customError' => '');
}

function fetchConfigVal($DATA) {
    return Setting::fetchConfigVal($DATA);
}

function handleConfig($plugin) {
    global $service, $typeSchema, $pluginURL, $pluginPath, $pluginName, $configMappings, $configVal, $adminSkinSetting;
    $context = Model_Context::getInstance();
    $typeSchema = array(
        'text'
    , 'textarea'
    , 'select'
    , 'checkbox'
    , 'radio'
    );
    $manifest = @file_get_contents(ROOT . "/plugins/$plugin/index.xml");
    $xmls = new XMLStruct();
    $CDSPval = '';
    $i = 0;
    $dfVal = Setting::fetchConfigVal(getCurrentSetting($plugin));
    $name = '';
    $clientData = '[';

    $title = $plugin;

    if ($manifest && $xmls->open($manifest)) {
        $title = $xmls->getValue('/plugin/title[lang()]');
        //설정 핸들러가 존재시 바꿈
        $config = $xmls->selectNode('/plugin/binding/config[lang()]');
        unset($xmls);
        if (!empty($config['.attributes']['manifestHandler'])) {
            $handler = $config['.attributes']['manifestHandler'];
            $oldconfig = $config;
            $context->setProperty('plugin.uri', $context->getProperty('service.path') . "/plugins/{$plugin}");
            $context->setProperty('plugin.path', ROOT . "/plugins/{$plugin}");
            $context->setProperty('plugin.name', $plugin);

            $pluginURL = $context->getProperty('plugin.uri'); // Legacy plugin support.
            $pluginPath = $context->getProperty('plugin.path');
            $pluginName = $context->getProperty('plugin.name');

            // Loading locale resource (PHP 7.4: initialize to avoid undefined variable Fatal Error)
            $languageDomain = null;
            if (is_dir($pluginPath . '/locale/')) {
                $locale = Locales::getInstance();
                $languageDomain = $locale->domain;
                // Textcube-NG: setDirectory() 로 바꾼 directory 도 domain 과 함께 되돌린다(common.module.php v3.188 과 같음)(r3-5)
                $languageDirectory = $locale->directory;
                if (file_exists($pluginPath . '/locale/' . $locale->defaultLanguage . '.php')) {
                    $locale->setDirectory($pluginPath . '/locale');
                    $locale->set($locale->defaultLanguage, $pluginName);
                    $locale->domain = $pluginName;
                }
            }

            include_once(ROOT . "/plugins/$plugin/index.php");
            if (function_exists($handler)) {
                if (!empty($configMappings[$plugin]['config'])) {
                    $configVal = getCurrentSetting($plugin);
                    $context->setProperty('plugin.config',Setting::fetchConfigVal($configVal));
                } else {
                    $configVal = '';
                    $context->setProperty('plugin.config',array());
                }


                $manifest = call_user_func($handler, $plugin);
            }
            // Textcube-NG: 플러그인 locale 로드는 function_exists 밖인데 domain 복원은 안에 있어, manifestHandler 함수가 없으면
            // domain 이 플러그인으로 남아 이후 owner 문구가 번역되지 않았다. 복원을 function_exists 블록 바로 뒤로 옮긴다(v3.187)
            if (!is_null($languageDomain)) {
                $locale->domain = $languageDomain;
                $locale->directory = $languageDirectory;
            }
            $newXmls = new XMLStruct();
            if ($newXmls->open($manifest)) {
                unset($config);
                $config = $newXmls->selectNode('/config[lang()]');
            }
            unset($newXmls);
        }
        // Textcube-NG: config 노드를 찾지 못해 $config 가 null 이면 'array offset on null' 경고가 났다. 값은 같다(v3.187)
        if (is_null($config['fieldset'] ?? null)) {
            return array('code' => _t('설정 값이 없습니다.'), 'script' => '[]');
        }
        foreach ($config['fieldset'] as $fieldset) {
            $legend = !empty($fieldset['.attributes']['legend']) ? htmlspecialchars($fieldset['.attributes']['legend']) : '';
            $CDSPval .= CRLF . TAB . "<fieldset>" . CRLF . TAB . TAB . "<legend><span class=\"text\">$legend</span></legend>" . CRLF;
            if (!empty($fieldset['field'])) {
                foreach ($fieldset['field'] as $field) {
                    if (empty($field['.attributes']['name'])) {
                        continue;
                    }
                    $name = $field['.attributes']['name'];
                    $clientData .= getFieldName($field, $name);
                    $CDSPval .= TreatType($field, $dfVal, $name);
                }
            }
            $CDSPval .= TAB . "</fieldset>" . CRLF;
        }
    } else {
        $CDSPval = _t('설정 값이 없습니다.');
    }
    $clientData .= ']';
    return array('code' => $CDSPval, 'script' => $clientData, 'title' => $title);
}

/***** Plugin configuration panel functions *****/

function getFieldName($field, $name) {
    if ('checkbox' != $field['.attributes']['type']) {
        return '"' . $name . '",';
    }
    $tname = '';
    foreach ($field['op'] as $op) {
        if (!empty($op['.attributes']['name'])) {
            $tname .= '"' . $op['.attributes']['name'] . '",';
        }
    }
    return $tname;
}

function TreatType($cmd, $dfVal, $name) {
    global $typeSchema;
    if (empty($cmd['.attributes']['type']) || !in_array($cmd['.attributes']['type'], $typeSchema)) {
        return '';
    }
    if (empty($cmd['.attributes']['title']) || empty($cmd['.attributes']['name'])) {
        return '';
    }
    $titleFw = empty($cmd['.attributes']['titledirection']) ? true : ($cmd['.attributes']['titledirection'] == 'bk' ? false : true);
    $fieldTitle = TAB . TAB . TAB . '<label class="fieldtitle" for="' . $name . '">' . htmlspecialchars($cmd['.attributes']['title']) . '</label>';
    $fieldControl = TAB . TAB . TAB . '<span class="fieldcontrol">' . CRLF . call_user_func($cmd['.attributes']['type'] . 'Treat', $cmd, $dfVal, $name) . TAB . TAB . TAB . '</span>';
    $caption = empty($cmd['caption'][0]) ? '' : TAB . TAB . TAB . '<div class="fieldcaption">' . $cmd['caption'][0]['.value'] . '</div>' . CRLF;
    if ($titleFw) {
        return TAB . TAB . '<div class="field" id="div_' . htmlspecialchars($cmd['.attributes']['name']) . '">' . CRLF . $fieldTitle . CRLF . $fieldControl . CRLF . $caption . TAB . TAB . "</div>\n";
    } else {
        return TAB . TAB . '<div class="field" id="div_' . htmlspecialchars($cmd['.attributes']['name']) . '">' . CRLF . $fieldControl . CRLF . $fieldTitle . CRLF . $caption . TAB . TAB . "</div>\n";
    }
}

function treatDefaultValue($value, $default, $allowZero = true) {
    if ($allowZero) {
        return (!isset($value) || trim($value) == '' ? $default : $value);
    } else {
        return (empty($value) ? $default : $value);
    }
}

function textTreat($cmd, $dfVal, $name) {
    // Textcube-NG: 저장된 설정이 없으면 $dfVal 이 null 이라 'array offset on null' 경고가 났다. 값은 같다(v3.187)
    $dfVal = (!is_null($dfVal[$name] ?? null)) ? $dfVal[$name] : ((!isset($cmd['.attributes']['value']) || (empty($cmd['.attributes']['value']) && $cmd['.attributes']['value'] !== 0)) ? null : $cmd['.attributes']['value']);
    $DSP = TAB . TAB . TAB . TAB . '<input type="text" class="textcontrol" ';
    $DSP .= ' id="' . $name . '" ';
    $DSP .= empty($cmd['.attributes']['size']) ? '' : 'size="' . $cmd['.attributes']['size'] . '"';
    $DSP .= is_null($dfVal) ? '' : 'value="' . htmlspecialchars($dfVal) . '"';
    $DSP .= ' />' . CRLF;
    return $DSP;
}

function textareaTreat($cmd, $dfVal, $name) {
    // Textcube-NG: 저장된 설정이 없으면 $dfVal 이 null 이라 'array offset on null' 경고가 났다. 값은 같다(v3.187)
    $dfVal = (!is_null($dfVal[$name] ?? null)) ? $dfVal[$name] : ((!isset($cmd['.value']) || (empty($cmd['.value']) && $cmd['.value'] !== 0)) ? null : $cmd['.value']);
    $DSP = TAB . TAB . TAB . TAB . '<textarea class="textareacontrol"';
    $DSP .= ' id="' . $name . '"';
    $DSP .= ' rows="' . ((!isset($cmd['.attributes']['rows']) || (empty($cmd['.attributes']['rows']) && $cmd['.attributes']['rows'] !== 0)) ? '2' : $cmd['.attributes']['rows']) . '"';
    $DSP .= ' cols="' . ((!isset($cmd['.attributes']['cols']) || (empty($cmd['.attributes']['cols']) && $cmd['.attributes']['cols'] !== 0)) ? '23' : $cmd['.attributes']['cols']) . '"';
    $DSP .= '>';
    $DSP .= is_null($dfVal) ? '' : htmlspecialchars($dfVal);
    $DSP .= '</textarea>' . CRLF;
    return $DSP;
}

function selectTreat($cmd, $dfVal, $name) {
    $DSP = TAB . TAB . TAB . TAB . '<select id="' . $name . '" class="selectcontrol">' . CRLF;
    $df = ((!isset($dfVal[$name]) || (empty($dfVal[$name]) && $dfVal[$name] != '0')) ? null : $dfVal[$name]);
    foreach ($cmd['op'] as $option) {
        $ov = ((!isset($option['.attributes']['value']) || (empty($option['.attributes']['value']) && $option['.attributes']['value'] != '0')) ? null : $option['.attributes']['value']);
        $oc = ((!isset($option['.attributes']['checked']) || (empty($option['.attributes']['checked']) && $option['.attributes']['checked'] !== 0)) ? null : $option['.attributes']['checked']);

        $DSP .= TAB . TAB . TAB . TAB . TAB . '<option ';
        $DSP .= !is_string($ov) ? '' : 'value="' . htmlspecialchars($ov) . '" ';
        $DSP .= is_string($oc) && 'checked' == $oc && is_null($dfVal) ? 'selected="selected" ' : '';
        $DSP .= is_string($df) && (!is_string($ov) ? false : $ov == $df) ? 'selected="selected" ' : '';
        $DSP .= '>';
        $DSP .= $option['.value'];
        $DSP .= '</option>' . CRLF;
    }
    $DSP .= TAB . TAB . TAB . TAB . '</select>' . CRLF;
    return $DSP;
}

function checkboxTreat($cmd, $dfVal, $name) {
    $DSP = '';
    foreach ($cmd['op'] as $option) {
        if (empty($option['.attributes']['name']) || !is_string($option['.attributes']['name'])) {
            continue;
        }
        $df = ((!isset($dfVal[$option['.attributes']['name']]) || (empty($dfVal[$option['.attributes']['name']]) && $dfVal[$option['.attributes']['name']] !== 0)) ? null : $dfVal[$option['.attributes']['name']]);
        $oc = ((!isset($option['.attributes']['checked']) || (empty($option['.attributes']['checked']) && $option['.attributes']['checked'] !== 0)) ? null : $option['.attributes']['checked']);

        $checked = !is_string($df) ?
            (is_string($oc) && 'checked' == $oc && is_null($dfVal) ? 'checked="checked" ' : '') :
            ('' != $df ? 'checked="checked" ' : '');
        $DSP .= TAB . TAB . TAB . TAB . '<input type="checkbox" class="checkboxcontrol"';
        $DSP .= ' id="' . $option['.attributes']['name'] . '" ';
        $DSP .= !is_string($option['.attributes']['value']) ? '' : 'value="' . htmlspecialchars($option['.attributes']['value']) . '" ';
        $DSP .= $checked;
        $DSP .= ' />';
        $DSP .= "<label class='checkboxlabel' for=\"" . $option['.attributes']['name'] . "\">{$option['.value']}</label>" . CRLF;
    }
    return $DSP;
}

function radioTreat($cmd, $dfVal, $name) {
    $DSP = '';
    $cnt = 0;
    $df = ((!isset($dfVal[$name]) || (empty($dfVal[$name]) && $dfVal[$name] !== 0)) ? null : $dfVal[$name]);
    foreach ($cmd['op'] as $option) {
        $cnt++;
        $DSP .= TAB . TAB . TAB . TAB . '<input type="radio"  class="radiocontrol" ';
        $DSP .= ' name="' . $name . '" id="' . $name . $cnt . '" ';
        $oc = ((!isset($option['.attributes']['checked']) || (empty($option['.attributes']['checked']) && $option['.attributes']['checked'] !== 0)) ? null : $option['.attributes']['checked']);
        $DSP .= !is_string($option['.attributes']['value']) ? '' : 'value="' . htmlspecialchars($option['.attributes']['value']) . '" ';
        $DSP .= is_string($oc) && 'checked' == $oc && is_null($dfVal) ? 'checked="checked" ' : '';
        $DSP .= is_string($df) && (!is_string($option['.attributes']['value']) ? false : $option['.attributes']['value'] == $df) ? 'checked="checked" ' : '';
        $DSP .= ' />';
        $DSP .= "<label class='radiolabel' for='" . $name . $cnt . "'>{$option['.value']}</label >" . CRLF;
    }
    return $DSP;
}

?>
