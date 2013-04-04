/**
 * Copyright (c) 2013 Stefan Fiedler
 * Object for TimeDebug GUI
 * @author: Stefan Fiedler
 */

// TODO: zobrazovani zmen v maximalizovanem rezimu
// TODO: ulozit nastaveni do localstorage a/nebo vyexportovat do konzole
// TODO: ulozit serii testu v TimeDebugu
// TODO: vyplivnout vystup do iframe nebo dalsiho okna

var td = {};

td.local = false;

td.logView = JAK.gel('logView');
td.logWrapper = td.logView.parentNode;
td.logContainer = td.logWrapper.parentNode;
td.logRows = [];
td.logRowsChosen = [];
td.logRowActive = null;
td.logRowActiveId = 0;
td.dumps = [];
td.indexes = [];
td.response = null;

td.tdContainer = JAK.mel('div', {id: 'tdContainer'});
td.tdOuterWrapper = JAK.mel('div', {id: 'tdOuterWrapper'});
td.tdInnerWrapper = JAK.mel('div', {id: 'tdInnerWrapper'});
td.tdView = JAK.mel('pre', {id: 'tdView'});
td.tdView.activeChilds = [];
td.tdListeners = [];
td.tdFullWidth = false;
td.tdWidth = 400;

td.help = JAK.cel('div', 'nd-help');
td.helpSpaceX = 0;
td.helpHtml = '';

td.visibleTitles = [];
td.titleActive = null;
td.titleHideTimeout = null;
td.viewSize = JAK.DOM.getDocSize();
td.spaceX = 0;
td.spaceY = 0;
td.zIndexMax = 100;

td.actionData = { element: null, listeners: [] };

td.tdConsole = null;
td.consoleConfig = {'x': 600, 'y': 340};
td.textareaTimeout = null;
td.consoleHoverTimeout = null;
td.changes = [];
td.tdChangeList = JAK.mel('div', {'id': 'tdChangeList'});
td.deleteChange = JAK.mel('div', {'id': 'tdDeleteChange', 'innerHTML': 'X', 'showLogRow': true});
td.hoveredChange = null;
td.noContainerChangeIndex = 65535;

td.tdHashEl = null;
td.tdAnchor = JAK.mel('a', {'name': 'tdanchor'});
td.logAnchor = JAK.mel('a', {'name': 'loganchor', 'id': 'logAnchor'});
td.setLocHashTimeout = null;
td.locationHashes = [];

td.encodeChars = {'&': '&amp;', '<': '&lt;', '>': '&gt;'};

