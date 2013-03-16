/**
 * Copyright (c) 2013 Stefan Fiedler
 * Object for TimeDebug GUI
 * @author: Stefan Fiedler
 */

// TODO: udelat selectnuti od oteviraci zavorky az do koncove 1s nebo do zmacknuti nasledujiciho znaku

// TODO: on-line podstrceni hodnoty pri dumpovani
// TODO: on-line podstrceni hodnoty pri logovani (jen logovane objekty v td)

// TODO: udelat pridavani prvku do pole
// TODO: ulozit nastaveni do cookie a/nebo vyexportovat do textarea
// TODO: ulozit serii automatickych otevreni TimeDebugu
// TODO: vypnout logovani


// TODO: zkontrolovat dumpovani resources

// TODO: vyplivnout vystup do iframe nebo dalsiho okna
// TODO: vytvorit unit testy

var TimeDebug = {};

TimeDebug.local = false;

TimeDebug.logView = JAK.gel('logView');
TimeDebug.logWrapper = TimeDebug.logView.parentNode;
TimeDebug.logContainer = TimeDebug.logWrapper.parentNode;
TimeDebug.logRows = [];
TimeDebug.logRowsChosen = [];
TimeDebug.logRowActive = null;
TimeDebug.logRowActiveId = 0;
TimeDebug.dumps = [];
TimeDebug.indexes = [];

TimeDebug.tdContainer = JAK.mel('div', {id:'tdContainer'});
TimeDebug.tdOuterWrapper = JAK.mel('div', {id:'tdOuterWrapper'});
TimeDebug.tdInnerWrapper = JAK.mel('div', {id:'tdInnerWrapper'});
TimeDebug.tdView = JAK.mel('pre', {id:'tdView'});
TimeDebug.tdView.activeChilds = [];
TimeDebug.tdListeners = [];
TimeDebug.tdFullWidth = false;
TimeDebug.tdWidth = 400;

TimeDebug.help = JAK.cel('div', 'nd-help');
TimeDebug.helpSpaceX = 0;
TimeDebug.helpHtml = '';

TimeDebug.visibleTitles = [];
TimeDebug.titleActive = null;
TimeDebug.titleHideTimeout = null;
TimeDebug.viewSize = JAK.DOM.getDocSize();
TimeDebug.spaceX = 0;
TimeDebug.spaceY = 0;
TimeDebug.zIndexMax = 100;

TimeDebug.actionData = { element: null, listeners: [] };

TimeDebug.tdConsole = null;
TimeDebug.consoleConfig = {'x':600, 'y':340};
TimeDebug.textareaTimeout = null;
TimeDebug.consoleHoverTimeout = null;
TimeDebug.changes = [];
TimeDebug.tdChangeList = JAK.mel('div', {'id':'tdChangeList'});
TimeDebug.deleteChange = JAK.mel('div', {'id':'tdDeleteChange', 'innerHTML':'X', 'showLogRow':true});
TimeDebug.hoveredChange = null;

TimeDebug.tdHashEl = null;
TimeDebug.tdAnchor = JAK.mel('a', {'name':'tdanchor'});
TimeDebug.logAnchor = JAK.mel('a', {'name':'loganchor', 'id':'logAnchor'});
TimeDebug.setLocHashTimeout = null;
TimeDebug.locationHashes = [];

TimeDebug.encodeChars = {'&':'&amp;', '<':'&lt;', '>':'&gt;'};

