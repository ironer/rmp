<?php

define('DEBUG', TRUE);
define('TIMEDEBUG', TRUE);
define('NOW', microtime(TRUE));
define('MEMORY', memory_get_usage());
define('ROOT', __DIR__);
define('APP', ROOT . "/app");
define('WEBPATH', (strlen($_webdir = dirname($_SERVER['SCRIPT_NAME'])) === 1 ? '' : $_webdir)); unset($_webdir);
define('WEBROOT', (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . WEBPATH);


try {
	$app = require_once(APP . '/mlmplus.php');
	$app->go();
} catch (Exception $e) {
	list($message, $file, $line) = array(htmlspecialchars($e->getMessage()), $e->getFile(), $e->getLine());
	echo "<pre style=\"margin: 3px 0\">Zachycena vyjimka: $message [<small><a href=\"editor://open/?file=" . rawurlencode($file)
			. "&line=$line" . '"><i>' . htmlspecialchars(substr($file, strlen(ROOT))) . "</i> <b>@$line</b></a></small>]</pre>";
}

if (DEBUG) {
	App::lg('Zobrazeni debuggeru', $app);
	echo '<hr>Generovani odpovedi: <b>' . App::runtime() . '</b>'
			. ' / Max. pouzita pamet: <b>' . App::maxMem() . '</b> / Max. alokovana pamet: <b>' . App::maxMem(TRUE) . '</b>';
	if (TIMEDEBUG) {
		?>
	/ Zmena logu: <b>&larr;</b> a <b>&rarr;</b> / <a href="<?=WEBROOT?>?mail=1">odeslat email</a>
	<hr>
	<script>
		var _ddLogs = <?=json_encode(App::$deepDebug)?>;
		var _preTags = document.body.getElementsByTagName('pre');
		var _ddRows = [];
		for(var i in _preTags) {
			if (hasClass(_preTags[i], 'nette-dump-row')) {
				_ddRows.push(_preTags[i]);
				_preTags[i].onclick = ddMouseClick;
			}
		}

		var _ddChosen = [];
		_ddChosen.length = _ddRows.length;
		var _ddActive = 0;
		var _ddView = document.createElement('div');
		document.body.appendChild(_ddView);
		showDdLog(1);

		function ddMouseClick(e) {
			e = e || window.event;
			var id = parseInt(this.id.split('_')[1]);

			if (e.altKey) {
			} else if (e.ctrlKey || e.metaKey) {
				_ddChosen[id - 1] = !_ddChosen[id - 1];
				if (_ddChosen[id - 1]) addClass(_ddRows[id - 1], 'nette-dump-chosen');
				else removeClass(_ddRows[id - 1], 'nette-dump-chosen');
			} else if (e.shiftKey) {
				var unset = false;
				if (hasClass(this, 'nette-dump-chosen')) unset = true;
				for(var i = Math.min(_ddActive, id), j = Math.max(_ddActive, id); i <= j; i++) {
					if (unset) {
						_ddChosen[i - 1] = false;
						removeClass(_ddRows[i - 1], 'nette-dump-chosen');
					} else {
						_ddChosen[i - 1] = true;
						addClass(_ddRows[i - 1], 'nette-dump-chosen');
					}
				}
			} else {
				showDdLog(id);
			}
			return false;
		}

		function showDdLog(id) {
			if (_ddActive === id) return false;
			if (_ddActive) removeClass(document.getElementById('ddId_' + _ddActive), 'nette-dump-active');
			addClass(document.getElementById('ddId_' + id), 'nette-dump-active');
			_ddActive = id;
			_ddView.innerHTML = _ddLogs[id - 1];
		}

		document.onkeydown = function(e) {
			e = e || window.event;

			if (!e.shiftKey && !e.altKey && !e.ctrlKey && !e.metaKey) {
				if (e.keyCode == 37 && _ddActive > 1) {
					showDdLog(selected() ? getPrev() : _ddActive - 1);
					return false;
				} else if (e.keyCode == 39 && _ddActive < _ddLogs.length) {
					showDdLog(selected() ? getNext() : _ddActive + 1);
					return false;
				}
			}
		};

		function selected() {
			for(var i = _ddChosen.length, j = 0; i-- > 0;) {
				if (!_ddChosen[i]) continue;
				j++;
				if (j > 1) return true;
			}
			return false;
		}

		function getPrev() {
			for(var i = _ddActive; --i > 0;) {
				if (_ddChosen[i - 1]) return i;
			}
			return _ddActive;
		}

		function getNext() {
			for(var i = _ddActive, j = _ddChosen.length; i++ < j;) {
				if (_ddChosen[i - 1]) return i;
			}
			return _ddActive;
		}

		function hasClass(el, classes) {
			var classNames = el.className;
			if (!classNames) return false;
			classes = classes.split(' ');
			for (var i = 0, j = classes.length; j-- > 0;) {
				if (classNames.indexOf(classes[j]) !== -1) i++;
			}
			if (i == classes.length) return true;
			return false;
		};

		function addClass(el, classes) {
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

		function removeClass(el, classes) {
			var classNames = el.className.split(' ');
			if (classNames.length == 0) { return el; }
			var newClassNames = [];
			for (var i = 0, j = classes.length; i < j; i++) {
				if (classNames[i] && classes.indexOf(classNames[i]) === -1) newClassNames.push(classNames[i]);
			}
			el.className = newClassNames.join(' ');
			return el;
		}

	</script>
	<hr>
	<?php
	} else {
		App::dump($app);
	}
}