td.jsonReplaces = {
	"string": [[/^(\w*)$/, '"$1"']],
	"dblquotes": [[/"/g, '\\"']],
	"quotes": [[/'/g, '"']],
	"commas": ['fixCommas'],
	"numbers": ['fixNumbers'],
	"objects": ['fixObjects'],
	"keys": [[/(\{\s*|,\s*)([^\{\}\[\]'",:\s]*)(?=\s*:)/gm, '$1"$2"']],
	"constants": [[/\b(true|false|null)\b/gi, function(w) { return w.toLowerCase(); }]]
};

td.jsonRepairs = [
	["constants"],
	["string"],
	["commas", "numbers"],
	["dblquotes", "quotes", "commas", "numbers"],
	["keys"],
	["dblquotes", "quotes", "keys", "constants"],
	["quotes", "keys", "constants"],
	["objects", "keys", "constants"],
	["objects", "keys", "commas", "numbers", "constants"],
	["dblquotes", "quotes", "objects", "keys", "constants"],
	["quotes", "objects", "keys", "constants"],
	["dblquotes", "quotes", "objects", "keys", "commas", "constants", "numbers"],
	["quotes", "objects", "keys", "commas", "constants", "numbers"]
];

td.keyChanges = {
	"'": ["'", "'"], '"': ['"', '"'],
	'[': ['[', ']'], ']': ['[', ']'],
	'(': ['(', ')'], ')': ['(', ')'],
	'{': ['{', '}'], '}': ['{', '}']
};

td.consoleSelects = {"]": "[", "}": "{"};

td.init = function(logId) {
	JAK.DOM.addClass(document.body.parentNode, 'nd-td' + (td.local ? ' nd-local' : ''));
	document.body.style.marginLeft = td.tdContainer.style.width = td.help.style.left = td.tdWidth + 'px';
	td.viewSize = JAK.DOM.getDocSize();

	var elements, tdIndex;
	var logNodes = td.logView.childNodes;

	for (var i = 0, j = logNodes.length, k; i < j; ++i) {
		if (logNodes[i].nodeType == 1 && logNodes[i].tagName.toLowerCase() == 'pre') {
			if (JAK.DOM.hasClass(logNodes[i], 'nd-dump')) {
				logNodes[i].attrRuntime = logNodes[i].getAttribute('data-runtime');
				logNodes[i].onmousedown = td.changeVar;
				td.setTitles(logNodes[i]);
			} else if (JAK.DOM.hasClass(logNodes[i], 'nd-log')) {
				td.logRows.push(logNodes[i]);
				logNodes[i].logId = td.logRows.length;
				logNodes[i].attrRuntime = logNodes[i].getAttribute('data-runtime');
				logNodes[i].attrTitle = logNodes[i].getAttribute('data-title');
				logNodes[i].onclick = td.logClick;
				td.setTitles(logNodes[i]);
				elements = logNodes[i].getElementsByTagName('a');
				for (k = elements.length; k-- > 0;) elements[k].onclick = JAK.Events.stopEvent;
			} else if (JAK.DOM.hasClass(logNodes[i], 'nd-view-dump')) {
				td.dumps.push(logNodes[i]);
				logNodes[i].objects = [];
				elements = logNodes[i].childNodes;
				for (k = elements.length; k-- > 0;) {
					if (elements[k].nodeType == 1 && (tdIndex = elements[k].getAttribute('data-tdindex')) !== false) {
						tdIndex = parseInt(tdIndex);
						logNodes[i].objects[tdIndex] = elements[k];
						elements[k].tdIndex = tdIndex;
					}
				}
			}
		}
	}

	td.logRowsChosen.length = td.logRows.length;

	td.tdInnerWrapper.appendChild(td.tdView);
	td.tdOuterWrapper.appendChild(td.tdInnerWrapper);
	td.tdContainer.appendChild(td.tdOuterWrapper);
	document.body.insertBefore(td.tdContainer, document.body.childNodes[0]);

	td.help.innerHTML = '<span class="nd-titled"><span id="menuTitle" class="nd-title"><strong class="nd-inner">'
			+ '<hr><div class="nd-menu">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
			+ (td.local ? '<span id="tdMenuSend"><b>odeslat</b></span>&nbsp;&nbsp;&nbsp;&nbsp;' : '')
			+ '<span onclick="td.restore()">obnovit</span>&nbsp;&nbsp;&nbsp;&nbsp;'
			+ '<span class="nd-titled"><span id="helpTitle" class="nd-title"><strong class="nd-inner">'
			+ td.helpHtml
			+ '</strong></span>napoveda</span>'
			+ '     |&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span>export</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;<span>import</span>'
			+ '     |&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span>ulozit</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;<span>nahrat</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;<span>smazat</span>'
			+ '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div><hr>'
			+ '</strong></span>*</span>';
	document.body.appendChild(td.help);
	td.help.onmousedown = td.logAction;
	td.setTitles(td.help);
	td.helpSpaceX = td.help.clientWidth + JAK.DOM.scrollbarWidth();
	JAK.gel('menuTitle').appendChild(td.tdChangeList);
	if (td.local) JAK.Events.addListener(JAK.gel('tdMenuSend'), 'click', td, 'sendChanges');

	td.showDump(logId);
	window.onresize = td.windowResize;
	document.onkeydown = td.readKeyDown;
	document.body.oncontextmenu = td.tdFalse;
	td.tdInnerWrapper.onmousedown = td.changeVar;
	if (window.addEventListener) window.addEventListener('DOMMouseScroll', td.mouseWheel, false);
	window.onmousewheel = document.onmousewheel = td.mouseWheel;

	if (td.response) td.loadChanges(td.response);
};

td.loadChanges = function(changes) {
	var path, log, container, varEl, change;
	for (var i = 0, j = changes.length; i < j; ++i) {
		path = changes[i].path.split(',');
		log = container = varEl = null;

		if (changes[i].res === 0 || changes[i].res === 7) {
		} else if (path[0] === 'log') {
			log = JAK.gel(path[1]);
			container = td.dumps[td.indexes[log.logId - 1]].objects[parseInt(path[2])];
			varEl = td.findVarEl(container, path.slice(3), changes[i].add);
		} else {
			container = JAK.gel(path[1]);
			varEl = td.findVarEl(container, path.slice(2), changes[i].add);
		}

		if (changes[i].add === 2) changes[i].value = JSON.parse(changes[i].value);

		if (varEl) {
			varEl = td.duplicateNode(varEl);
			varEl.title = td.formatJson(changes[i].value);
		}

		change = td.createChange(changes[i], container, varEl, log);
		change.valid = true;
		change.formated = true;
	}
	td.updateChangeList();
};

td.findVarEl = function(el, path, add) {
	if (typeof path[0] === 'undefined') return false;
	var i = 0, j, child;

	if (path[0][0] === '#' || path[0][0] === '*') path[0] = path[0].slice(1);
	var type = parseInt(path[0][0]);

	if (type === 9) return JAK.DOM.getElementsByClass('nd-top', el)[0] || null;
	else if (type >= 7) {
		if (add) {
			for (j = el.childNodes.length; i < j; ++i) {
				child = el.childNodes[i];
				if (child.nodeType == 1) {
					if (JAK.DOM.hasClass(child, 'nd-toggle')) child = child.firstChild;
					if (child.className === 'nd-array' && child.getAttribute('data-pk') === path[0]) return child;
				}
			}
		} else {
			for (j = el.childNodes.length; i < j; ++i) {
				if (el.childNodes[i].nodeType == 1 && el.childNodes[i].className === 'nd-key' && el.childNodes[i].innerHTML === path[0].slice(1)) {
					return el.childNodes[i];
				}
			}
		}
	} else {
		for (j = el.childNodes.length; i < j; ++i) {
			if (el.childNodes[i].nodeType == 1 && el.childNodes[i].getAttribute('data-pk') === path[0]) {
				return td.findVarEl(el.childNodes[i], path.slice(1), add);
			}
		}
	}
	return false;
};

td.mouseWheel = function(e) {
	e = e || window.event;
	var el = JAK.Events.getTarget(e);
	el = el.tagName.toLowerCase() === 'b' ? el.parentNode : el;

	if (td.titleActive === null && !JAK.DOM.hasClass(el, 'nd-titled')) return true;

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);

	el = td.titleActive || el.tdTitle;

	var delta = 0;

	if (e.wheelDelta) delta = (e.wheelDelta / 120 > 0 ? -1 : 16);
	else if (e.detail) delta = (e.detail / 3 < 0 ? -1 : 16);

	el.scrollTop = Math.max(0, 16 * parseInt((el.scrollTop + delta) / 16));
	return false;
};

td.changeVar = function(e) {
	e = e || window.event;

	if (!td.local || e.shiftKey || e.ctrlKey || e.metaKey || e.button !== JAK.Browser.mouse.right) return true;

	var el = JAK.Events.getTarget(e);
	el = el.tagName.toLowerCase() === 'b' ? el.parentNode : el;

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);

	if (e.altKey) {
		if (JAK.DOM.hasClass(el, 'nd-array')) {
			if (JAK.DOM.hasClass(el, 'nd-top')) td.hideTitle(td.titleActive);
			td.consoleOpen(el, td.saveArrayAdd);
		}
	} else if (JAK.DOM.hasClass(el, 'nd-key')) {
		td.consoleOpen(el, td.saveVarChange);
	} else if (JAK.DOM.hasClass(el, 'nd-top')) {
		td.hideTitle(td.titleActive);
		td.consoleOpen(el, td.saveVarChange);
	}

	return false;
};

td.changeAction = function(e) {
	e = e || window.event;

	if (!td.local || e.altKey || e.ctrlKey || e.metaKey) return true;

	var el = JAK.Events.getTarget(e);

	if (JAK.DOM.hasClass(el, 'nd-result') && e.button === JAK.Browser.mouse.left) {
		if (this.logRow) td.showLog(true, this.logRow);
		return true;
	}

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);

	var hashes = [];

	if (e.button === JAK.Browser.mouse.right) {
		if (el.id === 'tdDeleteChange') {
			this.deleteMe = true;

			td.updateChangeList();
			td.tdChangeList.removeChild(this);
			return false;
		}

		if (this.logRow) td.showLog(true, this.logRow);
		if (this.varEl) td.consoleOpen(this.varEl, td.editVarChange);
	} else if (e.button === JAK.Browser.mouse.left) {
		if (el.id === 'tdDeleteChange') {
			el.showLogRow = !el.showLogRow;
			td.checkDeleteChange();
			return false;
		}
		if (e.shiftKey) {
			if (this.valid) {
				if (!this.formated) {
					var formated = td.formatJson(this.data.value);
					if (formated === false) return this.formated = false;
					this.varEl.title = this.title = formated;
					this.formated = true;
					td.updateChangeList(this);
				}
			} else {
				this.varEl.title = this.title = JSON.stringify(this.data.value);
				this.valid = true;
				this.formated = (this.title === td.formatJson(this.data.value));
				td.updateChangeList(this);
			}
			return false;
		}
		if (this.logRow) {
			td.showLog(true, this.logRow);

			if (td.tdFullWidth) {
				if (this.varEl) hashes.push([td.tdInnerWrapper, 'tdanchor', td.tdContainer, 150]);
			} else {
				if (this.varEl) hashes.push([td.tdInnerWrapper, 'tdanchor', td.tdContainer, 50]);
				hashes.push([td.logWrapper, 'loganchor', td.logContainer, 50]);
			}
		} else {
			if (this.varEl) hashes.push([td.logWrapper, 'tdanchor', td.logContainer, 50]);
		}

		td.setLocationHashes(true, hashes);
	} else return true;

	return false;
};

td.formatJson = function(json) {
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
				retVal += text[i] + '\n' + td.padJson(nested);
				continue;
			} else if (ends.hasOwnProperty(text[i])) {
				if (--nested[ends[text[i]]] < 0) return false;
				retVal += '\n' + td.padJson(nested) + text[i];
				continue;
			} else if (text[i] === ',') {
				retVal += text[i] + '\n' + td.padJson(nested);
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

td.padJson = function(nested) {
	var retVal = '';
	for (var i = nested['['] + nested['{']; i-- > 0;) {
		retVal += '    ';
	}
	return retVal;
};

td.setLocationHashes = function(e, hashes) {
	var i;
	if (td.setLocHashTimeout) {
		window.clearTimeout(td.setLocHashTimeout);
		td.setLocHashTimeout = null;
	}
	if (e === true) {
		if (hashes) {
			td.locationHashes = hashes;
			td.setLocHashTimeout = window.setTimeout(td.setLocationHashes, 1);
		}
	} else if (i = (hashes = td.locationHashes).length) {
		while (i-- > 0) {
			hashes[i][0].style.height = (2 * hashes[i][0].clientHeight) + 'px';
			window.location.replace('#' + hashes[i][1]);
			hashes[i][2].scrollTop -= hashes[i][3];
			hashes[i][0].removeAttribute('style');
		}
		td.locationHashes.length = 0;
		window.location.replace('#');
	}
};

td.printPath = function(change) {
	var path = change.data.path.split(',');
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

		retVal += !close || key == parseInt(key) ? retKey + close : "'" + retKey + "'" + close;

		if (k % 2) {
			retVal += '.'; close = "";
		} else {
			retVal += "["; close = "]";
		}
	}

	if (k == 9) retVal += '<i>(' + retKey + ')</i>';
	else retVal += !close || key == parseInt(key) ? retKey + close : "'" + retKey + "'" + close;

	if (change.data.add) retVal += change.valid && typeof change.data.value == 'object' ? ' +=' : '[] =';
	else retVal += ' =';

	return retVal;
};

td.updateChangeList = function(el) {
	var change;
	var i = td.changes.length, j;
	if (el) el.lastChange = true;

	td.changes.sort(function(b,a) {
		return (parseFloat(a.runtime) - parseFloat(b.runtime)) ||
				(a.sortVals.parentPrefix !== b.sortVals.parentPrefix ? a.sortVals.parentPrefix > b.sortVals.parentPrefix :
						(a.sortVals.parentIndex !== b.sortVals.parentIndex ? a.sortVals.parentIndex > b.sortVals.parentIndex :
								a.sortVals.changeIndex > b.sortVals.changeIndex)
				);
	});

	while (i-- > 0) {
		change = td.changes[i];
		if (change.deleteMe === true) {
			change.style.display = 'none';
			if (change.logRow && change.logRow.changedVarEls && (j = change.logRow.changedVarEls.indexOf(change.varEl)) != -1) {
				change.logRow.changedVarEls.splice(j, 1);
			}
			if (change.listeners.length) JAK.Events.removeListeners(change.listeners);
			if (change.varEl) {
				td.deactivateChange(true, change);
				if (change.varEl.hideEl) change.varEl.hideEl.removeAttribute('style');
				change.varEl.parentNode.removeChild(change.varEl);
			}
			td.changes.splice(i, 1);
			continue;
		}

		change.innerHTML = '<span' + (typeof change.data.res != 'undefined' ? ' class="nd-res nd-restype' + change.data.res + '">' : '>')
				+ '[' + change.runtime + ']</span> ' + td.printPath(change) + ' <span class="nd-'
				+ (change.valid ? 'valid' : 'invalid') +'-json' + (change.formated ? ' nd-formated' : '') + '">'
				+ JSON.stringify(change.data.value) + '</span>';

		if (change.lastChange) {
			change.id = 'tdLastChange';
			change.lastChange = false;
		} else if (el) change.removeAttribute('id');
		change.title = change.varEl ? change.varEl.title : td.formatJson(change.data.value);
		td.tdChangeList.appendChild(change);
	}
	td.changes.reverse();

	el = td.tdChangeList.parentNode;

	if (typeof el.menuWidth == 'undefined' && el.oriWidth) {
		el.menuWidth = el.oriWidth;
		el.menuHeight = el.oriHeight;
	}
	if (el.oriWidth && el.style.display != 'none') {
		el.style.width = 'auto';
		el.oriWidth = Math.max(el.menuWidth, td.tdChangeList.clientWidth);
		el.oriHeight = el.menuHeight + (el.changesHeight = td.tdChangeList.clientHeight);
		td.titleAutosize(el);
	}
};

td.parseJson = function(text) {
	var i = 0, j = td.jsonRepairs.length, retObj = td.testJson(text);
	if (retObj.status && (retObj.valid = true)) return retObj;

	for (;i < j; ++i) {
		if ((retObj = td.testJson(text, td.jsonRepairs[i])).status) return (retObj.valid = false) || retObj;
	}
	return {'status': false};
};

td.testJson = function(text, tests) {
	tests = tests || [];
	var i, j = tests.length, k, l, json, test;

	if (!j) {
		try {
			json = JSON.parse(text);
			return {'status': true, 'json': json};
		} catch(e) { return {'status': false} }
	}

	for (i = 0; i < j; ++i) {
		for (k = 0, l = (test = td.jsonReplaces[tests[i]]).length; k < l; ++k) {
			if (test[k] === 'fixCommas') text = td.jsonFixCommas(text);
			else if (test[k] === 'fixNumbers') text = td.jsonFixNumbers(text);
			else if (test[k] === 'fixObjects') text = td.jsonFixObjects(text);
			else text = text.replace(test[k][0], test[k][1]);
		}
		try {
			json = JSON.parse(text);
			return {'status': true, 'json': json};
		} catch(e) {}
	}

	return {'status': false};
};

td.jsonFixCommas = function(text) {
	var retVal = '', escaped = false, nested = false;

	for (var i = 0, j = text.length; i < j; ++i) {
		if (escaped) escaped = false;
		else if (text[i] === '\\') escaped = true;
		else if (text[i] === "'" || text[i] === '"') nested = !nested;
		else if (!nested && text[i] === ',' && text.slice(i).match(/^,\s*(?:\]|\})/m)) continue;
		retVal += text[i];
	}

	return nested ? text : retVal;
};

td.jsonFixNumbers = function(text) {
	var retVal = '', escaped = false, nested = false, replace;

	for (var i = 0, j = text.length; i < j;) {
		if (escaped) escaped = false;
		else if (text[i] === '\\') escaped = true;
		else if (text[i] === "'" || text[i] === '"') nested = !nested;
		else if (!nested && '0123456789'.indexOf(text[i]) !== -1 && (replace = text.slice(i).match(/^\d+,\d+/))) {
			retVal += replace[0].replace(',', '.');
			i += replace[0].length;
			continue;
		}
		retVal += text[i];
		++i;
	}

	return nested ? text : retVal;
};

td.jsonFixObjects = function(text) {
	var retVal = '', nested = 0, arrayLevels = [], escaped = false, ch;
	var quotes = {"'": false, '"': false};

	for (var i = 0, j = text.length; i < j; i++) {
		ch = text[i];
		if (escaped) escaped = false;
		else if (text[i] === '\\') escaped = true;
		else if (quotes.hasOwnProperty(text[i])) quotes[text[i]] = !quotes[text[i]];
		else if (!quotes["'"] && !quotes['"']) {
			if (text[i] === '[' && (arrayLevels[++nested] = (td.findNearestChar(text, ':', i + 1) !== false))) ch = '{';
			else if (text[i] === ']' && arrayLevels[nested--]) ch = '}';
		}
		retVal += ch;
	}

	return nested ? text : retVal;
};

td.sumNested = function(nested) {
	nested = nested || {};
	var retVal = 0;
	for (var i in nested) {
		if (nested.hasOwnProperty(i)) retVal += nested[i];
	}
	return retVal;
};

td.noQuotes = function(quotes) {
	var retVal = false;
	for (var i in quotes) {
		if (quotes.hasOwnProperty(i)) retVal = retVal || quotes[i];
		if (retVal) break;
	}
	return !retVal;
};

td.findNearestChar = function(text, chars, index, rev, quotes) {
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
				var indexLevel = td.sumNested(nested);
				for (k = found.length; k-- > 0;) {
					if (found[k].level === indexLevel) return found[k].index;
					else if (found[k].level < indexLevel) return false;
				}
				return false;
			} else if (escaped) escaped = false;
			else if (text[i] === '\\') escaped = true;
			else if (quotes.hasOwnProperty(text[i])) quotes[text[i]] = !quotes[text[i]];
			else if (i < index && td.noQuotes(quotes)) {
				if (nested.hasOwnProperty(text[i])) ++nested[text[i]];
				else if (ends.hasOwnProperty(text[i]) && --nested[ends[text[i]]] < 0) return false;
				if (chars.indexOf(text[i]) !== -1) found.push({'index': i, 'level': td.sumNested(nested)});
			}
		}
	} else {
		for (i = 0, j = text.length; i < j; i++) {
			if (escaped) escaped = false;
			else if (text[i] === '\\') escaped = true;
			else if (quotes.hasOwnProperty(text[i])) quotes[text[i]] = !quotes[text[i]];
			else if (i >= index && td.noQuotes(quotes)) {
				if (chars.indexOf(text[i]) !== -1 && !td.sumNested(nested)) return i;
				else if (nested.hasOwnProperty(text[i])) ++nested[text[i]];
				else if (ends.hasOwnProperty(text[i]) && --nested[ends[text[i]]] < 0) return false;
			}
		}
	}

	return false;
};

td.saveVarChange = function(arrayAdd) {
	var varEl = td.tdConsole.varEl;
	var el = varEl;
	var key = el.getAttribute('data-pk') || '8';
	var privateVar = key[0] === '7';

	var areaVal = td.tdConsole.area.value;
	var i = -1, s = td.parseJson(areaVal);
	var value, valid, formated;

	var revPath = [];
	var change;
	var logRow = false;
	var add = arrayAdd ? 1 : 0;

	if (s.status) {
		value = s.json;
		valid = s.valid;
		formated = valid && (areaVal === td.formatJson(value));
		if (add && valid && JSON.stringify(value)[0] === '{') add = 2;
	} else {
		value = areaVal;
		formated = valid = false;
	}

	td.consoleClose();

	if (JAK.DOM.hasClass(el, 'nd-top')) {
		revPath.push('9' + el.className.split(' ')[0].split('-')[1]);
		while ((el = el.parentNode) && el.tagName.toLowerCase() != 'pre') {}
		revPath.push(el.id, 'dump');
	} else {
		if (JAK.DOM.hasClass(el, 'nd-key')) revPath.push(key + el.innerHTML);
		else if (JAK.DOM.hasClass(el, 'nd-array')) {
			revPath.push(key);
			if (JAK.DOM.hasClass(el.parentNode, 'nd-toggle')) el = el.parentNode;
		} else return false;

		while ((el = el.parentNode) && el.tagName.toLowerCase() == 'div' && null !== (key = el.getAttribute('data-pk'))) {
			if (parseInt(key[0]) % 2 && (++i + 1) && privateVar) {
				revPath.push((i ? '#' : '*') + key);
				privateVar = false;
			} else {
				revPath.push(key);
			}
			if (key[0] === '3' || key[0] === '4') privateVar = true;
		}
		if (JAK.DOM.hasClass(el, 'nd-dump')) {
			revPath.push(el.id, 'dump');
		} else {
			revPath.push(el.tdIndex, td.logRowActive.id, 'log');
			logRow = td.logRowActive;
		}
	}

	if (change = varEl.change) {
		if (change.data.value === value && change.valid === valid && change.formated === formated && change.data.add === add) return true;
		change.data.value = value;
		change.data.add = add;
	} else {
		varEl = td.duplicateNode(varEl);
		change = td.createChange({'path': revPath.reverse().join(','), 'value': value, 'add': add}, el, varEl, logRow);
	}

	change.valid = valid;
	change.formated = formated;
	varEl.title = areaVal;

	td.updateChangeList(change);
	return true;
};

td.editVarChange = function() {
	return td.saveVarChange(td.tdConsole.varEl.change.data.add);
};

td.saveArrayAdd = function() {
	return td.saveVarChange(1);
};

td.createChange = function(data, container, varEl, logRow) {
	container = container || null;
	varEl = varEl || null;
	logRow = logRow || false;

	var change = JAK.mel('pre', {'className': 'nd-change-data'}), changeEls, i, j, k, key = [], resEl;
	change.data = data;
	td.changes.push(change);

	change.varEl = varEl;
	change.listeners = [
		JAK.Events.addListener(change, 'mouseover', change, td.activateChange),
		JAK.Events.addListener(change, 'mouseout', change, td.deactivateChange),
		JAK.Events.addListener(change, 'mousedown', change, td.changeAction)
	];

	if (logRow) {
		change.logRow = logRow;
		change.runtime = logRow.attrRuntime;
		change.listeners.push(JAK.Events.addListener(change, 'mouseover', change.logRow, td.showLog));
		key = change.logRow.id.split('_');

		if (container && varEl) {
			if (typeof logRow.changedVarEls == 'undefined') logRow.changedVarEls = [varEl];
			else logRow.changedVarEls.push(varEl);

			JAK.DOM.addClass(varEl, 'nd-var-change');
			changeEls = JAK.DOM.getElementsByClass('nd-var-change', container.parentNode);
			for (i = 0, j = changeEls.length, k = 0; i < j; ++i) {
				if (change.logRow.changedVarEls.indexOf(changeEls[i]) != -1) {
					changeEls[i].parentPrefix = key[0];
					changeEls[i].parentIndex = key[1];
					changeEls[i].changeIndex = k++;
				}
			}
			change.sortVals = change.varEl;
		}
	} else if (container) {
		change.runtime = container.attrRuntime;
		key = container.id.split('_');

		if (varEl) {
			JAK.DOM.addClass(varEl, 'nd-var-change');
			changeEls = JAK.DOM.getElementsByClass('nd-var-change', container);
			for (i = 0, j = changeEls.length; i < j; ++i) {
				changeEls[i].parentPrefix = key[0];
				changeEls[i].parentIndex = key[1];
				changeEls[i].changeIndex = i;
			}
			change.sortVals = change.varEl;
		}
	} else change.runtime = ' N/A ';

	if (varEl) {
		varEl.change = change;
		change.listeners.push(JAK.Events.addListener(varEl, 'mouseover', varEl, td.hoverChange));
		change.listeners.push(JAK.Events.addListener(varEl, 'mouseout', varEl, td.unhoverChange));
	} else change.sortVals = {
		'parentPrefix': key[0] || 'zzzzz',
		'parentIndex': key[1] || 65535,
		'changeIndex': data.resId ? 32767 + data.resId.split('_').reverse()[0] : ++td.noContainerChangeIndex
	};

	if (data.resId) {
		if (resEl = JAK.gel(data.resId)) {
			change.resEl = resEl;
			resEl.change = change;
			resEl.res = data.res;
			change.listeners.push(JAK.Events.addListener(resEl, 'mouseover', resEl, td.hoverChange));
			change.listeners.push(JAK.Events.addListener(resEl, 'mouseout', resEl, td.unhoverChange));
			change.listeners.push(JAK.Events.addListener(resEl, 'mousedown', change, td.changeAction));
		} else data.resId = null;
	}

	return change;
};

td.duplicateNode = function(varEl) {
	var newEl = varEl.cloneNode(true);
	newEl.hideEl = varEl;
	varEl.parentNode.insertBefore(newEl, varEl);
	varEl.style.display = 'none';
	return newEl;
};

td.checkDeleteChange = function() {
	if (td.deleteChange.showLogRow === true) td.deleteChange.style.textDecoration = 'underline';
	else td.deleteChange.removeAttribute('style');
	return td.deleteChange;
};

td.activateChange = function(e, change) {
	td.hoveredChange = e === true ? change : this;
	if (td.hoveredChange.varEl) {
		td.tdHashEl = td.hoveredChange.varEl;
		td.tdHashEl.parentNode.insertBefore(td.tdAnchor, td.tdHashEl);
		JAK.DOM.addClass(td.tdHashEl, 'nd-hovered');
	}
	if (td.hoveredChange.resEl) JAK.DOM.addClass(td.hoveredChange.resEl, 'nd-hovered');

	td.hoveredChange.appendChild(td.checkDeleteChange());
};

td.deactivateChange = function(e, change) {
	change = e === true ? change : this;

	if (change.varEl) {
		if (change.varEl === td.tdHashEl) td.tdHashEl = null;
		JAK.DOM.removeClass(change.varEl, 'nd-hovered');
	}
	if (change.resEl) JAK.DOM.removeClass(change.resEl, 'nd-hovered');
	if (this === td.hoveredChange) td.hoveredChange = null;
};

td.hoverChange = function() {
	JAK.DOM.addClass(this.change, 'nd-hovered');
	if (this.res && this.change.varEl) JAK.DOM.addClass(this.change.varEl, 'nd-hovered');
	else if (this.change.resEl) JAK.DOM.addClass(this.change.resEl, 'nd-hovered');
};

td.unhoverChange = function() {
	JAK.DOM.removeClass(this.change, 'nd-hovered');
	if (this.res && this.change.varEl) JAK.DOM.removeClass(this.change.varEl, 'nd-hovered');
	else if (this.change.resEl) JAK.DOM.removeClass(this.change.resEl, 'nd-hovered');
};

td.consoleHover = function(areaClass) {
	if (td.consoleHoverTimeout) {
		window.clearTimeout(td.consoleHoverTimeout);
		td.consoleHoverTimeout = null;
	}

	if (areaClass) {
		td.tdConsole.area.className = areaClass;
		td.consoleHoverTimeout = window.setTimeout(td.consoleHover, 400);
	} else td.tdConsole.area.removeAttribute('class');
};

td.consoleAction = function(e) {
	e = e || window.event;

	if (e.button === JAK.Browser.mouse.left && (e.ctrlKey || e.metaKey)) {
		JAK.Events.cancelDef(e);
		JAK.Events.stopEvent(e);

		if (e.shiftKey && !e.altKey) {
			var parsed = td.parseJson(this.value);
			if (parsed.status) {
				var formated = td.formatJson(parsed.json);
				if (formated === this.value) return false;
				if (!parsed.valid) td.consoleHover('nd-area-parsed');
				td.areaWrite(this, td.formatJson(parsed.json), this.selectionStart);
			} else td.consoleHover('nd-area-error');
		} else if (!e.shiftKey && e.altKey) {
			var cc = td.consoleConfig;
			if (typeof cc.oriX != 'undefined') {
				cc.x = cc.oriX;
				cc.y = cc.oriY;
			}
			JAK.DOM.setStyle(this, {'width': cc.x + 'px', 'height': cc.y + 'px'});
		}
		return false;
	}
	return true;
};

td.consoleOpen = function(el, callback) {
	td.tdConsole = JAK.mel('div', {'id':'tdConsole'});
	td.tdConsole.mask = JAK.mel('div', {'id':'tdConsoleMask'});

	var attribs = {id:'tdConsoleArea'};
  if (el.title) {
	  attribs.value = td.tdConsole.mask.title = el.title;
	  el.title = null;
  }

	td.tdConsole.area = JAK.mel('textarea', attribs, {'width':td.consoleConfig.x + 'px',
		'height':td.consoleConfig.y + 'px'});
	td.tdConsole.appendChild(td.tdConsole.mask);
	td.tdConsole.appendChild(td.tdConsole.area);
	td.tdConsole.varEl = el;
	document.body.appendChild(td.tdConsole);

	td.tdConsole.listeners = [
		JAK.Events.addListener(td.tdConsole.area, 'keypress', td.tdConsole.area, td.readConsoleKeyPress),
		JAK.Events.addListener(td.tdConsole.area, 'mousedown', td.tdConsole.area, td.consoleAction),
		JAK.Events.addListener(td.tdConsole.mask, 'mousedown', td.tdConsole.mask, td.catchMask)
	];

	td.tdConsole.callback = callback || td.tdStop;
	td.textareaTimeout = window.setTimeout(td.textareaFocus, 1);

	JAK.DOM.addClass(el, 'nd-edited');
	if (el.change) {
		JAK.DOM.addClass(el.change, 'nd-edited');
		if (el.change.resEl) JAK.DOM.addClass(el.change.resEl, 'nd-edited');
	}
};

td.textareaFocus = function() {
	if (td.textareaTimeout) {
		window.clearTimeout(td.textareaTimeout);
		td.textareaTimeout = null;
	}
	td.tdConsole.area.focus();
	td.tdConsole.area.select();
};

td.catchMask = function(e) {
	e = e || window.event;

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	if (e.button === JAK.Browser.mouse.right && this.title == td.tdConsole.area.value) td.consoleClose();
	else td.tdConsole.area.focus();

	return false;
};

td.consoleClose = function() {
	var varEl = td.tdConsole.varEl;
	JAK.DOM.removeClass(varEl, 'nd-hovered nd-edited');
	if (varEl.change) {
		JAK.DOM.removeClass(varEl.change, 'nd-hovered nd-edited');
		if (varEl.change.resEl) JAK.DOM.removeClass(varEl.change.resEl, 'nd-hovered nd-edited');
	}

	if (td.tdConsole.listeners) {
		JAK.Events.removeListeners(td.tdConsole.listeners);
		td.tdConsole.listeners = null;
	}

	var cc = td.consoleConfig;
	if (typeof cc.oriX == 'undefined') {
		cc.oriX = cc.x;
		cc.oriY = cc.y;
	}

	cc.x = td.tdConsole.area.offsetWidth - 8;
	cc.y = td.tdConsole.area.clientHeight;

	if (td.tdConsole.mask.title) varEl.title = td.tdConsole.mask.title;
	document.body.removeChild(td.tdConsole);
	td.tdConsole = null;
	return false;
};

td.logAction = function(e) {
	e = e || window.event;

	if ((e.altKey ? e.shiftKey || td.tdFullWidth : !e.shiftKey) || e.ctrlKey || e.metaKey || e.button !== JAK.Browser.mouse.left) return true;

	JAK.Events.stopEvent(e);
	JAK.Events.cancelDef(e);
	var el = td.help;

	if (e.altKey) {
		td.actionData.startX = e.screenX;
		td.actionData.width = td.tdWidth;
		td.actionData.element = el;

		td.actionData.listeners.push(
				JAK.Events.addListener(document, 'mousemove', td, 'logResizing'),
				JAK.Events.addListener(document, 'mouseup', td, 'endLogResize'),
				JAK.Events.addListener(el, 'selectstart', td, 'tdStop'),
				JAK.Events.addListener(el, 'dragstart', td, 'tdStop')
		);

		document.body.focus();
	} else {
		if (td.tdFullWidth) {
			td.tdFullWidth = false;
			JAK.DOM.removeClass(document.body.parentNode, 'nd-fullscreen');

			document.body.style.marginLeft = td.tdContainer.style.width = td.help.style.left = td.tdWidth + 'px';
			td.logContainer.style.width = 'auto';
			td.logRowActive.removeAttribute('style');
		} else {
			td.tdFullWidth = true;
			JAK.DOM.addClass(document.body.parentNode, 'nd-fullscreen');

			document.body.style.marginLeft = td.help.style.left = 0;
			td.tdContainer.style.width = '100%';
			td.logContainer.style.width = td.tdContainer.clientWidth + 'px';
			td.logRowActive.style.width = (td.tdContainer.clientWidth - 48) + 'px';
		}

		td.tdResizeWrapper();
	}

	return false;
};

td.logResizing = function(e) {
	e = e || window.event;
	var el = td.actionData.element;

	if (e.button === JAK.Browser.mouse.left) {
		td.tdWidth = Math.max(0, Math.min(td.viewSize.width - td.helpSpaceX,
				td.actionData.width + e.screenX - td.actionData.startX));

		document.body.style.marginLeft = td.tdContainer.style.width = el.style.left = td.tdWidth + 'px';

		td.tdResizeWrapper();
	} else {
		td.endLogResize();
	}
};

td.endLogResize = function() {
	JAK.Events.removeListeners(td.actionData.listeners);
	td.actionData.listeners.length = 0;
	td.actionData.element = null;
};

td.showVarChanges = function(varEls) {
	for (var i = varEls.length; i-- > 0;) {
		varEls[i].style.display = 'inline';
		varEls[i].hideEl.style.display = 'none';
	}
};

td.hideVarChanges = function(varEls) {
	for (var i = varEls.length; i-- > 0;) {
		varEls[i].style.display = 'none';
		varEls[i].hideEl.style.display = 'inline';
		if (varEls[i] === td.tdHashEl) td.deactivateChange(true, varEls[i].change);
	}
};

td.showDump = function(id) {
	if (td.logRowActiveId == (id = id || 0)) return false;
	if (td.logRowActive) {
		JAK.DOM.removeClass(td.logRowActive, 'nd-active');
		td.logRowActive.removeAttribute('style');
		if (td.logRowActive.changedVarEls) td.hideVarChanges(td.logRowActive.changedVarEls);
	}

	JAK.DOM.addClass(td.logRowActive = td.logRows[id - 1], 'nd-active');
	if (td.logRowActive.changedVarEls) td.showVarChanges(td.logRowActive.changedVarEls);
	td.logRowActive.parentNode.insertBefore(td.logAnchor, td.logRowActive);
	if (td.hoveredChange && td.hoveredChange.logRow === td.logRowActive) td.activateChange(true, td.hoveredChange);

	document.title = '[' + td.logRowActive.attrRuntime + ' ms] ' + td.logRowActive.id + '::' + td.logRowActive.attrTitle;

	if (td.indexes[td.logRowActiveId - 1] !== td.indexes[id - 1]) {

		if (td.tdListeners.length) {
			JAK.Events.removeListeners(td.tdListeners);
			td.tdListeners.length = 0;
		}

		(td.tdView.oriId && (td.tdView.id = td.tdView.oriId)) || td.tdInnerWrapper.removeChild(td.tdView);

		td.tdView = td.dumps[td.indexes[id - 1]];
		td.tdInnerWrapper.appendChild(td.tdView);
		if (!td.tdView.oriId) {
			td.tdInnerWrapper.appendChild(td.tdView);
			td.tdView.oriId = td.tdView.id;
			td.tdView.activeChilds = [];
		}
		td.tdView.id = 'tdView';
		td.setTitles(td.tdView);
		td.tdResizeWrapper();
	}

	if (td.tdFullWidth) td.logRowActive.style.width = (td.tdContainer.clientWidth - 48) + 'px';
	td.logRowActiveId = id;

	return true;
};

td.setTitles = function(container) {
	var titleSpan, titleStrong, titleStrongs, listeners = [];

	titleStrongs = container.getElementsByTagName('strong');
	for (var i = titleStrongs.length; i-- > 0;) {
		if ((titleStrong = titleStrongs[i]).className == 'nd-inner') {
			titleSpan = titleStrong.parentNode.parentNode;
			titleSpan.tdTitle = titleStrong.parentNode;
			titleSpan.tdTitle.tdInner = titleStrong;

			listeners.push(JAK.Events.addListener(titleSpan, 'mousemove', titleSpan, td.showTitle),
					JAK.Events.addListener(titleSpan, 'mouseout', titleSpan, td.hideTimer),
					JAK.Events.addListener(titleSpan, 'click', titleSpan, td.pinTitle)
			);

			if (!container.titleListener) JAK.Events.addListener(titleSpan.tdTitle, 'mousedown', titleSpan.tdTitle, td.titleAction);
		}
	}

	if (container === td.tdView) {
		td.tdListeners = td.tdListeners.concat(listeners);
		if (!container.titleListener) container.titleListener = true;
	}
};

td.showTitle = function(e) {
	e = e || window.event;

	if (e.shiftKey || e.altKey || e.ctrlKey || e.metaKey || td.actionData.element !== null || td.tdConsole !== null) return false;
	var tdTitleRows, tdParents;

	JAK.Events.stopEvent(e);

	if (td.titleActive && td.titleActive !== this.tdTitle) {
		td.hideTitle();
	} else if (td.titleHideTimeout) {
		window.clearTimeout(td.titleHideTimeout);
		td.titleHideTimeout = null;
	}

	if (td.titleActive === null && this.tdTitle.style.display != 'block') {
		this.tdTitle.style.display = 'block';
		this.tdTitle.style.zIndex = ++td.zIndexMax;

		if (!this.tdTitle.hasOwnProperty('oriWidth')) {
			if ((tdParents = td.getParents(this)).length) this.tdTitle.parents = tdParents;
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
				td.tdChangeList.style.display = 'block';
			}
		}
		if (this.tdTitle.id === 'menuTitle') {
			this.tdTitle.style.width = 'auto';
			this.tdTitle.oriWidth = Math.max(this.tdTitle.menuWidth, td.tdChangeList.clientWidth);
			this.tdTitle.oriHeight = this.tdTitle.menuHeight + (this.tdTitle.changesHeight = td.tdChangeList.clientHeight);
		}
		if (tdParents = tdParents || this.tdTitle.parents) {

			for (var k = tdParents.length; k-- > 0;) {
				if (tdParents[k].hasOwnProperty('activeChilds')) {
					tdParents[k].activeChilds.push(this.tdTitle);
				} else tdParents[k].activeChilds = [this.tdTitle];
			}
		}
		td.titleActive = this.tdTitle;
		td.visibleTitles.push(td.titleActive);
	}

	if (td.titleActive === null) return true;

	td.titleActive.style.left = (td.titleActive.tdLeft = (e.pageX || e.clientX) + 20) + 'px';
	td.titleActive.style.top = (td.titleActive.tdTop = (e.pageY || e.clientY) - 5) + 'px';

	td.titleAutosize();

	return false;
};

