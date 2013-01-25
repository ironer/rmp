/**
 * Copyright (c) 2013 Stefan Fiedler (http://ironer.cz)
 * Object for TimeDebug GUI
 * @author: Stefan Fiedler 2013
 */

// TODO: vytrhnout log pres layout fixed
// TODO: enter ulozi data z textarea pro editaci

// TODO: on-line podstrceni hodnoty pri dumpovani
// TODO: on-line podstrceni hodnoty pri logovani (jen logovane objekty v td)

// TODO: ulozit nastaveni do cookie a/nebo vyexportovat do textarea

// TODO: vytvorit unit testy
// TODO: zkontrolovat dumpovani resources
// TODO: vyplivnout vystup do iframe nebo dalsiho okna

var TimeDebug = {};

TimeDebug.local = false;

TimeDebug.logView = document.getElementById('logView');
TimeDebug.logWrapper = TimeDebug.logView.parentNode;
TimeDebug.logContainer = TimeDebug.logWrapper.parentNode;
TimeDebug.logRows = [];
TimeDebug.logRowsChosen = [];
TimeDebug.logRowActiveId = 0;
TimeDebug.dumps = [];
TimeDebug.indexes = [];

TimeDebug.tdContainer = JAK.mel('div', {id:'tdContainer'});
TimeDebug.tdOuterWrapper = JAK.mel('div', {id:'tdOuterWrapper'});
TimeDebug.tdInnerWrapper = JAK.mel('div', {id:'tdInnerWrapper'});
TimeDebug.tdView = JAK.mel('div', {id:'tdView'});
TimeDebug.tdView.activeChilds = [];
TimeDebug.tdListeners = [];
TimeDebug.tdFullWidth = false;
TimeDebug.tdWidth = 400;

TimeDebug.help = JAK.cel('div', 'nd-help');
TimeDebug.helpHtml = '';
TimeDebug.helpSpaceX = 0;

TimeDebug.visibleTitles = [];
TimeDebug.titleActive = null;
TimeDebug.titleHideTimeout = null;
TimeDebug.viewSize = JAK.DOM.getDocSize();
TimeDebug.spaceX = 0;
TimeDebug.spaceY = 0;
TimeDebug.zIndexMax = 100;

TimeDebug.tdConsole = null;
TimeDebug.textareaTimeout = null;
TimeDebug.changes = [];

TimeDebug.actionData = { element: null, listeners: [] };

