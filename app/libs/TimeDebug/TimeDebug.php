<?php

/**
 * Author of base PHP class 'Dumper': 2004 David Grudl (http://davidgrudl.com)
 * Author of 'TimeDebug': 2013 Stefan Fiedler
 */

class TimeDebug {

	const DEPTH = 'depth', // how many nested levels of array/object properties display (defaults to 4)
			TRUNCATE = 'truncate', // how truncate long strings? (defaults to 70)
			COLLAPSE = 'collapse', // always collapse? (defaults to false)
			COLLAPSE_COUNT = 'collapsecount', // how big array/object are collapsed? (defaults to 7)
			LOCATION = 'location', // show location string? (defaults to false)
			LOCATION_LINK = 'loclink', // show location string as link (defaults to true)
			NO_BREAK = 'nobreak', // return dump without line breaks (defaults to false)
			APP_RECURSION = 'apprecursion', // force { RECURSION } on all nested objects with given self::$recClass
			TAG_ID_PREFIX = 'tagidprefix', // sets the prefix of auto incrementing tags' ids for dumped titles (defaults to 'tId')
			PARENT_KEY = 'parentkey', // sets parent key for children's div to attribute 'data-pk' for arrays and objects
			DUMP_ID = 'dumpid', // id for .nd 'pre' in HTML form
			TDVIEW_INDEX = 'tdindex'; // data-tdindex of .nd 'pre' in tdView

	private static $initialized = FALSE;
	private static $advancedLog;
	private static $local;
	private static $root;
	private static $startTime;
	private static $startMem;
	private static $lastRuntime;
	private static $lastMemory;

	public static $recClass = 'App';

	public static $idPrefix = 'td';
	private static $idCounters = array('dumps' => array(), 'logs' => array(), 'titles' => array());

	private static $timeDebug = array();
	private static $timeDebugMD5 = array();

	public static $resources = array('stream' => 'stream_get_meta_data', 'stream-context' => 'stream_context_get_options', 'curl' => 'curl_getinfo');

	
	public static function init($advancedLog = FALSE, $local = FALSE, $root = '', $startTime = 0, $startMem = 0) {
		if (self::$initialized) throw new Exception("Trida TimeDebug uz byla inicializovana drive.");

		header('Content-type: text/html; charset=utf-8');
		header("Cache-control: private");
		echo "<!DOCTYPE html>\n<html>\n<head>\n<meta charset=\"utf-8\">\n<title>TimeDebug</title>\n<style>\n";
		readfile(__DIR__ . '/timedebug.css');
		echo "\n</style></head>\n<body>\n<div id=\"logContainer\">\n<div id=\"logWrapper\">\n<div id=\"logView\">\n";

		self::$advancedLog = !!($advancedLog);
		self::$local = !!($local);
		self::$root = $root;
		self::$lastRuntime = self::$startTime = $startTime ?: microtime(TRUE);
		self::$lastMemory = self::$startMem = $startMem;
		self::$initialized = TRUE;

		if (self::$advancedLog) register_shutdown_function(array(__CLASS__, '_closeDebug'));
	}