td.removeFromParents = function(el) {
	for (var i = el.parents.length, j; i-- > 0;) {
		for (j = el.parents[i].activeChilds.length; j-- > 0;) {
			if (el.parents[i].activeChilds[j] === el) el.parents[i].activeChilds.splice(j, 1);
		}
	}
};

td.getParents = function(el) {
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

td.getMaxZIndex = function() {
	for (var retVal = 100, i = td.visibleTitles.length; i-- > 0;) {
		retVal = Math.max(retVal, td.visibleTitles[i].style.zIndex);
	}

	return retVal;
};

td.titleAction = function(e) {
	e = e || window.event;

	if (!this.pined || e.button !== JAK.Browser.mouse.left) return true;

	JAK.Events.stopEvent(e);

	if (this.style.zIndex < td.zIndexMax) {
		this.style.zIndex = ++td.zIndexMax;

		if (td.zIndexMax > td.visibleTitles.length + 1000) {
			td.visibleTitles.sort(function(a,b) { return parseInt(a.style.zIndex) - parseInt(b.style.zIndex); });
			for (var i = 0, j = td.visibleTitles.length; i < j;) td.visibleTitles[i].style.zIndex = ++i + 100;
			td.zIndexMax = j + 100;
		}
	}

	if (e.altKey) {
		if (!e.ctrlKey && !e.metaKey) {
			if (e.shiftKey) td.hideTitle(this);
			else td.startTitleDrag(e, this);
		} else if (!e.shiftKey) {
			this.resized = false;
			td.titleAutosize(this);
		} else return true;
	} else if (e.shiftKey) return true;
	else if (e.ctrlKey || e.metaKey) {
		td.startTitleResize(e, this)
	} else return true;

	JAK.Events.cancelDef(e);
	return false;
};

td.startTitleResize = function(e, el) {
	el.resized = true;
	td.actionData.startX = e.screenX;
	td.actionData.startY = e.screenY;
	td.actionData.width = (el.tdWidth === 'auto' ? el.offsetWidth : el.tdWidth);
	td.actionData.height = (el.tdHeight === 'auto' ? el.clientHeight : el.tdHeight);
	td.actionData.element = el;

	td.actionData.listeners.push(
			JAK.Events.addListener(document, 'mousemove', td, 'titleResizing'),
			JAK.Events.addListener(document, 'mouseup', td, 'endTitleAction'),
			JAK.Events.addListener(el, 'selectstart', td, 'tdStop'),
			JAK.Events.addListener(el, 'dragstart', td, 'tdStop')
	);

	document.body.focus();
};

td.titleResizing = function(e) {
	e = e || window.event;
	var el = td.actionData.element;

	if (e.button === JAK.Browser.mouse.left) {
		el.userWidth = el.tdWidth = Math.max(Math.min(td.viewSize.width - el.tdLeft - 20, td.actionData.width + e.screenX - td.actionData.startX), 16);
		el.userHeight = el.tdHeight = 16 * parseInt(Math.max(Math.min(td.viewSize.height - el.tdTop - 35, td.actionData.height + e.screenY - td.actionData.startY), 16) / 16);

		JAK.DOM.setStyle(el, { width:el.tdWidth + 'px', height:el.tdHeight + 'px' });
	} else {
		td.endTitleAction();
	}
};

td.startTitleDrag = function(e, el) {
	td.actionData.startX = e.screenX;
	td.actionData.startY = e.screenY;
	td.actionData.offsetX = el.tdLeft;
	td.actionData.offsetY = el.tdTop;
	td.actionData.element = el;

	td.actionData.listeners.push(
			JAK.Events.addListener(document, 'mousemove', td, 'titleDragging'),
			JAK.Events.addListener(document, 'mouseup', td, 'endTitleAction'),
			JAK.Events.addListener(el, 'selectstart', td, 'tdStop'),
			JAK.Events.addListener(el, 'dragstart', td, 'tdStop')
	);

	document.body.focus();
};

td.titleDragging = function(e) {
	e = e || window.event;
	var el = td.actionData.element;

	if (e.button === JAK.Browser.mouse.left) {
		el.tdLeft = Math.max(Math.min(td.viewSize.width - 36, td.actionData.offsetX + e.screenX - td.actionData.startX), 0);
		el.tdTop = Math.max(Math.min(td.viewSize.height - 51, td.actionData.offsetY + e.screenY - td.actionData.startY), 0);

		JAK.DOM.setStyle(el, { left:el.tdLeft + 'px', top:el.tdTop + 'px' });

		td.titleAutosize(el);
	} else {
		td.endTitleAction();
	}
};

td.endTitleAction = function() {
	var el = td.actionData.element;

	if (el !== null) {
		JAK.Events.removeListeners(td.actionData.listeners);
		td.actionData.listeners.length = 0;
		td.actionData.element = null;
	}
};

td.tdFalse = function() {
	return false;
};

td.tdStop = function(e) {
	e = e || window.event;
	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);
	return false;
};