TimeDebug.init = function(logId) {
	JAK.DOM.addClass(document.body.parentNode, 'nd-td' + (TimeDebug.local ? ' nd-local' : ''));
	document.body.style.marginLeft = TimeDebug.tdContainer.style.width = TimeDebug.help.style.left = TimeDebug.tdWidth + 'px';
	TimeDebug.viewSize = JAK.DOM.getDocSize();

	var links;
	var logNodes = TimeDebug.logView.childNodes;

	for (var i = 0, j = logNodes.length, k; i < j; ++i) {
		if (logNodes[i].nodeType == 1 && logNodes[i].tagName.toLowerCase() == 'pre') {
			if (JAK.DOM.hasClass(logNodes[i], 'nd-dump')) {
				logNodes[i].onmousedown = TimeDebug.changeVar;
			} else if (JAK.DOM.hasClass(logNodes[i], 'nd-row')) {
				TimeDebug.logRows.push(logNodes[i]);
				logNodes[i].onclick = TimeDebug.logClick;
				links = logNodes[i].getElementsByTagName('a');
				for (k = links.length; k-- > 0;) links[k].onclick = JAK.Events.stopEvent;
			}
		}
	}

	TimeDebug.logRowsChosen.length = TimeDebug.logRows.length;

	TimeDebug.tdInnerWrapper.appendChild(TimeDebug.tdView);
	TimeDebug.tdOuterWrapper.appendChild(TimeDebug.tdInnerWrapper);
	TimeDebug.tdContainer.appendChild(TimeDebug.tdOuterWrapper);
	document.body.insertBefore(TimeDebug.tdContainer, document.body.childNodes[0]);

	TimeDebug.help.innerHTML = '<span class="nd-titled"><span id="tId_1" class="nd-title"><strong class="nd-inner">'
			+ '<hr><div class="nd-menu">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
			+ '<span class="nd-titled"><span id="tId_1" class="nd-title"><strong class="nd-inner">'
			+ TimeDebug.helpHtml
			+ '</strong></span>napoveda</span>'
			+ '     |&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span>export</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;<span>import</span>'
			+ '     |&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span>ulozit</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;<span>nahrat</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;<span>smazat</span>'
			+ '     |&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span onclick="document.location.reload()">obnovit</span>'
			+ (TimeDebug.local ? '&nbsp;&nbsp;&nbsp;&nbsp;<span>odeslat</span>' : '')
			+ '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div><hr>'
			+ '</strong></span>*</span>';
	document.body.appendChild(TimeDebug.help);
	TimeDebug.help.onmousedown = TimeDebug.logAction;
	TimeDebug.setTitles(TimeDebug.help);
	TimeDebug.helpSpaceX = TimeDebug.help.clientWidth + JAK.DOM.scrollbarWidth();

	TimeDebug.setTitles(TimeDebug.logView);
	TimeDebug.showDump(logId);
	window.onresize = TimeDebug.windowResize;
	document.onkeydown = TimeDebug.readKeyDown;
	document.body.oncontextmenu = TimeDebug.tdFalse;
	TimeDebug.tdView.onmousedown = TimeDebug.changeVar;
};

TimeDebug.changeVar = function(e) {
	e = e || window.event;

	if (!TimeDebug.local || e.altKey || e.shiftKey || e.ctrlKey || e.metaKey || e.button != JAK.Browser.mouse.right) return true;

	var el = JAK.Events.getTarget(e);

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);

	if (el.id == 'tdConsoleMask') {
		TimeDebug.consoleClose();
	} else if (el.className == 'nd-key') {
		TimeDebug.consoleOpen(el, TimeDebug.saveVarChange);
	} else if (JAK.DOM.hasClass(el, 'nd-top')) {
		TimeDebug.consoleOpen(el, TimeDebug.saveVarChange);
	}

	return false;
};

TimeDebug.saveVarChange = function() {
	console.debug(TimeDebug.tdConsole.parentNode.className);
};

TimeDebug.consoleOpen = function(el, callback) {
	TimeDebug.tdConsole = JAK.mel('span', {id:'tdConsole'});
	TimeDebug.tdConsole.mask = JAK.mel('span', {id:'tdConsoleMask'});
	TimeDebug.tdConsole.area = JAK.mel('textarea', {id:'tdConsoleArea'});
	TimeDebug.tdConsole.appendChild(TimeDebug.tdConsole.mask);
	TimeDebug.tdConsole.appendChild(TimeDebug.tdConsole.area);
	el.appendChild(TimeDebug.tdConsole);

	TimeDebug.tdConsole.callback = callback || TimeDebug.tdStop;
	TimeDebug.tdConsole.mask.onmousedown = TimeDebug.catchMask;
	TimeDebug.tdConsole.area.oncontextmenu = TimeDebug.restoreContextMenu;

	TimeDebug.textareaTimeout = window.setTimeout(TimeDebug.textareaFocus, 1);
};

TimeDebug.textareaFocus = function() {
	if (TimeDebug.textareaTimeout) {
		window.clearTimeout(TimeDebug.textareaTimeout);
		TimeDebug.textareaTimeout = null;
	}
	TimeDebug.tdConsole.area.focus();
};

TimeDebug.restoreContextMenu = function(e) {
	e = e || window.event;
	JAK.Events.stopEvent(e);
	return true;
};

