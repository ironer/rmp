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

class HtmlTable {
	const
		TABLE_TYPE = 'type',
		TYPE_SCREEN = 'screen', // default
		TYPE_EXCEL = 'xls',

		EXPORT_FILENAME = 'filename',
		TABLE_SOURCE = 'source',
		TABLE_COLUMNS = 'columns',

		COLUMN_FORMAT = 'format',
		COLUMN_HEADER = 'header',
		COLUMN_PREFIX = 'pre',
		COLUMN_POSTFIX = 'post',
		COLUMN_FUNCTION = 'func',
		COLUMN_ALIGN = 'align',

		T_HEAD_TR_ATTRIBUTES = 'thead',
		T_BODY_TR_EVEN_ATTRIBUTES = 'even',
		T_FOOT_TR_ATTRIBUTES = 'tfoot',

		FORMAT_TEXT = 'text',
		FORMAT_INTEGER = 'int',
		FORMAT_FLOAT = 'float',
		FORMAT_UT = 'ut',

		FLOAT_DECIMALS = 'decs',
		DATE_FORMAT = 'date';


	public $id;
	public $container;

	private $type = self::TYPE_SCREEN;
	private $filename = 'document';
	private $columns = array();
	private $data;
	private $resource;
	private $resColumns = array();

	private $headBg = '#88ccff';
	private $evenBg = '#ccffff';
	private $footBg = '#ffddbb';

	private $decimals = 2;
	private $xlsDecs = '00';
	private $dateFormat = 'd.m.Y H:i:s'; //'j.n.Y G:i:s';
	private $xlsDate = 'dd\.mm\.yyyy hh\:mm\:ss'; //'d\.m\.yyyy h\:mm\:ss';

	private $debug = FALSE;


	public function __construct($id = NULL, $container = NULL) {
		$this->debug = defined('DEBUG') ? DEBUG : FALSE;

		if ($container instanceof App) {
			$this->id = $id;
			$this->container = $container;
			if ($this->debug) App::lg("Vytvoren exporter pro Excel '$this->id'", $this);
		}
	}


	public function config($options = array()) {
		if (!is_array($options)) throw new Exception("Konfigurator exporteru pro Excel ocekava pole s konfiguraci.");

		if (!empty($options[self::TABLE_TYPE]) && !$this->debug) $this->type = strtolower($options[self::TABLE_TYPE]);
		if (!empty($options[self::EXPORT_FILENAME])) $this->filename = $options[self::EXPORT_FILENAME];

		if (!empty($options[self::TABLE_SOURCE])) {
			if (($type = gettype($options[self::TABLE_SOURCE])) === 'resource') $this->resource = $options[self::TABLE_SOURCE];
			elseif ($type === 'array' && isset($options[self::TABLE_SOURCE][0][0])) $this->data = $options[self::TABLE_SOURCE];
		}

		if (!empty($options[self::TABLE_COLUMNS])) $this->columns = $options[self::TABLE_COLUMNS];

		if (!empty($options[self::T_HEAD_TR_ATTRIBUTES])) $this->headBg = ' ' . $options[self::T_HEAD_TR_ATTRIBUTES];

		if (!empty($options[self::T_BODY_TR_EVEN_ATTRIBUTES])) $this->evenBg = ' ' . $options[self::T_BODY_TR_EVEN_ATTRIBUTES];

		if (!empty($options[self::T_FOOT_TR_ATTRIBUTES])) $this->footBg = ' ' . $options[self::T_FOOT_TR_ATTRIBUTES];

		if (!empty($options[self::FLOAT_DECIMALS])) {
			$this->decimals = $options[self::FLOAT_DECIMALS];
			$this->xlsDecs = str_pad('', $this->decimals, '0');
		}
		if (!empty($options[self::DATE_FORMAT])) $this->dateFormat = $options[self::DATE_FORMAT];

		if ($this->debug) App::lg('Nactena konfigurace', $this);
	}