td.titleAutosize = function(el) {
	el = el || td.titleActive;

	var tdCheckWidthDif = false;
	var tdWidthDif;
	td.spaceX = Math.max(td.viewSize.width - el.tdLeft - 20, 0);
	td.spaceY = 16 * parseInt(Math.max(td.viewSize.height - el.tdTop - 35, 0) / 16);

	if (el.resized) {
		el.style.width = (td.spaceX < el.userWidth ? el.tdWidth = td.spaceX : el.tdWidth = el.userWidth) + 'px';
	} else if (td.spaceX < el.oriWidth) {
		el.style.width = (el.tdWidth = td.spaceX) + 'px';
	} else {
		el.style.width = el.tdWidth = 'auto';
		tdCheckWidthDif = true;
	}

	if (el.resized) {
		el.style.height = (td.spaceY < el.userHeight ? el.tdHeight = td.spaceY : el.tdHeight = el.userHeight) + 'px';
	} else if (td.spaceY < (el.changesHeight || 0) + el.tdInner.clientHeight || td.spaceY < el.oriHeight) {
		el.style.height = (el.tdHeight = td.spaceY) + 'px';
		if (tdCheckWidthDif && (tdWidthDif = Math.max(el.oriWidth - el.clientWidth, 0))) {
			el.style.width = (el.tdWidth = Math.min(el.oriWidth + tdWidthDif, td.spaceX)) + 'px';
		}
	} else {
		el.style.height = el.tdHeight = 'auto';
	}
	return true;
};

