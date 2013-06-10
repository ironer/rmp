<?php

/*
 * $data is non-associative array of non-associtive arrays (rows) containing data
 * $columns is non-associative array of associtive arrays (columns):
 * 	format: cell data format (number, ut, defaults to text)
 * 	header: header of column
 * 	pre: prefix of every data in given column
 * 	post: postfix of every data in given column
 * 	funct: PHP aggregate function (min, max, avg, sum), which is calculated on all data in column
 * 	align: align of text in cell (right, center, defaults to left)
 */


class Table {
	const
		TABLE_TYPE = 'type',
		TYPE_HTML = 'html', // default
		TYPE_XML = 'xml',

		OUTPUT_STREAM = 'stream',

		EXPORT_FILENAME = 'filename',
		TABLE_SOURCE = 'source',
		TABLE_COLUMNS = 'columns',

		COLUMN_FORMAT = 'format',
		COLUMN_HEADER = 'header',
		COLUMN_PREFIX = 'pre',
		COLUMN_POSTFIX = 'post',
		COLUMN_FUNCTION = 'func',
		COLUMN_ALIGN = 'align',
		COLUMN_CHAR_WIDTH = 'width',

		T_HEAD_TR_BACKGROUND = 'headbg',
		T_BODY_TR_EVEN_BACKGROUND = 'evenbg',
		T_FOOT_TR_BACKGROUND = 'footbg',

		FORMAT_TEXT = 'text',
		FORMAT_INTEGER = 'int',
		FORMAT_FLOAT = 'float',
		FORMAT_UT = 'ut',

		FLOAT_DECIMALS = 'decs',
		DATE_FORMAT = 'date',
		XML_DATE_STRING = 'Y-m-d\TH:i:s.000',
		XML_ZOOM = 'zoom';

	private static $date2xml = array('d' => 'dd', 'j' => 'd', 'm' => 'mm', 'n' => 'm', 'y' => 'yy', 'Y' => 'yyyy',
		'G' => 'h', 'H' => 'hh', 'i' => 'mm', 's' => 'ss', ' ' => '\ ', '.' => '\.', '/' => '\/', '-' => '\-', ':' => '\:');

	public $id;
	public $container;

	private $type = self::TYPE_HTML;
	private $stream = FALSE;

	private $filename = 'document';
	private $columns = array();
	private $data;
	private $resource;
	private $resColumns = array();

	private $headBg = '#99CCFF';
	private $evenBg = '#CCFFFF';
	private $footBg = '#CCFFCC';

	private $decimals = 2;
	private $xmlDecs = '00';
	private $dateFormat = 'd.m.Y H:i:s';
	private $xmlDate = 'dd\.mm\.yyyy\ hh\:mm\:ss';
	private $xmlZoom = 100;

	private $debug = FALSE;


	public function __construct($id = '', $container = NULL) {
		$this->debug = defined('DEBUG') ? DEBUG : FALSE;

		$this->id = strval($id) ?: 'worksheet';

		if ($container instanceof App) $this->container = $container;

		if ($this->debug) App::lg("Vytvoren exporter tabulky '$this->id'", $this);
	}


	public function config($options = array()) {
		if (!is_array($options)) throw new Exception("Konfigurator exporteru tabulky ocekava pole s konfiguraci.");

		if (!empty($options[self::TABLE_TYPE]) && $options[self::TABLE_TYPE] === self::TYPE_XML && !$this->debug) {
			$this->type = self::TYPE_XML;
		}

		if (isset($options[self::OUTPUT_STREAM])) $this->stream = !!$options[self::OUTPUT_STREAM];

		if (!empty($options[self::EXPORT_FILENAME])) $this->filename = $options[self::EXPORT_FILENAME];

		if (!empty($options[self::TABLE_SOURCE])) {
			if (($type = gettype($options[self::TABLE_SOURCE])) === 'resource') $this->resource = $options[self::TABLE_SOURCE];
			elseif ($type === 'array' && isset($options[self::TABLE_SOURCE][0][0])) $this->data = $options[self::TABLE_SOURCE];
		}

		if (!empty($options[self::TABLE_COLUMNS])) $this->columns = $options[self::TABLE_COLUMNS];

		if (!empty($options[self::T_HEAD_TR_BACKGROUND])) $this->headBg = $options[self::T_HEAD_TR_BACKGROUND];

		if (!empty($options[self::T_BODY_TR_EVEN_BACKGROUND])) $this->evenBg = $options[self::T_BODY_TR_EVEN_BACKGROUND];

		if (!empty($options[self::T_FOOT_TR_BACKGROUND])) $this->footBg = $options[self::T_FOOT_TR_BACKGROUND];

		if (!empty($options[self::FLOAT_DECIMALS])) {
			$this->decimals = $options[self::FLOAT_DECIMALS];
			$this->xmlDecs = str_pad('', $this->decimals, '0');
		}

		if (!empty($options[self::DATE_FORMAT])) {
			if (1 === preg_match('#^[djmnyY][\./-][djmnyY][\./-][djmnyY] [GH]:i(:s)?$#', $options[self::DATE_FORMAT])) {
				$this->dateFormat = $options[self::DATE_FORMAT];
				$this->xmlDate = strtr($this->dateFormat, self::$date2xml);
			}
		}

		if (!empty($options[self::XML_ZOOM])) $this->xmlZoom = intval(strval($options[self::XML_ZOOM]));

		if ($this->debug) App::lg('Nactena konfigurace', $this);
	}