TimeDebug.jsonReplaces = {
	"dblquotes": [[/"/g, '\\"']],
	"quotes": [[/'/g, '"']],
	"commas": ['fixCommas'],
	"objects": ['fixObjects'],
	"keys": [[/(\{\s*|,\s*)([^\{\}\[\]'",:\s]*)(?=\s*:)/gm, '$1"$2"']],
	"constants": [[/\b(true|false|null)\b/gi, function(w) { return w.toLowerCase(); }]]
};

TimeDebug.jsonRepairs = [
	["commas"],
	["dblquotes", "quotes", "commas"],
	["keys"],
	["constants"],
	["dblquotes", "quotes", "keys", "constants"],
	["quotes", "keys", "constants"],
	["objects", "keys", "constants"],
	["objects", "keys", "commas", "constants"],
	["dblquotes", "quotes", "objects", "keys", "constants"],
	["quotes", "objects", "keys", "constants"],
	["dblquotes", "quotes", "objects", "keys", "commas", "constants"],
	["quotes", "objects", "keys", "commas", "constants"]
];

TimeDebug.keyChanges = {
	"'":["'", "'"], '"':['"', '"'],
	'[':['[', ']'], ']':['[', ']'],
	'(':['(', ')'], ')':['(', ')'],
	'{':['{', '}'], '}':['{', '}']
};

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
				TimeDebug.setTitles(logNodes[i]);
			} else if (JAK.DOM.hasClass(logNodes[i], 'nd-log')) {
				TimeDebug.logRows.push(logNodes[i]);
				logNodes[i].logId = TimeDebug.logRows.length;
				logNodes[i].onclick = TimeDebug.logClick;
				TimeDebug.setTitles(logNodes[i]);
				links = logNodes[i].getElementsByTagName('a');
				for (k = links.length; k-- > 0;) links[k].onclick = JAK.Events.stopEvent;
			} else if (JAK.DOM.hasClass(logNodes[i], 'nd-view-dump')) {
				TimeDebug.dumps.push(logNodes[i]);
			}
		}
	}

	TimeDebug.logRowsChosen.length = TimeDebug.logRows.length;

	TimeDebug.tdInnerWrapper.appendChild(TimeDebug.tdView);
	TimeDebug.tdOuterWrapper.appendChild(TimeDebug.tdInnerWrapper);
	TimeDebug.tdContainer.appendChild(TimeDebug.tdOuterWrapper);
	document.body.insertBefore(TimeDebug.tdContainer, document.body.childNodes[0]);

	TimeDebug.help.innerHTML = '<span class="nd-titled"><span id="menuTitle" class="nd-title"><strong class="nd-inner">'
			+ '<hr><div class="nd-menu">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
			+ (TimeDebug.local ? '<span id="tdMenuSend"><b>odeslat</b></span>&nbsp;&nbsp;&nbsp;&nbsp;' : '')
			+ '<span onclick="TimeDebug.restore()">obnovit</span>&nbsp;&nbsp;&nbsp;&nbsp;'
			+ '<span class="nd-titled"><span id="helpTitle" class="nd-title"><strong class="nd-inner">'
			+ TimeDebug.helpHtml
			+ '</strong></span>napoveda</span>'
			+ '     |&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span>export</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;<span>import</span>'
			+ '     |&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span>ulozit</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;<span>nahrat</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;<span>smazat</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div><hr>'
			+ '</strong></span>*</span>';
	document.body.appendChild(TimeDebug.help);
	TimeDebug.help.onmousedown = TimeDebug.logAction;
	TimeDebug.setTitles(TimeDebug.help);
	TimeDebug.helpSpaceX = TimeDebug.help.clientWidth + JAK.DOM.scrollbarWidth();
	JAK.gel('menuTitle').appendChild(TimeDebug.tdChangeList);
	if (TimeDebug.local) JAK.Events.addListener(JAK.gel('tdMenuSend'), 'click', TimeDebug, 'sendChanges');

	TimeDebug.showDump(logId);
	window.onresize = TimeDebug.windowResize;
	document.onkeydown = TimeDebug.readKeyDown;
	document.body.oncontextmenu = TimeDebug.tdFalse;
	TimeDebug.tdInnerWrapper.onmousedown = TimeDebug.changeVar;
	if (window.addEventListener) window.addEventListener('DOMMouseScroll', TimeDebug.mouseWheel, false);
	window.onmousewheel = document.onmousewheel = TimeDebug.mouseWheel;
};

TimeDebug.mouseWheel = function(e) {
	e = e || window.event;
	var el = JAK.Events.getTarget(e);
	el = el.tagName.toLowerCase() === 'b' ? el.parentNode : el;

	if (TimeDebug.titleActive === null && !JAK.DOM.hasClass(el, 'nd-titled')) return true;

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);

	el = TimeDebug.titleActive || el.tdTitle;

	var delta = 0;

	if (e.wheelDelta) delta = (e.wheelDelta / 120 > 0 ? -16 : 16);
	else if (e.detail) delta = (e.detail / 3 < 0 ? -16 : 16);

	el.scrollTop = Math.max(0, 16 * parseInt((el.scrollTop + delta) / 16));
	return false;
};

TimeDebug.changeVar = function(e) {
	e = e || window.event;

	if (!TimeDebug.local || e.altKey || e.shiftKey || e.ctrlKey || e.metaKey || e.button !== JAK.Browser.mouse.right) return true;

	var el = JAK.Events.getTarget(e);
	el = el.tagName.toLowerCase() === 'b' ? el.parentNode : el;

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);

	if (JAK.DOM.hasClass(el, 'nd-key')) {
		TimeDebug.consoleOpen(el, TimeDebug.saveVarChange);
	} else if (JAK.DOM.hasClass(el, 'nd-top')) {
		TimeDebug.hideTitle(TimeDebug.titleActive);
		TimeDebug.consoleOpen(el, TimeDebug.saveVarChange);
	}

	return false;
};

TimeDebug.changeAction = function(e) {
	e = e || window.event;

	if (!TimeDebug.local || e.altKey || e.ctrlKey || e.metaKey) return true;

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);

	var el = JAK.Events.getTarget(e);
	var hashes = [];

	if (e.button === JAK.Browser.mouse.right) {
		if (el.id === 'tdDeleteChange') {
			this.deleteMe = true;

			TimeDebug.updateChangeList();
			TimeDebug.tdChangeList.removeChild(this);
			return false;
		}

		if (this.logRow) TimeDebug.showLog(true, this.logRow);
		TimeDebug.consoleOpen(this.varEl, TimeDebug.saveVarChange);
	} else if (e.button === JAK.Browser.mouse.left) {
		if (el.id === 'tdDeleteChange') {
			el.showLogRow = !el.showLogRow;
			TimeDebug.checkDeleteChange();
			return false;
		}
		if (e.shiftKey) {
			if (this.valid) {
				if (!this.formated) {
					var formated = TimeDebug.formatJson(this.data.value);
					if (formated === false) return this.formated = false;
					this.formated = true;
					this.varEl.title = this.title = formated;
					TimeDebug.updateChangeList(this);
				}
			} else {
				this.valid = true;
				this.varEl.title = this.title = JSON.stringify(this.data.value);
				TimeDebug.updateChangeList(this);
			}
			return false;
		}
		if (this.logRow) {
			TimeDebug.showLog(true, this.logRow);

			if (TimeDebug.tdFullWidth) {
				hashes.push([TimeDebug.tdInnerWrapper, 'tdanchor', TimeDebug.tdContainer, 150]);
			} else {
				hashes.push([TimeDebug.tdInnerWrapper, 'tdanchor', TimeDebug.tdContainer, 50]);
				hashes.push([TimeDebug.logWrapper, 'loganchor', TimeDebug.logContainer, 50]);
			}
		} else {
			hashes.push([TimeDebug.logWrapper, 'tdanchor', TimeDebug.logContainer, 50]);
		}

		TimeDebug.setLocationHashes(true, hashes);
	} else return true;

	return false;
};

TimeDebug.formatJson = function(json) {
	var text = JSON.stringify(json);
	var escaped = false, retVal = '';

	var quotes = {'"': false};
	var nested = {'[': 0, '{': 0};
	var ends = {']': '[', '}': '{'};

	for (var i = 0, j = text.length; i < j; i++) {
		if (escaped) escaped = false;
		else if (text[i] === '\\') escaped = true;
		else if (quotes.hasOwnProperty(text[i])) quotes[text[i]] = !quotes[text[i]];
		else if (!quotes['"']) {
			if (nested.hasOwnProperty(text[i])) {
				++nested[text[i]];
				retVal += text[i] + '\n' + TimeDebug.padJson(nested);
				continue;
			} else if (ends.hasOwnProperty(text[i])) {
				if (--nested[ends[text[i]]] < 0) return false;
				retVal += '\n' + TimeDebug.padJson(nested) + text[i];
				continue;
			} else if (text[i] === ',') {
				retVal += text[i] + '\n' + TimeDebug.padJson(nested);
				continue;
			} else if (text[i] === ':') {
				retVal += text[i] + ' ';
				continue;
			}
		}
		retVal += text[i];
	}

	if (nested['['] || nested['{'] || quotes['"']) return false;
	return retVal;
};

TimeDebug.padJson = function(nested) {
	var retVal = '';
	for (var i = nested['['] + nested['{']; i-- > 0;) {
		retVal += '    ';
	}
	return retVal;
};

TimeDebug.setLocationHashes = function(e, hashes) {
	var i;
	if (TimeDebug.setLocHashTimeout) {
		window.clearTimeout(TimeDebug.setLocHashTimeout);
		TimeDebug.setLocHashTimeout = null;
	}
	if (e === true) {
		TimeDebug.locationHashes = hashes;
		TimeDebug.setLocHashTimeout = window.setTimeout(TimeDebug.setLocationHashes, 1);
	} else if (i = (hashes = TimeDebug.locationHashes).length) {
		while (i-- > 0) {
			hashes[i][0].style.height = (2 * hashes[i][0].clientHeight) + 'px';
			window.location.hash = hashes[i][1];
			hashes[i][2].scrollTop -= hashes[i][3];
			hashes[i][0].removeAttribute('style');
		}
		TimeDebug.locationHashes.length = 0;
		window.location.hash = null;
	}
};

TimeDebug.printPath = function(path) {
	path = (path || '').split(',');
	var i, j = path.length, k, close = '', key, retKey, retVal = '', elStart = '', elEnd = '';

	if (path[0] == 'log') {
		retVal = '<b>' + path[1] + '</b>(' + path[i = 2] + ') ';
	} else if (path[0] == 'dump') {
		retVal = '<b>' + path[i = 1] + '</b> ';
	} else return '';

	while (++i < j) {
		if (path[i][0] === '*') {
			k = parseInt(path[i][1]);
			retKey = '<b class="nd-reflection">' + (key = path[i].substring(2)) + '</b>';
			elStart = '<i class="nd-private">';
			elEnd = '</i>';
		} else if (path[i][0] === '#') {
			k = parseInt(path[i][1]);
			retKey = '<b class="nd-array-access">' + (key = path[i].substring(2)) + '</b>';
			elStart = '<i class="nd-private">';
			elEnd = '</i>';
		} else {
			k = parseInt(path[i][0]);
			retKey = elStart + (key = path[i].substring(1) || 'array') + elEnd;
			elStart = '';
			elEnd = '';
		}
		if (k > 6) break;

		retVal += (!close || key == parseInt(key) ? retKey + close : "'" + retKey + "'" + close);

		if (k % 2) {
			retVal += '->'; close = "";
		} else {
			retVal += "["; close = "]";
		}
	}

	if (k == 9) retVal += '<i>(' + retKey + ')</i> =';
	else retVal += (!close || key == parseInt(key) ? retKey + close : "'" + retKey + "'" + close) + ' =';

	return retVal;
};

TimeDebug.updateChangeList = function(el) {
	var change;
	var i = TimeDebug.changes.length, j;
	if (el) el.lastChange = true;

	TimeDebug.changes.sort(function(b,a) {
		return (parseFloat(a.runtime) - parseFloat(b.runtime)) ||
				(a.varEl.parentPrefix !== b.varEl.parentPrefix ? a.varEl.parentPrefix > b.varEl.parentPrefix :
						(a.varEl.parentIndex !== b.varEl.parentIndex ? a.varEl.parentIndex > b.varEl.parentIndex :
								a.varEl.changeIndex > b.varEl.changeIndex)
				);
	});

	while (i-- > 0) {
		change = TimeDebug.changes[i];
		if (change.deleteMe === true) {
			change.style.display = 'none';
			if (change.logRow && change.logRow.varChanges && (j = change.logRow.varChanges.indexOf(change.varEl)) != -1) {
				change.logRow.varChanges.splice(j, 1);
			}
			if (change.listeners.length) JAK.Events.removeListeners(change.listeners);
			TimeDebug.deactivateChange(true, change.varEl);
			if (change.varEl.hideEl) change.varEl.hideEl.removeAttribute('style');
			change.varEl.parentNode.removeChild(change.varEl);
			TimeDebug.changes.splice(i, 1);
			continue;
		}

		change.innerHTML = '[' + change.runtime + '] ' + TimeDebug.printPath(change.data.path) + ' <span class="nd-'
				+ (change.valid ? 'valid' : 'invalid') +'-json' + (change.formated ? ' nd-formated' : '') + '">'
				+ JSON.stringify(change.data.value) + '</span>';

		if (change.lastChange) {
			change.id = 'tdLastChange';
			change.lastChange = false;
		} else if (el) change.removeAttribute('id');
		change.title = change.varEl.title;
		TimeDebug.tdChangeList.appendChild(change);
	}
	TimeDebug.changes.reverse();

	el = TimeDebug.tdChangeList.parentNode;

	if (typeof(el.menuWidth) == 'undefined' && el.oriWidth) {
		el.menuWidth = el.oriWidth;
		el.menuHeight = el.oriHeight;
	}
	if (el.oriWidth && el.style.display != 'none') {
		el.style.width = 'auto';
		el.oriWidth = Math.max(el.menuWidth, TimeDebug.tdChangeList.clientWidth);
		el.oriHeight = el.menuHeight + (el.changesHeight = TimeDebug.tdChangeList.clientHeight);
		TimeDebug.titleAutosize(el);
	}
};

TimeDebug.parseJson = function(text) {
	var i = 0, j = TimeDebug.jsonRepairs.length, retObj = TimeDebug.testJson(text);
	if (retObj.status && (retObj.valid = true)) return retObj;

	for (;i < j; ++i) {
		if ((retObj = TimeDebug.testJson(text, TimeDebug.jsonRepairs[i])).status) return (retObj.valid = false) || retObj;
	}
	return {'status': false};
};

TimeDebug.testJson = function(text, tests) {
	tests = tests || [];
	var i, j = tests.length, k, l, json, test;

	if (!j) {
		try {
			json = JSON.parse(text);
			return {'status': true, 'json': json};
		} catch(e) { return {'status': false} }
	}

	for (i = 0; i < j; ++i) {
		for (k = 0, l = (test = TimeDebug.jsonReplaces[tests[i]]).length; k < l; ++k) {
			if (test[k] === 'fixCommas') text = TimeDebug.jsonFixCommas(text);
			else if (test[k] === 'fixObjects') text = TimeDebug.jsonFixObjects(text);
			else text = text.replace(test[k][0], test[k][1]);
		}
		try {
			json = JSON.parse(text);
			return {'status': true, 'json': json};
		} catch(e) {}
	}

	return {'status': false};
};

TimeDebug.jsonFixCommas = function(text) {
	var retVal = '', escaped = false, nested = false, replace;

	for (var i = 0, j = text.length; i < j;) {
		if (escaped) escaped = false;
		else if (text[i] === '\\') escaped = true;
		else if (text[i] === "'" || text[i] === '"') nested = !nested;
		else if (!nested) {
			if ('0123456789'.indexOf(text[i]) !== -1 && (replace = text.slice(i).match(/^\d+,\d+/))) {
				retVal += replace[0].replace(',', '.');
				i += replace[0].length;
				continue;
			} else if (text[i] === ',' && text.slice(i).match(/^,\s*(?:\]|\})/m) && ++i) continue;
		}
		retVal += text[i];
		++i;
	}

	return nested ? text : retVal;
};