TimeDebug.catchMask = function(e) {
	e = e || window.event;
	if (e.altKey || e.shiftKey || e.ctrlKey || e.metaKey || e.button != JAK.Browser.mouse.right) {
		TimeDebug.tdConsole.area.focus();
		return TimeDebug.tdStop(e);
	}
	return true;
};

TimeDebug.consoleClose = function() {
	TimeDebug.tdConsole.parentNode.removeChild(TimeDebug.tdConsole);
	TimeDebug.tdConsole = null;
	return false;
};

TimeDebug.logAction = function(e) {
	e = e || window.event;

	if ((e.altKey ? e.shiftKey || TimeDebug.tdFullWidth : !e.shiftKey) || e.ctrlKey || e.metaKey || e.button != JAK.Browser.mouse.left) return true;

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);
	var el = TimeDebug.help;

	if (e.altKey) {
		TimeDebug.actionData.startX = e.screenX;
		TimeDebug.actionData.width = TimeDebug.tdWidth;
		TimeDebug.actionData.element = el;

		TimeDebug.actionData.listeners.push(
				JAK.Events.addListener(document, 'mousemove', TimeDebug, 'logResizing'),
				JAK.Events.addListener(document, 'mouseup', TimeDebug, 'endLogResize'),
				JAK.Events.addListener(el, 'selectstart', TimeDebug, 'tdStop'),
				JAK.Events.addListener(el, 'dragstart', TimeDebug, 'tdStop')
		);

		document.body.focus();
	} else {
		if (!TimeDebug.tdFullWidth) {
			TimeDebug.tdFullWidth = true;
			JAK.DOM.addClass(document.body.parentNode, 'nd-fullscreen');

			document.body.style.marginLeft = TimeDebug.help.style.left = 0;
			TimeDebug.tdContainer.style.width = '100%';
			TimeDebug.logContainer.style.width = TimeDebug.tdContainer.clientWidth + 'px';
		} else {
			TimeDebug.tdFullWidth = false;
			JAK.DOM.removeClass(document.body.parentNode, 'nd-fullscreen');

			document.body.style.marginLeft = TimeDebug.tdContainer.style.width = TimeDebug.help.style.left = TimeDebug.tdWidth + 'px';
			TimeDebug.logContainer.style.width = 'auto';
		}

		TimeDebug.resizeWrapper();
	}

	return false;
};

TimeDebug.logResizing = function(e) {
	e = e || window.event;
	var el = TimeDebug.actionData.element;

	if (e.button != JAK.Browser.mouse.left) {
		TimeDebug.endLogResize();
	} else {
		TimeDebug.tdWidth = Math.max(0, Math.min(TimeDebug.viewSize.width - TimeDebug.helpSpaceX,
				TimeDebug.actionData.width + e.screenX - TimeDebug.actionData.startX));

		document.body.style.marginLeft = TimeDebug.tdContainer.style.width = el.style.left = TimeDebug.tdWidth + 'px';

		TimeDebug.resizeWrapper();
	}
};

TimeDebug.endLogResize = function() {
	JAK.Events.removeListeners(TimeDebug.actionData.listeners);
	TimeDebug.actionData.listeners.length = 0;
	TimeDebug.actionData.element = null;
};

TimeDebug.showDump = function(id) {
	if (TimeDebug.logRowActiveId == (id = id || 0)) return false;
	if (TimeDebug.logRowActiveId) JAK.DOM.removeClass(document.getElementById('logId_' + TimeDebug.logRowActiveId), 'nd-active');

	JAK.DOM.addClass(document.getElementById('logId_' + id), 'nd-active');
	if (TimeDebug.indexes[TimeDebug.logRowActiveId - 1] !== TimeDebug.indexes[id - 1]) {
		if (TimeDebug.tdListeners.length) {
			JAK.Events.removeListeners(TimeDebug.tdListeners);
			TimeDebug.tdListeners.length = 0;
		}
		for (var i = TimeDebug.tdView.activeChilds.length, j; i-- > 0;) {
			if ((j = TimeDebug.visibleTitles.indexOf(TimeDebug.tdView.activeChilds[i])) !== -1) TimeDebug.visibleTitles.splice(j, 1);
		}
		TimeDebug.tdView.activeChilds.length = 0;

		TimeDebug.tdView.innerHTML = TimeDebug.dumps[TimeDebug.indexes[id - 1]];
		TimeDebug.setTitles(TimeDebug.tdView);
		TimeDebug.resizeWrapper();
	}
	TimeDebug.logRowActiveId = id;
	return true;
};

