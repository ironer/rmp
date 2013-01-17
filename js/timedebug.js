var TimeDebug = {};

TimeDebug.logView = document.getElementById('logView');
TimeDebug.logRows = [];
TimeDebug.logRowsChosen = [];
TimeDebug.logRowActiveId = 0;
TimeDebug.dumps = [];
TimeDebug.indexes = [];

TimeDebug.tdContainer = JAK.mel('div', {id:'tdContainer'});
TimeDebug.tdOuterWrapper = JAK.mel('div', {id:'tdOuterWrapper'});
TimeDebug.tdView = JAK.mel('div', {id:'tdView'});
TimeDebug.tdWidth = 400;

TimeDebug.help = JAK.cel('div', 'nette-dump-help');
TimeDebug.helpHtml = '>';

TimeDebug.visibleTitles = [];
TimeDebug.titleActive = null;
TimeDebug.titleHideTimeout = null;
TimeDebug.viewSize = JAK.DOM.getDocSize();
TimeDebug.spaceX = 0;
TimeDebug.spaceY = 0;
TimeDebug.zIndexMax = 100;

TimeDebug.actionData = { element: null, listeners: [] };

TimeDebug.init = function(tdId) {
	TimeDebug.logView.parentNode.style.overflow = 'scroll';
	TimeDebug.logView.style.padding = '8px';
	JAK.DOM.setStyle(document.body, {height:'100%', margin:'0 0 0 ' + TimeDebug.tdWidth + 'px', overflow:'hidden'});
	TimeDebug.tdContainer.style.width = TimeDebug.help.style.left = TimeDebug.tdWidth + 'px';

	var links;
	var logNodes = TimeDebug.logView.childNodes;

	for(var i = 0, j = logNodes.length, k; i < j; ++i) {
		if (logNodes[i].nodeType == 1 && logNodes[i].tagName.toLowerCase() == 'pre' && JAK.DOM.hasClass(logNodes[i], 'nette-dump-row')) {
			TimeDebug.logRows.push(logNodes[i]);
			logNodes[i].onclick = this.logClick;
			links = logNodes[i].getElementsByTagName('a');
			for(k = links.length; k-- > 0;) links[k].onclick = JAK.Events.stopEvent;
		}
	}

	TimeDebug.logRowsChosen.length = TimeDebug.logRows.length;

	var _tdInnerWrapper = JAK.mel('div', {id:'tdInnerWrapper'});

	_tdInnerWrapper.appendChild(TimeDebug.tdView);
	TimeDebug.tdOuterWrapper.appendChild(_tdInnerWrapper);
	TimeDebug.tdContainer.appendChild(TimeDebug.tdOuterWrapper);
	document.body.insertBefore(TimeDebug.tdContainer, document.body.childNodes[0]);

	TimeDebug.help.innerHTML = TimeDebug.helpHtml;
	TimeDebug.logView.appendChild(TimeDebug.help);
	TimeDebug.help.onmousedown = TimeDebug.resizeLog;

	TimeDebug.setTitles(TimeDebug.logView);
	TimeDebug.showDump(tdId);
	window.onresize = TimeDebug.resizeWrapper;
	document.onkeydown = TimeDebug.readKeyDown;
};

TimeDebug.resizeLog = function(e) {
	e = e || window.event;

	if (e.shiftKey || !e.altKey || e.ctrlKey || e.metaKey ||	e.button != JAK.Browser.mouse.left) return true;

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);

	var el = TimeDebug.help;
		
	TimeDebug.actionData.startX = e.screenX;
	TimeDebug.actionData.width = TimeDebug.tdWidth;

	TimeDebug.actionData.element = el;

	TimeDebug.actionData.listeners.push(JAK.Events.addListener(document, 'mousemove', TimeDebug, 'logResizing'));
	TimeDebug.actionData.listeners.push(JAK.Events.addListener(document, 'mouseup', TimeDebug, 'endLogResize'));

	document.body.focus();

	TimeDebug.actionData.listeners.push(JAK.Events.addListener(el, 'selectstart', TimeDebug, 'stop'));
	TimeDebug.actionData.listeners.push(JAK.Events.addListener(el, 'dragstart', TimeDebug, 'stop'));

	return false;
};

TimeDebug.logResizing = function(e) {
	e = e || window.event;
	var el = TimeDebug.actionData.element;

	if (e.button != JAK.Browser.mouse.left) {
		TimeDebug.endLogResize();
	} else {
		TimeDebug.tdWidth = Math.max(Math.min(TimeDebug.viewSize.width - 20, TimeDebug.actionData.width + e.screenX - TimeDebug.actionData.startX), 0);
		document.body.style.marginLeft = TimeDebug.tdContainer.style.width = el.style.left = TimeDebug.tdWidth + 'px';
	}
};

TimeDebug.endLogResize = function() {
	JAK.Events.removeListeners(TimeDebug.actionData.listeners);
	TimeDebug.actionData.listeners.length = 0;
	TimeDebug.actionData.element = null;
};