TimeDebug.jsonFixObjects = function(text) {
	var retVal = '', nested = 0, arrayLevels = [], escaped = false, ch;
	var quotes = {"'": false, '"': false};

	for (var i = 0, j = text.length; i < j; i++) {
		ch = text[i];
		if (escaped) escaped = false;
		else if (text[i] === '\\') escaped = true;
		else if (quotes.hasOwnProperty(text[i])) quotes[text[i]] = !quotes[text[i]];
		else if (!quotes["'"] && !quotes['"']) {
			if (text[i] === '[' && (arrayLevels[++nested] = (TimeDebug.findNearestChar(text, ':', i + 1) !== false))) ch = '{';
			else if (text[i] === ']' && arrayLevels[nested--]) ch = '}';
		}
		retVal += ch;
	}

	return nested ? text : retVal;
};

TimeDebug.sumNested = function(nested) {
	nested = nested || {};
	var retVal = 0;
	for (var i in nested) {
		if (nested.hasOwnProperty(i)) retVal += nested[i];
	}
	return retVal;
};

TimeDebug.noQuotes = function(quotes) {
	var retVal = false;
	for (var i in quotes) {
		if (quotes.hasOwnProperty(i)) retVal = retVal || quotes[i];
		if (retVal) break;
	}
	return !retVal;
};