TimeDebug.setTitles = function(el) {
	var titleSpan, titleStrong, titleStrongs, listeners = [];

	titleStrongs = el.getElementsByTagName('strong');
	for (var i = titleStrongs.length; i-- > 0;) {
		if ((titleStrong = titleStrongs[i]).className == 'nd-inner') {
			titleSpan = titleStrong.parentNode.parentNode;
			titleSpan.tdTitle = titleStrong.parentNode;
			titleSpan.tdTitle.tdInner = titleStrong;

			listeners.push(JAK.Events.addListener(titleSpan, 'mousemove', titleSpan, TimeDebug.showTitle),
					JAK.Events.addListener(titleSpan, 'mouseout', titleSpan, TimeDebug.hideTimer),
					JAK.Events.addListener(titleSpan, 'click', titleSpan, TimeDebug.pinTitle),
					JAK.Events.addListener(titleSpan.tdTitle, 'mousedown', titleSpan.tdTitle, TimeDebug.titleAction)
			);
		}
	}

	if (el === TimeDebug.tdView) TimeDebug.tdListeners = TimeDebug.tdListeners.concat(listeners);
};

TimeDebug.showTitle = function(e) {
	e = e || window.event;

	if (e.shiftKey || e.altKey || e.ctrlKey || e.metaKey || TimeDebug.actionData.element !== null) return false;
	var tdTitleRows, tdParents;

	JAK.Events.stopEvent(e);

	if (TimeDebug.titleActive && TimeDebug.titleActive !== this.tdTitle) {
		TimeDebug.hideTitle();
	} else if (TimeDebug.titleHideTimeout) {
		window.clearTimeout(TimeDebug.titleHideTimeout);
		TimeDebug.titleHideTimeout = null;
	}

	if (TimeDebug.titleActive === null && this.tdTitle.style.display != 'block') {
		this.tdTitle.style.display = 'block';
		this.tdTitle.style.zIndex = ++TimeDebug.zIndexMax;

		if (!this.tdTitle.hasOwnProperty('oriWidth')) {
			if ((tdParents = TimeDebug.getParents(this)).length) this.tdTitle.parents = tdParents;
			this.tdTitle.style.position = 'fixed';
			this.tdTitle.oriWidth = this.tdTitle.clientWidth;
			this.tdTitle.oriHeight = this.tdTitle.clientHeight;
			tdTitleRows = this.tdTitle.tdInner.childNodes;
			for (var i = 0, j = tdTitleRows.length, c = 1; i < j; ++i) {
				if (tdTitleRows[i].nodeType == 1 && tdTitleRows[i].tagName.toLowerCase() == 'i' && ++c % 2) {
					tdTitleRows[i].className = "nd-even";
				}
			}
		}
		if (tdParents = tdParents || this.tdTitle.parents) {

			for (var k = tdParents.length; k-- > 0;) {
				if (tdParents[k].hasOwnProperty('activeChilds')) {
					tdParents[k].activeChilds.push(this.tdTitle);
				} else tdParents[k].activeChilds = [this.tdTitle];
			}
		}
		TimeDebug.titleActive = this.tdTitle;
		TimeDebug.visibleTitles.push(TimeDebug.titleActive);
	}

	if (TimeDebug.titleActive === null) return true;

	TimeDebug.titleActive.style.left = (TimeDebug.titleActive.tdLeft = (e.pageX || e.clientX) + 20) + 'px';
	TimeDebug.titleActive.style.top = (TimeDebug.titleActive.tdTop = (e.pageY || e.clientY) - 5) + 'px';

	TimeDebug.titleAutosize();

	return false;
};