td.hideTimer = function(e) {
	e = e || window.event;
	JAK.Events.stopEvent(e);
	if (td.titleHideTimeout) window.clearTimeout(td.titleHideTimeout);
	td.titleHideTimeout = window.setTimeout(td.hideTitle, 300);
};

td.hideTitle = function(el) {
	var index;

	if (td.titleHideTimeout) {
		window.clearTimeout(td.titleHideTimeout);
		td.titleHideTimeout = null;
	}

	if (el && el.pined) {
		el.pined = false;
	} else if ((el = td.titleActive) !== null) {
		td.titleActive = null;
	} else return false;

	if ((index = td.visibleTitles.indexOf(el)) !== -1) td.visibleTitles.splice(index, 1);
	if (el.style.zIndex == td.zIndexMax) td.zIndexMax = td.getMaxZIndex();
	if (el.parents) td.removeFromParents(el);
	if (el.activeChilds && el.activeChilds.length) {
		for (index = el.activeChilds.length; index-- > 0;) td.hideTitle(el.activeChilds[index]);
	}
	el.style.display = 'none';

	return true;
};

td.pinTitle = function(e) {
	e = e || window.event;
	JAK.Events.stopEvent(e);

	if (e.altKey || e.shiftKey || e.ctrlKey || e.metaKey || e.button !== JAK.Browser.mouse.left) return false;

	if (td.titleHideTimeout) {
		window.clearTimeout(td.titleHideTimeout);
		td.titleHideTimeout = null;
	}

	var el = JAK.Events.getTarget(e);
	el = el.tagName.toLowerCase() === 'b' ? el.parentNode : el;

	if (!JAK.DOM.hasClass(el, 'nd-titled')) return false;

	if (td.titleActive && td.titleActive !== el.tdTitle) td.hideTitle();

	if (td.titleActive === null) {
		td.titleActive = el.tdTitle;
		td.titleActive.pined = false;
	} else {
		td.titleActive.pined = true;
		td.titleActive = null;
	}
	return false;
};

