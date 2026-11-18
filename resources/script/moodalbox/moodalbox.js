/******************************************************************/
/*                        MOOdalBox 1.2.1                         */
/* A modal box (inline popup), used to display remote content     */
/* loaded using AJAX, written for the mootools framework          */
/*         by Razvan Brates, razvan [at] e-magine.ro              */
/******************************************************************/
/*               http://www.e-magine.ro/moodalbox                 */
/******************************************************************/
/*                                                                */
/* MIT style license:                                             */
/* http://en.wikipedia.org/wiki/MIT_License                       */
/*                                                                */
/* mootools found at:                                             */
/* http://mootools.net/                                           */
/*                                                                */
/* Original code based on "Slimbox", by Christophe Beyls:         */
/* http://www.digitalia.be/software/slimbox                       */
/******************************************************************/

// Constants defined here can be changed for easy config / translation
// (defined as vars, because of MSIE's lack of support for const)

var _ERROR_MESSAGE = "Oops.. there was a problem with your request.<br /><br />" +
					"Please try again.<br /><br />" +
					"<em>Click anywhere to close.</em>"; // the error message displayed when the request has a problem
var _RESIZE_DURATION 		= 400; 		// Duration of height and width resizing (ms)
var _INITIAL_WIDTH			= 250;		// Initial width of the box (px)
var _INITIAL_HEIGHT			= 250;		// Initial height of the box (px)
var _CONTENTS_WIDTH 		= 500;		// Actual width of the box (px)
var _CONTENTS_HEIGHT		= 400;		// Actual height of the box (px)
var _DEF_CONTENTS_WIDTH		= 500;		// Default width of the box (px) - used for resetting when a different setting was used
var _DEF_CONTENTS_HEIGHT	= 400;		// Default height of the box (px) - used for resetting when a different setting was used
var _ANIMATE_CAPTION		= true;		// Enable/Disable caption animation
var _EVAL_SCRIPTS			= false;	// Option to evaluate scripts in the response text
var _EVAL_RESPONSE			= false;	// Option to evaluate the whole response text

// Textcube-NG: MooTools 1.0 없이 네이티브 DOM 과 jQuery(animate·ajax)로 동작하도록 옮김. 아래 함수는 MOOdalBox 가 쓰던
// MooTools 기능(Element 의 setStyle·setStyles·getStyle, Fx.Style/Fx.Styles 의 set·hide·custom·chain·clearTimer)을 같은 의미로 옮긴 것.
function MOOdalBoxSetStyle(el, p, v) {
	if (p == 'opacity') {
		v = parseFloat(v);
		el.style.visibility = (v == 0) ? 'hidden' : 'visible';
		el.style.opacity = v;
		return;
	}
	if (typeof v == 'number' && p != 'zIndex' && p != 'zoom') v += 'px';
	el.style[p] = v;
}

function MOOdalBoxSetStyles(el, styles) {
	for (var p in styles) MOOdalBoxSetStyle(el, p, styles[p]);
}

function MOOdalBoxGetStyle(el, p) {
	var v = el.style[p];
	if (v) return v;
	if (p == 'opacity') return 1;
	return jQuery(el).css(p);
}

// prop 이 있으면 Fx.Style(속성 하나), null 이면 Fx.Styles({속성: [시작, 끝]}). 진행 중이면 새 custom 은 무시(wait), 끝나면 10ms 뒤 onComplete·chain.
function MOOdalBoxFx(el, prop, duration, onComplete) {
	var anim = null, chains = [];
	var fx = {
		set: function(v) {
			if (prop) MOOdalBoxSetStyle(el, prop, v); else MOOdalBoxSetStyles(el, v);
			return fx;
		},
		hide: function() {
			return fx.set(0);
		},
		chain: function(fn) {
			chains.push(fn);
			return fx;
		},
		custom: function(from, to) {
			if (anim) return fx;
			var map = {}, start = {}, end = {}, p, v, f, t;
			if (prop) map[prop] = [from, to]; else map = from;
			for (p in map) {
				v = map[p];
				if (!v.push) v = [v];
				f = v[0];
				t = v[1];
				if (!t && t != 0) {
					t = f;
					f = MOOdalBoxGetStyle(el, p);
				}
				start[p] = parseFloat(f);
				end[p] = parseFloat(t);
			}
			anim = jQuery(start);
			anim.animate(end, {
				duration: duration,
				easing: 'swing',
				queue: false,
				step: function(now, tween) {
					MOOdalBoxSetStyle(el, tween.prop, now);
				},
				complete: function() {
					anim = null;
					if (onComplete) setTimeout(function() { onComplete(el); }, 10);
					if (chains.length) setTimeout(chains.shift(), 10);
				}
			});
			return fx;
		},
		clearTimer: function() {
			if (anim) {
				anim.stop();
				anim = null;
			}
			return fx;
		}
	};
	return fx;
}