	public function go() {
		if (isset($this->resource) && $row = mysql_fetch_assoc($this->resource)) {
				$this->resColumns = array_keys($row);
				$this->data = array(array_values($row));
		} elseif (empty($this->data)) $this->data = array();

		if (!isset($this->data[0])) {}
		elseif (($i = count($this->columns)) > ($j = count($this->data[0]))) array_splice($this->columns, $j);
		elseif ($i < $j) for (; $i < $j; ++$i) $this->columns[$i] = array();

		if ($this->debug) App::lg('Tabulka pripravena pro export', $this);
		elseif ($this->type !== self::TYPE_SCREEN) $this->sendHead();

		$table = $this->getTable();

		if ($this->type === self::TYPE_EXCEL) {
			$html = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" . "<?mso-application progid=\"Excel.Sheet\"?>\n" . "<Workbook"
				. " xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\""
				. " xmlns:x=\"urn:schemas-microsoft-com:office:excel\""
				. " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\""
				. " xmlns:html=\"http://www.w3.org/TR/REC-html40\">" . "\n";

			$html .= "<Styles>\n"
				. "\t<Style ss:ID=\"headRow\">\n"
				. "\t\t<Borders>\n"
				. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#aaaaaa\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t</Borders>\n"
				. "\t\t<Interior ss:Color=\"$this->headBg\" ss:Pattern=\"Solid\" />\n"
				. "\t\t<Font ss:Bold=\"1\" />\n"
				. "\t\t<Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\" ss:WrapText=\"1\" />\n"
				. "\t</Style>\n"
				. "\t<Style ss:ID=\"oddRow\">\n"
				. "\t\t<Borders>\n"
				. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#dddddd\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t</Borders>\n"
				. "\t\t<Alignment ss:Horizontal=\"Left\" ss:Vertical=\"Center\" />\n"
				. "\t</Style>\n"
				. "\t<Style ss:ID=\"evenRow\">\n"
				. "\t\t<Borders>\n"
				. "\t\t\t<Border ss:Position=\"Top\" ss:Color=\"#dddddd\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#dddddd\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#dddddd\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t</Borders>\n"
				. "\t\t<Interior ss:Color=\"$this->evenBg\" ss:Pattern=\"Solid\" />\n"
				. "\t\t<Alignment ss:Horizontal=\"Left\" ss:Vertical=\"Center\" />\n"
				. "\t</Style>\n"
				. "\t<Style ss:ID=\"resultRow\">\n"
				. "\t\t<Borders>\n"
				. "\t\t\t<Border ss:Position=\"Top\" ss:Color=\"#aaaaaa\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#aaaaaa\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#aaaaaa\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t</Borders>\n"
				. "\t\t<Interior ss:Color=\"$this->footBg\" ss:Pattern=\"Solid\" />\n"
				. "\t\t<Font ss:Bold=\"1\" />\n"
				. "\t\t<Alignment ss:Horizontal=\"Left\" ss:Vertical=\"Center\" />\n"
				. "\t</Style>\n"
				. "\t<Style ss:ID=\"footRow\">\n"
				. "\t\t<Borders>\n"
				. "\t\t\t<Border ss:Position=\"Right\" ss:Color=\"#aaaaaa\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t\t<Border ss:Position=\"Bottom\" ss:Color=\"#aaaaaa\" ss:LineStyle=\"Continuous\" ss:Weight=\"1\" />\n"
				. "\t\t</Borders>\n"
				. "\t\t<Interior ss:Color=\"$this->footBg\" ss:Pattern=\"Solid\" />\n"
				. "\t\t<Font ss:Bold=\"1\" />\n"
				. "\t\t<Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\" />\n"
				. "\t</Style>\n"
				. "</Styles>\n";

			$html .= "<Worksheet ss:Name=\"Worksheet\">\n" . $table;

			$html .= "\t<WorksheetOptions xmlns=\"urn:schemas-microsoft-com:office:excel\">\n";
			$html .= "\t\t<FrozenNoSplit />\n";
			$html .= "\t\t<SplitHorizontal>1</SplitHorizontal>\n";
			$html .= "\t\t<TopRowBottomPane>1</TopRowBottomPane>\n";
			$html .= "\t\t<ActivePane>2</ActivePane>\n";
			$html .= "\t</WorksheetOptions>\n";
			$html .= "</Worksheet>\n</Workbook>";

			echo $html;
			die;
		} else {
			if ($this->debug) App::lg('Tabulka vyexportovana', $this);
			return $table;
		}
	}