td.showLog = function(e, el) {
	if (e === true) {
		td.showDump(el.logId);
	} else if (td.deleteChange.showLogRow === true) {
		td.showDump(this.logId);
	}
};

td.logClick = function(e) {
	e = e || window.event;
	var id = parseInt(this.logId);

	if (e.altKey) {
		if (td.tdConsole && td.keyChanges.indexOf(e.keyCode)) {}
	} else if (e.ctrlKey || e.metaKey) {
		td.logRowsChosen[id - 1] = !td.logRowsChosen[id - 1];
		if (td.logRowsChosen[id - 1]) JAK.DOM.addClass(td.logRows[id - 1], 'nd-chosen');
		else JAK.DOM.removeClass(td.logRows[id - 1], 'nd-chosen');
	} else if (e.shiftKey) {
		var unset = false;
		if (JAK.DOM.hasClass(this, 'nd-chosen')) unset = true;
		for (var i = Math.min(td.logRowActiveId, id), j = Math.max(td.logRowActiveId, id); i <= j; ++i) {
			if (unset) {
				td.logRowsChosen[i - 1] = false;
				JAK.DOM.removeClass(td.logRows[i - 1], 'nd-chosen');
			} else {
				td.logRowsChosen[i - 1] = true;
				JAK.DOM.addClass(td.logRows[i - 1], 'nd-chosen');
			}
		}
	} else {
		td.showDump(id);
	}
	return false;
};