TimeDebug.showDump = function(id) {
	if (TimeDebug.logRowActiveId == (id = id || 0)) return false;
	if (TimeDebug.logRowActiveId) JAK.DOM.removeClass(document.getElementById('tdId_' + TimeDebug.logRowActiveId), 'nette-dump-active');
	JAK.DOM.addClass(document.getElementById('tdId_' + id), 'nette-dump-active');
	if (TimeDebug.indexes[TimeDebug.logRowActiveId - 1] !== TimeDebug.indexes[id - 1]) {
		TimeDebug.tdView.innerHTML = TimeDebug.dumps[TimeDebug.indexes[id - 1]];
		TimeDebug.setTitles(TimeDebug.tdView);
		TimeDebug.resizeWrapper();
	}
	TimeDebug.logRowActiveId = id;
	return true;
};

TimeDebug.setTitles = function(el) {
	var _titleSpan;
	var _titleStrong;
	var _titleStrongs;

	_titleStrongs = el.getElementsByTagName('strong');
	for (var i = _titleStrongs.length; i-- > 0;) {
		if ((_titleStrong = _titleStrongs[i]).className == 'nette-dump-inner') {
			_titleSpan = _titleStrong.parentNode.parentNode;
			_titleSpan.tdTitle = _titleStrong.parentNode;
			_titleSpan.tdTitle.tdInner = _titleStrong;
			_titleSpan.onmousemove = TimeDebug.showTitle;
			_titleSpan.onmouseout = TimeDebug.hideTimer;
			_titleSpan.onclick = TimeDebug.pinTitle;
			_titleSpan.tdTitle.onmousedown = TimeDebug.titleAction;
		}
	}
};