	public function go() {
		mb_internal_encoding('UTF-8');

		if (isset($this->resource) && $row = mysql_fetch_assoc($this->resource)) {
			$this->resColumns = array_keys($row);
			$this->data = array(array_values($row));
		} elseif (empty($this->data)) $this->data = array();

		if (!isset($this->data[0])) {}
		elseif (($i = count($this->columns)) > ($j = count($this->data[0]))) array_splice($this->columns, $j);
		elseif ($i < $j) for (; $i < $j; ++$i) $this->columns[$i] = array();

		if ($this->debug) App::lg('Tabulka pripravena pro export', $this);

		$columns = $this->prepareColumns();

		if ($this->type === self::TYPE_XML) {
			$this->sendHeaders();
			echo $this->getXmlHead() . $this->getXmlStyles() . "<Worksheet ss:Name=\"$this->id\">\n\t<Table>\n";

			if ($this->stream) {
				echo $this->getXmlColumns($columns);
				$this->getTable($columns);
			} else {
				$xmlTable = $this->getTable($columns);
				echo $this->getXmlColumns($this->columns) . $xmlTable;
			}

			echo "\t</Table>\n" . $this->getXmlWorksheetOptions() . "</Worksheet>\n</Workbook>";
			die;
		} elseif ($this->stream) {
			echo "\t<table border=\"1\" cellpadding=\"3\" cellspacing=\"0\">\n";
			$this->getTable($columns);
			echo "\t</table>\n";
			if ($this->debug) App::lg('Tabulka vypsana', $this);
		} else {
			$htmlTable = $this->getTable($columns);
			if ($this->debug) App::lg('Tabulka ulozena do promenne', $this);
			return "\t<table border=\"1\" cellpadding=\"3\" cellspacing=\"0\">\n" . $htmlTable . "\t</table>\n";
		}
		return FALSE;
	}


	private function sendHeaders() {
		if ($this->type === self::TYPE_XML) {
			header("Pragma: no-cache");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: application/vnd.ms-excel; charset=utf-8");
			header("Content-Disposition: attachment; filename=$this->filename.xml");
		}
	}


	private function getTable(&$columns) {
		$rowEls = array(self::TYPE_HTML => array('_tr' => "\t\t<tr>\n", '_/tr' => "\t\t</tr>\n", '_trLeft' => "\t\t<tr align=\"left\">\n"),
			self::TYPE_XML => array('_tr' => "\t\t<Row>\n", '_/tr' => "\t\t</Row>\n", '_trLeft' => "\t\t<Row>\n"));

		$header = $rowEls[$this->type]['_tr'] . $this->getTableHeader($columns) . $rowEls[$this->type]['_/tr'];
		if ($this->stream) echo $header;

		$body = '';
		$rowNum = 0;
		if (isset($this->data)) {
			for ($j = count($this->data); $rowNum < $j; ++$rowNum) {
				$body .= $rowEls[$this->type]['_trLeft'] . $this->getRow($this->data[$rowNum], $rowNum + 1, $columns) . $rowEls[$this->type]['_/tr'];
				if ($this->stream) {
					echo $body;
					$body = '';
				}
			}
		}
		if (isset($this->resource)) {
			while ($row = mysql_fetch_assoc($this->resource)) {
				$body .= $rowEls[$this->type]['_trLeft'] . $this->getRow(array_values($row), ++$rowNum, $columns) . $rowEls[$this->type]['_/tr'];
				if ($this->stream) {
					echo $body;
					$body = '';
				}
			}
		}

		$footer = $this->getTableFooter($rowNum, $columns);
		$footer = $rowEls[$this->type]['_tr'] . $footer[0] . $rowEls[$this->type]['_/tr']
			. $rowEls[$this->type]['_tr'] . $footer[1] . $rowEls[$this->type]['_/tr'];

		if ($this->stream) {
			echo $footer;
			return TRUE;
		}

		return $header . $body . $footer;
	}