td.readConsoleKeyPress = function(e) {
	e = e || window.event;
	var key = String.fromCharCode(e.which);

	if (this.selectedText) td.unselectConsole();

	if (e.ctrlKey || e.metaKey) {
		if (key == 'd') return td.duplicateText(e, this);
		else if (key == 'y') return td.removeRows(e, this);
		else if (key == 'b') return td.selectBlock(e, this);
	} else if (this.selectionStart === this.selectionEnd) {
		if (td.consoleSelects[key]) td.selectConsole(e, this, key);
	} else if (td.keyChanges[key]) return td.wrapSelection(e, this, key);

	return true;
};

td.readKeyDown = function(e) {
	e = e || window.event;

	var tdNext;

	if (e.shiftKey) {
		if (e.keyCode == 13 && td.tdConsole) return td.tdConsole.callback();
	} else if (!e.altKey && !e.ctrlKey && !e.metaKey) {
		if (e.keyCode == 38 && !td.tdConsole && td.logRowActiveId > 1) {
			tdNext = td.selected() ? td.getPrevious() : td.logRowActiveId - 1;
			if (tdNext === td.logRowActiveId) return true;
			td.showDump(tdNext);
			if (!td.tdFullWidth) {
				td.setLocationHashes(true, [[td.logWrapper, 'loganchor', td.logContainer, 50]]);
			}
			return false;
		} else if (e.keyCode == 40 && !td.tdConsole && td.logRowActiveId < td.indexes.length) {
			td.logView.blur();
			tdNext = td.selected() ? td.getNext() : td.logRowActiveId + 1;
			if (tdNext === td.logRowActiveId) return true;
			td.showDump(tdNext);
			if (!td.tdFullWidth) {
				td.setLocationHashes(true, [[td.logWrapper, 'loganchor', td.logContainer, 50]]);
			}
			return false;
		} else if (e.keyCode == 37 && td.titleActive) {
				td.titleActive.scrollTop = 16 * parseInt((td.titleActive.scrollTop - 16) / 16);
				return false;
		} else if (e.keyCode == 39 && td.titleActive) {
				td.titleActive.scrollTop = 16 * parseInt((td.titleActive.scrollTop + 16) / 16);
				return false;
		} else if (e.keyCode == 27) {
			if (td.tdConsole) return td.consoleClose();
			if (!(td.visibleTitles.length - (td.titleActive === null ? 0 : 1)) || !confirm('Opravdu resetovat nastaveni?')) {
				return true;
			}
			if (td.titleHideTimeout) {
				window.clearTimeout(td.titleHideTimeout);
				td.titleHideTimeout = null;
			}
			for (var i = td.visibleTitles.length; i-- > 0;) {
				td.visibleTitles[i].style.display = 'none';
				td.visibleTitles[i].pined = false;
				td.visibleTitles[i].resized = false;
			}
			td.visibleTitles.length = 0;
			td.zIndexMax = 100;
			td.titleActive = null;
			return false;
		} else if (e.keyCode == 13 && td.tdConsole) {
			JAK.Events.stopEvent(e);
		}
	}
	return true;
};