TimeDebug.findNearestChar = function(text, chars, index, rev, quotes) {
	index = index || 0;
	rev = rev || false;
	quotes = quotes || {"'": false, '"': false};

	var nested = {'[': 0, '{': 0};
	var ends = {']': '[', '}': '{'};
	var escaped = false;
	var found = [];
	var i, j, k;

	if (rev) {
		for (i = 0, j = text.length; i < j; i++) {
			if (i === index) {
				var indexLevel = TimeDebug.sumNested(nested);
				for (k = found.length; k-- > 0;) {
					if (found[k].level === indexLevel) return found[k].index;
					else if (found[k].level < indexLevel) return false;
				}
				return false;
			} else if (escaped) escaped = false;
			else if (text[i] === '\\') escaped = true;
			else if (quotes.hasOwnProperty(text[i])) quotes[text[i]] = !quotes[text[i]];
			else if (i < index && TimeDebug.noQuotes(quotes)) {
				if (nested.hasOwnProperty(text[i])) ++nested[text[i]];
				else if (ends.hasOwnProperty(text[i]) && --nested[ends[text[i]]] < 0) return false;
				if (chars.indexOf(text[i]) !== -1) found.push({'index': i, 'level': TimeDebug.sumNested(nested)});
			}
		}
	} else {
		for (i = 0, j = text.length; i < j; i++) {
			if (escaped) escaped = false;
			else if (text[i] === '\\') escaped = true;
			else if (quotes.hasOwnProperty(text[i])) quotes[text[i]] = !quotes[text[i]];
			else if (i >= index && TimeDebug.noQuotes(quotes)) {
				if (chars.indexOf(text[i]) !== -1 && !TimeDebug.sumNested(nested)) return i;
				else if (nested.hasOwnProperty(text[i])) ++nested[text[i]];
				else if (ends.hasOwnProperty(text[i]) && --nested[ends[text[i]]] < 0) return false;
			}
		}
	}

	return false;
};

TimeDebug.saveVarChange = function() {
	var varEl = TimeDebug.tdConsole.parentNode;
	var el = varEl;
	var key = parseInt(el.getAttribute('data-pk')) || 8;
	var privateVar = !!(key % 2);

	var areaVal = TimeDebug.tdConsole.area.value;
	var i = -1, j, k, s = TimeDebug.parseJson(areaVal);
	var value;
	var valid = true;

	var revPath = [];
	var runTime;
	var change;
	var logClone = false;
	var changeEls;
	var mouseOver = false;

	if (s.status) {
		value = s.json;
		valid = s.valid;
	} else {
		value = areaVal;
		valid = false;
	}

	TimeDebug.consoleClose();

	if (JAK.DOM.hasClass(el, 'nd-key')) {
		revPath.push(key + el.innerHTML);

		while ((el = el.parentNode) && el.tagName.toLowerCase() == 'div' && null !== (key = el.getAttribute('data-pk'))) {
			if (parseInt(key[0]) % 2 && (++i + 1) && privateVar) {
				revPath.push((i ? '#' : '*') + key);
				privateVar = false;
			} else revPath.push(key);
			if (key[0] === '3' || key[0] === '4') privateVar = true;
		}
		if (JAK.DOM.hasClass(el, 'nd-dump')) {
			revPath.push(el.id, 'dump');
			runTime = (el.attrRuntime || (el.attrRuntime = el.getAttribute('data-runtime')));
		} else {
			revPath.push(parseInt(el.getAttribute('data-tdindex')), TimeDebug.logRowActive.id, 'log');
			runTime = (TimeDebug.logRowActive.attrRuntime || (TimeDebug.logRowActive.attrRuntime = TimeDebug.logRowActive.getAttribute('data-runtime')));
			logClone = el.parentNode;
		}
	} else if (JAK.DOM.hasClass(el, 'nd-top')) {
		revPath.push('9' + el.className.split(' ')[0].split('-')[1]);
		while ((el = el.parentNode) && el.tagName.toLowerCase() != 'pre') {}
		revPath.push(el.id, 'dump');
		runTime = (el.attrRuntime || (el.attrRuntime = el.getAttribute('data-runtime')));
	} else return false;

	if (change = varEl.varListRow) {
		if (change.data.value === value && change.valid === valid) return true;
		change.data.value = value;
		if (change.valid = valid) change.formated = (areaVal === TimeDebug.formatJson(value));
		else change.formated = false;
	} else {
		change = JAK.mel('pre', {className:'nd-change-data'});

		var newEl = varEl.cloneNode(true);
		newEl.hideEl = varEl;
		varEl.parentNode.insertBefore(newEl, varEl);
		varEl.style.display = 'none';
		varEl = newEl;

		if (logClone) {
			if (typeof(TimeDebug.logRowActive.varChanges) == 'undefined') TimeDebug.logRowActive.varChanges = [varEl];
			else TimeDebug.logRowActive.varChanges.push(varEl);
			change.logRow = TimeDebug.logRowActive;
			mouseOver = JAK.Events.addListener(change, 'mouseover', change.logRow, TimeDebug.showLog);

			JAK.DOM.addClass(varEl, 'nd-var-change');
			changeEls = JAK.DOM.getElementsByClass('nd-var-change', logClone);
			for (i = 0, j = changeEls.length, k = 0; i < j; ++i) {
				if (change.logRow.varChanges.indexOf(changeEls[i]) != -1) {
					changeEls[i].parentPrefix = (key = change.logRow.id.split('_'))[0];
					changeEls[i].parentIndex = key[1];
					changeEls[i].changeIndex = k++;
				}
			}
		} else {
			JAK.DOM.addClass(varEl, 'nd-var-change');
			changeEls = JAK.DOM.getElementsByClass('nd-var-change', el);
			for (i = 0, j = changeEls.length; i < j; ++i) {
				changeEls[i].parentPrefix = (key = el.id.split('_'))[0];
				changeEls[i].parentIndex = key[1];
				changeEls[i].changeIndex = i;
			}
		}

		change.data = {'path':revPath.reverse().join(','), 'value':value};
		TimeDebug.changes.push(change);
		change.valid = valid;
		change.runtime = runTime;
		change.varEl = varEl;
		varEl.varListRow = change;
		change.listeners = [
			JAK.Events.addListener(varEl, 'mouseover', change, TimeDebug.hoverChange),
			JAK.Events.addListener(varEl, 'mouseout', change, TimeDebug.unhoverChange),
			JAK.Events.addListener(change, 'mouseover', change, TimeDebug.activateChange),
			JAK.Events.addListener(change, 'mouseout', change, TimeDebug.deactivateChange),
			JAK.Events.addListener(change, 'mousedown', change, TimeDebug.changeAction)
		];
		if (mouseOver) change.listeners.push(mouseOver);
	}

	varEl.title = areaVal;

	TimeDebug.updateChangeList(change);
	return true;
};