	private function getTableHeader(&$columns) {
		for ($header = $colgroup = '', $i = 0, $j = count($columns); $i < $j; ++$i) {
			$value = htmlspecialchars($columns[$i][self::COLUMN_HEADER], ENT_QUOTES);

			if ($columns[$i][self::COLUMN_CHAR_WIDTH] === 'auto'
				&& ($len = mb_strlen(strval($columns[$i][self::COLUMN_HEADER]))) > $columns[$i]['_maxLength']) $columns[$i]['_maxLength'] = $len;

			if ($this->type === self::TYPE_HTML) {
				$header .= "\t\t\t<th bgcolor=\"$this->headBg\"" . ' style="mso-number-format: \'@\'">' . $value . "</th>\n";
			} else {
				$header .= "\t\t\t<Cell ss:StyleID=\"h\"><Data ss:Type=\"String\">" . $value . "</Data></Cell>\n";
			}
		}

		return $header;
	}


	private function getRow($row, $rowNum, &$columns) {
		$rowText = '';
		for ($i = 0, $j = count($row); $i < $j; ++$i) {
			$col = &$columns[$i];

			switch($col[self::COLUMN_FORMAT]) {
				case self::FORMAT_INTEGER:
					$var = number_format($value = is_int($row[$i]) ? $row[$i] : intval(strval($row[$i])), 0, '.', '');
					if ($col[self::COLUMN_CHAR_WIDTH] === 'auto' && ($len = mb_strlen(strval($var)) + $col['_prePostLen']) > $col['_maxLength']) {
						$col['_maxLength'] = $len;
					}
					break;
				case self::FORMAT_FLOAT:
					$var = number_format($value = is_float($row[$i]) ? $row[$i] : floatval(strval($row[$i])), $this->decimals, '.', '');
					if ($col[self::COLUMN_CHAR_WIDTH] === 'auto' && ($len = mb_strlen(strval($var)) + $col['_prePostLen']) > $col['_maxLength']) {
						$col['_maxLength'] = $len;
					}
					break;
				case self::FORMAT_UT:
					$var = date($this->dateFormat, $value = intval(strval($row[$i])));
					if ($col[self::COLUMN_CHAR_WIDTH] === 'auto' && ($len = mb_strlen(strval($var)) + $col['_prePostLen']) > $col['_maxLength']) {
						$col['_maxLength'] = $len;
					}
					if ($this->type === self::TYPE_XML) $var = date(self::XML_DATE_STRING, $value);
					break;
				default:
					$var = htmlspecialchars($value = strval($row[$i]), ENT_QUOTES);
					if ($col[self::COLUMN_CHAR_WIDTH] === 'auto' && ($len = mb_strlen($value) + $col['_prePostLen']) > $col['_maxLength']) {
						$col['_maxLength'] = $len;
					}
			}

			switch($col[self::COLUMN_FUNCTION]) {
				case 'min':
					if (!isset($col['_calc']) || ($col['_num'] ? $value < $col['_calc'] : strcasecmp($value, $col['_calc']) < 0)) {
						$col['_calc'] = $value;
					}
					break;
				case 'max':
					if (!isset($col['_calc']) || ($col['_num'] ? $value > $col['_calc'] : strcasecmp($value, $col['_calc']) > 0)) {
						$col['_calc'] = $value;
					}
					break;
				case 'avg':
				case 'sum':
					if (!isset($col['_calc'])) $col['_calc'] = $value;
					else $col['_calc'] += $value;
			}

			$num = FALSE;
			if ($col['_prePostLen']) $var = $col[self::COLUMN_PREFIX] . $var . $col[self::COLUMN_POSTFIX];
			else $num = $col['_num'];

			if ($this->type === self::TYPE_HTML) {
				$attributes = $rowNum % 2 ? '' : " bgcolor=\"$this->evenBg\"";
				if (!$num) $attributes .= ' style="mso-number-format: \'@\'"';
				elseif ($col[self::COLUMN_FORMAT] === self::FORMAT_UT) $attributes .= ' style="mso-number-format: \'' . $this->xmlDate . '\'"';
				elseif ($col[self::COLUMN_FORMAT] === self::FORMAT_FLOAT) $attributes .= ' style="mso-number-format: \'0.' . $this->xmlDecs . '\'"';
				else $attributes .= ' style="mso-number-format: \'0\'"';

				if ($col[self::COLUMN_ALIGN] != 'left') $attributes .= ' align="' . $col[self::COLUMN_ALIGN] . '"';
				$rowText .= "\t\t\t<td" . $attributes . '>' . $var . "</td>\n";
			} else {
				if (!$num) $type = array('String', 't');
				elseif ($col[self::COLUMN_FORMAT] === self::FORMAT_UT) $type = array('DateTime', 'u');
				elseif ($col[self::COLUMN_FORMAT] === self::FORMAT_FLOAT) $type = array('Number', 'f');
				else $type = array('Number', 'i');

				$styleId = ($rowNum % 2 ? 'o' : 'e') . $col['_xmlAlign'] . $type[1];

				$rowText .= "\t\t\t<Cell ss:StyleID=\"$styleId\"><Data ss:Type=\"$type[0]\">" . $var . "</Data></Cell>\n";
			}

		} unset($col);

		return $rowText;
	}