	public static function _closeDebug() {
		$tdHelp = array(
			'OVLADANI LOGU' => array(
				'↑' => 'posun na predchozi (oznaceny) log',
				'↓' => 'posun na nasledujici (oznaceny) log',
				'Left Click' => 'vyber logu',
				'Ctrl/Cmd + LC' => 'oznaceni/odznaceni logu',
				'Shift + LC' => 'oznaceni/odznaceni rozsahu logu'
			),
			'OVLADANI TITULKU' => array(
				'MouseWheel Up' => 'skrolovani nahoru',
				'MouseWheel Down' => 'skrolovani dolu',
				'Left Click' => 'prispendlit/odspendlit titulek',
				'Alt + LC' => 'presunout titulek',
				'Ctrl/Cmd + LC' => 'zmenit velikost titulku',
				'Ctrl/Cmd + Alt + LC' => 'vychozi velikost titulku',
				'Shift + Alt + LC' => 'zavrit titulek (s podtitulky)',
				'klavesa Esc' => 'zavrit a resetovat vsechny titulky'
			),
			'OVLADANI HVEZDICKY' => array(
				'Alt + LC' => 'zmena velikosti oken',
				'Shift + LC' => 'maximalizovany rezim'
			),
			'EDITACE PROMENNYCH (pouze local)' => array(
				'Right Click' => 'otevrit modal konzoli pro zadani',
				'Esc / (RC na masku)' => 'zavrit otevrenou konzoli',
				'klavesa Enter' => 'ulozit zmeny a zavrit konzoli',
				'Shift + Enter' => 'v konzoli dalsi radek'
			),
			'UPRAVA ZADANYCH ZMEN (pouze local)' => array(
				'Left Click' => 'naskrolovat na vybranou zmenu',
				'Right Click' => 'otevrit modal konzoli pro upravu',
				'LC na krizek' => 'vypnout automaticke zvyraznovani logu',
				'RC na krizek' => 'smazat vybranou zmenu'
			)
		);

		echo "\n<script>\n";
		readfile(__DIR__ . '/jak.packer.js');
		echo "\n";
		readfile(__DIR__ . '/timedebug.js');
		echo "\nTimeDebug.local = " . (self::$local ? 'true' : 'false') . ";\n"
				. "TimeDebug.indexes = ". json_encode(self::$timeDebug) . ";\n"
				. "TimeDebug.helpHtml = ". (!empty($tdHelp) ? json_encode(trim(self::toHtml($tdHelp))): "''") . ";\n"
				. "TimeDebug.init(1);\n</script>\n";
	}


	public static function lg($text = '', $object = NULL, $reset = FALSE) {
		if (!self::$initialized) throw new Exception("Trida TimeDebug nebyla inicializovana statickou metodou 'init'.");

		list($file, $line, $code) = self::findLocation(TRUE);

		if ($reset) {
			self::$lastRuntime = self::$startTime;
			self::$lastMemory = self::$startMem;
		}

		$textOut = $text = htmlspecialchars($text);

		if ($object) {
			$objects = array($object);
			$path =  isset($object->id) ? array($object->id) : array();

			while (isset($object->container)) {
				array_unshift($objects, $object = $object->container);
				if (isset($object->id)) $path[] = $object->id;
			}

			$textOut = ($path ? '<span class="nd-path">' . ($path = htmlspecialchars(implode('/', array_reverse($path)))) . '</span> ' : '')
					. $text;
		}

		if (self::$advancedLog && isset($objects)) {
			$dumpVars = array(); $i = 0;
			foreach($objects as $curObj) {
				$dumpVars[] = self::toHtml($curObj, array(self::TDVIEW_INDEX => $i++));
			}
			$dump = implode('<hr>', $dumpVars);
			$dumpMD5 = md5($dump);
			if (isset(self::$timeDebugMD5[$dumpMD5])) {
				self::$timeDebug[] = self::$timeDebugMD5[$dumpMD5];
			} else {
				self::$timeDebug[] = self::$timeDebugMD5[$dumpMD5] = $cnt = count(self::$timeDebugMD5);
				echo '<pre id="tdView_' . ++$cnt . '" class="nd-view-dump">' . $dump . '</pre>';
			}
			$tdParams = ' id="' . self::$idPrefix . 'L_' . self::incCounter('logs') . '" class="nd-row nd-log"';
		} else $tdParams = ' class="nd-row"';

		echo "<pre data-runtime=\"" . number_format(1000*(microtime(TRUE)-self::$startTime),2,'.','')
				. "\" data-title=\"" . (empty($path) ? '' : "$path> ") . "$text\"$tdParams>["
				. str_pad(self::runtime(self::$lastRuntime), 8, ' ', STR_PAD_LEFT) . ' / '
				. str_pad(self::memory(self::$lastMemory), 8, ' ', STR_PAD_LEFT) . ']' . " $textOut [<small>";

		if (self::$local) {
			echo '<a href="editor://open/?file=' . rawurlencode($file) . "&line=$line"
					. "\" class=\"nd-editor\"><i>" . htmlspecialchars(substr($file, strlen(self::$root))) . "</i> <b>@$line</b></a>";
		} else {
			echo "<span class=\"nd-editor\"><i>" . htmlspecialchars(substr($file, strlen(self::$root))) . "</i> <b>@$line</b></span>";
		}

		echo ($code ? " $code" : '') . '</small>]</pre>';
	}