TimeDebug.checkDeleteChange = function() {
	if (TimeDebug.deleteChange.showLogRow === true) TimeDebug.deleteChange.style.textDecoration = 'underline';
	else TimeDebug.deleteChange.removeAttribute('style');
	return TimeDebug.deleteChange;
};

TimeDebug.activateChange = function(e, el) {
	TimeDebug.hoveredChange = e === true ? el : this;
	TimeDebug.tdHashEl = TimeDebug.hoveredChange.varEl;
	TimeDebug.tdHashEl.parentNode.insertBefore(TimeDebug.tdAnchor, TimeDebug.tdHashEl);
	JAK.DOM.addClass(TimeDebug.tdHashEl, 'nd-hovered');

	TimeDebug.hoveredChange.appendChild(TimeDebug.checkDeleteChange());
};

TimeDebug.deactivateChange = function(e, el) {
	el = e === true ? el : this.varEl;

	if (el === TimeDebug.tdHashEl) TimeDebug.tdHashEl = null;
	JAK.DOM.removeClass(el, 'nd-hovered');

	if (this === TimeDebug.hoveredChange) TimeDebug.hoveredChange = null;
};

TimeDebug.hoverChange = function() {
	JAK.DOM.addClass(this, 'nd-hovered');
};

TimeDebug.unhoverChange = function() {
	JAK.DOM.removeClass(this, 'nd-hovered');
};

TimeDebug.consoleHover = function(areaClass) {
	if (TimeDebug.consoleHoverTimeout) {
		window.clearTimeout(TimeDebug.consoleHoverTimeout);
		TimeDebug.consoleHoverTimeout = null;
	}

	if (areaClass) {
		TimeDebug.tdConsole.area.className = areaClass;
		TimeDebug.consoleHoverTimeout = window.setTimeout(TimeDebug.consoleHover, 400);
	} else TimeDebug.tdConsole.area.removeAttribute('class');
};

TimeDebug.consoleAction = function(e) {
	e = e || window.event;

	if (e.button === JAK.Browser.mouse.left && (e.ctrlKey || e.metaKey)) {
		JAK.Events.cancelDef(e);
		JAK.Events.stopEvent(e);

		if (e.shiftKey && !e.altKey) {
			var parsed = TimeDebug.parseJson(this.value);
			if (parsed.status) {
				var formated = TimeDebug.formatJson(parsed.json);
				if (formated === this.value) return false;
				if (!parsed.valid) TimeDebug.consoleHover('nd-area-parsed');
				TimeDebug.areaWrite(this, TimeDebug.formatJson(parsed.json), this.selectionStart);
			} else TimeDebug.consoleHover('nd-area-error');
		} else if (!e.shiftKey && e.altKey) {
			var cc = TimeDebug.consoleConfig;
			if (typeof(cc.oriX) != 'undefined') {
				cc.x = cc.oriX;
				cc.y = cc.oriY;
			}
			JAK.DOM.setStyle(this, {'width': cc.x + 'px', 'height': cc.y + 'px'});
		}
		return false;
	}
	return true;
};

TimeDebug.consoleOpen = function(el, callback) {
	TimeDebug.tdConsole = JAK.mel('span', {'id':'tdConsole'});
	TimeDebug.tdConsole.mask = JAK.mel('span', {'id':'tdConsoleMask'});

	var attribs = {id:'tdConsoleArea'};
  if (el.title) {
	  attribs.value = TimeDebug.tdConsole.mask.title = el.title;
	  el.title = null;
  }

	TimeDebug.tdConsole.area = JAK.mel('textarea', attribs, {'width':TimeDebug.consoleConfig.x + 'px',
		'height':TimeDebug.consoleConfig.y + 'px'});
	TimeDebug.tdConsole.appendChild(TimeDebug.tdConsole.mask);
	TimeDebug.tdConsole.appendChild(TimeDebug.tdConsole.area);
	el.appendChild(TimeDebug.tdConsole);

	TimeDebug.tdConsole.listeners = [
		JAK.Events.addListener(TimeDebug.tdConsole.area, 'keypress', TimeDebug.tdConsole.area, TimeDebug.readConsoleKeyPress),
		JAK.Events.addListener(TimeDebug.tdConsole.area, 'mousedown', TimeDebug.tdConsole.area, TimeDebug.consoleAction),
		JAK.Events.addListener(TimeDebug.tdConsole.mask, 'mousedown', TimeDebug.tdConsole.mask, TimeDebug.catchMask)
	];

	TimeDebug.tdConsole.callback = callback || TimeDebug.tdStop;
	TimeDebug.textareaTimeout = window.setTimeout(TimeDebug.textareaFocus, 1);
};

TimeDebug.textareaFocus = function() {
	if (TimeDebug.textareaTimeout) {
		window.clearTimeout(TimeDebug.textareaTimeout);
		TimeDebug.textareaTimeout = null;
	}
	TimeDebug.tdConsole.area.focus();
	TimeDebug.tdConsole.area.select();
};

TimeDebug.catchMask = function(e) {
	e = e || window.event;

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	if (e.button === JAK.Browser.mouse.right && this.title == TimeDebug.tdConsole.area.value) TimeDebug.consoleClose();
	else TimeDebug.tdConsole.area.focus();

	return false;
};

