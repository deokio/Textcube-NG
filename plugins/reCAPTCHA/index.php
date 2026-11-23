<?php

function Recaptcha_AddInputValidatorRule($target, $mother) {
	$signed_in = (doesHaveOwnership() || doesHaveMembership());
	if ($mother == 'interface/blog/comment/add/' || $mother == 'interface/blog/comment/comment/') {
		$target['POST']['g-recaptcha-response'] = array('string', 'default' => '', 'mandatory' => !$signed_in);
	}
	return $target;
}

function Recaptcha_Header($target) {
	global $configVal;
	$config = Setting::fetchConfigVal($configVal);
	if (is_null($config) || !isset($config['siteKey'])) return $target;

	$siteKey = htmlspecialchars($config['siteKey'], ENT_QUOTES, 'UTF-8');
	$version = isset($config['version']) ? $config['version'] : 'v2';

	if ($version === 'v3') {
		$target .= <<<EOS
<script type="text/javascript">
function recaptcha_executeV3(entryId) {
	var $ = jQuery;
	grecaptcha.ready(function() {
		grecaptcha.execute('{$siteKey}', {action: 'comment'}).then(function(token) {
			var selector = (entryId != null)
				? 'form[id=entry' + entryId + 'WriteComment]'
				: 'form[id$="WriteComment"], form#guestbookWriteComment';
			$(selector).each(function() {
				var inputEl = $(this).find('input[name="g-recaptcha-response"]');
				if (inputEl.length === 0) {
					inputEl = $('<input type="hidden" name="g-recaptcha-response">');
					$(this).append(inputEl);
				}
				inputEl.val(token);
			});
		});
	});
}
/* v3 토큰 유효시간 2분 — 90초마다 갱신 */
setInterval(function() {
	if (!doesHaveOwnership) recaptcha_executeV3(null);
}, 90000);
</script>
<script src="https://www.google.com/recaptcha/api.js?render={$siteKey}" async defer></script>
EOS;
	} else {
		$target .= <<<EOS
<script type="text/javascript">

var recaptcha_widgets = {};
function recaptcha_addControl(f, entryId) {
	var $ = jQuery;
	var blockId = 'comment_recaptcha_' + entryId;
	var widgetId;
	if ($('#' + blockId).length > 0) {
		if (recaptcha_widgets[entryId] != undefined)
			grecaptcha.reset(recaptcha_widgets[entryId]);
		return;
	}
	$(f).find('textarea').after('<div style="margin: 5pt 0 5pt 0" id="' + blockId + '"></div>');
	widgetId = grecaptcha.render(blockId, {
		'sitekey': '{$siteKey}'
	});
	recaptcha_widgets[entryId] = widgetId;
}

function recaptcha_checkForms() {
	var $ = jQuery;
	var _entryIds = entryIds;
	if ($('#tt-body-guestbook').length > 0) {
		_entryIds = [0];
	}
	$.each(_entryIds, function(idx, entryId) {
		var v = $('#entry' + entryId + 'Comment:visible');
		var f = $('form[id=entry' + entryId + 'WriteComment]');
		if (f.length > 0 && v.length > 0)
			recaptcha_addControl(f, entryId);
	});
}

var recaptcha_waitTrials;
var recaptcha_waitTimer = null;
function recaptcha_waitForElement(selector, cb) {
	var $ = jQuery;
	recaptcha_waitTrials = 0;
	var finder = function() {
		var o = $(selector);
		if (o.length > 0) {
			window.clearInterval(recaptcha_waitTimer);
			recaptcha_waitTimer = null;
			cb(o);
		} else {
			recaptcha_waitTrials++;
			if (recaptcha_waitTrials > 25) {
				window.clearInterval(recaptcha_waitTimer);
				recaptcha_waitTimer = null;
			}
		}
	};
	recaptcha_waitTimer = window.setInterval(finder, 200);
}
</script>
<script src="https://www.google.com/recaptcha/api.js?render=explicit&amp;onload=recaptcha_checkForms" async defer></script>
EOS;
	}
	return $target;
}

function Recaptcha_CCHeader($target) {
	global $configVal;
	$config = Setting::fetchConfigVal($configVal);
	if (is_null($config) || !isset($config['siteKey'])) return $target;

	$siteKey = htmlspecialchars($config['siteKey'], ENT_QUOTES, 'UTF-8');
	$version = isset($config['version']) ? $config['version'] : 'v2';

	if ($version === 'v3') {
		$target .= <<<EOS
<script type="text/javascript">
function recaptcha_init() {
	var $ = jQuery;
	if (!doesHaveOwnership) {
		grecaptcha.ready(function() {
			grecaptcha.execute('{$siteKey}', {action: 'comment'}).then(function(token) {
				var inputEl = $('form').find('input[name="g-recaptcha-response"]');
				if (inputEl.length === 0) {
					inputEl = $('<input type="hidden" name="g-recaptcha-response">');
					$('form').append(inputEl);
				}
				inputEl.val(token);
			});
		});
	}
}
</script>
<script src="https://www.google.com/recaptcha/api.js?render={$siteKey}&amp;onload=recaptcha_init" async defer></script>
EOS;
	} else {
		$target .= <<<EOS
<script type="text/javascript">
var recaptcha_waitTimer = null;
function recaptcha_init() {
	var $ = jQuery;
	if (!doesHaveOwnership) {
		$('form').find('textarea').after('<div style="margin: 5pt 0 5pt 0" id="comment_recaptcha"></div>');
		grecaptcha.render('comment_recaptcha', {
			'sitekey': '{$siteKey}'
		});
		var scope = (window.location !== window.parent.location ? window.parent : window);
		if (scope == window.parent) {
			recaptcha_waitTimer = scope.setInterval(function() {
				var v = $('#comment_recaptcha');
				if (v.length > 0) {
					resizeDialog(0, parseInt(v.outerHeight(true)), true);
					scope.clearInterval(recaptcha_waitTimer);
				}
			}, 200);
		} else {
			recaptcha_waitTimer = window.setInterval(function() {
				var v = $('#comment_recaptcha');
				if (v.length > 0) {
					window.resizeBy(0, v.outerHeight(true));
					window.clearInterval(recaptcha_waitTimer);
				}
			}, 200);
		}
	}
}
</script>
<script src="https://www.google.com/recaptcha/api.js?render=explicit&amp;onload=recaptcha_init" async defer></script>
EOS;
	}
	return $target;
}