	public static function dump(&$arg0 = NULL, &$arg1 = NULL, &$arg2 = NULL, &$arg3 = NULL, &$arg4 = NULL, &$arg5 = NULL, &$arg6 = NULL, &$arg7 = NULL, &$arg8 = NULL, &$arg9 = NULL) {

		if (!self::$initialized) throw new Exception("Trida TimeDebug nebyla inicializovana statickou metodou 'init'.");
		if (func_num_args() > 10) throw new Exception("Staticka metoda 'dump' muze prijmout nejvyse 10 argumentu.");

		$callbackIndex = (func_num_args() == 0) ? 1 : 0;
		$backtrace = debug_backtrace(FALSE);
		echo '<hr>';
		foreach ($backtrace[$callbackIndex]["args"] as &$var) {
			//if (is_array($var)) $var[0][0] = 'jana';
			echo self::toHtml($var, array('location' => TRUE, 'loclink' => LOCAL, 'dumpid' => self::$idPrefix . 'D_' . self::incCounter('dumps')));
			echo '<hr>';
		} unset($var);
	}


	public static function runtime($minus = NULL) {
		if ($minus === NULL) $minus = self::$startTime;
		return self::num(((self::$lastRuntime = microtime(TRUE)) - $minus) * 1000, 0, 'ms', $minus !== self::$startTime);
	}


	public static function memory($minus = NULL) {
		if ($minus === NULL) $minus = self::$startMem;
		return self::num(((self::$lastMemory = memory_get_usage()) - $minus) / 1024, 0, 'kB', $minus !== self::$startMem);
	}


	public static function maxMem($real = FALSE) {
		return self::num(memory_get_peak_usage($real) / 1024, 0, 'kB');
	}


	private static function num($val = 0, $decs = 2, $units = '', $delta = FALSE) {
		return ($delta && ($val = round($val, $decs)) > 0 ? '+' : '') . number_format($val, $decs, ',', ' ') . ($units ? " $units" : '');
	}


	private static function toHtml($var, array $options = NULL) {
		list($file, $line, $code) = empty($options[self::LOCATION]) ? NULL : self::findLocation();
		return '<pre' . (!empty($options[self::DUMP_ID]) ? ' data-runtime="' . number_format(1000*(microtime(TRUE)-self::$startTime),2,'.','')
				. '" id="' . $options[self::DUMP_ID] . '" class="nd nd-dump"': ' class="nd"')
				. (isset($options[self::TDVIEW_INDEX]) ? ' data-tdindex="' . $options[self::TDVIEW_INDEX] . '">' : '>')
				. self::dumpVar($var, (array) $options + array(
					self::DEPTH => 4,
					self::TRUNCATE => 70,
					self::COLLAPSE => FALSE,
					self::COLLAPSE_COUNT => 7,
					self::NO_BREAK => FALSE,
					self::APP_RECURSION => is_object($var) && (get_class($var) != self::$recClass)
				))
				. ($file ? '<small>in <' . (empty($options[self::LOCATION_LINK]) ? 'span' : 'a href="editor://open/?file='
						. rawurlencode($file) . "&amp;line=$line\"" ) . " class=\"nd-editor\"><i>"
						. htmlspecialchars(substr($file, strlen(self::$root))) . "</i> <b>@$line</b></a> $code</small>" : '') . "</pre>\n";
	}


	private static function incCounter($cType = 'titles') {
		if(isset(self::$idCounters[$cType][self::$idPrefix])) return ++self::$idCounters[$cType][self::$idPrefix];
		else return self::$idCounters[$cType][self::$idPrefix] = 1;
	}