TimeDebug.consoleClose = function() {
	if (TimeDebug.tdConsole.parentNode.varListRow) {
		JAK.DOM.removeClass(TimeDebug.tdConsole.parentNode.varListRow, 'nd-hovered');
	}
	if (TimeDebug.tdConsole.listeners) {
		JAK.Events.removeListeners(TimeDebug.tdConsole.listeners);
		TimeDebug.tdConsole.listeners = null;
	}

	var cc = TimeDebug.consoleConfig;
	if (typeof(cc.oriX) == 'undefined') {
		cc.oriX = cc.x;
		cc.oriY = cc.y;
	}

	cc.x = TimeDebug.tdConsole.area.offsetWidth - 8;
	cc.y = TimeDebug.tdConsole.area.clientHeight;

	if (TimeDebug.tdConsole.mask.title) TimeDebug.tdConsole.parentNode.title = TimeDebug.tdConsole.mask.title;
	TimeDebug.tdConsole.parentNode.removeChild(TimeDebug.tdConsole);
	TimeDebug.tdConsole = null;
	return false;
};

TimeDebug.logAction = function(e) {
	e = e || window.event;

	if ((e.altKey ? e.shiftKey || TimeDebug.tdFullWidth : !e.shiftKey) || e.ctrlKey || e.metaKey || e.button !== JAK.Browser.mouse.left) return true;

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
		if (TimeDebug.tdFullWidth) {
			TimeDebug.tdFullWidth = false;
			JAK.DOM.removeClass(document.body.parentNode, 'nd-fullscreen');

			document.body.style.marginLeft = TimeDebug.tdContainer.style.width = TimeDebug.help.style.left = TimeDebug.tdWidth + 'px';
			TimeDebug.logContainer.style.width = 'auto';
			TimeDebug.logRowActive.removeAttribute('style');
		} else {
			TimeDebug.tdFullWidth = true;
			JAK.DOM.addClass(document.body.parentNode, 'nd-fullscreen');

			document.body.style.marginLeft = TimeDebug.help.style.left = 0;
			TimeDebug.tdContainer.style.width = '100%';
			TimeDebug.logContainer.style.width = TimeDebug.tdContainer.clientWidth + 'px';
			TimeDebug.logRowActive.style.width = (TimeDebug.tdContainer.clientWidth - 48) + 'px';
		}

		TimeDebug.tdResizeWrapper();
	}

	return false;
};

TimeDebug.logResizing = function(e) {
	e = e || window.event;
	var el = TimeDebug.actionData.element;

	if (e.button === JAK.Browser.mouse.left) {
		TimeDebug.tdWidth = Math.max(0, Math.min(TimeDebug.viewSize.width - TimeDebug.helpSpaceX,
				TimeDebug.actionData.width + e.screenX - TimeDebug.actionData.startX));

		document.body.style.marginLeft = TimeDebug.tdContainer.style.width = el.style.left = TimeDebug.tdWidth + 'px';

		TimeDebug.tdResizeWrapper();
	} else {
		TimeDebug.endLogResize();
	}
};

TimeDebug.endLogResize = function() {
	JAK.Events.removeListeners(TimeDebug.actionData.listeners);
	TimeDebug.actionData.listeners.length = 0;
	TimeDebug.actionData.element = null;
};

TimeDebug.showVarChanges = function(changes) {
	for (var i = changes.length; i-- > 0;) {
		changes[i].style.display = 'inline';
		changes[i].hideEl.style.display = 'none';
	}
};

TimeDebug.hideVarChanges = function(changes) {
	if (TimeDebug.tdHashEl !== null) TimeDebug.deactivateChange(true, TimeDebug.tdHashEl);
	for (var i = changes.length; i-- > 0;) {
		changes[i].style.display = 'none';
		changes[i].hideEl.style.display = 'inline';
	}
};

TimeDebug.showDump = function(id) {
	if (TimeDebug.logRowActiveId == (id = id || 0)) return false;
	if (TimeDebug.logRowActive) {
		JAK.DOM.removeClass(TimeDebug.logRowActive, 'nd-active');
		TimeDebug.logRowActive.removeAttribute('style');
		if (TimeDebug.logRowActive.varChanges) TimeDebug.hideVarChanges(TimeDebug.logRowActive.varChanges);
	}

	JAK.DOM.addClass(TimeDebug.logRowActive = TimeDebug.logRows[id - 1], 'nd-active');
	if (TimeDebug.logRowActive.varChanges) TimeDebug.showVarChanges(TimeDebug.logRowActive.varChanges);

	TimeDebug.logRowActive.parentNode.insertBefore(TimeDebug.logAnchor, TimeDebug.logRowActive);

	if (TimeDebug.hoveredChange && TimeDebug.hoveredChange.logRow === TimeDebug.logRowActive) {
		TimeDebug.activateChange(true, TimeDebug.hoveredChange);
	}

	document.title = '['
			+ (TimeDebug.logRowActive.attrRuntime || (TimeDebug.logRowActive.attrRuntime = TimeDebug.logRowActive.getAttribute('data-runtime')))
			+ ' ms] TimeDebug::'
			+ (TimeDebug.logRowActive.attrTitle || (TimeDebug.logRowActive.attrTitle = TimeDebug.logRowActive.getAttribute('data-title')));

	if (TimeDebug.indexes[TimeDebug.logRowActiveId - 1] !== TimeDebug.indexes[id - 1]) {

		if (TimeDebug.tdListeners.length) {
			JAK.Events.removeListeners(TimeDebug.tdListeners);
			TimeDebug.tdListeners.length = 0;
		}

		(TimeDebug.tdView.oriId && (TimeDebug.tdView.id = TimeDebug.tdView.oriId)) || TimeDebug.tdInnerWrapper.removeChild(TimeDebug.tdView);

		TimeDebug.tdView = TimeDebug.dumps[TimeDebug.indexes[id - 1]];
		TimeDebug.tdInnerWrapper.appendChild(TimeDebug.tdView);
		if (!TimeDebug.tdView.oriId) {
			TimeDebug.tdInnerWrapper.appendChild(TimeDebug.tdView);
			TimeDebug.tdView.oriId = TimeDebug.tdView.id;
			TimeDebug.tdView.activeChilds = [];
		}
		TimeDebug.tdView.id = 'tdView';
		TimeDebug.setTitles(TimeDebug.tdView);
		TimeDebug.tdResizeWrapper();
	}

	if (TimeDebug.tdFullWidth) TimeDebug.logRowActive.style.width = (TimeDebug.tdContainer.clientWidth - 48) + 'px';
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
					JAK.Events.addListener(titleSpan, 'click', titleSpan, TimeDebug.pinTitle)
			);

			if (!el.titleListener) JAK.Events.addListener(titleSpan.tdTitle, 'mousedown', titleSpan.tdTitle, TimeDebug.titleAction);
		}
	}

	if (el === TimeDebug.tdView) {
		TimeDebug.tdListeners = TimeDebug.tdListeners.concat(listeners);
		if (!el.titleListener) el.titleListener = true;
	}
};