	private function sendHead() {
		if ($this->type !== self::TYPE_SCREEN) {
			header("Pragma: no-cache");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Content-Type: application/vnd.ms-excel; charset=utf-8");
			header("Content-Disposition: attachment; filename=$this->filename.xls");
		}
	}


	private function getTable() {
		$columns = $this->prepareColumns();

		$header = $this->getTableHeader($columns);

		$body = '';
		$rowNum = 0;
		if (isset($this->data)) {
			for ($j = count($this->data); $rowNum < $j; ++$rowNum) {
				if ($this->type === self::TYPE_SCREEN) {
					$body .= "\t\t<tr align=\"left\">\n" . $this->printOneRow($this->data[$rowNum], $rowNum + 1, $columns) . "\t\t</tr>\n";
				} else {
					$body .= "\t\t<Row ss:StyleID=\"" . ($rowNum % 2 ? 'evenRow' : 'oddRow') . "\">\n"
						. $this->printOneRow($this->data[$rowNum], $rowNum + 1, $columns) . "\t\t</Row>\n";
				}
			}
		}
		if (isset($this->resource)) {
			while ($row = mysql_fetch_assoc($this->resource)) {
				if ($this->type === self::TYPE_SCREEN) {
					$body .= "\t\t<tr align=\"left\">\n" . $this->printOneRow(array_values($row), ++$rowNum, $columns) . "\t\t</tr>\n";
				} else {
					$body .= "\t\t<Row ss:StyleID=\"" . ($rowNum % 2 ? 'evenRow' : 'oddRow') . "\">\n"
						. $this->printOneRow(array_values($row), ++$rowNum, $columns) . "\t\t</Row>\n";
				}
			}
		}

		$footer = $this->getTableFooter($rowNum, $columns);

		if ($this->type === self::TYPE_SCREEN) {
			$retText = "\t<table border=\"1\" cellpadding=\"3\" cellspacing=\"0\">\n\t\t<tr>\n" . $header . "\t\t</tr>\n"
				. $body . "\t\t<tr>" . implode("\t\t</tr>\n\t\t<tr>\n", $footer) . "\t\t</tr>\n\t</table>\n";
		} else {
			$retText = "\t<Table>\n\t\t<Row ss:StyleID=\"headRow\">\n" . $header . "\t\t</Row>\n"
				. $body . "\t\t<Row ss:StyleID=\"resultRow\">\n"
				. $footer[0] . "\t\t</Row>\n\t\t<Row ss:StyleID=\"footRow\">\n"
				. $footer[1] . "\t\t</Row>\n\t</Table>\n";
		}

		return $retText;
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
				self::COLUMN_ALIGN => 'left'
			);

			$func = $col[self::COLUMN_FUNCTION] = strtolower(strval($col[self::COLUMN_FUNCTION]));

			$col['_prePostLen'] = strlen($col[self::COLUMN_PREFIX] . $col[self::COLUMN_POSTFIX]);
			$col['_num'] = $col[self::COLUMN_FORMAT] === self::FORMAT_INTEGER || $col[self::COLUMN_FORMAT] === self::FORMAT_FLOAT
				|| $col[self::COLUMN_FORMAT] === self::FORMAT_UT;;