	private static function findLocation($getMethod = FALSE) {
		$backtrace = debug_backtrace(FALSE);
		foreach ($backtrace as $id => $item) {
			if (isset($backtrace[$id + 1], $item['class']) && $item['class'] === 'TimeDebug') {
				continue;
			} elseif (!isset($item['file'], $item['line']) || !is_file($item['file'])) {
				break;
			} else {
				$lines = file($item['file']);
				$line = trim($lines[$item['line'] - 1]);

				if (!$getMethod) {
					$code = preg_match('#\w*dump(er::\w+)?\(.*\)#i', $line, $m) ? $m[0] : $line;
				} else {
					++$id;
					while (!isset($backtrace[$id]['function'], $backtrace[$id]['class'], $backtrace[$id]['type'])) {
						if (!isset($backtrace[++$id])) {
							$id = 0;
							break;
						}
					}

					if ($id) {
						$args = array();
						if (!empty($backtrace[$id]['args'])) {
							foreach($backtrace[$id]['args'] as $arg) {
								if(self::$advancedLog && is_array($arg) && $titleId = self::incCounter() && $cnt = count($arg)) {
									$args[] = '<span class="nd-array nd-titled"><span id="' . self::$idPrefix . 'T_' . $titleId
											. '" class="nd-title"><strong class="nd-inner"><pre class="nd">'
											.self::dumpVar($arg, array(
												self::APP_RECURSION => FALSE,
												self::DEPTH => 2,
												self::COLLAPSE => FALSE,
												self::COLLAPSE_COUNT => 5,
												self::TRUNCATE => 30,
												self::NO_BREAK => FALSE
											)) . '</pre></strong></span>array</span> (' . $cnt . ')';
								} else {
									$args[] = self::dumpVar($arg, array(
										self::APP_RECURSION => FALSE,
										self::DEPTH => -1,
										self::TRUNCATE => 10,
										self::NO_BREAK => TRUE
									));
								}
							}
						}

						$lines = file($backtrace[$id]['file']);
						$line = trim($lines[$backtrace[$id]['line'] - 1]);

						$code = '<span title="' . htmlspecialchars($line . "\nin " . substr($backtrace[$id]['file'], strlen(self::$root))
								. ' @' . $backtrace[$id]['line'], ENT_COMPAT) . '">' . $backtrace[$id]['class']
								. $backtrace[$id]['type'] . $backtrace[$id]['function'] . '</span>(' . implode(', ', $args) . ')';
					} else {
						$code = '';
					}

				}

				return array($item['file'], $item['line'], $code);
			}
		}
		return false;
	}


	private static function dumpVar(&$var, array $options, $level = 0) {
		if (method_exists(__CLASS__, $m = 'dump' . gettype($var))) {
			return self::$m($var, $options, $level);
		} else {
			return "<span>unknown type</span>" . ($options[self::NO_BREAK] ? '' : "\n");
		}
	}


	private static function dumpNull(&$var, $options, $level) {
		return '<span class="nd-null' . (($level || empty($options[self::DUMP_ID])) ? '' : ' nd-top') . '">NULL</span>'
				. ($options[self::NO_BREAK] ? '' : "\n");
	}


	private static function dumpBoolean(&$var, $options, $level) {
		return '<span class="nd-bool' . (($level || empty($options[self::DUMP_ID])) ? '' : ' nd-top') . '">'
				. ($var ? 'TRUE' : 'FALSE') . "</span>" . ($options[self::NO_BREAK] ? '' : "\n");
	}

	
	private static function dumpInteger(&$var, $options, $level) {
		return "<span class=\"nd-number" . (($level || empty($options[self::DUMP_ID])) ? '' : ' nd-top') . "\">$var</span>"
				. ($options[self::NO_BREAK] ? '' : "\n");
	}


	private static function dumpDouble(&$var, $options, $level) {
		$var = var_export($var, TRUE);
		return '<span class="nd-number' . (($level || empty($options[self::DUMP_ID])) ? '' : ' nd-top') . '">' . $var
				. (strpos($var, '.') === FALSE ? '.0' : '') . "</span>" . ($options[self::NO_BREAK] ? '' : "\n");
	}