TimeDebug.showTitle = function(e) {
	e = e || window.event;

	if (e.shiftKey || e.altKey || e.ctrlKey || e.metaKey || TimeDebug.actionData.element !== null || TimeDebug.tdConsole !== null) return false;
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
			if (this.tdTitle.id === 'menuTitle') {
				this.tdTitle.menuWidth = this.tdTitle.oriWidth;
				this.tdTitle.menuHeight = this.tdTitle.oriHeight;
				TimeDebug.tdChangeList.style.display = 'block';
			}
		}
		if (this.tdTitle.id === 'menuTitle') {
			this.tdTitle.style.width = 'auto';
			this.tdTitle.oriWidth = Math.max(this.tdTitle.menuWidth, TimeDebug.tdChangeList.clientWidth);
			this.tdTitle.oriHeight = this.tdTitle.menuHeight + (this.tdTitle.changesHeight = TimeDebug.tdChangeList.clientHeight);
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

	if (!this.pined || e.button !== JAK.Browser.mouse.left) return true;

	JAK.Events.stopEvent(e);

	if (this.style.zIndex < TimeDebug.zIndexMax) {
		this.style.zIndex = ++TimeDebug.zIndexMax;

		if (TimeDebug.zIndexMax > TimeDebug.visibleTitles.length + 1000) {
			TimeDebug.visibleTitles.sort(function(a,b) { return parseInt(a.style.zIndex) - parseInt(b.style.zIndex); });
			for (var i = 0, j = TimeDebug.visibleTitles.length; i < j;) TimeDebug.visibleTitles[i].style.zIndex = ++i + 100;
			TimeDebug.zIndexMax = j + 100;
		}
	}

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

	if (e.button === JAK.Browser.mouse.left) {
		el.userWidth = el.tdWidth = Math.max(Math.min(TimeDebug.viewSize.width - el.tdLeft - 20, TimeDebug.actionData.width + e.screenX - TimeDebug.actionData.startX), 16);
		el.userHeight = el.tdHeight = 16 * parseInt(Math.max(Math.min(TimeDebug.viewSize.height - el.tdTop - 35, TimeDebug.actionData.height + e.screenY - TimeDebug.actionData.startY), 16) / 16);

		JAK.DOM.setStyle(el, { width:el.tdWidth + 'px', height:el.tdHeight + 'px' });
	} else {
		TimeDebug.endTitleAction();
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

	if (e.button === JAK.Browser.mouse.left) {
		el.tdLeft = Math.max(Math.min(TimeDebug.viewSize.width - 36, TimeDebug.actionData.offsetX + e.screenX - TimeDebug.actionData.startX), 0);
		el.tdTop = Math.max(Math.min(TimeDebug.viewSize.height - 51, TimeDebug.actionData.offsetY + e.screenY - TimeDebug.actionData.startY), 0);

		JAK.DOM.setStyle(el, { left:el.tdLeft + 'px', top:el.tdTop + 'px' });

		TimeDebug.titleAutosize(el);
	} else {
		TimeDebug.endTitleAction();
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
	} else if (TimeDebug.spaceY < (el.changesHeight || 0) + el.tdInner.clientHeight || TimeDebug.spaceY < el.oriHeight) {
		el.style.height = (el.tdHeight = TimeDebug.spaceY) + 'px';
		if (tdCheckWidthDif && (tdWidthDif = Math.max(el.oriWidth - el.clientWidth, 0))) {
			el.style.width = (el.tdWidth = Math.min(el.oriWidth + tdWidthDif, TimeDebug.spaceX)) + 'px';
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

	if (e.altKey || e.shiftKey || e.ctrlKey || e.metaKey || e.button !== JAK.Browser.mouse.left) return false;

	if (TimeDebug.titleHideTimeout) {
		window.clearTimeout(TimeDebug.titleHideTimeout);
		TimeDebug.titleHideTimeout = null;
	}

	var el = JAK.Events.getTarget(e);
	el = el.tagName.toLowerCase() === 'b' ? el.parentNode : el;

	if (!JAK.DOM.hasClass(el, 'nd-titled')) return false;

	if (TimeDebug.titleActive && TimeDebug.titleActive !== el.tdTitle) TimeDebug.hideTitle();

	if (TimeDebug.titleActive === null) {
		TimeDebug.titleActive = el.tdTitle;
		TimeDebug.titleActive.pined = false;
	} else {
		TimeDebug.titleActive.pined = true;
		TimeDebug.titleActive = null;
	}
	return false;
};

TimeDebug.showLog = function(e, el) {
	if (e === true) {
		TimeDebug.showDump(el.logId);
	} else if (TimeDebug.deleteChange.showLogRow === true) {
		TimeDebug.showDump(this.logId);
	}
};

TimeDebug.logClick = function(e) {
	e = e || window.event;
	var id = parseInt(this.logId);

	if (e.altKey) {
		if (TimeDebug.tdConsole && TimeDebug.keyChanges.indexOf(e.keyCode)) {}
	} else if (e.ctrlKey || e.metaKey) {
		TimeDebug.logRowsChosen[id - 1] = !TimeDebug.logRowsChosen[id - 1];
		if (TimeDebug.logRowsChosen[id - 1]) JAK.DOM.addClass(TimeDebug.logRows[id - 1], 'nd-chosen');
		else JAK.DOM.removeClass(TimeDebug.logRows[id - 1], 'nd-chosen');
	} else if (e.shiftKey) {
		var unset = false;
		if (JAK.DOM.hasClass(this, 'nd-chosen')) unset = true;
		for (var i = Math.min(TimeDebug.logRowActiveId, id), j = Math.max(TimeDebug.logRowActiveId, id); i <= j; ++i) {
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

TimeDebug.readConsoleKeyPress = function(e) {
	e = e || window.event;
	if (!TimeDebug.tdConsole) return true;

	var key = String.fromCharCode(e.which);

	if (e.ctrlKey || e.metaKey) {
		if (key == 'd') return TimeDebug.duplicateText(e, this);
		else if (key == 'y') return TimeDebug.removeRows(e, this);
		else if (key == 'b') return TimeDebug.selectBlock(e, this);
	} else if (TimeDebug.keyChanges[key]) return TimeDebug.wrapSelection(e, this, key);
	
	return true;
};

TimeDebug.readKeyDown = function(e) {
	e = e || window.event;

	var tdNext;

	if (e.shiftKey) {
		if (e.keyCode == 13 && TimeDebug.tdConsole) return TimeDebug.tdConsole.callback();
	} else if (!e.altKey && !e.ctrlKey && !e.metaKey) {
		if (e.keyCode == 38 && !TimeDebug.tdConsole && TimeDebug.logRowActiveId > 1) {
			tdNext = TimeDebug.selected() ? TimeDebug.getPrevious() : TimeDebug.logRowActiveId - 1;
			if (tdNext === TimeDebug.logRowActiveId) return true;
			TimeDebug.showDump(tdNext);
			if (!TimeDebug.tdFullWidth) {
				TimeDebug.setLocationHashes(true, [[TimeDebug.logWrapper, 'loganchor', TimeDebug.logContainer, 50]]);
			}
			return false;
		} else if (e.keyCode == 40 && !TimeDebug.tdConsole && TimeDebug.logRowActiveId < TimeDebug.indexes.length) {
			TimeDebug.logView.blur();
			tdNext = TimeDebug.selected() ? TimeDebug.getNext() : TimeDebug.logRowActiveId + 1;
			if (tdNext === TimeDebug.logRowActiveId) return true;
			TimeDebug.showDump(tdNext);
			if (!TimeDebug.tdFullWidth) {
				TimeDebug.setLocationHashes(true, [[TimeDebug.logWrapper, 'loganchor', TimeDebug.logContainer, 50]]);
			}
			return false;
		} else if (e.keyCode == 37 && TimeDebug.titleActive) {
				TimeDebug.titleActive.scrollTop = 16 * parseInt((TimeDebug.titleActive.scrollTop - 16) / 16);
				return false;
		} else if (e.keyCode == 39 && TimeDebug.titleActive) {
				TimeDebug.titleActive.scrollTop = 16 * parseInt((TimeDebug.titleActive.scrollTop + 16) / 16);
				return false;
		} else if (e.keyCode == 27) {
			if (TimeDebug.tdConsole) return TimeDebug.consoleClose();
			if (!(TimeDebug.visibleTitles.length - (TimeDebug.titleActive === null ? 0 : 1)) || !confirm('Opravdu resetovat nastaveni?')) {
				return true;
			}
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
			JAK.Events.stopEvent(e);
		}
	}
	return true;
};

TimeDebug.windowResize = function() {
	TimeDebug.tdResizeWrapper();
	TimeDebug.viewSize = JAK.DOM.getDocSize();
	if (TimeDebug.tdFullWidth) {
		TimeDebug.logContainer.style.width = TimeDebug.tdContainer.clientWidth + 'px';
		TimeDebug.logRowActive.style.width = (TimeDebug.tdContainer.clientWidth - 48) + 'px';
	}
	for (var i = TimeDebug.visibleTitles.length; i-- > 0;) TimeDebug.titleAutosize(TimeDebug.visibleTitles[i]);
};

TimeDebug.tdResizeWrapper = function() {
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

TimeDebug.htmlEncode = function(text) {
	for (var retVal = '', i = 0, j = text.length; i < j; ++i) retVal += TimeDebug.encodeChars[text[i]] || text[i];
	return retVal;
};

TimeDebug.fire = function(text) {
	if (!--this.counter) {
		this.counter = 1000;
		console.clear();
	}
	console.debug(text);
};

TimeDebug.restore = function() {
	location.href = location.protocol + '//' + location.host + location.pathname;
};

TimeDebug.sendChanges = function(e) {
	e = e || window.event;

	var retVal = [];
	for (var i = 0, j = TimeDebug.changes.length; i < j; ++i) retVal.push(TimeDebug.changes[i].data);

	if (!retVal.length) return false;

	var req = JAK.mel('form', {'action': location.protocol + '//' + location.host + location.pathname, method:'get'}, {'display': 'none'});
	if (e.shiftKey) req.target = '_blank';

	req.appendChild(JAK.mel('textarea', {'name': 'tdrequest', 'value': JSON.stringify(retVal)}));
	TimeDebug.logView.appendChild(req);
	req.submit();

	return false;
};

TimeDebug.getRows = function(el, start, end) {
	var i = el.value.indexOf('\n', end || start);
	return [start - el.value.slice(0, start).split('\n').reverse()[0].length, i === -1 ? el.value.length : i];
};

TimeDebug.duplicateText = function(e, el) {
	var start = el.selectionStart, end = el.selectionEnd;

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	if (start === end || el.value.slice(start, end).indexOf('\n') !== -1) {
		var rows = TimeDebug.getRows(el, start, end);
		TimeDebug.areaWrite(el, el.value.slice(0, rows[1]) + '\n' + el.value.slice(rows[0]), start, end);
	} else TimeDebug.areaWrite(el, el.value.slice(0, end) + el.value.slice(start), start, end);

	return false;
};

TimeDebug.removeRows = function(e, el) {
	var rows = TimeDebug.getRows(el, el.selectionStart, el.selectionEnd);

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	TimeDebug.areaWrite(el, el.value.slice(0, rows[0]) + el.value.slice(rows[1] + 1), rows[0]);

	return false;
};

TimeDebug.getBlock = function(el, index) {
	var blockStart, i = TimeDebug.findNearestChar(el.value, '[{', index, true, {'"': false});
	blockStart = i === false ? 0 : i;

	var blockEnd, j = TimeDebug.findNearestChar(el.value, ']}', index, false, {'"': false});
	blockEnd = j === false ? el.value.length : j + 1;

	return [blockStart, blockEnd];
};

TimeDebug.selectBlock = function(e, el) {
	var start = el.selectionStart, end = el.selectionEnd;
	var i, j, s, longest = [start, end];

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	var rows = el.value.slice(start, end).split('\n');
	var checkPoints = [end];

	for (i = 0, j = rows.length, s = start; i < j; ++i) {
		checkPoints.push(s);
		s += rows[i].length + 1;
	}

	for (i = 0, j = checkPoints.length; i < j; ++i) {
		s = TimeDebug.getBlock(el, checkPoints[i]);
		if (s[1] - s[0] > longest[1] - longest[0]) longest = s;
	}

	el.selectionStart = longest[0];
	el.selectionEnd = longest[1];
	return false;
};

TimeDebug.wrapSelection = function(e, el, key) {
	var start = el.selectionStart, end = el.selectionEnd;
	if (start === end) return true;

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	var swap = TimeDebug.keyChanges[key];
	var retVal = el.value.slice(0, start) + swap[0];

	if (end - start > 1 && (key == "'" || key == '"') && ((el.value[start] == '"' && el.value[end - 1] == '"') || (el.value[start] == "'" && el.value[end - 1] == "'"))) {
		TimeDebug.areaWrite(el, retVal + el.value.slice(start + 1, end - 1) + swap[1] + el.value.slice(end), start + 1, end - 1);
	} else TimeDebug.areaWrite(el, retVal + el.value.slice(start, end) + swap[1] + el.value.slice(end), start + 1, end + 1);

	return false;
};

TimeDebug.areaWrite = function(el, text, start, end) {
	start = start || 0;
	end = end || start;
	var top = el.scrollTop;

	el.value = text;
	el.selectionStart = start;
	el.selectionEnd = end;
	el.scrollTop = top;
};