	private function getTableFooter($rowNum, &$columns) {
		$labels = $results = '';

		for ($i = 0, $j = count($columns); $i < $j; ++$i) {
			$col = &$columns[$i];
			$result = NULL;
			$label = '';

			switch ($col[self::COLUMN_FUNCTION]) {
				case 'min':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'min';
					}
					break;
				case 'max':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'max';
					}
					break;
				case 'avg':
					if (isset($col['_calc'])) {
						$result = $rowNum ? round($col['_calc'] / $rowNum, $this->decimals) : 0;
						$label = 'ø';
					}
					break;
				case 'sum':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'Σ';
					}
			}

			$num = FALSE;
			$float = $col[self::COLUMN_FORMAT] === self::FORMAT_FLOAT;

			if ($result !== NULL) {
				switch($col[self::COLUMN_FORMAT]) {
					case self::FORMAT_INTEGER:
						if ($col[self::COLUMN_FUNCTION] === 'avg') {
							$var = number_format(is_float($result) ? $result : floatval(strval($result)), $this->decimals, '.', '');
							$float = TRUE;
						} else {
							$var = number_format(is_int($result) ? $result : intval(strval($result)), 0, '.', '');
						}
						if ($col[self::COLUMN_CHAR_WIDTH] === 'auto' && ($len = mb_strlen(strval($var)) + $col['_prePostLen']) > $col['_maxLength']) {
							$col['_maxLength'] = $len;
						}
						break;
					case self::FORMAT_FLOAT:
						$var = number_format(is_float($result) ? $result : floatval(strval($result)), $this->decimals, '.', '');
						if ($col[self::COLUMN_CHAR_WIDTH] === 'auto' && ($len = mb_strlen(strval($var)) + $col['_prePostLen']) > $col['_maxLength']) {
							$col['_maxLength'] = $len;
						}
						break;
					case self::FORMAT_UT:
						$var = date($this->dateFormat, $value = intval(strval($result)));
						if ($col[self::COLUMN_CHAR_WIDTH] === 'auto' && ($len = mb_strlen(strval($var)) + $col['_prePostLen']) > $col['_maxLength']) {
							$col['_maxLength'] = $len;
						}
						if ($this->type === self::TYPE_XML) $var = date(self::XML_DATE_STRING, $value);
						break;
					default:
						$var = htmlspecialchars(strval($result), ENT_QUOTES);
						if ($col[self::COLUMN_CHAR_WIDTH] === 'auto' && ($len = mb_strlen(strval($result)) + $col['_prePostLen']) > $col['_maxLength']) {
							$col['_maxLength'] = $len;
						}
				}

				if ($col['_prePostLen']) $var = $col[self::COLUMN_PREFIX] . $var . $col[self::COLUMN_POSTFIX];
				else $num = $col['_num'];
			} else $var = '';


			if ($col[self::COLUMN_CHAR_WIDTH] === 'default') $this->columns[$i][self::COLUMN_CHAR_WIDTH] = 'default';
			elseif ($col[self::COLUMN_CHAR_WIDTH] === 'auto') $this->columns[$i][self::COLUMN_CHAR_WIDTH] = $col['_maxLength'] ?: 'default';
			else $this->columns[$i][self::COLUMN_CHAR_WIDTH] = $col[self::COLUMN_CHAR_WIDTH];

			if ($this->type === self::TYPE_HTML) {
				if (!$num) $attributes = 'style="mso-number-format: \'@\'"';
				elseif ($col[self::COLUMN_FORMAT] === self::FORMAT_UT) $attributes = 'style="mso-number-format: \'' . $this->xmlDate . '\'"';
				elseif ($float) $attributes = 'style="mso-number-format: \'0.' . $this->xmlDecs . '\'"';
				else $attributes = 'style="mso-number-format: \'0\'"';

				if ($col[self::COLUMN_ALIGN] != 'center') $attributes .= ' align="' . $col[self::COLUMN_ALIGN] . '"';

				$results .= "\t\t\t<th bgcolor=\"$this->footBg\" $attributes>" . $var . "</td>\n";
				$labels .=  "\t\t\t<th bgcolor=\"$this->footBg\">" . $label . "</th>\n";
			} else {
				if (!$num) $type = array('String', 't');
				elseif ($col[self::COLUMN_FORMAT] === self::FORMAT_UT) $type = array('DateTime', 'u');
				elseif ($float) $type = array('Number', 'f');
				else $type = array('Number', 'i');

				$results .= "\t\t\t<Cell ss:StyleID=\"r$col[_xmlAlign]$type[1]\"><Data ss:Type=\"$type[0]\">" . $var . "</Data></Cell>\n";
				$labels .=  "\t\t\t<Cell ss:StyleID=\"f\"><Data ss:Type=\"String\">" . $label . "</Data></Cell>\n";
			}
		} unset($col);

		return array($results, $labels);
	}


	private function prepareColumns() {
		for ($columns = array(), $i = 0, $j = count($this->columns); $i < $j; ++$i) {
			$col = &$columns[$i];

			$col = (array) $this->columns[$i] + array(
				self::COLUMN_FORMAT => self::FORMAT_TEXT,
				self::COLUMN_HEADER => isset($this->resColumns[$i]) ? $this->resColumns[$i] : 'Sloupec ' . ($i + 1),
				self::COLUMN_PREFIX => '',
				self::COLUMN_POSTFIX => '',
				self::COLUMN_FUNCTION => '',
				self::COLUMN_ALIGN => 'left',
				self::COLUMN_CHAR_WIDTH => 'auto',
				'_maxLength' => 0
			);

			$col['_prePostLen'] = mb_strlen($col[self::COLUMN_PREFIX] . $col[self::COLUMN_POSTFIX]);
			$col[self::COLUMN_PREFIX] = htmlspecialchars($col[self::COLUMN_PREFIX], ENT_QUOTES);
			$col[self::COLUMN_POSTFIX] = htmlspecialchars($col[self::COLUMN_POSTFIX], ENT_QUOTES);

			$func = $col[self::COLUMN_FUNCTION] = strtolower(strval($col[self::COLUMN_FUNCTION]));

			if (in_array($align = strtolower($col[self::COLUMN_ALIGN]), array('left', 'center', 'right'), TRUE)) {
				$col[self::COLUMN_ALIGN] = $align;
				$col['_xmlAlign'] = $align[0];
			} else {
				$col[self::COLUMN_ALIGN] = 'left';
				$col['_xmlAlign'] = 'l';
			}

			$col['_num'] = $col[self::COLUMN_FORMAT] === self::FORMAT_INTEGER || $col[self::COLUMN_FORMAT] === self::FORMAT_FLOAT
				|| $col[self::COLUMN_FORMAT] === self::FORMAT_UT;;

			if (!$col['_num'] && ($func === 'sum' || $func === 'avg')) $col[self::COLUMN_FUNCTION] = '';

			if (strtolower($col[self::COLUMN_CHAR_WIDTH]) === 'auto') {
				$col[self::COLUMN_CHAR_WIDTH] = $this->type === $this->stream ? 'default' : 'auto';//self::TYPE_HTML ||
			} else $col[self::COLUMN_CHAR_WIDTH] = intval(strval($col[self::COLUMN_CHAR_WIDTH]));

		} unset($col);

		return $columns;
	}


	private function getXmlHead() {
		return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<?mso-application progid=\"Excel.Sheet\"?>"
			. "\n<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\""
			. " xmlns:x=\"urn:schemas-microsoft-com:office:excel\""
			. " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\""
			. " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n";
	}


	private function getXmlWorksheetOptions() {
		return "\t<WorksheetOptions xmlns=\"urn:schemas-microsoft-com:office:excel\">\n"
			. "\t\t<Zoom>$this->xmlZoom</Zoom>\n"
			. "\t\t<FrozenNoSplit />\n"
			. "\t\t<SplitHorizontal>1</SplitHorizontal>\n"
			. "\t\t<TopRowBottomPane>1</TopRowBottomPane>\n"
			. "\t\t<ActivePane>2</ActivePane>\n"
			. "\t</WorksheetOptions>\n";
	}


	private function getXmlColumns(&$columns) {
		for ($colsXml = '', $i = 0, $j = count($columns); $i < $j; ++$i) {
			$colsXml .= "\t\t<Column ss:AutoFitWidth=\"0\"";

			if (in_array($columns[$i][self::COLUMN_CHAR_WIDTH], array('default', 'auto'), TRUE)) $colsXml .= " ss:Width=\"asdsada\"";
			else $colsXml .= " ss:Width=\"" . min(500, 10 * $columns[$i][self::COLUMN_CHAR_WIDTH]) . "\"";

			$colsXml .= " />\n";
		}
		return $colsXml;
	}


	private function getXmlStyles() {
		return "<Styles>\n"

			. "\t<Style ss:ID=\"h\" ss:Name=\"h\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Top\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Interior ss:Color=\"$this->headBg\" ss:Pattern=\"Solid\" />\n"
			. "\t\t<Font ss:Bold=\"1\" ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\" ss:WrapText=\"1\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"
			. "\t<Style ss:ID=\"f\" ss:Parent=\"h\">\n"
			. "\t\t<Interior ss:Color=\"$this->footBg\" ss:Pattern=\"Solid\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\" ss:WrapText=\"0\" />\n"
			. "\t</Style>\n"

			. "\t<Style ss:ID=\"olt\" ss:Name=\"olt\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Font ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Left\" ss:Vertical=\"Center\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"
			. "\t<Style ss:ID=\"elt\" ss:Name=\"elt\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Top\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Interior ss:Color=\"$this->evenBg\" ss:Pattern=\"Solid\" />\n"
			. "\t\t<Font ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Left\" ss:Vertical=\"Center\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"
			. "\t<Style ss:ID=\"oct\" ss:Name=\"oct\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Font ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"
			. "\t<Style ss:ID=\"ect\" ss:Name=\"ect\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Top\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Interior ss:Color=\"$this->evenBg\" ss:Pattern=\"Solid\" />\n"
			. "\t\t<Font ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"
			. "\t<Style ss:ID=\"ort\" ss:Name=\"ort\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Font ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Right\" ss:Vertical=\"Center\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"
			. "\t<Style ss:ID=\"ert\" ss:Name=\"ert\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Top\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#C0C0C0\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Interior ss:Color=\"$this->evenBg\" ss:Pattern=\"Solid\" />\n"
			. "\t\t<Font ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Right\" ss:Vertical=\"Center\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"

			. "\t<Style ss:ID=\"oli\" ss:Parent=\"olt\">\n\t\t<NumberFormat ss:Format=\"0\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"eli\" ss:Parent=\"elt\">\n\t\t<NumberFormat ss:Format=\"0\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"oci\" ss:Parent=\"oct\">\n\t\t<NumberFormat ss:Format=\"0\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"eci\" ss:Parent=\"ect\">\n\t\t<NumberFormat ss:Format=\"0\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"ori\" ss:Parent=\"ort\">\n\t\t<NumberFormat ss:Format=\"0\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"eri\" ss:Parent=\"ert\">\n\t\t<NumberFormat ss:Format=\"0\" />\n\t</Style>\n"

			. "\t<Style ss:ID=\"olf\" ss:Parent=\"olt\">\n\t\t<NumberFormat ss:Format=\"0.$this->xmlDecs\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"elf\" ss:Parent=\"elt\">\n\t\t<NumberFormat ss:Format=\"0.$this->xmlDecs\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"ocf\" ss:Parent=\"oct\">\n\t\t<NumberFormat ss:Format=\"0.$this->xmlDecs\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"ecf\" ss:Parent=\"ect\">\n\t\t<NumberFormat ss:Format=\"0.$this->xmlDecs\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"orf\" ss:Parent=\"ort\">\n\t\t<NumberFormat ss:Format=\"0.$this->xmlDecs\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"erf\" ss:Parent=\"ert\">\n\t\t<NumberFormat ss:Format=\"0.$this->xmlDecs\" />\n\t</Style>\n"

			. "\t<Style ss:ID=\"olu\" ss:Parent=\"olt\">\n\t\t<NumberFormat ss:Format=\"$this->xmlDate\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"elu\" ss:Parent=\"elt\">\n\t\t<NumberFormat ss:Format=\"$this->xmlDate\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"ocu\" ss:Parent=\"oct\">\n\t\t<NumberFormat ss:Format=\"$this->xmlDate\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"ecu\" ss:Parent=\"ect\">\n\t\t<NumberFormat ss:Format=\"$this->xmlDate\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"oru\" ss:Parent=\"ort\">\n\t\t<NumberFormat ss:Format=\"$this->xmlDate\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"eru\" ss:Parent=\"ert\">\n\t\t<NumberFormat ss:Format=\"$this->xmlDate\" />\n\t</Style>\n"

			. "\t<Style ss:ID=\"rlt\" ss:Name=\"rlt\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Top\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Interior ss:Color=\"$this->footBg\" ss:Pattern=\"Solid\" />\n"
			. "\t\t<Font ss:Bold=\"1\" ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Left\" ss:Vertical=\"Center\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"
			. "\t<Style ss:ID=\"rct\" ss:Name=\"rct\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Top\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Interior ss:Color=\"$this->footBg\" ss:Pattern=\"Solid\" />\n"
			. "\t\t<Font ss:Bold=\"1\" ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"
			. "\t<Style ss:ID=\"rrt\" ss:Name=\"rrt\">\n"
			. "\t\t<Borders>\n"
			. "\t\t\t<Border ss:Position=\"Top\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t\t<Border ss:Position=\"Left\" ss:Color=\"#969696\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
			. "\t\t</Borders>\n"
			. "\t\t<Interior ss:Color=\"$this->footBg\" ss:Pattern=\"Solid\" />\n"
			. "\t\t<Font ss:Bold=\"1\" ss:FontName=\"Courier New\" ss:Size=\"13\" />\n"
			. "\t\t<Alignment ss:Horizontal=\"Right\" ss:Vertical=\"Center\" />\n"
			. "\t\t<NumberFormat ss:Format=\"@\" />\n"
			. "\t</Style>\n"

			. "\t<Style ss:ID=\"rli\" ss:Parent=\"rlt\">\n\t\t<NumberFormat ss:Format=\"0\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"rci\" ss:Parent=\"rct\">\n\t\t<NumberFormat ss:Format=\"0\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"rri\" ss:Parent=\"rrt\">\n\t\t<NumberFormat ss:Format=\"0\" />\n\t</Style>\n"

			. "\t<Style ss:ID=\"rlf\" ss:Parent=\"rlt\">\n\t\t<NumberFormat ss:Format=\"0.$this->xmlDecs\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"rcf\" ss:Parent=\"rct\">\n\t\t<NumberFormat ss:Format=\"0.$this->xmlDecs\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"rrf\" ss:Parent=\"rrt\">\n\t\t<NumberFormat ss:Format=\"0.$this->xmlDecs\" />\n\t</Style>\n"

			. "\t<Style ss:ID=\"rlu\" ss:Parent=\"rlt\">\n\t\t<NumberFormat ss:Format=\"$this->xmlDate\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"rcu\" ss:Parent=\"rct\">\n\t\t<NumberFormat ss:Format=\"$this->xmlDate\" />\n\t</Style>\n"
			. "\t<Style ss:ID=\"rru\" ss:Parent=\"rrt\">\n\t\t<NumberFormat ss:Format=\"$this->xmlDate\" />\n\t</Style>\n"

			. "</Styles>\n";
	}

}

