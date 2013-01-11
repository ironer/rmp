<?php

echo "<link rel=\"stylesheet\" href=\"" . WEBROOT . CSS . "/nette-dump.css\">\n";

/**
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 * Modified for RMCV by Stefan Fiedler [2013] (http://ironer.cz)
 */

/**
 * Dumps a variable.
 *
 * @author     David Grudl
 */
class Dumper
{
	const DEPTH = 'depth', // how many nested levels of array/object properties display (defaults to 4)
			TRUNCATE = 'truncate', // how truncate long strings? (defaults to 70)
			COLLAPSE = 'collapse', // always collapse? (defaults to false)
			COLLAPSE_COUNT = 'collapsecount', // how big array/object are collapsed? (defaults to 7)
			LOCATION = 'location', // show location string? (defaults to false)
			LOCATION_LINK = 'loclink', // show location string as link (defaults to true)
			NO_BREAK = 'nobreak', // return dump without line breaks (defaults to false)
			FORCE_HTML = 'html'; // force HTML output

	/** @var array */
	public static $terminalColors = array(
		'bool' => '1;33',
		'null' => '1;33',
		'number' => '1;32',
		'string' => '1;36',
		'array' => '1;31',
		'key' => '1;37',
		'object' => '1;31',
		'visibility' => '1;30',
		'resource' => '1;37',
		'indent' => '1;30',
	);

	/** @var array */
	public static $resources = array('stream' => 'stream_get_meta_data', 'stream-context' => 'stream_context_get_options', 'curl' => 'curl_getinfo');

	private static $appRecursion = false;

	/**
	 * Dumps variable to the output.
	 * @param $var
	 * @param array $options
	 * @return mixed  variable
	 */
	public static function dump($var, array $options = NULL)
	{
		self::$appRecursion = is_object($var) && (get_class($var) !== 'App');
		$options = (array) $options + array(self::FORCE_HTML => FALSE);

		if ($options[self::FORCE_HTML] || preg_match('#^Content-Type: text/html#im', implode("\n", headers_list()))) {
			return self::toHtml($var, $options);
		} elseif (self::$terminalColors && substr(getenv('TERM'), 0, 5) === 'xterm') {
			return self::toTerminal($var, $options);
		} else {
			return self::toText($var, $options);
		}
	}


	/**
	 * Dumps variable to HTML.
	 * @param $var
	 * @param array $options
	 * @return string
	 */
	public static function toHtml($var, array $options = NULL)
	{
		list($file, $line, $code) = empty($options[self::LOCATION]) ? NULL : self::findLocation();
		return '<pre class="nette-dump"'
				. ($file ? ' title="' . htmlspecialchars("$code\nin file " . ($fileTitle = substr($file, strlen(ROOT))) . " on line $line") . '">' : '>')
				. self::dumpVar($var, (array) $options + array(
					self::DEPTH => 4,
					self::TRUNCATE => 70,
					self::COLLAPSE => FALSE,
					self::COLLAPSE_COUNT => 7,
					self::NO_BREAK => FALSE
				))
				. ($file ? '<small>in <' . (empty($options[self::LOCATION_LINK]) ? 'span' : 'a href="editor://open/?file='
						. rawurlencode($file) . "&amp;line=$line\"" ) . " class=\"nette-dump-editor\"><i>"
						. htmlspecialchars($fileTitle) . "</i> <b>@$line</b></a></small>" : '') . "</pre>\n";
	}


