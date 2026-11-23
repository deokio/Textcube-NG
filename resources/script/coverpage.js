/// Copyright (c) 2004-2016, Needlworks  / Tatter Network Foundation
/// All rights reserved. Licensed under the GPL.
/// See the GNU General Public License for more details. (/documents/LICENSE, /documents/COPYRIGHT)

	// Textcube-NG: Dojo 0.4.3 대신 jQuery UI(draggable·droppable, jquery.ui.essentials)와 <dialog> 로 동작.
	// tcDnD 는 이 파일이 쓰던 dojo.dnd.HtmlDragSource/HtmlDropTarget·dojo.inherits·dojo.lang.extend·dojo.html.* 를 같은 의미로 옮긴 것
	// (놓을 위치 판정·표시선·삽입은 Dojo 0.4.3 HtmlDragAndDrop.js, 놓기 처리 순서는 HtmlDragManager.js 와 같음).
	var tcDnD = (function($) {
		var NORTH = 1, SOUTH = 2, EAST = 4, WEST = 8;
		var state = {dragObject: null, current: null, acceptable: false, lastEvent: null};

		function byId(node) {
			return (typeof node == 'string') ? document.getElementById(node) : node;
		}
		// dojo.lang.inArray 와 같이 == 비교(형식 ["sidebar"] 와 "sidebar" 가 같음)
		function inArray(arr, value) {
			for (var i = 0; i < arr.length; i++) {
				if (arr[i] == value) return true;
			}
			return false;
		}
		function px(node, prop) {
			var v = parseFloat($(node).css(prop));
			return isNaN(v) ? 0 : v;
		}
		function getScroll() {
			var top = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
			var left = window.pageXOffset || document.documentElement.scrollLeft || document.body.scrollLeft || 0;
			return {top: top, left: left, offset: {x: left, y: top}};
		}
		function getViewport() {
			return {width: window.innerWidth, height: window.innerHeight};
		}
		function getBorderBox(node) {
			return {width: node.offsetWidth, height: node.offsetHeight};
		}
		function getMarginBox(node) {
			return {width: node.offsetWidth + px(node, 'margin-left') + px(node, 'margin-right'),
				height: node.offsetHeight + px(node, 'margin-top') + px(node, 'margin-bottom')};
		}
		// dojo.html.getAbsolutePosition(node, true): 문서 기준 content box 위치
		function getAbsolutePosition(node) {
			var o = $(node).offset();
			var x = o.left + px(node, 'border-left-width') + px(node, 'padding-left');
			var y = o.top + px(node, 'border-top-width') + px(node, 'padding-top');
			return {x: x, y: y, left: x, top: y};
		}
		function gravity(node, e) {
			var abs = getAbsolutePosition(node), bb = getBorderBox(node);
			var cx = abs.x + (bb.width / 2), cy = abs.y + (bb.height / 2);
			return ((e.pageX < cx ? WEST : EAST) | (e.pageY < cy ? NORTH : SOUTH));
		}
		function insertBefore(node, ref) {
			if (node === ref || node.nextSibling === ref) return false;
			ref.parentNode.insertBefore(node, ref);
			return true;
		}
		function insertAfter(node, ref) {
			var pn = ref.parentNode;
			if (ref == pn.lastChild) {
				if (node === ref) return false;
				pn.appendChild(node);
				return true;
			}
			return insertBefore(node, ref.nextSibling);
		}
		function removeNode(node) {
			if (node && node.parentNode) return node.parentNode.removeChild(node);
		}
		function hasParent(node) {
			return Boolean(node && node.parentNode && node.parentNode.nodeType == 1);
		}
		function clearSelection() {
			try {
				if (window.getSelection) window.getSelection().removeAllRanges();
			} catch (err) {}
		}
		function inherits(subclass, superclass) {
			subclass.prototype = new superclass();
			subclass.prototype.constructor = subclass;
			subclass.superclass = superclass.prototype;
			subclass['super'] = superclass.prototype;
		}
		function extend(constructor, props) {
			for (var k in props) constructor.prototype[k] = props[k];
			return constructor;
		}
		// dojo.dnd.HtmlDragObject.createDragNode(): 복제본에 dragClass, 불투명도 0.5
		function createDragNode(node, dragClass) {
			var clone = node.cloneNode(true);
			if (dragClass) $(clone).addClass(dragClass);
			clone.style.opacity = 0.5;
			clone.style.zIndex = 999;
			return clone;
		}
		function eventOf(ev, dragObjects) {
			return {pageX: ev.pageX, pageY: ev.pageY, dragObjects: dragObjects};
		}
		// dojo.dnd.HtmlDragManager.onMouseUp(): 놓은 대상의 onDrop(받을 수 없으면 onDragOut), 성공 여부를 돌려줌
		function finishDrop() {
			var t = state.current, o = state.dragObject, ok = state.acceptable, ev = state.lastEvent, ret = null;
			try {
				if (!t || !o) return false;
				t.onDropStart();
				var e = {pageX: ev.pageX, pageY: ev.pageY, dragSource: o.dragSource, dragObject: o};
				var ce = t.domNode.childNodes;
				if (ce.length > 0) {
					e.dropTarget = ce[0];
					while (e.dropTarget == o.domNode) e.dropTarget = e.dropTarget.nextSibling;
				} else {
					e.dropTarget = t.domNode;
				}
				if (ok) {
					ret = t.onDrop(e);
				} else {
					t.onDragOut(e);
				}
				t.onDropEnd();
				return !!(ok && ret);
			} finally {
				state.dragObject = state.current = state.lastEvent = null;
				state.acceptable = false;
			}
		}

		function HtmlDragSource(node, type) {
			node = byId(node);
			this.dragObjects = [];
			this.constrainToContainer = false;
			if (node) {
				this.domNode = node;
				this.dragObject = node;
				this.type = (type) || (this.domNode.nodeName.toLowerCase());
				this.reregister();
			}
		}
		HtmlDragSource.prototype = {
			constructor: HtmlDragSource,
			type: '',
			dragClass: '',
			reregister: function() {
				var src = this;
				$(this.domNode).draggable({
					addClasses: false,
					distance: 8,
					scroll: false,
					appendTo: 'body',
					revertDuration: 300,
					helper: function() {
						state.dragObject = {domNode: src.dragObject, type: src.type, dragSource: src};
						return createDragNode(src.dragObject, src.dragClass);
					},
					start: function(ev) {
						clearSelection();
						state.current = null;
						state.acceptable = false;
						state.lastEvent = ev;
					},
					drag: function(ev) {
						var t = state.current, o = state.dragObject;
						state.lastEvent = ev;
						if (t && state.acceptable && $.ui.intersect($(this).draggable('instance'), $(t.domNode).droppable('instance'), 'pointer', ev)) {
							t.onDragMove(eventOf(ev, [o]), [o]);
						}
					},
					revert: function() {
						return !finishDrop();
					}
				}).data('tcDragSource', this);
			},
			unregister: function() {
				$(this.domNode).draggable('destroy').removeData('tcDragSource');
			}
		};

		function HtmlDropTarget(node, types) {
			if (arguments.length == 0) return;
			this.domNode = byId(node);
			if (types && typeof types == 'string') types = [types];
			this.acceptedTypes = types || [];
			this.register();
		}
		HtmlDropTarget.prototype = {
			constructor: HtmlDropTarget,
			vertical: false,
			accepts: function(dragObjects) {
				if (!inArray(this.acceptedTypes, '*')) {
					for (var i = 0; i < dragObjects.length; i++) {
						if (!inArray(this.acceptedTypes, dragObjects[i].type)) return false;
					}
				}
				return true;
			},
			onDropStart: function() {},
			onDropEnd: function() {},
			onDragOver: function(e) {
				if (!this.accepts(e.dragObjects)) return false;
				this.childBoxes = [];
				for (var i = 0, child; i < this.domNode.childNodes.length; i++) {
					child = this.domNode.childNodes[i];
					if (child.nodeType != 1) continue;
					var pos = getAbsolutePosition(child);
					var inner = getBorderBox(child);
					this.childBoxes.push({top: pos.y, bottom: pos.y + inner.height, left: pos.x, right: pos.x + inner.width, height: inner.height, width: inner.width, node: child});
				}
				return true;
			},
			_getNodeUnderMouse: function(e) {
				for (var i = 0, box; i < this.childBoxes.length; i++) {
					box = this.childBoxes[i];
					if (e.pageX >= box.left && e.pageX <= box.right && e.pageY >= box.top && e.pageY <= box.bottom) return i;
				}
				return -1;
			},
			createDropIndicator: function() {
				this.dropIndicator = document.createElement('div');
				var s = this.dropIndicator.style;
				s.position = 'absolute';
				s.zIndex = 999;
				if (this.vertical) {
					s.borderLeftWidth = '1px';
					s.borderLeftColor = 'black';
					s.borderLeftStyle = 'solid';
					s.height = getBorderBox(this.domNode).height + 'px';
					s.top = getAbsolutePosition(this.domNode).y + 'px';
				} else {
					s.borderTopWidth = '1px';
					s.borderTopColor = 'black';
					s.borderTopStyle = 'solid';
					s.width = getBorderBox(this.domNode).width + 'px';
					s.left = getAbsolutePosition(this.domNode).x + 'px';
				}
			},
			onDragMove: function(e, dragObjects) {
				var i = this._getNodeUnderMouse(e);
				if (!this.dropIndicator) this.createDropIndicator();
				var g = this.vertical ? WEST : NORTH;
				var hide = false, before;
				if (i < 0) {
					if (this.childBoxes.length) {
						before = (gravity(this.childBoxes[0].node, e) & g);
						if (before) hide = true;
					} else {
						before = true;
					}
				} else {
					var child = this.childBoxes[i];
					before = (gravity(child.node, e) & g);
					if (child.node === dragObjects[0].dragSource.domNode) {
						hide = true;
					} else {
						var currentPosChild = before ? (i > 0 ? this.childBoxes[i - 1] : child) : (i < this.childBoxes.length - 1 ? this.childBoxes[i + 1] : child);
						if (currentPosChild.node === dragObjects[0].dragSource.domNode) hide = true;
					}
				}
				if (hide) {
					this.dropIndicator.style.display = 'none';
					return;
				} else {
					this.dropIndicator.style.display = '';
				}
				this.placeIndicator(e, dragObjects, i, before);
				if (!hasParent(this.dropIndicator)) document.body.appendChild(this.dropIndicator);
			},
			placeIndicator: function(e, dragObjects, boxIndex, before) {
				var targetProperty = this.vertical ? 'left' : 'top';
				var child;
				if (boxIndex < 0) {
					if (this.childBoxes.length) {
						child = before ? this.childBoxes[0] : this.childBoxes[this.childBoxes.length - 1];
					} else {
						this.dropIndicator.style[targetProperty] = getAbsolutePosition(this.domNode)[this.vertical ? 'x' : 'y'] + 'px';
					}
				} else {
					child = this.childBoxes[boxIndex];
				}
				if (child) {
					this.dropIndicator.style[targetProperty] = (before ? child[targetProperty] : child[this.vertical ? 'right' : 'bottom']) + 'px';
					if (this.vertical) {
						this.dropIndicator.style.height = child.height + 'px';
						this.dropIndicator.style.top = child.top + 'px';
					} else {
						this.dropIndicator.style.width = child.width + 'px';
						this.dropIndicator.style.left = child.left + 'px';
					}
				}
			},
			onDragOut: function(e) {
				if (this.dropIndicator) {
					removeNode(this.dropIndicator);
					delete this.dropIndicator;
				}
			},
			onDrop: function(e) {
				this.onDragOut(e);
				var i = this._getNodeUnderMouse(e);
				var g = this.vertical ? WEST : NORTH;
				if (i < 0) {
					if (this.childBoxes.length) {
						if (gravity(this.childBoxes[0].node, e) & g) {
							return this.insert(e, this.childBoxes[0].node, 'before');
						} else {
							return this.insert(e, this.childBoxes[this.childBoxes.length - 1].node, 'after');
						}
					}
					return this.insert(e, this.domNode, 'append');
				}
				var child = this.childBoxes[i];
				if (gravity(child.node, e) & g) {
					return this.insert(e, child.node, 'before');
				} else {
					return this.insert(e, child.node, 'after');
				}
			},
			insert: function(e, refNode, position) {
				var node = e.dragObject.domNode;
				if (position == 'before') {
					return insertBefore(node, refNode);
				} else if (position == 'after') {
					return insertAfter(node, refNode);
				} else if (position == 'append') {
					refNode.appendChild(node);
					return true;
				}
				return false;
			},
			register: function() {
				var target = this;
				$(this.domNode).droppable({
					addClasses: false,
					tolerance: 'pointer',
					accept: function(d) {
						var src = $(d).data('tcDragSource');
						return !!src && target.accepts([src]);
					},
					over: function(ev) {
						if (state.current && state.current !== target) state.current.onDragOut(eventOf(ev, [state.dragObject]));
						state.current = target;
						state.lastEvent = ev;
						state.acceptable = target.onDragOver(eventOf(ev, [state.dragObject]));
					},
					out: function(ev) {
						if (state.current === target) {
							target.onDragOut(eventOf(ev, [state.dragObject]));
							state.current = null;
							state.acceptable = false;
						}
					},
					drop: function(ev) {
						state.lastEvent = ev;
					}
				});
			},
			unregister: function() {
				$(this.domNode).droppable('destroy');
			}
		};

		return {
			HtmlDragSource: HtmlDragSource,
			HtmlDropTarget: HtmlDropTarget,
			inherits: inherits,
			extend: extend,
			removeNode: removeNode,
			getScroll: getScroll,
			getViewport: getViewport,
			getMarginBox: getMarginBox
		};
	})(jQuery);


	DragPanel = function(node, type) {
		tcDnD.HtmlDragSource.call(this, node, type);
		this.dragClass = "ajax-floating-panel";
		this.opacity = 0.9;
		
        if (this.domNode.coverpageNumber != null) decorateDragPanel(this.domNode);		
	}
	
	tcDnD.inherits(DragPanel, tcDnD.HtmlDragSource);

	DragPanelAdd = function(node, type) {
		tcDnD.HtmlDragSource.call(this, node, type);
		this.dragClass = "ajax-floating-panel";
		this.opacity = 0.9;
	}
	tcDnD.inherits(DragPanelAdd, tcDnD.HtmlDragSource);

	DropPanel = function(node, type) {
		tcDnD.HtmlDropTarget.call(this, node, type);
	}
	tcDnD.inherits(DropPanel, tcDnD.HtmlDropTarget);
	
	DropDeletePanel = function(node, type) {
		tcDnD.HtmlDropTarget.call(this, node, type);
	}
	tcDnD.inherits(DropDeletePanel, tcDnD.HtmlDropTarget);
	
	var globalChker = true;
	var globalNewNodeCounter = 0;

	tcDnD.extend(DropPanel, {
		onDrop: function(e) {
			if ((e.dragObject.domNode.ajaxtype == 'register') && (e.dragObject.domNode.moduleCategory == 'plugin')) 
			{
				var newNode = document.createElement(e.dragObject.domNode.tagName);
				newNode.id = 'newDragPanel_' + globalNewNodeCounter++;
				newNode.className = 'coverpage-module coverpage-plugin-module';
				newNode.ajaxtype = 'register';
				newNode.moduleCategory = e.dragObject.domNode.moduleCategory;
				newNode.identifier = e.dragObject.domNode.identifier;
				newNode.innerHTML = e.dragObject.domNode.innerHTML;
				newNode.hasPropertyEdit = e.dragObject.domNode.hasPropertyEdit;

				e.dragObject.domNode = newNode;
				
				new DragPanel(newNode, ["coverpage"]);
			}
			if ((e.dragObject.domNode.ajaxtype == 'register') && (e.dragObject.domNode.moduleCategory == 'coverpage_element')) 
			{
				//decorateDragPanel(e.dragObject.domNode);
			}
			this.parentMethod = DropPanel.superclass.onDrop;
			var retVal = this.parentMethod(e);
			delete this.parentMethod;
			
			if ((retVal == true) && (globalChker == true)) {
				var targetCoverpage = this.domNode.coverpage;
				var targetPosition = 0;
				
				var prevNode = e.dragObject.domNode.previousSibling;
				while (prevNode != null) {
					if ((prevNode.nodeType != 3/* TEXT_NODE */) && (prevNode.className.indexOf("coverpage-module") != -1)) break;
					prevNode = prevNode.previousSibling;
				}
				if (prevNode != null) {
					targetPosition = prevNode.modulePos + 1;
				}
				
				if (e.dragObject.domNode.ajaxtype == 'reorder') {
					var sourceCoverpage = e.dragObject.domNode.coverpageNumber;
					var sourcePostion = e.dragObject.domNode.modulePos;
					e.dragObject.domNode.coverpageNumber = targetCoverpage;
				
					var requestURL = blogURL + "/owner/skin/coverpage/order?coverpageNumber=" + sourceCoverpage + "&targetCoverpageNumber=" + targetCoverpage + "&modulePos=" + sourcePostion + "&targetPos=" + targetPosition + viewMode + "&ajaxcall=1";	// Textcube-NG: order 는 링크도 POST 라 XHR 임을 ajaxcall 로 알린다(v3.148)
					
					var request = new HTTPRequest("POST", requestURL);
					request.onSuccess = function () {
					    clearWaitServerResponse();
					}
					request.onError = function () {
						globalChker = false;
					    errorWaitServerResponse();
					}
					// Textcube-NG: onVerify 덮어쓰기(늘 true)를 지움. 응답이 ResultPage XML 이라 기본 onVerify(EAF4.js, /response/error==0)로 실패 응답을 onError 로 보낸다(v3.192)
					request.send();
					waitServerResponse();
				} else if (e.dragObject.domNode.ajaxtype == 'register') {
					e.dragObject.domNode.coverpageNumber = targetCoverpage;
					e.dragObject.domNode.ajaxtype = 'reorder';
					
					var requestURL = blogURL + "/owner/skin/coverpage/register?coverpageNumber=" + targetCoverpage + "&modulePos=" + targetPosition + "&moduleId=" + e.dragObject.domNode.identifier + viewMode + "&ajaxcall=1";	// Textcube-NG: XHR 임을 ajaxcall 로 알려 ResultPage XML 로 답받는다(order 와 같음)(r3-5)

					var request = new HTTPRequest("POST", requestURL);
					request.coverpage = targetCoverpage;
					request.modulepos = targetPosition;
					request.moduleCategory = e.dragObject.domNode.moduleCategory;
					request.onSuccess = function () {
					    clearWaitServerResponse();
						if (this.moduleCategory == 'plugin') previewPlugin(this.coverpage, this.modulepos);
						decorateDragPanel(e.dragObject.domNode);
					}
					request.onError = function () {
						globalChker = false;
					    errorWaitServerResponse();
					}
					// Textcube-NG: onVerify 덮어쓰기(늘 true)를 지움. 응답이 ResultPage XML 이라 기본 onVerify(EAF4.js, /response/error==0)로 실패 응답을 onError 로 보낸다(r3-5)
					request.send();
					waitServerResponse();
				} else {
					alert(e.dragObject.domNode.ajaxtype);
				}
				reordering();
			}
			return retVal;
		},
		
		createDropIndicator: function() {
			this.parentMethod = DropPanel.superclass.createDropIndicator;
			var retVal = this.parentMethod();
			delete this.parentMethod;
			
			with (this.dropIndicator.style) {
				borderTopWidth = "5px";
				borderTopColor = "silver";
				borderTopStyle = "solid";
			};

			return retVal;		
		}
	});

	tcDnD.extend(DropDeletePanel, {
		onDrop: function(e) {
			if (e.dragObject.domNode.ajaxtype == 'register')
			{
		        if(this.dropIndicator) {
			        tcDnD.removeNode(this.dropIndicator);
			        delete this.dropIndicator;
		        }
		        return false;
			}
			
			var sourceCoverpage = e.dragObject.domNode.coverpageNumber;
			var sourcePostion = e.dragObject.domNode.modulePos;

			this.parentMethod = DropPanel.superclass.onDrop;
			var retVal = this.parentMethod(e);
			delete this.parentMethod;
			
			postNavigate(blogURL + "/owner/skin/coverpage/delete?coverpageNumber=" + sourceCoverpage + "&modulePos=" + sourcePostion + viewMode);	// Textcube-NG: 삭제는 POST(v3.148)
			
			return retVal;
		},
		createDropIndicator: function() {
			this.parentMethod = DropPanel.superclass.createDropIndicator;
			var retVal = this.parentMethod();
			delete this.parentMethod;
			
			with (this.dropIndicator.style) {
				borderTopWidth = "5px";
				borderTopColor = "silver";
				borderTopStyle = "solid";
			};

			return retVal;		
		}
	});

	var dlg;
	
	// Textcube-NG: dojo.widget.Dialog 를 상속한 popupWindow 위젯 대신 네이티브 <dialog>(showModal). 바탕 가림막(::backdrop, 검정 0.4)과
	// 모달 동작은 Dialog 와 같고, Dialog 처럼 ESC 로 닫히지 않음. showModal 이 없는 브라우저에서는 display 로 보이고 숨김.
	function PopupWindow() {
		var d = document.createElement('dialog');
		d.setAttribute('data-tc-popup', '');
		this.modal = (typeof d.showModal == 'function');
		d.style.position = 'absolute';
		d.style.zIndex = 999;
		d.style.overflow = 'visible';
		if (this.modal) {
			d.addEventListener('cancel', function(e) { e.preventDefault(); });
			this._onKey = function(e) {
				if (e.key == 'Escape' || e.keyCode == 27) e.preventDefault();
			};
		} else {
			d.style.display = 'none';
		}
		if (!document.getElementById('tc-popup-style')) {
			var st = document.createElement('style');
			st.id = 'tc-popup-style';
			st.appendChild(document.createTextNode('dialog[data-tc-popup]{color:inherit;max-width:none;max-height:none}dialog[data-tc-popup]::backdrop{background-color:rgba(0,0,0,0.4)}'));
			document.getElementsByTagName('head')[0].appendChild(st);
		}
		document.body.appendChild(d);
		this.domNode = this.containerNode = d;
	}
	PopupWindow.prototype = {
		constructor: PopupWindow,
		setContent: function(/*String*/ data) {
			this.domNode.innerHTML = data;
		},
		setCloseControl: function(node) {
			var self = this;
			this.closeNode = (typeof node == 'string') ? document.getElementById(node) : node;
			this.closeNode.addEventListener('click', function() { self.hide(); }, false);
		},
		placeModalDialog: function() {
			    var scroll_offset = tcDnD.getScroll().offset;
			    var viewport_size = tcDnD.getViewport();
    			
			    // find the size of the dialog
			    var mb = tcDnD.getMarginBox(this.containerNode);
			    if (mb.width<200) mb.width = 200;
			    if (mb.height<200) mb.height = 200;
    			
			    var x = scroll_offset.x + (viewport_size.width - mb.width)/2;
			    var y = scroll_offset.y + (viewport_size.height - mb.height)/2;

			    with(this.domNode.style){
				    left = x + "px";
				    top = y + "px";
			    }
		},
		show: function() {
			this.placeModalDialog();
			if (!this.modal) {
				this.domNode.style.display = '';
			} else if (!this.domNode.open) {
				document.addEventListener('keydown', this._onKey, true);
				this.domNode.showModal();
			}
		},
		hide: function() {
			if (!this.modal) {
				this.domNode.style.display = 'none';
			} else {
				document.removeEventListener('keydown', this._onKey, true);
				if (this.domNode.open) this.domNode.close();
			}
		}
	};

	function submitCoverpagePlugin(coverpage, modulepos) {
		var pNode = dlg.domNode.firstChild;
		while (pNode != null) {
			if ((pNode.tagName != null) && (pNode.tagName.toLowerCase() == 'form')) {
				break;
			}
			pNode = pNode.nextSibling;
		}
		if (pNode != null) {
			var requestURL = blogURL + "/owner/skin/coverpage/setPlugin?coverpageNumber=" + coverpage + "&modulePos=" + modulepos + "&ajaxcall=true" + viewMode;
            var postData = "";
			pNode = pNode.firstChild;
			while (pNode != null) {
			    if ((pNode.className != null) && (pNode.className.toLowerCase() == 'field-box')) {
			        pNode = pNode.firstChild;
			        break;
			    }
			    pNode = pNode.nextSibling;
			}
			while (pNode != null) {
				if ((pNode.tagName != null) && (pNode.tagName.toLowerCase() == 'div')) {
					var p2Node = pNode.firstChild;
					while (p2Node != null) {
						if ((p2Node.tagName != null) && (p2Node.tagName.toLowerCase() == 'input') && p2Node.type.toLowerCase() == 'text') {
							requestURL += '&' + encodeURIComponent(p2Node.name) + '=' + encodeURIComponent(p2Node.value);
						} else if ((p2Node.tagName != null) && (p2Node.tagName.toLowerCase() == 'textarea')) {
                            if (postData.length > 0) postData += '&';
                            postData += p2Node.name + '=' + encodeURIComponent(p2Node.value);
                        }
						p2Node = p2Node.nextSibling;
					}
				}
				pNode = pNode.nextSibling;
			}
			var request = new HTTPRequest("POST", requestURL);
			request.coverpage = coverpage;
			request.modulepos = modulepos;
			request.onSuccess = function () {
				previewPlugin(this.coverpage, this.modulepos);
				return true;
			}
			request.onError = function () {
			    errorWaitServerResponse();
				globalChker = false;
			}
			// Textcube-NG: onVerify 덮어쓰기(늘 true)를 지움. 응답이 ResultPage XML 이라 기본 onVerify(EAF4.js, /response/error==0)로 실패 응답을 onError 로 보낸다(r3-5)
			request.send(postData);
		}

		dlg.hide();
	}

	function previewPlugin(coverpage, modulepos) {
		var requestURL = blogURL + "/owner/skin/coverpage/preview?coverpageNumber=" + coverpage + "&modulePos=" + modulepos + previewMode;
		
		var request = new HTTPRequest("GET", requestURL);
		request.coverpage = coverpage;
		request.modulepos = modulepos
		request.onSuccess = function () {
			var pNode = document.getElementById('coverpage-ul-' + this.coverpage);
			if (pNode != null) pNode = pNode.firstChild;
			
			while (pNode != null) {
				if ((pNode.tagName != null) && (pNode.tagName.toLowerCase() == 'li')) {
					if (this.modulepos <= 0) break;
					this.modulepos--;
				}
				pNode = pNode.nextSibling;
			}
			
			if (pNode != null) pNode = pNode.lastChild;
			while (pNode != null) {
				if ((pNode.tagName != null) && (pNode.tagName.toLowerCase() == 'div')) {
					break;
				}
				pNode = pNode.previousSibling;
			}
			if (pNode != null) pNode.innerHTML = this._request.responseText;
		}
		request.onError = function () {
			globalChker = false;
		}
		request.onVerify = function () {
			return true;
		}
		request.send();
	}

	function decorateDragPanel(node) {
		var sourceCoverpage = node.coverpageNumber;
		var sourcePostion = node.modulePos;
		var pNode = node.firstChild;
		while (pNode != null) {
			if ((pNode.tagName != null) && (pNode.tagName.toLowerCase() == 'h4')) break;
			pNode = pNode.nextSibling;
		}
		if (pNode != null) {
			var newNode = document.createElement('a');
			newNode.className = "module-close";
			newNode.href = blogURL + "/owner/skin/coverpage/delete/?coverpageNumber=" + sourceCoverpage + "&modulePos=" + sourcePostion + viewMode;
			newNode.onclick = function () { return postNavigate(this.href); };	// Textcube-NG: 삭제는 POST(v3.148)
			newNode.title = decorateDragPanelString_deleteTitle;
			newNode.innerHTML = '<img src="' + servicePath + adminSkin + '/image/img_delete_module.gif" border="0" alt="'+ commonString_delete +'" />';
			if (pNode.nextSibling != null) {		
				node.insertBefore(newNode,pNode.nextSibling);
			} else {
				node.appendChild(newNode);
			}
		}
		var pNode = node.firstChild;
		while (pNode != null) {
			if ((pNode.tagName != null) && (pNode.tagName.toLowerCase() == 'div')) {
				pNode.style.clear = 'both';
			}
			pNode = pNode.nextSibling;
		}
	}

	function editCoverpagePlugin(coverpage, modulepos) {
		var requestURL = blogURL + "/owner/skin/coverpage/edit?coverpageNumber=" + coverpage + "&modulePos=" + modulepos + "&ajaxcall=submitCoverpagePlugin(" + coverpage + "," + modulepos + ")" + viewMode;

		var request = new HTTPRequest("GET", requestURL);
		request.onSuccess = function () {
			if (dlg != null) {
				dlg.setContent(this._request.responseText);
				var btn = document.createElement('input');
				btn.type = 'button';
				btn.value = commonString_cancel;
				btn.className = 'input-button';
				
				var pNode = dlg.domNode.firstChild;
				while (pNode != null) {
					if ((pNode.tagName != null) && (pNode.tagName.toLowerCase() == 'form')) {
					    pNode = pNode.firstChild;
						break;
					}
					pNode = pNode.nextSibling;
				}
				while (pNode != null) {
					if ((pNode.className != null) && (pNode.className.toLowerCase() == 'button-box')) {
        				pNode.appendChild(btn);
						break;
					}
					pNode = pNode.nextSibling;
				}
				
				dlg.setCloseControl(btn);
				dlg.show();
			}
		}
		request.onError = function () {
			globalChker = false;
		}
		request.onVerify = function () {
			return true;
		}
		request.send();
	}
	
	function waitServerResponse()
	{
		if (dlg != null) {
			dlg.setContent('<p class="waiting-string">' + commonString_saving + '</p>');
			dlg.show();
		}
	}
	
	function clearWaitServerResponse()
	{
	    dlg.hide();
	}
	
	function errorWaitServerResponse()
	{
	    dlg.setContent('<p class="error-string">' + commonString_error + '</p>');
		var btn = document.createElement('input');
		btn.type = 'button';
		btn.value = commonString_close;
		btn.className = 'input-button';
		btn.onclick = function () { window.location.reload(); return false; };
		
		var oDiv = document.createElement('div');
		oDiv.className = 'button-box';
		
		oDiv.appendChild(btn);
		var pNode = dlg.domNode;
		pNode.appendChild(oDiv);
			
		dlg.setCloseControl(btn);
		dlg.show();
	}