function Recaptcha_Footer($target) {
	global $configVal;
	$config = Setting::fetchConfigVal($configVal);
	if (is_null($config) || !isset($config['siteKey'])) return $target;

	$version = isset($config['version']) ? $config['version'] : 'v2';

	if ($version === 'v3') {
		$target .= <<<EOS
<script type="text/javascript">
(function($) {
$(document).ready(function() {
	if (!doesHaveOwnership) {
		recaptcha_executeV3(null);
		$('a[id^=commentCount]').click(function(e) {
			var entryId = $(e.target).attr('id').match(/(\d+)/)[1];
			setTimeout(function() {
				if ($('#entry' + entryId + 'Comment:visible').length > 0)
					recaptcha_executeV3(entryId);
			}, 100);
		});
	}
});
})(jQuery);
</script>
EOS;
	} else {
		$target .= <<<EOS
<script type="text/javascript">
(function($) {
$(document).ready(function() {
	if (!doesHaveOwnership) {
		$('a[id^=commentCount]').click(function(e) {
			var entryId = $(e.target).attr('id').match(/(\d+)/)[1];
			$('#entry' + entryId + 'Comment').empty();
			if ($('#entry' + entryId + 'Comment:visible').length > 0) {
				if (recaptcha_waitTimer != null) {
					window.clearInterval(recaptcha_waitTimer);
					recaptcha_waitTimer = null;
				}
				recaptcha_waitForElement('form[id=entry' + entryId + 'WriteComment]', function(f) {
					recaptcha_addControl(f, entryId);
				});
			} else {
				if (recaptcha_waitTimer != null) {
					window.clearInterval(recaptcha_waitTimer);
					recaptcha_waitTimer = null;
				}
				if (recaptcha_widgets[entryId] != undefined)
					delete recaptcha_widgets[entryId];
			}
		});
	}
});
})(jQuery);
</script>
EOS;
	}
	return $target;
}

function Recaptcha_ConfigHandler($data) {
	$config = Setting::fetchConfigVal($data);
	return true;
}

function Recaptcha_AddingCommentHandler($target, $mother)
{
	global $configVal;
	$config = Setting::fetchConfigVal($configVal);
	if (doesHaveOwnership() || doesHaveMembership()) return true;
	if (!is_null($config) && isset($config['secretKey'])) {
		$recaptchaResponse = isset($_POST['g-recaptcha-response']) ? $_POST['g-recaptcha-response'] : '';
		$version   = isset($config['version']) ? $config['version'] : 'v2';
		$threshold = ($version === 'v3' && isset($config['scoreThreshold']) && is_numeric($config['scoreThreshold']))
			? floatval($config['scoreThreshold'])
			: 0.5;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
			'secret'   => $config['secretKey'],
			'response' => $recaptchaResponse,
			'remoteip' => $_SERVER['REMOTE_ADDR'],
		)));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		$output = curl_exec($ch);
		curl_close($ch);

		if ($output === false) {
			Respond::PrintResult(array('error' => 2, 'description' => 'Cannot connect to the Google reCAPTCHA server.'));
			return false;
		}

		$resp = json_decode($output, true);
		if (!is_array($resp)) {
			Respond::PrintResult(array('error' => 2, 'description' => 'Invalid response from the Google reCAPTCHA server.'));
			return false;
		}

		if ($resp['success'] === true) {
			if ($version === 'v3') {
				$score = isset($resp['score']) ? floatval($resp['score']) : 0.0;
				if ($score < $threshold) {
					return false;
				}
			}
			return true;
		}

		if (!empty($resp['error-codes'])) {
			$err = implode(' ', $resp['error-codes']);
			if (strpos($err, 'missing-input-secret') !== false) {
				Respond::PrintResult(array('error' => 2, 'description' => 'Missing reCAPTCHA secret key!'));
			} elseif (strpos($err, 'missing-input-response') !== false) {
				Respond::PrintResult(array('error' => 2, 'description' => 'Missing reCAPTCHA response!'));
			} elseif (strpos($err, 'invalid-input-secret') !== false) {
				Respond::PrintResult(array('error' => 2, 'description' => 'Invalid reCAPTCHA secret key.'));
			} elseif (strpos($err, 'invalid-input-response') !== false) {
				Respond::PrintResult(array('error' => 2, 'description' => 'Invalid reCAPTCHA response.'));
			} else {
				Respond::PrintResult(array('error' => 2, 'description' => 'reCAPTCHA verification failed.'));
			}
		}

		return false;
	}
	return true;
}

/* vim: set noet ts=4 sts=4 sw=4: */
?>