TimeDebug.removeFromParents = function(el) {
	for (var i = el.parents.length, j; i-- > 0;) {
		for (j = el.parents[i].activeChilds.length; j-- > 0;) {
			if (el.parents[i].activeChilds[j] === el) el.parents[i].activeChilds.splice(j, 1);
		}
	}
};

TimeDebug.getParents = function(el) {
	if (!el) return [];
	var tag, parents = [];

	while ((tag = (el = el.parentNode).tagName.toLowerCase()) != 'body') {
		if (tag == 'strong' && el.className == 'nd-inner') parents.push(el = el.parentNode);
		else if (el.id === 'tdView') {
			parents.push(el);
			break;
		}
	}
	return parents;
};

TimeDebug.getMaxZIndex = function() {
	for (var retVal = 100, i = TimeDebug.visibleTitles.length; i-- > 0;) {
		retVal = Math.max(retVal, TimeDebug.visibleTitles[i].style.zIndex);
	}
	return retVal;
};

TimeDebug.titleAction = function(e) {
	e = e || window.event;

	if (!this.pined || e.button != JAK.Browser.mouse.left) return true;

	JAK.Events.stopEvent(e);

	if (this.style.zIndex < TimeDebug.zIndexMax) this.style.zIndex = ++TimeDebug.zIndexMax;

	if (e.altKey) {
		if (!e.ctrlKey && !e.metaKey) {
			if (e.shiftKey) TimeDebug.hideTitle(this);
			else TimeDebug.startTitleDrag(e, this);
		} else if (!e.shiftKey) {
			this.resized = false;
			TimeDebug.titleAutosize(this);
		} else return true;
	} else if (e.shiftKey) return true;
	else if (e.ctrlKey || e.metaKey) {
		TimeDebug.startTitleResize(e, this)
	} else return true;

	JAK.Events.cancelDef(e);
	return false;
};

TimeDebug.startTitleResize = function(e, el) {
	el.resized = true;
	TimeDebug.actionData.startX = e.screenX;
	TimeDebug.actionData.startY = e.screenY;
	TimeDebug.actionData.width = (el.tdWidth === 'auto' ? el.offsetWidth : el.tdWidth);
	TimeDebug.actionData.height = (el.tdHeight === 'auto' ? el.clientHeight : el.tdHeight);
	TimeDebug.actionData.element = el;

	TimeDebug.actionData.listeners.push(
			JAK.Events.addListener(document, 'mousemove', TimeDebug, 'titleResizing'),
			JAK.Events.addListener(document, 'mouseup', TimeDebug, 'endTitleAction'),
			JAK.Events.addListener(el, 'selectstart', TimeDebug, 'tdStop'),
			JAK.Events.addListener(el, 'dragstart', TimeDebug, 'tdStop')
	);

	document.body.focus();
};

TimeDebug.titleResizing = function(e) {
	e = e || window.event;
	var el = TimeDebug.actionData.element;

	if (e.button != JAK.Browser.mouse.left) {
		TimeDebug.endTitleAction();
	} else {
		el.userWidth = el.tdWidth = Math.max(Math.min(TimeDebug.viewSize.width - el.tdLeft - 20, TimeDebug.actionData.width + e.screenX - TimeDebug.actionData.startX), 16);
		el.userHeight = el.tdHeight = 16 * parseInt(Math.max(Math.min(TimeDebug.viewSize.height - el.tdTop - 35, TimeDebug.actionData.height + e.screenY - TimeDebug.actionData.startY), 16) / 16);

		JAK.DOM.setStyle(el, { width: el.tdWidth + 'px', height: el.tdHeight + 'px' });
	}
};