	private static function dumpString(&$var, $options, $level) {
		$titleId = self::incCounter();

		if ($options[self::TRUNCATE] && ($varLen = strlen($var)) > $options[self::TRUNCATE]) {
			$retVal = '"' . self::encodeString(substr($var, 0, min($options[self::TRUNCATE], 512)), TRUE)
					. '&hellip;"</span> (' . $varLen . ')';
			$retTitle = self::$advancedLog ? '<span id="' . self::$idPrefix . 'T_' . $titleId
					. '" class="nd-title nd-color"><strong class="nd-inner"><i>'
					. str_replace(array('\\r', '\\n', '\\t'), array('<b>\\r</b>', '<b>\\n</b></i><i>', '<b>\\t</b>'),
						self::encodeString(substr($var, 0, max($options[self::TRUNCATE], 1024)), TRUE))
					. ($varLen > 1024 ? '&hellip; &lt; TRUNCATED to 1kB &gt;' : '') . '</i></strong></span>' : '';
			$retClass = self::$advancedLog ? ' nd-titled' : '';
		} else {
			$retTitle = '';
			$retVal = self::encodeString($var) . '</span>';
			$retClass = '';
		}

		$retVal = str_replace(array('\\r', '\\n', '\\t'), array('<b>\\r</b>', '<b>\\n</b>', '<b>\\t</b>'), $retVal);

		return '<span class="nd-string' . $retClass . (($level || empty($options[self::DUMP_ID])) ? '' : ' nd-top') . '">'
				. $retTitle . $retVal . ($options[self::NO_BREAK] ? '' : "\n");
	}


	private static function dumpArray(&$var, $options, $level)
	{
		$parentKey = isset($options[self::PARENT_KEY]) ? '1' . $options[self::PARENT_KEY] : FALSE;

		static $marker;
		if ($marker === NULL) {
			$marker = uniqid("\x00", TRUE);
		}

		$out = '<span class="nd-array' . (($level || empty($options[self::DUMP_ID])) ? '' : ' nd-top') . '">array</span> (';

		if (empty($var)) {
			return $out . "0)" . ($options[self::NO_BREAK] ? '' : "\n");

		} elseif (isset($var[$marker])) {
			return $out . (count($var) - 1) . ") [ <i>RECURSION</i> ]" . ($options[self::NO_BREAK] ? '' : "\n");

		} elseif (!$options[self::DEPTH] || $level < $options[self::DEPTH]) {
			$collapsed = $level ? count($var) >= $options[self::COLLAPSE_COUNT] : $options[self::COLLAPSE];
			$out = '<span class="nette-toggle' . ($collapsed ? '-collapsed">' : '">') . $out . count($var)
					. ")</span>\n<div" . ($collapsed ? ' class="nette-collapsed"' : '')
					. (self::$advancedLog && $parentKey ? " data-pk=\"$parentKey\">" : (!$level ? " data-pk=\"1\">" : '>'));
			$var[$marker] = TRUE;
			foreach ($var as $k => &$v) {
				if ($k !== $marker) {
					$out .= '<span class="nd-indent">   ' . str_repeat('|  ', $level) . '</span><span class="nd-key">'
							. (preg_match('#^\w+\z#', $k) ? $myKey = $k : '"' . ($myKey = self::encodeString($k, TRUE)) . '"')
							. '</span> => ' . self::dumpVar($v, array(self::PARENT_KEY => "$myKey") + $options, $level + 1);
				}
			}
			unset($var[$marker]);
			return $out . '</div>';

		} else {
			return $out . count($var) . ")" . ($options[self::NO_BREAK] ? '' : " [ ... ]\n");
		}
	}