td.windowResize = function() {
	td.tdResizeWrapper();
	td.viewSize = JAK.DOM.getDocSize();
	if (td.tdFullWidth) {
		td.logContainer.style.width = td.tdContainer.clientWidth + 'px';
		td.logRowActive.style.width = (td.tdContainer.clientWidth - 48) + 'px';
	}
	for (var i = td.visibleTitles.length; i-- > 0;) td.titleAutosize(td.visibleTitles[i]);
};

td.tdResizeWrapper = function() {
	var viewWidth = td.tdView.clientWidth;
	var viewHeight = td.tdView.clientHeight;
	if (viewWidth > td.tdOuterWrapper.clientWidth) td.tdOuterWrapper.style.width = viewWidth + 'px';
	if (viewHeight > td.tdOuterWrapper.clientHeight) td.tdOuterWrapper.style.height = viewHeight + 'px';
	if (td.tdContainer.clientWidth > td.tdOuterWrapper.clientWidth) td.tdOuterWrapper.style.width = '100%';
};

td.selected = function() {
	for (var i = td.logRowsChosen.length; i-- > 0;) {
		if (td.logRowsChosen[i]) return true;
	}
	return false;
};

td.getPrevious = function() {
	for (var i = td.logRowActiveId; --i > 0;) {
		if (td.logRowsChosen[i - 1]) return i;
	}
	return td.logRowActiveId;
};

td.getNext = function() {
	for (var i = td.logRowActiveId, j = td.logRowsChosen.length; i++ < j;) {
		if (td.logRowsChosen[i - 1]) return i;
	}
	return td.logRowActiveId;
};

td.htmlEncode = function(text) {
	for (var retVal = '', i = 0, j = text.length; i < j; ++i) retVal += td.encodeChars[text[i]] || text[i];
	return retVal;
};

td.restore = function() {
	location.href = location.protocol + '//' + location.host + location.pathname;
};

td.sendChanges = function(e) {
	e = e || window.event;

	var change, request = [];
	for (var i = 0, j = td.changes.length; i < j; ++i) {
		change = td.changes[i].data;
		request.push([change.path, change.add === 2 ? JSON.stringify(change.value) : change.value, td.changes[i].data.add]);
	}

	if (!request.length) return false;

	var req = JAK.mel('form', {'action': location.protocol + '//' + location.host + location.pathname, method:'get'}, {'display': 'none'});
	if (e.shiftKey) req.target = '_blank';

	req.appendChild(JAK.mel('textarea', {'name': 'tdrequest', 'value': b62s.base8To62(b62s.compress(JSON.stringify(request)))}));
	td.logView.appendChild(req);
	req.submit();

	return false;
};

td.getRows = function(el, start, end) {
	var i = el.value.indexOf('\n', end || start);
	return [start - el.value.slice(0, start).split('\n').reverse()[0].length, i === -1 ? el.value.length : i];
};

td.duplicateText = function(e, el) {
	var start = el.selectionStart, end = el.selectionEnd;

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	if (start === end || el.value.slice(start, end).indexOf('\n') !== -1) {
		var rows = td.getRows(el, start, end);
		td.areaWrite(el, el.value.slice(0, rows[1]) + '\n' + el.value.slice(rows[0]), start, end);
	} else td.areaWrite(el, el.value.slice(0, end) + el.value.slice(start), start, end);

	return false;
};

td.removeRows = function(e, el) {
	var rows = td.getRows(el, el.selectionStart, el.selectionEnd);

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	td.areaWrite(el, el.value.slice(0, rows[0]) + el.value.slice(rows[1] + 1), rows[0]);

	return false;
};

td.getBlock = function(el, index) {
	var blockStart, i = td.findNearestChar(el.value, '[{', index, true, {'"': false});
	blockStart = i === false ? 0 : i;

	var blockEnd, j = td.findNearestChar(el.value, ']}', index, false, {'"': false});
	blockEnd = j === false ? el.value.length : j + 1;

	return [blockStart, blockEnd];
};

td.selectBlock = function(e, el) {
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
		s = td.getBlock(el, checkPoints[i]);
		if (s[1] - s[0] > longest[1] - longest[0]) longest = s;
	}

	el.selectionStart = longest[0];
	el.selectionEnd = longest[1];
	return false;
};

td.selectConsole  = function(e, el, key) {
	var end = el.selectionEnd;
	var text = el.value.slice(0, end) + key + el.value.slice(end);
	var start = td.findNearestChar(text, td.consoleSelects[key], end, true, {'"': false});

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	if (start === false) td.areaWrite(el, text, end + 1);
	else {
		td.areaWrite(el, text, start, end + 1);
		td.unselectConsole(el.selectedText = true);
	}

	return false;
};

td.unselectConsole = function(wait) {
	if (td.consoleSelectTimeout) {
		window.clearTimeout(td.consoleSelectTimeout);
		td.consoleSelectTimeout = null;
	}
	if (td.tdConsole === null) return false;

	if (wait) td.consoleSelectTimeout = window.setTimeout(td.unselectConsole, 1000);
	else {
		td.tdConsole.area.selectionStart = td.tdConsole.area.selectionEnd;
		td.tdConsole.area.selectedText = false;
	}
	return true;
};

td.wrapSelection = function(e, el, key) {
	var start = el.selectionStart, end = el.selectionEnd;

	JAK.Events.cancelDef(e);
	JAK.Events.stopEvent(e);

	var swap = td.keyChanges[key];
	var retVal = el.value.slice(0, start) + swap[0];

	if (end - start > 1 && (key == "'" || key == '"') && ((el.value[start] == '"' && el.value[end - 1] == '"') || (el.value[start] == "'" && el.value[end - 1] == "'"))) {
		td.areaWrite(el, retVal + el.value.slice(start + 1, end - 1) + swap[1] + el.value.slice(end), start + 1, end - 1);
	} else td.areaWrite(el, retVal + el.value.slice(start, end) + swap[1] + el.value.slice(end), start + 1, end + 1);

	return false;
};

td.areaWrite = function(el, text, start, end) {
	start = start || 0;
	end = end || start;
	var top = el.scrollTop;

	el.value = text;
	el.selectionStart = start;
	el.selectionEnd = end;
	el.scrollTop = top;
};

td.fire = function(text) {
	if (!--this.counter) {
		this.counter = 1000;
		console.clear();
	}
	console.debug(text);
};
