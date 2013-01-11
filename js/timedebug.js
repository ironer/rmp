var _logContainer = document.createElement('div');
_logContainer.id = 'logContainer';
var _logView = document.createElement('div');
_logView.id = 'logView';
_logView.innerHTML = document.body.innerHTML;
document.body.innerHTML = '';
document.body.style.margin = '0px';
document.body.style.marginLeft = '400px';
document.body.style.overflow = 'hidden';
_logContainer.appendChild(_logView);
document.body.appendChild(_logContainer);

var _tdLinks;
var _tdPreTags = _logView.getElementsByTagName('pre');
var _tdRows = [];

for(var i = 0, j = _tdPreTags.length, k; i < j; ++i) {
	if (_tdHasClass(_tdPreTags[i], 'nette-dump-row')) {
		_tdRows.push(_tdPreTags[i]);
		_tdPreTags[i].onclick = _tdMouseClick;
		_tdLinks = _tdPreTags[i].getElementsByTagName('a');
		for(k = _tdLinks.length; k-- > 0;) _tdLinks[k].onclick = _tdStopPropagation;

	}
}

var _titleSpan;
var _titleStrong;
var _titleStrongs;

_tdSetHovers(_logView);

var _tdContainer = document.createElement('div');
_tdContainer.id = 'tdContainer';
var _tdOuterWrapper = document.createElement('div');
_tdOuterWrapper.id = 'tdOuterWrapper';
var _tdInnerWrapper = document.createElement('div');
_tdInnerWrapper.id = 'tdInnerWrapper';
var _tdView = document.createElement('div');
_tdView.id = 'tdView';

_tdInnerWrapper.appendChild(_tdView);
_tdOuterWrapper.appendChild(_tdInnerWrapper);
_tdContainer.appendChild(_tdOuterWrapper);
document.body.appendChild(_tdContainer);

var _tdChosen = [];
_tdChosen.length = _tdRows.length;
var _tdActive = 0;
var _tdTitle = null;
var _tdHideTimeout = null;
var _tdPosition = [];
var _tdWindowSize = _tdGetWindowSize();

_tdShowLog(1);

function _tdShowLog(id) {
	if (_tdActive == (id = id || 0)) return false;
	if (_tdActive) _tdRemoveClass(document.getElementById('tdId_' + _tdActive), 'nette-dump-active');
	_tdAddClass(document.getElementById('tdId_' + id), 'nette-dump-active');
	if (_tdIndex[_tdActive - 1] !== _tdIndex[id - 1]) {
		_tdView.innerHTML = _tdLogs[_tdIndex[id - 1]];
		_tdSetHovers(_tdView);
		_tdResizeWrapper();
	}
	_tdActive = id;
	return true;
}

function _tdSetHovers(el) {
	_titleStrongs = el.getElementsByTagName('strong');
	for (var i = _titleStrongs.length; i-- > 0;) {
		if (_tdHasClass(_titleStrong = _titleStrongs[i], 'nette-dump-title')) {
			_titleSpan = _titleStrong.parentNode;
			_titleSpan.tdTitle = _titleStrong;
			_titleSpan.onmousemove = _tdShowTitle;
			_titleSpan.onmouseout = _tdHideTitle;
		}
	}
}

function _tdShowTitle(e) {
	e = e || window.event;

	if (_tdTitle && _tdTitle !== this.tdTitle) {
		_tdHide();
	}
	else if (_tdHideTimeout) {
		window.clearTimeout(_tdHideTimeout);
		_tdHideTimeout = null;
	}

	if (_tdTitle === null) {
		_tdTitle = this.tdTitle;
		_tdTitle.style.display = 'table-cell';
		_tdTitle.style.position = 'fixed';
		_tdTitle.style.zIndex = 1000;
		if (!_tdTitle.hasOwnProperty('oriWidth')) {
			_tdTitle.oriWidth = _tdTitle.clientWidth
		}
		_tdTitle.focus();
	}

	_tdPosition = [(e.pageX || e.clientX) + 20, (e.pageY || e.clientY) - 5];
	_tdTitle.style.left =  _tdPosition[0] + 'px';
	_tdTitle.style.top = _tdPosition[1] + 'px';
}

function _tdHideTitle(e) {
	e = e || window.event;

	if (_tdHideTimeout) window.clearTimeout(_tdHideTimeout);
	_tdHideTimeout = window.setTimeout('_tdHide()', 300);
}

