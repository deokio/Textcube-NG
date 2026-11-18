/**
 * CodeMirror plugin for Textcube editor
 * Needlworks / TNF (http://www.needlworks.org)
 *
 * Based on tinyMCE codemirror plugin.js, created by Arjan Havorkamp (www.webpower.nl)
 *
 * History
 * -------
 * 2.1.0 (05/10/2015) : add - complete markdown editing support
 * 2.0.0 (05/09/2015) : add - codemirror editor overlay mode
 */

/*jshint unused:false */
/*global tinymce:true */

tinymce.PluginManager.requireLangPack('codemirror', 'en,ko,nl'); // PHP port(Textcube-NG): 있는 언어 파일만(7.x 는 ko_KR 등 코드로 찾음, R-173)

tinymce.PluginManager.add('codemirror', function(editor, url) {
	var t = {}; // PHP port(Textcube-NG): 6.x 이후 플러그인 함수는 new 없이 불리므로 this 대신 객체를 만들어 돌려줌(R-173)
	t.name="CodeMirrorPlugin";
	editor.doesCodeMirrorEditorEnabled = false;
	// PHP port(Textcube-NG): Editor 의 settings 속성 제거(6.8.6 CHANGELOG 749 #TINY-8236) → 사용자 옵션 codemirror 를 등록해 editor.options.get 으로 읽음
	editor.options.register('codemirror', { processor: 'object', default: {} });
	t.showSourceEditorFrame = function () {
		if (editor.doesCodeMirrorEditorEnabled == false) {
			// Insert caret marker
			editor.focus();
			editor.selection.collapse(true);
			editor.selection.setContent('<span class="CmCaReT" style="display:none">&#0;</span>');
			jQuery(".tox-edit-area").hide();
			jQuery(".tox-statusbar").hide();
			jQuery('<iframe />', {
				id: 'codeMirrorEditor',
				name:'codeMirrorEditor',
				src:url + '/source_frame.html',
				width:'100%',
				height:'450px',
				style:'width:100%; height:450px; border: 1px solid #eee; box-shadow:0 0 5px rgba(0,0,0,0.3);'
			}).appendTo('.editorbox-container');
			editor.doesCodeMirrorEditorEnabled = true;
		} else {
			var node = document.getElementById('codeMirrorEditor');
			node.contentWindow.submit();
			node.parentNode.removeChild(node);
			var cmInst = document.getElementById('CodeMirrorInstruction');
			cmInst.parentNode.removeChild(cmInst);
			jQuery(".tox-edit-area").show();
			jQuery(".tox-statusbar").show();
			editor.doesCodeMirrorEditorEnabled = false;
		}
	};
	t.syncToTinyMCE = function () {
		var node = document.getElementById('codeMirrorEditor');
		node.contentWindow.syncEditorContent();
	};
	// Add a button to the button bar
	function toggleEditor() {
		t.showSourceEditorFrame();
	};
	editor.ui.registry.addButton('code', {
		tooltip: 'Source code',
		icon: 'sourcecode',
		onAction: toggleEditor
	});

	// Add a menu item to the tools menu
	editor.ui.registry.addMenuItem('code', {
		icon: 'sourcecode',
		text: 'Source code',
		onAction: toggleEditor
	});
	return t;
});