TimeDebug.startTitleDrag = function(e, el) {
	TimeDebug.actionData.startX = e.screenX;
	TimeDebug.actionData.startY = e.screenY;
	TimeDebug.actionData.offsetX = el.tdLeft;
	TimeDebug.actionData.offsetY = el.tdTop;
	TimeDebug.actionData.element = el;

	TimeDebug.actionData.listeners.push(
			JAK.Events.addListener(document, 'mousemove', TimeDebug, 'titleDragging'),
			JAK.Events.addListener(document, 'mouseup', TimeDebug, 'endTitleAction'),
			JAK.Events.addListener(el, 'selectstart', TimeDebug, 'tdStop'),
			JAK.Events.addListener(el, 'dragstart', TimeDebug, 'tdStop')
	);

	document.body.focus();
};

TimeDebug.titleDragging = function(e) {
	e = e || window.event;
	var el = TimeDebug.actionData.element;

	if (e.button != JAK.Browser.mouse.left) {
		TimeDebug.endTitleAction();
	} else {
		el.tdLeft = Math.max(Math.min(TimeDebug.viewSize.width - 36, TimeDebug.actionData.offsetX + e.screenX - TimeDebug.actionData.startX), 0);
		el.tdTop = Math.max(Math.min(TimeDebug.viewSize.height - 51, TimeDebug.actionData.offsetY + e.screenY - TimeDebug.actionData.startY), 0);

		JAK.DOM.setStyle(el, { left: el.tdLeft + 'px', top: el.tdTop + 'px' });

		TimeDebug.titleAutosize(el);
	}
};

TimeDebug.endTitleAction = function() {
	var el = TimeDebug.actionData.element;

	if (el !== null) {
		JAK.Events.removeListeners(TimeDebug.actionData.listeners);
		TimeDebug.actionData.listeners.length = 0;
		TimeDebug.actionData.element = null;
	}
};

TimeDebug.tdFalse = function() {
	return false;
};

TimeDebug.tdStop = function(e) {
	e = e || window.event;
	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);
	return false;
};

TimeDebug.titleAutosize = function(el) {
	el = el || TimeDebug.titleActive;

	var tdCheckWidthDif = false;
	var tdWidthDif;
	TimeDebug.spaceX = Math.max(TimeDebug.viewSize.width - el.tdLeft - 20, 0);
	TimeDebug.spaceY = 16 * parseInt(Math.max(TimeDebug.viewSize.height - el.tdTop - 35, 0) / 16);

	if (el.resized) {
		el.style.width = (TimeDebug.spaceX < el.userWidth ? el.tdWidth = TimeDebug.spaceX : el.tdWidth = el.userWidth) + 'px';
	} else if (TimeDebug.spaceX < el.oriWidth) {
		el.style.width = (el.tdWidth = TimeDebug.spaceX) + 'px';
	} else {
		el.style.width = el.tdWidth = 'auto';
		tdCheckWidthDif = true;
	}

	if (el.resized) {
		el.style.height = (TimeDebug.spaceY < el.userHeight ? el.tdHeight = TimeDebug.spaceY : el.tdHeight = el.userHeight) + 'px';
	} else if (TimeDebug.spaceY < el.tdInner.clientHeight || TimeDebug.spaceY < el.oriHeight) {
		el.style.height = (el.tdHeight = TimeDebug.spaceY) + 'px';
		if (tdCheckWidthDif && (tdWidthDif = Math.max(el.oriWidth - el.clientWidth, 0))) {
			el.style.width = (el.tdWidth = el.oriWidth + tdWidthDif) + 'px';
		}
	} else {
		el.style.height = el.tdHeight = 'auto';
	}
	return true;
};

TimeDebug.hideTimer = function(e) {
	e = e || window.event;
	JAK.Events.stopEvent(e);
	if (TimeDebug.titleHideTimeout) window.clearTimeout(TimeDebug.titleHideTimeout);
	TimeDebug.titleHideTimeout = window.setTimeout(TimeDebug.hideTitle, 300);
};