function _tdHide() {
	if (_tdHideTimeout) {
		window.clearTimeout(_tdHideTimeout);
		_tdHideTimeout = null;
	}
	if (_tdTitle !== null) {
		_tdTitle.style.display = 'none';
		_tdTitle = null;
	}
}

function _tdMouseClick(e) {
	e = e || window.event;
	var id = parseInt(this.id.split('_')[1]);

	if (e.altKey) {
	} else if (e.ctrlKey || e.metaKey) {
		_tdChosen[id - 1] = !_tdChosen[id - 1];
		if (_tdChosen[id - 1]) _tdAddClass(_tdRows[id - 1], 'nette-dump-chosen');
		else _tdRemoveClass(_tdRows[id - 1], 'nette-dump-chosen');
	} else if (e.shiftKey) {
		var unset = false;
		if (_tdHasClass(this, 'nette-dump-chosen')) unset = true;
		for(var i = Math.min(_tdActive, id), j = Math.max(_tdActive, id); i <= j; i++) {
			if (unset) {
				_tdChosen[i - 1] = false;
				_tdRemoveClass(_tdRows[i - 1], 'nette-dump-chosen');
			} else {
				_tdChosen[i - 1] = true;
				_tdAddClass(_tdRows[i - 1], 'nette-dump-chosen');
			}
		}
	} else {
		_tdShowLog(id);
	}
	return false;
}

document.onkeydown = function(e) {
	e = e || window.event;

	if (!e.shiftKey && !e.altKey && !e.ctrlKey && !e.metaKey) {
		if (e.keyCode == 37 && _tdActive > 1) {
			_tdShowLog(_tdSelected() ? _tdGetPrev() : _tdActive - 1);
			return false;
		} else if (e.keyCode == 39 && _tdActive < _tdIndex.length) {
			_tdShowLog(_tdSelected() ? _tdGetNext() : _tdActive + 1);
			return false;
		}
	}
	return true;
};

window.onresize = _tdResizeWrapper;

function _tdResizeWrapper() {
	var viewWidth = parseInt(_tdView.clientWidth);
	var viewHeight = parseInt(_tdView.clientHeight);
	if (viewWidth > _tdOuterWrapper.clientWidth) _tdOuterWrapper.style.width =  viewWidth + 'px';
	if (viewHeight > _tdOuterWrapper.clientHeight) _tdOuterWrapper.style.height = viewHeight + 'px';
	_tdWindowSize = _tdGetWindowSize();
}

function _tdSelected() {
	for(var i = _tdChosen.length; i-- > 0;) {
		if (_tdChosen[i]) return true;
	}
	return false;
}

function _tdGetPrev() {
	for(var i = _tdActive; --i > 0;) {
		if (_tdChosen[i - 1]) return i;
	}
	return _tdActive;
}

function _tdGetNext() {
	for(var i = _tdActive, j = _tdChosen.length; i++ < j;) {
		if (_tdChosen[i - 1]) return i;
	}
	return _tdActive;
}

function _tdStopPropagation(e) {
	e = e || window.event;
	if (e.stopPropagation) e.stopPropagation();
	else e.cancelBubble = true;
}

function _tdHasClass(el, classes) {
	var classNames = el.className;
	if (!classNames) return false;
	classes = classes.split(' ');
	for (var i = 0, j = classes.length; j-- > 0;) {
		if (classNames.indexOf(classes[j]) !== -1) i++;
	}
	return i == classes.length;
}

function _tdAddClass(el, classes) {
	var classNames = el.className;
	if (!classNames) {
		el.className = classes;
		return el;
	}
	classes = classes.split(' ');
	for (var i = 0, j = classes.length; i < j; i++) {
		if (classNames.indexOf(classes[i]) === -1) classNames += ' ' + classes[i];
	}
	el.className = classNames;
	return el;
}

function _tdRemoveClass(el, classes) {
	var classNames = el.className.split(' ');
	if (classNames.length == 0) { return el; }
	var newClassNames = [];
	for (var i = 0, j = classes.length; i < j; i++) {
		if (classNames[i] && classes.indexOf(classNames[i]) === -1) newClassNames.push(classNames[i]);
	}
	el.className = newClassNames.join(' ');
	return el;
}

function _tdGetWindowSize() {
	return [
		window.innerWidth || document.documentElement.clientWidth || document.getElementsByTagName('body')[0].clientWidth,
		window.innerHeight || document.documentElement.clientHeight || document.getElementsByTagName('body')[0].clientHeight
	];
}