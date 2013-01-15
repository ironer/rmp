var TimeDebug = {};

TimeDebug.logView = document.getElementById('logView');
TimeDebug.logRows = [];
TimeDebug.logRowsChosen = [];
TimeDebug.logRowActiveId = 0;
TimeDebug.tdDumps = [];
TimeDebug.tdIndexes = [];

TimeDebug.tdOuterWrapper = JAK.mel('div', {id:'tdOuterWrapper'});
TimeDebug.tdView = JAK.mel('div', {id:'tdView'});

TimeDebug.visibleTitles = [];
TimeDebug.titleActive = null;
TimeDebug.titleHideTimeout = null;
TimeDebug.viewSize = JAK.DOM.getDocSize();
TimeDebug.spaceX = 0;
TimeDebug.spaceY = 0;

TimeDebug.tdInit = function(tdId) {
	TimeDebug.logView.parentNode.style.overflow = 'scroll';
	TimeDebug.logView.style.padding = '8px';
	JAK.DOM.setStyle(document.body, {height:'100%', margin:'0 0 0 400px', overflow:'hidden'});

	var links;
	var preTags = TimeDebug.logView.getElementsByTagName('pre');

	for(var i = 0, j = preTags.length, k; i < j; ++i) {
		if (JAK.DOM.hasClass(preTags[i], 'nette-dump-row')) {
			TimeDebug.logRows.push(preTags[i]);
			preTags[i].onclick = this.tdMouseClick;
			links = preTags[i].getElementsByTagName('a');
			for(k = links.length; k-- > 0;) links[k].onclick = JAK.Events.stopEvent;
		}
	}

	TimeDebug.logRowsChosen.length = TimeDebug.logRows.length;

	var _tdContainer = JAK.mel('div', {id:'tdContainer'});
	var _tdInnerWrapper = JAK.mel('div', {id:'tdInnerWrapper'});

	_tdInnerWrapper.appendChild(TimeDebug.tdView);
	TimeDebug.tdOuterWrapper.appendChild(_tdInnerWrapper);
	_tdContainer.appendChild(TimeDebug.tdOuterWrapper);
	document.body.appendChild(_tdContainer);

	TimeDebug.tdSetTitles(TimeDebug.logView);
	TimeDebug.tdShowDump(tdId);
	window.onresize = TimeDebug.tdResizeWrapper;
	document.onkeydown = TimeDebug.tdKeyDown;
};

TimeDebug.tdShowDump = function(id) {
	if (TimeDebug.logRowActiveId == (id = id || 0)) return false;
	if (TimeDebug.logRowActiveId) JAK.DOM.removeClass(document.getElementById('tdId_' + TimeDebug.logRowActiveId), 'nette-dump-active');
	JAK.DOM.addClass(document.getElementById('tdId_' + id), 'nette-dump-active');
	if (TimeDebug.tdIndexes[TimeDebug.logRowActiveId - 1] !== TimeDebug.tdIndexes[id - 1]) {
		TimeDebug.tdView.innerHTML = TimeDebug.tdDumps[TimeDebug.tdIndexes[id - 1]];
		TimeDebug.tdSetTitles(TimeDebug.tdView);
		TimeDebug.tdResizeWrapper();
	}
	TimeDebug.logRowActiveId = id;
	return true;
};

TimeDebug.tdSetTitles = function(el) {
	var _titleSpan;
	var _titleStrong;
	var _titleStrongs;

	_titleStrongs = el.getElementsByTagName('strong');
	for (var i = _titleStrongs.length; i-- > 0;) {
		if (JAK.DOM.hasClass(_titleStrong = _titleStrongs[i], 'nette-dump-inner')) {
			_titleSpan = _titleStrong.parentNode.parentNode;
			_titleSpan.tdTitle = _titleStrong.parentNode;
			_titleSpan.tdTitle.tdInner = _titleStrong;
			_titleSpan.onmousemove = TimeDebug.tdShowTitle;
			_titleSpan.onmouseout = TimeDebug.tdHideTimer;
			_titleSpan.onclick = TimeDebug.tdPinTitle;
			_titleSpan.tdTitle.onmousedown = TimeDebug.tdMoveTitle;
		}
	}
};

TimeDebug.tdShowTitle = function(e) {
	e = e || window.event;
	JAK.Events.stopEvent(e);
	var tdTitleRows;

	if (TimeDebug.titleActive && TimeDebug.titleActive !== this.tdTitle) {
		TimeDebug.tdHideTitle();
	}
	else if (TimeDebug.titleHideTimeout) {
		window.clearTimeout(TimeDebug.titleHideTimeout);
		TimeDebug.titleHideTimeout = null;
	}

	if (TimeDebug.titleActive === null && this.tdTitle.style.display != 'block')  {
		TimeDebug.titleActive = this.tdTitle;
		TimeDebug.titleActive.style.display = 'block';
		TimeDebug.titleActive.style.position = 'fixed';
		if (!TimeDebug.titleActive.hasOwnProperty('oriWidth')) {
			TimeDebug.titleActive.oriWidth = TimeDebug.titleActive.clientWidth;
			TimeDebug.titleActive.oriHeight = TimeDebug.titleActive.clientHeight;
			tdTitleRows = TimeDebug.titleActive.getElementsByTagName('i');
			for (var i = 0, j = tdTitleRows.length; ++i < j; ++i)
				tdTitleRows[i].className = "nette-dump-even";
		}
		TimeDebug.visibleTitles.push(TimeDebug.titleActive);
	}

	if (TimeDebug.titleActive === null) return true;

	TimeDebug.titleActive.style.left = (TimeDebug.titleActive.tdLeft = (e.pageX || e.clientX) + 20) + 'px';
	TimeDebug.titleActive.style.top = (TimeDebug.titleActive.tdTop = (e.pageY || e.clientY) - 5) + 'px';

	TimeDebug.tdTitleAutosize();

	// TODO: aktivator lokalniho menu pod kurzorem (<Alt> nebo podrzeni leveho mysitka)
	// TODO: udelat menu oken(lt drag, rb resize, close, select content - word, line, all)
	// TODO: moznost zapinat a vypinat sude podbarveni
	// TODO: minihra - zavirani title s danou velikosti

	// TODO: udelat resizovani time debugu
	// TODO: udelat fullwidth mod time debugu

	return false;
};