TimeDebug.hideTitle = function(el) {
	var index;

	if (TimeDebug.titleHideTimeout) {
		window.clearTimeout(TimeDebug.titleHideTimeout);
		TimeDebug.titleHideTimeout = null;
	}

	if (el && el.pined) {
		el.pined = false;
	} else if ((el = TimeDebug.titleActive) !== null) {
		TimeDebug.titleActive = null;
	} else return false;

	if ((index = TimeDebug.visibleTitles.indexOf(el)) !== -1) TimeDebug.visibleTitles.splice(index, 1);
	if (el.style.zIndex == TimeDebug.zIndexMax) TimeDebug.zIndexMax = TimeDebug.getMaxZIndex();
	if (el.parents) TimeDebug.removeFromParents(el);
	if (el.activeChilds && el.activeChilds.length) {
		for (index = el.activeChilds.length; index-- > 0;) TimeDebug.hideTitle(el.activeChilds[index]);
	}
	el.style.display = 'none';

	return true;
};

TimeDebug.pinTitle = function(e) {
	e = e || window.event;
	JAK.Events.stopEvent(e);

	if (e.altKey || e.shiftKey || e.ctrlKey || e.metaKey || e.button != JAK.Browser.mouse.left) return false;

	if (TimeDebug.titleHideTimeout) {
		window.clearTimeout(TimeDebug.titleHideTimeout);
		TimeDebug.titleHideTimeout = null;
	}

	var el = JAK.Events.getTarget(e);

	if (!JAK.DOM.hasClass(el, 'nd-titled')) return false;

	if (TimeDebug.titleActive && TimeDebug.titleActive !== el.tdTitle) TimeDebug.hideTitle();

	if (TimeDebug.titleActive !== null) {
		TimeDebug.titleActive.pined = true;
		TimeDebug.titleActive = null;
	} else {
		TimeDebug.titleActive = el.tdTitle;
		TimeDebug.titleActive.pined = false;
	}
	return false;
};

TimeDebug.logClick = function(e) {
	e = e || window.event;
	var id = parseInt(this.id.split('_')[1]);

	if (e.altKey) {
	} else if (e.ctrlKey || e.metaKey) {
		TimeDebug.logRowsChosen[id - 1] = !TimeDebug.logRowsChosen[id - 1];
		if (TimeDebug.logRowsChosen[id - 1]) JAK.DOM.addClass(TimeDebug.logRows[id - 1], 'nd-chosen');
		else JAK.DOM.removeClass(TimeDebug.logRows[id - 1], 'nd-chosen');
	} else if (e.shiftKey) {
		var unset = false;
		if (JAK.DOM.hasClass(this, 'nd-chosen')) unset = true;
		for (var i = Math.min(TimeDebug.logRowActiveId, id), j = Math.max(TimeDebug.logRowActiveId, id); i <= j; i++) {
			if (unset) {
				TimeDebug.logRowsChosen[i - 1] = false;
				JAK.DOM.removeClass(TimeDebug.logRows[i - 1], 'nd-chosen');
			} else {
				TimeDebug.logRowsChosen[i - 1] = true;
				JAK.DOM.addClass(TimeDebug.logRows[i - 1], 'nd-chosen');
			}
		}
	} else {
		TimeDebug.showDump(id);
	}
	return false;
};