	/**
	 * Finds the location where dump was called, tries to find method and object class if $getMethod is TRUE
	 * @param bool $getMethod
	 * @return array [file, line, code]
	 */
	public static function findLocation($getMethod = FALSE)
	{
		$backtrace = (debug_backtrace(FALSE));
		foreach ($backtrace as $id => $item) {
			if (isset($item['class']) && $item['class'] === 'Dumper') {
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
								$args[] = self::dumpVar($arg, array(self::FORCE_HTML => TRUE, self::DEPTH => -1, self::TRUNCATE => 10, self::NO_BREAK => TRUE));
							}
						}

						$code = $backtrace[$id]['class'] . $backtrace[$id]['type'] . $backtrace[$id]['function']
								. '(' . implode(', ', $args) . ')';
					} else {
						$code = '';
					}

				}

				return array(
					$item['file'],
					$item['line'],
					$code
				);
			}
		}
		return false;
	}


	/**
	 * Dumps variable to plain text.
	 * @param $var
	 * @param array $options
	 * @return string
	 */
	public static function toText($var, array $options = NULL)
	{
		return htmlspecialchars_decode(strip_tags(self::toHtml($var, $options)), ENT_QUOTES);
	}


	/**
	 * Dumps variable to x-terminal.
	 * @param $var
	 * @param array $options
	 * @return string
	 */
	public static function toTerminal($var, array $options = NULL)
	{
		return htmlspecialchars_decode(strip_tags(preg_replace_callback('#<span class="nette-dump-(\w+)">|</span>#', function($m) {
			return "\033[" . (isset($m[1], Dumper::$terminalColors[$m[1]]) ? Dumper::$terminalColors[$m[1]] : '0') . "m";
		}, self::toHtml($var, $options))), ENT_QUOTES);
	}


	/**
	 * Internal toHtml() dump implementation.
	 * @param $var
	 * @param array $options
	 * @param int $level
	 * @return string
	 */
	private static function dumpVar(&$var, array $options, $level = 0)
	{
		if (method_exists(__CLASS__, $m = 'dump' . gettype($var))) {
			return self::$m($var, $options, $level);
		} else {
			return "<span>unknown type</span>" . ($options[self::NO_BREAK] ? '' : "\n");
		}
	}


	private static function dumpNull(&$var, $options)
	{
		return "<span class=\"nette-dump-null\">NULL</span>" . ($options[self::NO_BREAK] ? '' : "\n");
	}


	private static function dumpBoolean(&$var, $options)
	{
		return '<span class="nette-dump-bool">' . ($var ? 'TRUE' : 'FALSE') . "</span>" . ($options[self::NO_BREAK] ? '' : "\n");
	}


	private static function dumpInteger(&$var, $options)
	{
		return "<span class=\"nette-dump-number\">$var</span>" . ($options[self::NO_BREAK] ? '' : "\n");
	}


	private static function dumpDouble(&$var, $options)
	{
		$var = var_export($var, TRUE);
		return '<span class="nette-dump-number">' . $var . (strpos($var, '.') === FALSE ? '.0' : '') . "</span>"
				. ($options[self::NO_BREAK] ? '' : "\n");
	}


	private static function dumpString(&$var, $options)
	{
		if ($options[self::TRUNCATE] && strlen($var) > $options[self::TRUNCATE]) {
			$retVal = self::encodeString(substr($var, 0, $options[self::TRUNCATE]), TRUE) . '</span> (' . strlen($var) . ')';
		} else {
			$retVal = self::encodeString($var) . '</span>';
		}
		if ($options[self::FORCE_HTML])
			$retVal = str_replace(array('\\r', '\\n', '\\t'), array('<b>\\r</b>', '<b>\\n</b>', '<b>\\t</b>'), $retVal);

		return '<span class="nette-dump-string">' . $retVal . ($options[self::NO_BREAK] ? '' : "\n");

	}


	private static function dumpArray(&$var, $options, $level)
	{
		static $marker;
		if ($marker === NULL) {
			$marker = uniqid("\x00", TRUE);
		}

		$out = '<span class="nette-dump-array">array</span> (';

		if (empty($var)) {
			return $out . "0)" . ($options[self::NO_BREAK] ? '' : "\n");

		} elseif (isset($var[$marker])) {
			return $out . (count($var) - 1) . ") [ <i>RECURSION</i> ]" . ($options[self::NO_BREAK] ? '' : "\n");

		} elseif (!$options[self::DEPTH] || $level < $options[self::DEPTH]) {
			$collapsed = $level ? count($var) >= $options[self::COLLAPSE_COUNT] : $options[self::COLLAPSE];
			$out = '<span class="nette-toggle' . ($collapsed ? '-collapsed">' : '">') . $out . count($var) . ")</span>\n<div" . ($collapsed ? ' class="nette-collapsed"' : '') . ">";
			$var[$marker] = TRUE;
			foreach ($var as $k => &$v) {
				if ($k !== $marker) {
					$out .= '<span class="nette-dump-indent">   ' . str_repeat('|  ', $level) . '</span>'
							. '<span class="nette-dump-key">' . (preg_match('#^\w+\z#', $k) ? $k : self::encodeString($k)) . '</span> => '
							. self::dumpVar($v, $options, $level + 1);
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
		$fields = (array) $var;

		static $list = array();
		$varClass = get_class($var);
		$out = '<span class="nette-dump-object">' . $varClass . "</span> (" . count($fields) . ')';

		if (empty($fields)) {
			return $options[self::NO_BREAK] ? $out : "$out\n";

		} elseif (in_array($var, $list, TRUE) || (self::$appRecursion && $varClass === 'App')) {
			return $out . " { <i>RECURSION</i> }" . ($options[self::NO_BREAK] ? '' : "\n");

		} elseif (!$options[self::DEPTH] || $level < $options[self::DEPTH]) {
			$collapsed = $level ? count($fields) >= $options[self::COLLAPSE_COUNT] : $options[self::COLLAPSE];
			$out = '<span class="nette-toggle' . ($collapsed ? '-collapsed">' : '">') . $out . "</span>\n<div" . ($collapsed ? ' class="nette-collapsed"' : '') . ">";
			$list[] = $var;
			foreach ($fields as $k => &$v) {
				$vis = '';
				if ($k[0] === "\x00") {
					$vis = ' <span class="nette-dump-visibility">' . ($k[1] === '*' ? 'protected' : 'private') . '</span>';
					$k = substr($k, strrpos($k, "\x00") + 1);
				}
				$out .= '<span class="nette-dump-indent">   ' . str_repeat('|  ', $level) . '</span>'
						. '<span class="nette-dump-key">' . (preg_match('#^\w+\z#', $k) ? $k : self::encodeString($k)) . "</span>$vis => "
						. self::dumpVar($v, $options, $level + 1);
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
		$out = '<span class="nette-dump-resource">' . htmlSpecialChars($type) . ' resource</span>';
		if (isset(self::$resources[$type])) {
			$out = "<span class=\"nette-toggle-collapsed\">$out</span>\n<div class=\"nette-collapsed\">";
			foreach (call_user_func(self::$resources[$type], $var) as $k => $v) {
				$out .= '<span class="nette-dump-indent">   ' . str_repeat('|  ', $level) . '</span>'
						. '<span class="nette-dump-key">' . htmlSpecialChars($k) . "</span> => " . self::dumpVar($v, $options, $level + 1);
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
		return '"' . htmlSpecialChars($s, ENT_NOQUOTES) . ($truncated ? '&hellip;' : '') . '"';
	}

}