TimeDebug.showTitle = function(e) {
	e = e || window.event;

	if (e.shiftKey || e.altKey || e.ctrlKey || e.metaKey || TimeDebug.actionData.element !== null) return false;
	var tdTitleRows, tdParents;

	JAK.Events.stopEvent(e);

	if (TimeDebug.titleActive && TimeDebug.titleActive !== this.tdTitle) {
		TimeDebug.hideTitle();
	}
	else if (TimeDebug.titleHideTimeout) {
		window.clearTimeout(TimeDebug.titleHideTimeout);
		TimeDebug.titleHideTimeout = null;
	}

	if (TimeDebug.titleActive === null && this.tdTitle.style.display != 'block')  {
		this.tdTitle.style.display = 'block';
		this.tdTitle.style.zIndex = ++TimeDebug.zIndexMax;

		if (!this.tdTitle.hasOwnProperty('oriWidth')) {
			if ((tdParents = TimeDebug.getParentTitles(this)).length) this.tdTitle.parents = tdParents;
			this.tdTitle.style.position = 'fixed';
			this.tdTitle.oriWidth = this.tdTitle.clientWidth;
			this.tdTitle.oriHeight = this.tdTitle.clientHeight;
			tdTitleRows = this.tdTitle.tdInner.childNodes;
			for (var i = 0, j = tdTitleRows.length, c = 1; i < j; ++i) {
				if (tdTitleRows[i].nodeType == 1 && tdTitleRows[i].tagName.toLowerCase() == 'i' && ++c % 2) {
					tdTitleRows[i].className = "nette-dump-even";
				}
			}
		}

		if (tdParents = tdParents || this.tdTitle.parents) {
			for(var k = tdParents.length; k-- > 0;) {
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

	// TODO: udelat fullwidth mod time debugu

	return false;
};

TimeDebug.removeFromParents = function(el) {
	for (var i = el.parents.length, j; i-- > 0;) {
		for (j = el.parents[i].activeChilds.length; j-- > 0;) {
			if (el.parents[i].activeChilds[j] === el) el.parents[i].activeChilds.splice(j, 1);
		}
	}
};

TimeDebug.getParentTitles = function(el) {
	if (!el) return [];
	var tag, parents = [];

	while ((tag = (el = el.parentNode).tagName.toLowerCase()) != 'body') {
		if (tag == 'strong' && el.className == 'nette-dump-inner') parents.push(el = el.parentNode);
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

	if (!this.pined) return true;

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

	TimeDebug.actionData.listeners.push(JAK.Events.addListener(document, 'mousemove', TimeDebug, 'titleResizing'));
	TimeDebug.actionData.listeners.push(JAK.Events.addListener(document, 'mouseup', TimeDebug, 'endTitleAction'));

	document.body.focus();

	TimeDebug.actionData.listeners.push(JAK.Events.addListener(el, 'selectstart', TimeDebug, 'stop'));
	TimeDebug.actionData.listeners.push(JAK.Events.addListener(el, 'dragstart', TimeDebug, 'stop'));
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

	TimeDebug.actionData.listeners.push(JAK.Events.addListener(document, 'mousemove', TimeDebug, 'titleDragging'));
	TimeDebug.actionData.listeners.push(JAK.Events.addListener(document, 'mouseup', TimeDebug, 'endTitleAction'));

	document.body.focus();

	TimeDebug.actionData.listeners.push(JAK.Events.addListener(el, 'selectstart', TimeDebug, 'stop'));
	TimeDebug.actionData.listeners.push(JAK.Events.addListener(el, 'dragstart', TimeDebug, 'stop'));
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

TimeDebug.stop = function(e) {
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

	if (e.shiftKey || e.altKey || e.ctrlKey || e.metaKey) return false;

	if (TimeDebug.titleHideTimeout) {
		window.clearTimeout(TimeDebug.titleHideTimeout);
		TimeDebug.titleHideTimeout = null;
	}
	if (TimeDebug.titleActive !== null) {
		TimeDebug.titleActive.pined = true;
		TimeDebug.titleActive = null;
	}
	return false;
};

TimeDebug.logClick = function(e) {
	e = e || window.event;
	var id = parseInt(this.id.split('_')[1]);

	if (e.altKey) {
	} else if (e.ctrlKey || e.metaKey) {
		TimeDebug.logRowsChosen[id - 1] = !TimeDebug.logRowsChosen[id - 1];
		if (TimeDebug.logRowsChosen[id - 1]) JAK.DOM.addClass(TimeDebug.logRows[id - 1], 'nette-dump-chosen');
		else JAK.DOM.removeClass(TimeDebug.logRows[id - 1], 'nette-dump-chosen');
	} else if (e.shiftKey) {
		var unset = false;
		if (JAK.DOM.hasClass(this, 'nette-dump-chosen')) unset = true;
		for(var i = Math.min(TimeDebug.logRowActiveId, id), j = Math.max(TimeDebug.logRowActiveId, id); i <= j; i++) {
			if (unset) {
				TimeDebug.logRowsChosen[i - 1] = false;
				JAK.DOM.removeClass(TimeDebug.logRows[i - 1], 'nette-dump-chosen');
			} else {
				TimeDebug.logRowsChosen[i - 1] = true;
				JAK.DOM.addClass(TimeDebug.logRows[i - 1], 'nette-dump-chosen');
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
		if (e.keyCode == 37 && TimeDebug.logRowActiveId > 1) {
			tdNext = TimeDebug.selected() ? TimeDebug.getPrevious() : TimeDebug.logRowActiveId - 1;
			if (tdNext === TimeDebug.logRowActiveId) return true;
			TimeDebug.showDump(tdNext);
			return false;
		} else if (e.keyCode == 39 && TimeDebug.logRowActiveId < TimeDebug.indexes.length) {
			tdNext = TimeDebug.selected() ? TimeDebug.getNext() : TimeDebug.logRowActiveId + 1;
			if (tdNext === TimeDebug.logRowActiveId) return true;
			TimeDebug.showDump(tdNext);
			return false;
		} else if (e.keyCode == 38 && TimeDebug.titleActive) {
			TimeDebug.titleActive.scrollTop = 16 * parseInt((TimeDebug.titleActive.scrollTop - 16) / 16);
			return false;
		} else if (e.keyCode == 40 && TimeDebug.titleActive) {
			TimeDebug.titleActive.scrollTop = 16 * parseInt((TimeDebug.titleActive.scrollTop + 16) / 16);
			return false;
		} else if (e.keyCode == 27 && TimeDebug.visibleTitles.length) {
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
		}
	}
	return true;
};

TimeDebug.resizeWrapper = function() {
	var viewWidth = parseInt(TimeDebug.tdView.clientWidth);
	var viewHeight = parseInt(TimeDebug.tdView.clientHeight);
	if (viewWidth > TimeDebug.tdOuterWrapper.clientWidth) TimeDebug.tdOuterWrapper.style.width =  viewWidth + 'px';
	if (viewHeight > TimeDebug.tdOuterWrapper.clientHeight) TimeDebug.tdOuterWrapper.style.height = viewHeight + 'px';
	TimeDebug.viewSize = JAK.DOM.getDocSize();
	for (var i = TimeDebug.visibleTitles.length; i-- > 0;) TimeDebug.titleAutosize(TimeDebug.visibleTitles[i]);
};

TimeDebug.selected = function() {
	for(var i = TimeDebug.logRowsChosen.length; i-- > 0;) {
		if (TimeDebug.logRowsChosen[i]) return true;
	}
	return false;
};

TimeDebug.getPrevious = function() {
	for(var i = TimeDebug.logRowActiveId; --i > 0;) {
		if (TimeDebug.logRowsChosen[i - 1]) return i;
	}
	return TimeDebug.logRowActiveId;
};

TimeDebug.getNext = function() {
	for(var i = TimeDebug.logRowActiveId, j = TimeDebug.logRowsChosen.length; i++ < j;) {
		if (TimeDebug.logRowsChosen[i - 1]) return i;
	}
	return TimeDebug.logRowActiveId;
};