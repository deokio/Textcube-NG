/**
 * orphan-check.js
 * 새 게시물 작성 중 parent=0 임시 첨부파일이 있을 때
 * "글 쓰기" 링크 클릭 또는 새 탭에서 글쓰기 페이지 열림을 감지하여 경고 다이얼로그를 표시한다.
 *
 * #tcDialog 대신 전용 div(orphan-warn-dlg)를 사용하여 bPopup 충돌을 방지한다.
 */
(function () {
    'use strict';

    function closeOrphanDlg() {
        var dlg = document.getElementById('orphan-warn-dlg');
        if (dlg && dlg.parentNode) { dlg.parentNode.removeChild(dlg); }
        var ov = document.getElementById('orphan-overlay');
        if (ov && ov.parentNode) { ov.parentNode.removeChild(ov); }
    }

    function makeOverlay() {
        var ov = document.createElement('div');
        ov.id = 'orphan-overlay';
        ov.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:9999;';
        document.body.appendChild(ov);
        return ov;
    }

    function createOrphanDlg() {
        closeOrphanDlg();
        var dlg = document.createElement('div');
        dlg.id = 'orphan-warn-dlg';
        dlg.style.cssText = [
            'display:block',
            'position:fixed',
            'top:50%',
            'left:50%',
            'transform:translate(-50%,-50%)',
            'background:#fff',
            'border:2px solid #aaa',
            'border-radius:4px',
            'z-index:10000',
            'min-width:340px',
            'box-shadow:0 4px 16px rgba(0,0,0,0.35)'
        ].join(';');
        document.body.appendChild(dlg);
        return dlg;
    }

    function deleteOrphansAndNavigate(href) {
        if (typeof blogURL === 'undefined') { location.href = href; return; }
        var xhr = new XMLHttpRequest();
        xhr.open('POST', blogURL + '/owner/entry/attachmulti/orphandelete/', true);
        xhr.onload  = function () { location.href = href; };
        xhr.onerror = function () { location.href = href; };
        xhr.send();
    }

    function saveAndNavigate(href) {
        if (typeof entryManager !== 'undefined' && !entryManager.nowsaving) {
            entryManager.autoSave = true;
            entryManager.saveEntry();
            var waited = 0;
            var poll = setInterval(function () {
                waited += 200;
                if (!entryManager.nowsaving || waited >= 5000) {
                    clearInterval(poll);
                    location.href = href;
                }
            }, 200);
        } else {
            location.href = href;
        }
    }

    /* 같은 창에서 글 쓰기 클릭 시 표시하는 다이얼로그 */
    function showNavigateWarning(href, count) {
        var dlg = createOrphanDlg();

        var canSave = (typeof entryManager !== 'undefined'
            && entryManager.entryId === 0
            && !entryManager.isSaved);

        var btns = '';
        if (canSave) {
            btns += '<button id="orphan-btn-save" style="margin-right:6px;padding:5px 10px;">' + _t('임시저장 후 새 글쓰기') + '</button>';
        }
        btns += '<button id="orphan-btn-continue" style="margin-right:6px;padding:5px 10px;">' + _t('파일 무시하고 계속') + '</button>'
              + '<button id="orphan-btn-cancel" style="padding:5px 10px;">' + _t('취소') + '</button>';

        dlg.innerHTML = '<div style="padding:20px 20px 14px 20px;font-size:13px;">'
            + '<p style="margin:0 0 12px 0;line-height:1.6;">'
            + _t('저장되지 않은 새 게시물에 업로드된 첨부파일이 <strong>%1개</strong> 있습니다.').replace('%1', function() { return count; }) + '<br>'
            + _t('새 글쓰기를 열면 해당 파일들이 새 글에 연결됩니다.') + '</p>'
            + '<div style="text-align:right;">' + btns + '</div></div>';

        makeOverlay();

        if (canSave) {
            document.getElementById('orphan-btn-save').onclick = function () {
                closeOrphanDlg();
                saveAndNavigate(href);
            };
        }
        document.getElementById('orphan-btn-continue').onclick = function () {
            closeOrphanDlg();
            deleteOrphansAndNavigate(href);
        };
        document.getElementById('orphan-btn-cancel').onclick = closeOrphanDlg;
    }

    /* 새 탭에서 글쓰기 페이지 로드 시 표시하는 다이얼로그 */
    function showNewTabWarning(count) {
        var dlg = createOrphanDlg();

        dlg.innerHTML = '<div style="padding:20px 20px 14px 20px;font-size:13px;">'
            + '<p style="margin:0 0 12px 0;line-height:1.6;">'
            + _t('다른 탭/창에서 작성 중인 새 게시물의 첨부파일이 <strong>%1개</strong> 있습니다.').replace('%1', function() { return count; }) + '<br>'
            + _t('이 페이지에서 글을 저장하면 해당 파일들이 이 글에 연결됩니다.') + '<br>'
            + _t('다른 탭에서 먼저 임시저장할 것을 권장합니다.') + '</p>'
            + '<div style="text-align:right;">'
            + '<button id="orphan-newpost-ok" style="padding:5px 10px;">' + _t('확인') + '</button>'
            + '</div></div>';

        makeOverlay();

        document.getElementById('orphan-newpost-ok').onclick = closeOrphanDlg;
    }

    function fetchOrphanCount(callback) {
        if (typeof blogURL === 'undefined') { callback(0); return; }
        var xhr = new XMLHttpRequest();
        xhr.open('GET', blogURL + '/owner/entry/attachmulti/orphancheck/', true);
        xhr.onload = function () {
            try {
                var res = JSON.parse(xhr.responseText);
                callback(res.orphanCount || 0);
            } catch (e) { callback(0); }
        };
        xhr.onerror = function () { callback(0); };
        xhr.send();
    }

    document.addEventListener('DOMContentLoaded', function () {
        /* 1. "글 쓰기" 링크 클릭 인터셉터 */
        var links = document.querySelectorAll('a');
        for (var i = 0; i < links.length; i++) {
            (function (a) {
                if (a.href && a.href.indexOf('/owner/entry/post') !== -1) {
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        var dest = this.href;
                        fetchOrphanCount(function (count) {
                            if (count > 0) {
                                showNavigateWarning(dest, count);
                            } else {
                                location.href = dest;
                            }
                        });
                    });
                }
            })(links[i]);
        }

        /* 2. 새 탭에서 글쓰기 페이지가 열렸을 때 페이지 로드 경고 */
        if (typeof tcIsNewPost !== 'undefined' && tcIsNewPost === true) {
            fetchOrphanCount(function (count) {
                if (count > 0) {
                    showNewTabWarning(count);
                }
            });
        }
    });
}());