	private static function dumpObject(&$var, $options, $level)
	{
		$parentKey = isset($options[self::PARENT_KEY]) ? '0' . $options[self::PARENT_KEY] : FALSE;

		$fields = (array) $var;

		static $list = array();
		$varClass = get_class($var);
		$out = '<span class="nd-object' . (($level || empty($options[self::DUMP_ID])) ? '' : ' nd-top') . '">' . $varClass
				. "</span> (" . count($fields) . ')';

		if (empty($fields)) {
			return $options[self::NO_BREAK] ? $out : "$out\n";

		} elseif (in_array($var, $list, TRUE) || ($options[self::APP_RECURSION] && $varClass === self::$recClass)) {
			return $out . " { <i>RECURSION</i> }" . ($options[self::NO_BREAK] ? '' : "\n");

		} elseif (!$options[self::DEPTH] || $level < $options[self::DEPTH]) {
			$collapsed = $level ? count($fields) >= $options[self::COLLAPSE_COUNT] : $options[self::COLLAPSE];
			$out = '<span class="nette-toggle' . ($collapsed ? '-collapsed">' : '">') . $out . "</span>\n<div"
					. ($collapsed ? ' class="nette-collapsed"' : '')
					. (self::$advancedLog && $parentKey ? " data-pk=\"$parentKey\">" : (!$level ? " data-pk=\"0$varClass\">" : '>'));
			$list[] = $var;
			foreach ($fields as $k => &$v) {
				$vis = '';
				if ($k[0] === "\x00") {
					$vis = ' <span class="nd-visibility">' . ($k[1] === '*' ? 'protected' : 'private') . '</span>';
					$k = substr($k, strrpos($k, "\x00") + 1);
				}
				$out .= '<span class="nd-indent">   ' . str_repeat('|  ', $level) . '</span><span class="nd-key">'
						. (preg_match('#^\w+\z#', $k) ? $myKey = $k : '"' . ($myKey = self::encodeString($k, TRUE)) . '"')
						. "</span>$vis => " . self::dumpVar($v, array(self::PARENT_KEY => "$myKey") + $options, $level + 1);
			}
			array_pop($list);
			return $out . '</div>';

		} else {
			return $options[self::NO_BREAK] ? "$out" : "$out { ... }\n";
		}
	}


	private static function dumpResource(&$var, $options, $level)
	{
		$type = get_resource_type($var);
		$out = '<span class="nd-resource">' . htmlSpecialChars($type) . ' resource</span>';
		if (isset(self::$resources[$type])) {
			$out = "<span class=\"nette-toggle-collapsed\">$out</span>\n<div class=\"nette-collapsed\">";
			foreach (call_user_func(self::$resources[$type], $var) as $k => $v) {
				$out .= '<span class="nd-indent">   ' . str_repeat('|  ', $level) . '</span>'
						. '<span class="nd-key">' . htmlSpecialChars($k) . "</span> => " . self::dumpVar($v, $options, $level + 1);
			}
			return $out . '</div>';
		}
		return $options[self::NO_BREAK] ? $out : "$out\n";
	}


	private static function encodeString($s, $truncated = FALSE)
	{
		static $utf, $binary;
		if ($utf === NULL) {
			foreach (range("\x00", "\xFF") as $ch) {
				if (ord($ch) < 32 && strpos("\r\n\t", $ch) === FALSE) {
					$utf[$ch] = $binary[$ch] = '\\x' . str_pad(dechex(ord($ch)), 2, '0', STR_PAD_LEFT);
				} elseif (ord($ch) < 127) {
					$utf[$ch] = $binary[$ch] = $ch;
				} else {
					$utf[$ch] = $ch; $binary[$ch] = '\\x' . dechex(ord($ch));
				}
			}
			$binary["\\"] = '\\\\';
			$utf["\t"] = $binary["\t"] = '\\t';
			$utf["\r"] = $binary["\r"] = '\\r';
			$utf["\n"] = $binary["\n"] = '\\n';
			$utf['\\x'] = $binary['\\x'] = '\\\\x';
		}

		$s = strtr($s, preg_match('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]#u', $s) || preg_last_error() ? $binary : $utf);
		return $truncated ? htmlSpecialChars($s, ENT_NOQUOTES) : '"' . htmlSpecialChars($s, ENT_NOQUOTES) . '"';
	}

}