TimeDebug.tdMoveTitle = function(e) {
	e = e || window.event;
	JAK.Events.stopEvent(e);
};

TimeDebug.tdTitleAutosize = function(el) {
	el = el || TimeDebug.titleActive;
	var tdCheckWidthDif;
	var tdWidthDif;
	TimeDebug.spaceX = Math.max(TimeDebug.viewSize[0] - el.tdLeft - 50, 0);
	TimeDebug.spaceY = 16 * parseInt(Math.max(TimeDebug.viewSize[1] - el.tdTop - 50, 0) / 16);

	if (TimeDebug.spaceX < el.oriWidth) {
		el.style.width = TimeDebug.spaceX + 'px';
		tdCheckWidthDif = false;
	} else {
		el.style.width = 'auto';
		tdCheckWidthDif = true;
	}

	if (TimeDebug.spaceY < el.tdInner.clientHeight || TimeDebug.spaceY < el.oriHeight) {
		el.style.height = TimeDebug.spaceY + 'px';
		if (tdCheckWidthDif) {
			tdWidthDif = Math.max(el.oriWidth - el.clientWidth, 0);
			if (tdWidthDif) el.style.width = el.oriWidth + tdWidthDif + 1;
		}
	} else {
		el.style.height = 'auto';
	}
};

TimeDebug.tdHideTimer = function(e) {
	e = e || window.event;
	JAK.Events.stopEvent(e);
	if (TimeDebug.titleHideTimeout) window.clearTimeout(TimeDebug.titleHideTimeout);
	TimeDebug.titleHideTimeout = window.setTimeout(TimeDebug.tdHideTitle, 300);
};

TimeDebug.tdHideTitle = function() {
	if (TimeDebug.titleHideTimeout) {
		window.clearTimeout(TimeDebug.titleHideTimeout);
		TimeDebug.titleHideTimeout = null;
	}
	if (TimeDebug.titleActive !== null) {
		var i = TimeDebug.visibleTitles.indexOf(TimeDebug.titleActive);
		if (i !== -1) TimeDebug.visibleTitles.splice(i, 1);
		TimeDebug.titleActive.style.display = 'none';
		TimeDebug.titleActive = null;
	}
};

TimeDebug.tdPinTitle = function() {
	if (TimeDebug.titleHideTimeout) {
		window.clearTimeout(TimeDebug.titleHideTimeout);
		TimeDebug.titleHideTimeout = null;
	}
	TimeDebug.titleActive = null;
};

TimeDebug.tdMouseClick = function(e) {
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
		TimeDebug.tdShowDump(id);
	}
	return false;
};

TimeDebug.tdKeyDown = function(e) {
	e = e || window.event;
	var tdNext;

	if (!e.shiftKey && !e.altKey && !e.ctrlKey && !e.metaKey) {
		if (e.keyCode == 37 && TimeDebug.logRowActiveId > 1) {
			tdNext = TimeDebug.tdSelected() ? TimeDebug.tdGetPrev() : TimeDebug.logRowActiveId - 1;
			if (tdNext === TimeDebug.logRowActiveId) return true;
			TimeDebug.tdShowDump(tdNext);
			return false;
		} else if (e.keyCode == 39 && TimeDebug.logRowActiveId < TimeDebug.tdIndexes.length) {
			tdNext = TimeDebug.tdSelected() ? TimeDebug.tdGetNext() : TimeDebug.logRowActiveId + 1;
			if (tdNext === TimeDebug.logRowActiveId) return true;
			TimeDebug.tdShowDump(tdNext);
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
			}
			TimeDebug.visibleTitles.length = 0;
			TimeDebug.titleActive = null;
			return false;
		}
	}
	return true;
};

TimeDebug.tdResizeWrapper = function() {
	var viewWidth = parseInt(TimeDebug.tdView.clientWidth);
	var viewHeight = parseInt(TimeDebug.tdView.clientHeight);
	if (viewWidth > TimeDebug.tdOuterWrapper.clientWidth) TimeDebug.tdOuterWrapper.style.width =  viewWidth + 'px';
	if (viewHeight > TimeDebug.tdOuterWrapper.clientHeight) TimeDebug.tdOuterWrapper.style.height = viewHeight + 'px';
	TimeDebug.viewSize = JAK.DOM.getDocSize();
	for (var i = TimeDebug.visibleTitles.length; i-- > 0;) TimeDebug.tdTitleAutosize(TimeDebug.visibleTitles[i]);
};

TimeDebug.tdSelected = function() {
	for(var i = TimeDebug.logRowsChosen.length; i-- > 0;) {
		if (TimeDebug.logRowsChosen[i]) return true;
	}
	return false;
};

TimeDebug.tdGetPrev = function() {
	for(var i = TimeDebug.logRowActiveId; --i > 0;) {
		if (TimeDebug.logRowsChosen[i - 1]) return i;
	}
	return TimeDebug.logRowActiveId;
};

TimeDebug.tdGetNext = function() {
	for(var i = TimeDebug.logRowActiveId, j = TimeDebug.logRowsChosen.length; i++ < j;) {
		if (TimeDebug.logRowsChosen[i - 1]) return i;
	}
	return TimeDebug.logRowActiveId;
};