TimeDebug.readKeyDown = function(e) {
	e = e || window.event;
	var tdNext;

	if (!e.shiftKey && !e.altKey && !e.ctrlKey && !e.metaKey) {
		if (e.keyCode == 37 && !TimeDebug.tdConsole && TimeDebug.logRowActiveId > 1) {
			tdNext = TimeDebug.selected() ? TimeDebug.getPrevious() : TimeDebug.logRowActiveId - 1;
			if (tdNext === TimeDebug.logRowActiveId) return true;
			TimeDebug.showDump(tdNext);
			return false;
		} else if (e.keyCode == 39 && !TimeDebug.tdConsole && TimeDebug.logRowActiveId < TimeDebug.indexes.length) {
			TimeDebug.logView.blur();
			tdNext = TimeDebug.selected() ? TimeDebug.getNext() : TimeDebug.logRowActiveId + 1;
			if (tdNext === TimeDebug.logRowActiveId) return true;
			TimeDebug.showDump(tdNext);
			return false;
		} else if (e.keyCode == 38) {
			console.debug(this);
			if (TimeDebug.titleActive) {
				TimeDebug.titleActive.scrollTop = 16 * parseInt((TimeDebug.titleActive.scrollTop - 16) / 16);
				return false;
			}
		} else if (e.keyCode == 40) {
			console.debug(this);
			TimeDebug.logContainer.scrollTop = 0;
			if (TimeDebug.titleActive) {
				TimeDebug.titleActive.scrollTop = 16 * parseInt((TimeDebug.titleActive.scrollTop + 16) / 16);
				return false;
			}
		} else if (e.keyCode == 27) {
			if (TimeDebug.tdConsole) return TimeDebug.consoleClose();
			if (!TimeDebug.visibleTitles.length) return true;
			if (TimeDebug.titleHideTimeout) {
				window.clearTimeout(TimeDebug.titleHideTimeout);
				TimeDebug.titleHideTimeout = null;
			}
			for (var i = TimeDebug.visibleTitles.length; i-- > 0;) {
				TimeDebug.visibleTitles[i].style.display = 'none';
				TimeDebug.visibleTitles[i].pined = false;
				TimeDebug.visibleTitles[i].resized = false;
			}
			TimeDebug.visibleTitles.length = 0;
			TimeDebug.zIndexMax = 100;
			TimeDebug.titleActive = null;
			return false;
		} else if (e.keyCode == 13 && TimeDebug.tdConsole) {
			TimeDebug.tdConsole.callback();
			return TimeDebug.consoleClose();
		}
	}
	return true;
};

TimeDebug.windowResize = function() {
	TimeDebug.resizeWrapper();
	TimeDebug.viewSize = JAK.DOM.getDocSize();
	if (TimeDebug.tdFullWidth) TimeDebug.logContainer.style.width = TimeDebug.tdContainer.clientWidth + 'px';
	for (var i = TimeDebug.visibleTitles.length; i-- > 0;) TimeDebug.titleAutosize(TimeDebug.visibleTitles[i]);
};

TimeDebug.resizeWrapper = function() {
	var viewWidth = TimeDebug.tdView.clientWidth;
	var viewHeight = TimeDebug.tdView.clientHeight;
	if (viewWidth > TimeDebug.tdOuterWrapper.clientWidth) TimeDebug.tdOuterWrapper.style.width = viewWidth + 'px';
	if (viewHeight > TimeDebug.tdOuterWrapper.clientHeight) TimeDebug.tdOuterWrapper.style.height = viewHeight + 'px';
	if (TimeDebug.tdContainer.clientWidth > TimeDebug.tdOuterWrapper.clientWidth) TimeDebug.tdOuterWrapper.style.width = '100%';
};

TimeDebug.selected = function() {
	for (var i = TimeDebug.logRowsChosen.length; i-- > 0;) {
		if (TimeDebug.logRowsChosen[i]) return true;
	}
	return false;
};

TimeDebug.getPrevious = function() {
	for (var i = TimeDebug.logRowActiveId; --i > 0;) {
		if (TimeDebug.logRowsChosen[i - 1]) return i;
	}
	return TimeDebug.logRowActiveId;
};

TimeDebug.getNext = function() {
	for (var i = TimeDebug.logRowActiveId, j = TimeDebug.logRowsChosen.length; i++ < j;) {
		if (TimeDebug.logRowsChosen[i - 1]) return i;
	}
	return TimeDebug.logRowActiveId;
};