// The MOOdalBox object in its beauty
var MOOdalBox = {

	// init the MOOdalBox
	init: function (options) {

		// init default options
		this.options = jQuery.extend({
			resizeDuration: 	_RESIZE_DURATION,
			initialWidth: 		_INITIAL_WIDTH,
			initialHeight: 		_INITIAL_HEIGHT,
			contentsWidth: 		_CONTENTS_WIDTH,
			contentsHeight: 	_CONTENTS_HEIGHT,
			defContentsWidth: 	_DEF_CONTENTS_WIDTH,
			defContentsHeight: 	_DEF_CONTENTS_HEIGHT,
			animateCaption: 	_ANIMATE_CAPTION,
			evalScripts: 		_EVAL_SCRIPTS,
			evalResponse: 		_EVAL_RESPONSE
		}, jQuery.isPlainObject(options) ? options : {});

		// scan anchors for those opening a MOOdalBox
		this.anchors = [];
		var self = this;
		var links = document.getElementsByTagName('a');
		for (var i = 0; i < links.length; i++) {
			(function(el){
				// we use a regexp to check for links that
				// have a rel attribute starting with "moodalbox"
				if(el.rel && el.href && /^moodalbox/i.test(el.rel)) {
					el.onclick = function() { return self.click(el); };
					self.anchors.push(el);
				}
			})(links[i]);
		}

		// add event listeners
		this.eventKeyDown = this.keyboardListener.bind(this);
		this.eventPosition = this.position.bind(this);

		// init the HTML elements
		// the overlay (clickable to close)
		this.overlay = document.createElement('div');
		this.overlay.id = 'mb_overlay';
		document.body.appendChild(this.overlay);
		// the center element
		this.center = document.createElement('div');
		this.center.id = 'mb_center';
		MOOdalBoxSetStyles(this.center, {width: this.options.initialWidth+'px', height: this.options.initialHeight+'px', marginLeft: '-'+(this.options.initialWidth/2)+'px', display: 'none'});
		document.body.appendChild(this.center);
		// the actual page contents
		this.contents = document.createElement('div');
		this.contents.id = 'mb_contents';
		this.center.appendChild(this.contents);

		// the bottom part (caption / close)
		this.bottom = document.createElement('div');
		this.bottom.id = 'mb_bottom';
		this.bottom.style.display = 'none';
		document.body.appendChild(this.bottom);
		this.closelink = document.createElement('a');
		this.closelink.id = 'mb_close_link';
		this.closelink.setAttribute('href', '#');
		this.bottom.appendChild(this.closelink);
		this.caption = document.createElement('div');
		this.caption.id = 'mb_caption';
		this.bottom.appendChild(this.caption);
		var clear = document.createElement('div');
		clear.style.clear = 'both';
		this.bottom.appendChild(clear);

		this.error = document.createElement('div');
		this.error.id = 'mb_error';
		this.error.innerHTML = _ERROR_MESSAGE;

		// attach the close event to the close button / the overlay
		this.closelink.onclick = this.overlay.onclick = this.close.bind(this);

		// init the effects
		var nextEffect = this.nextEffect.bind(this);
		this.fx = {
			overlay: 	MOOdalBoxFx(this.overlay, 'opacity', 500).hide(),
			resize: 	MOOdalBoxFx(this.center, null, this.options.resizeDuration, nextEffect),
			contents: 	MOOdalBoxFx(this.contents, 'opacity', 500, nextEffect),
			bottom: 	MOOdalBoxFx(this.bottom, null, 400, nextEffect)
		};

		this.ajaxRequest = jQuery.noop;

	},

	click: function(link) {
		return this.open (link.href, link.title, link.rel);
	},

	open: function(sLinkHref, sLinkTitle, sLinkRel) {
		this.href = sLinkHref;
		this.title = sLinkTitle;
		this.rel = sLinkRel;
		this.position();
		this.setup(true);
		this.top = (window.pageYOffset || document.documentElement.scrollTop) + (window.innerHeight / 15);
		MOOdalBoxSetStyles(this.center, {top: this.top+'px', display: ''});
		this.fx.overlay.custom(0.8);
		return this.loadContents(sLinkHref);
	},

	position: function() {
		MOOdalBoxSetStyles(this.overlay, {top: (window.pageYOffset || document.documentElement.scrollTop)+'px', height: window.innerHeight+'px'});
	},

	setup: function(open) {
		var lists = [document.getElementsByTagName('object'), document.getElementsByTagName(window.ActiveXObject ? 'select' : 'embed')];
		for (var l = 0; l < lists.length; l++) {
			for (var i = 0; i < lists[l].length; i++) lists[l][i].style.visibility = open ? 'hidden' : '';
		}
		var fn = open ? 'addEventListener' : 'removeEventListener';
		window[fn]('scroll', this.eventPosition, false);
		window[fn]('resize', this.eventPosition, false);
		document[fn]('keydown', this.eventKeyDown, false);
		this.step = 0;
	},

	loadContents: function() {
		if(this.step) return false;
		this.step = 1;

		// check to see if there are specified dimensions
		// if not, fall back to default values
		var aDim = this.rel.match(/[0-9]+/g);
		this.options.contentsWidth = (aDim && (aDim[0] > 0)) ? aDim[0] : this.options.defContentsWidth;
		this.options.contentsHeight = (aDim && (aDim[1] > 0)) ? aDim[1] : this.options.defContentsHeight;

		MOOdalBoxSetStyles(this.bottom, {opacity: '0', height: '0px', display: 'none'});
		this.center.className = 'mb_loading';

		this.fx.contents.hide();

		// AJAX call here
		var nextEffect = this.nextEffect.bind(this);
		var ajaxFailure = this.ajaxFailure.bind(this);
		var contents = this.contents;
		this.ajaxRequest = jQuery.ajax({
			url: 			this.href,
			type: 			'GET',
			dataType: 		'text',
			headers: 		{'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/javascript, text/html, application/xml, text/xml, */*'},
			success: 		function(text) {
				contents.innerHTML = text;
				setTimeout(nextEffect, 20);
			},
			error: 			ajaxFailure
			});

		return false;
	},

	ajaxFailure: function (){
		this.contents.innerHTML = '';
		this.contents.appendChild(this.error.cloneNode(true));
		this.nextEffect();
		this.center.style.cursor = 'pointer';
		this.bottom.style.cursor = 'pointer';
		this.center.onclick = this.bottom.onclick = this.close.bind(this);
	},

	nextEffect: function() {
		switch(this.step++) {
		case 1:
			// remove previous styling from the elements
			// (e.g. styling applied in case of an error)
			this.center.className = '';
			this.center.style.cursor = 'default';
			this.bottom.style.cursor = 'default';
			this.center.onclick = this.bottom.onclick = null;
			this.caption.innerHTML = this.title;

			MOOdalBoxSetStyles(this.contents, {width: this.options.contentsWidth + "px", height: this.options.contentsHeight + "px"});

			if(this.center.clientHeight != this.contents.offsetHeight) {
				this.fx.resize.custom({height: [this.center.clientHeight, this.contents.offsetHeight]});
				break;
			}
			this.step++;

		case 2:
			if(this.center.clientWidth != this.contents.offsetWidth) {
				this.fx.resize.custom({width: [this.center.clientWidth, this.contents.offsetWidth], marginLeft: [-this.center.clientWidth/2, -this.contents.offsetWidth/2]});
				break;
			}
			this.step++;

		case 3:
			MOOdalBoxSetStyles(this.bottom, {top: (this.top + this.center.clientHeight)+'px', width: this.contents.style.width, marginLeft: this.center.style.marginLeft, display: ''});
			this.fx.contents.custom(0,1);
			break;

		case 4:
			if(this.options.animateCaption) {
				this.fx.bottom.custom({opacity: [0, 1], height: [0, this.bottom.scrollHeight]});
				break;
			}
			MOOdalBoxSetStyles(this.bottom, {opacity: '1', height: this.bottom.scrollHeight+'px'});

		case 5:
			this.step = 0;
		}
	},


	keyboardListener: function(event) {
		// close the MOOdalBox when the user presses CTRL + W, CTRL + X, ESC
		var code = event.which || event.keyCode;
		var key = (code == 27) ? 'esc' : ((code > 111 && code < 124) ? 'f' + (code - 111) : String.fromCharCode(code).toLowerCase());
		if ((event.ctrlKey && key == 'w') || (event.ctrlKey && key == 'x') || (key == 'esc')) {
			this.close();
			event.stopPropagation();
			event.preventDefault();
		}
	},

	close: function() {
		if(this.step < 0) return;
		this.step = -1;
		for(var f in this.fx) this.fx[f].clearTimer();
		this.center.style.display = this.bottom.style.display = 'none';
		this.center.className = 'mb_loading';
		this.fx.overlay.chain(this.setup.bind(this, false)).custom(0);
		return false;
	}

};

// startup
/**
 * workaround for "Operation Aborted" error of IE7 (modified by creorix)
 * http://support.microsoft.com/default.aspx/kb/927917
 */
// Window.onDomReady(MOOdalBox.init.bind(MOOdalBox));
window.addEventListener("load", MOOdalBox.init.bind(MOOdalBox),true);