			if (!$col['_num'] && ($func === 'sum' || $func === 'avg')) $col[self::COLUMN_FUNCTION] = '';
		} unset($col);

		return $columns;
	}


	private function getTableHeader(&$columns) {
		for ($header = $colgroup = '', $i = 0, $j = count($columns); $i < $j; ++$i) {
			$value = htmlspecialchars($columns[$i][self::COLUMN_HEADER], ENT_QUOTES);
			if ($this->type === self::TYPE_SCREEN) {
				$header .= "\t\t\t<th bgcolor=\"$this->headBg\"" . ' style="mso-number-format: \'\@\'">' . $value . "</th>\n";
			} else {
				$header .= "\t\t\t<Cell><Data ss:Type=\"String\">" . $value . "</Data></Cell>\n";
			}
		}

		return $header;
	}


	private function printOneRow($row, $rowNum, &$columns) {
		$rowText = '';
		for ($i = 0, $j = count($row); $i < $j; ++$i) {
			$col = &$columns[$i];

			switch($col[self::COLUMN_FORMAT]) {
				case self::FORMAT_INTEGER:
					$value = $var = is_int($row[$i]) ? $row[$i] : intval(strval($row[$i]));
					break;
				case self::FORMAT_FLOAT:
					$value = $var = is_float($row[$i]) ? $row[$i] : floatval(strval($row[$i]));
					break;
				case self::FORMAT_UT:
					$var = date($this->dateFormat, $value = intval(strval($row[$i])));
					break;
				default:
					if (strlen($value = strval($row[$i]))) {
						$var = htmlspecialchars($value, ENT_QUOTES);
					} else $var = $value = '';
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

			$attributes = $rowNum % 2 ? '' : " bgcolor=\"$this->evenBg\"";
			if (!$num) $attributes .= ' style="mso-number-format: \'\@\'"';
			elseif ($col[self::COLUMN_FORMAT] === self::FORMAT_UT) $attributes .= ' style="mso-number-format: \'' . $this->xlsDate . '\'"';
			elseif ($col[self::COLUMN_FORMAT] === self::FORMAT_FLOAT) $attributes .= ' style="mso-number-format: \'0\.' . $this->xlsDecs . '\'"';
			else $attributes .= ' style="mso-number-format: \'0\'"';

			if ($this->type === self::TYPE_SCREEN) {
				if ($col[self::COLUMN_ALIGN] != 'left') $attributes .= ' align="' . $col[self::COLUMN_ALIGN] . '"';
				$rowText .= "\t\t\t<td" . $attributes . '>' . $var . "</td>\n";
			} else {
				$rowText .= "\t\t\t<Cell><Data ss:Type=\"String\">"
					. $var . "</Data></Cell>\n";
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
							$var = is_float($result) ? $result : floatval(strval($result));
							$float = TRUE;
						} else $var = is_int($result) ? $result : intval(strval($result));
						break;
					case self::FORMAT_FLOAT:
						$var = is_float($result) ? $result : floatval(strval($result));
						break;
					case self::FORMAT_UT:
						$var = date($this->dateFormat, intval(strval($result)));
						break;
					default:
						$var = htmlspecialchars("$result", ENT_QUOTES);
				}

				if ($col['_prePostLen']) $var = $col[self::COLUMN_PREFIX] . $result . $col[self::COLUMN_POSTFIX];
				else $num = $col['_num'];
			} else $var = '';

			if (!$num) $attributes = 'style="mso-number-format: \'\@\'"';
			elseif ($col[self::COLUMN_FORMAT] === self::FORMAT_UT) $attributes = 'style="mso-number-format: \'' . $this->xlsDate . '\'"';
			elseif ($float) $attributes = 'style="mso-number-format: \'0\.' . $this->xlsDecs . '\'"';
			else $attributes = 'style="mso-number-format: \'0\'"';

			if ($this->type === self::TYPE_SCREEN) {
				if ($col[self::COLUMN_ALIGN] != 'center') $attributes .= ' align="' . $col[self::COLUMN_ALIGN] . '"';

				$results .= "\t\t\t<th bgcolor=\"$this->footBg\" $attributes>" . $var . "</td>\n";
				$labels .=  "\t\t\t<th bgcolor=\"$this->footBg\">" . $label . "</th>\n";
			} else {
				$results .= "\t\t\t<Cell><Data ss:Type=\"String\">" . $var . "</Data></Cell>\n";
				$labels .=  "\t\t\t<Cell><Data ss:Type=\"String\">" . $label . "</Data></Cell>\n";
			}
		} unset($col);

		return array($results, $labels);
	}

}

