<?php

/*
 * $data is non-associative array of non-associtive arrays (rows) containing data
 * $columns is non-associative array of associtive arrays (columns):
 * 	format: cell data format (number, ut, defaults to text)
 * 	header: header of column
 * 	pre: prefix of every data in given column
 * 	post: postfix of every data in given column
 * 	funct: PHP aggregate function (min, max, avg, sum), which is calculated on all data in column, or string
 * 	align: align of text in cell (right, center, defaults to left)
 * 	halign: align of header in cell (right, center, defaults to left)
 * 	falign: align of footer in cell (right, center, defaults to left)
 * 	hback: background of header cell
 * 	fback: background of footer cell
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
		COLUMN_HOVER = 'hover',
		
		TABLE_ATTRIBUTES = 'table',
		T_HEAD_ATTRIBUTES = 'thead',
		T_HEAD_TR_ATTRIBUTES = 'theadtr',
		T_HEAD_TR_TD_ATTRIBUTES = 'theadtrtd',
		T_BODY_ATTRIBUTES = 'tbody',
		T_BODY_TR_ODD_ATTRIBUTES = 'odd',
		T_BODY_TR_EVEN_ATTRIBUTES = 'odd',
		T_FOOT_ATTRIBUTES = 'tfoot',
		T_FOOT_TR_ATTRIBUTES = 'tfoottr',
		T_FOOT_TR_TD_ATTRIBUTES = 'tfoottrtd',

		FORMAT_TEXT = 'text',
		FORMAT_INTEGER = 'int',
		FORMAT_FLOAT = 'float',
		FORMAT_UT = 'ut';

	public $id;
	public $container;

	private $type = self::TYPE_SCREEN;
	private $filename = 'document';
	private $columns = array();
	private $data;
	private $resource;
	private $resColumns = array();

	private $table = ' border="1" cellpadding="3px" cellspacing="0" align"left" bgcolor="#ffffff" color="#000000"';
	private $thead = ' valign="middle"';
	private $theadtr = ' bgcolor="#009edf"';
	private $theadtrtd = ' color="#ffffff"';
	private $tbody = ' valign="middle"';
	private $odd = '';
	private $even = ' bgcolor="#e6e6e6"';
	private $tfoot = ' valign="middle"';
	private $tfoottr = ' bgcolor="444444"';
	private $tfoottrtd = ' color="#ffffff"';


	public function __construct($id, $container) {
		if ($container instanceof App) {
			$this->id = $id;
			$this->container = $container;
			App::lg("Vytvoren exporter pro Excel '$this->id'", $this);
		} else {
			throw new Exception("Konstruktor exporteru pro Excel ocekava odkaz na kontajner. Druhy argument neni objekt tridy 'App'.");
		}
	}


	public function config($options = array()) {
		if (!is_array($options)) throw new Exception("Konfigurator exporteru pro Excel ocekava pole s konfiguraci.");

		if (!empty($options[self::TABLE_TYPE]) && !DEBUG) $this->type = strtolower($options[self::TABLE_TYPE]);
		if (!empty($options[self::EXPORT_FILENAME])) $this->filename = $options[self::EXPORT_FILENAME];

		if (!empty($options[self::TABLE_SOURCE])) {
			if (($type = gettype($options[self::TABLE_SOURCE])) === 'resource') $this->resource = $options[self::TABLE_SOURCE];
			elseif ($type === 'array' && isset($options[self::TABLE_SOURCE][0][0])) $this->data = $options[self::TABLE_SOURCE];
		}

		if (!empty($options[self::TABLE_COLUMNS])) $this->columns = $options[self::TABLE_COLUMNS];
		if (!empty($options[self::TABLE_ATTRIBUTES])) $this->table = ' ' . $options[self::TABLE_ATTRIBUTES];

		if (!empty($options[self::T_HEAD_ATTRIBUTES])) $this->thead = ' ' . $options[self::T_HEAD_ATTRIBUTES];
		if (!empty($options[self::T_HEAD_TR_ATTRIBUTES])) $this->theadtr = ' ' . $options[self::T_HEAD_TR_ATTRIBUTES];
		if (!empty($options[self::T_HEAD_TR_TD_ATTRIBUTES])) $this->theadtrtd = ' ' . $options[self::T_HEAD_TR_TD_ATTRIBUTES];

		if (!empty($options[self::T_BODY_ATTRIBUTES])) $this->tbody = ' ' . $options[self::T_BODY_ATTRIBUTES];
		if (!empty($options[self::T_BODY_TR_ODD_ATTRIBUTES])) $this->odd = ' ' . $options[self::T_BODY_TR_ODD_ATTRIBUTES];
		if (!empty($options[self::T_BODY_TR_EVEN_ATTRIBUTES])) $this->even = ' ' . $options[self::T_BODY_TR_EVEN_ATTRIBUTES];

		if (!empty($options[self::T_FOOT_ATTRIBUTES])) $this->tfoot = ' ' . $options[self::T_FOOT_ATTRIBUTES];
		if (!empty($options[self::T_FOOT_TR_ATTRIBUTES])) $this->tfoottr = ' ' . $options[self::T_FOOT_TR_ATTRIBUTES];
		if (!empty($options[self::T_FOOT_TR_TD_ATTRIBUTES])) $this->tfoottrtd = ' ' . $options[self::T_FOOT_TR_TD_ATTRIBUTES];

		App::lg('Nactena konfigurace', $this);
	}

	public function go() {
		if (isset($this->resource) && $row = mysql_fetch_assoc($this->resource)) {
			$this->resColumns = array_keys($row);
			$this->data = array(array_values($row));
		} elseif (empty($this->data)) $this->data = array(array());

		if (($i = count($this->columns)) > ($j = count($this->data[0]))) array_splice($this->columns, $j);
		elseif ($i < $j) for (; $i < $j; ++$i) $this->columns[$i] = array();

		if (DEBUG) App::lg('Tabulka pripravena pro export', $this);
		else $this->sendHead();

		echo $this->getTable();

		if ($this->type === self::TYPE_EXCEL) die;
		else if ($this->container) $this->container->stop = TRUE;

		App::lg('Tabulka vyexportovana', $this);
	}


	private function sendHead() {
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Disposition: attachment; filename=$this->filename.xls");

		if ($this->type === self::TYPE_EXCEL) {
			header("Content-Type: application/vnd.ms-excel; charset=utf-16");
		} else {
			header("Content-Type: application/vnd.ms-excel; charset=utf-8");
		}
	}


	private function getTable() {
		$columns = $this->prepareColumns();

		$retText = "<table$this->table>\n<thead$this->thead>\n" . $this->getTableHeader($columns) . "</thead>\n<tbody$this->tbody>\n";

		$rowNum = 0;
		if (isset($this->data)) {
			for ($j = count($this->data); $rowNum < $j; ++$rowNum) {
				$retText .= $this->printOneRow($this->data[$rowNum], $rowNum + 1, $columns);
			}
		}
		if (isset($this->resource)) {
			while ($row = mysql_fetch_row($this->resource)) {
				$retText .= $this->printOneRow($row, ++$rowNum, $columns);
			}
		}

		$retText .= "</tbody>\n<tfoot$this->tfoot>\n" . $this->getTableFooter($rowNum, $columns) . "</tfoot>\n</table>\n";

		return $this->type === self::TYPE_EXCEL ? mb_convert_encoding($retText, 'UTF-16LE', 'UTF-8') : $retText;
	}


	private function prepareColumns() {
		for ($columns = array(), $i = 0, $j = count($this->columns); $i < $j; ++$i) {
			$col = &$columns[$i];

			$col = (array) $this->columns[$i] + array(
				self::COLUMN_FORMAT => self::FORMAT_TEXT,
				self::COLUMN_HEADER => isset($this->resColumns[$i]) ? $this->resColumns[$i] : 'Sloupec ' . ($i + 1),
				self::COLUMN_PREFIX => '',
				self::COLUMN_POSTFIX => '',
				self::COLUMN_FUNCTION => ''
			);
			
			$func = $col[self::COLUMN_FUNCTION] = strtolower(strval($col[self::COLUMN_FUNCTION]));

			$col['_prePostLen'] = strlen($col[self::COLUMN_PREFIX] . $col[self::COLUMN_POSTFIX]);
			$num = $col['_num'] = $col[self::COLUMN_FORMAT] === self::FORMAT_INTEGER
				|| $col[self::COLUMN_FORMAT] === self::FORMAT_FLOAT;
			$avg = $num || $col[self::COLUMN_FORMAT] === self::FORMAT_UT;
			if (!$num && $func === 'sum') $col[self::COLUMN_FUNCTION] = '';
			if (!$avg && $func === 'avg') $col[self::COLUMN_FUNCTION] = '';
		} unset($col);

		return $columns;
	}


	private function getTableHeader(&$columns) {
		for ($header = '', $i = 0, $j = count($columns); $i < $j; ++$i) {
			$header .= "<td$this->theadtrtd><b>" . $columns[$i][self::COLUMN_HEADER] . '</b></td>';
		}

		return "<tr$this->theadtr>" . $header . "</tr>\n";
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
					$var = date('%d.%m.%Y %H:%i:%s', $value = $row[$i]);
					break;
				default:
					$value = $var = strval($row[$i]);
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

			$rowText .= '<td>' . $var . '</td>';
		} unset($col);

		return '<tr' . $rowNum ? $this->odd : $this->even . '>' . $rowText . "</tr>\n";
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
						$label = 'Minimum';
					}
					break;
				case 'max':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'Maximum';
					}
					break;
				case 'avg':
					if (isset($col['_calc'])) {
						$result = $rowNum ? $col['_calc'] / $rowNum : 0 ;
						$label = 'Prumer';
					}
					break;
				case 'sum':
					if (isset($col['_calc'])) {
						$result = $col['_calc'];
						$label = 'Suma';
					}
			}

			$num = FALSE;

			if ($result !== NULL) {
				switch($col[self::COLUMN_FORMAT]) {
					case self::FORMAT_INTEGER:
						if ($col[self::COLUMN_FUNCTION] === 'avg') {
							$var = is_float($result) ? $result : floatval(strval($result));
						} else $var = is_int($result) ? $result : intval(strval($result));
						break;
					case self::FORMAT_FLOAT:
						$var = is_float($result) ? $result : floatval(strval($result));
						break;
					case self::FORMAT_UT:
						$var = date('%d.%m.%Y %H:%i:%s', $result);
						break;
					default:
						$var = strval($result);
				}

				if ($col['_prePostLen']) $var = $col[self::COLUMN_PREFIX] . $result . $col[self::COLUMN_POSTFIX];
				else $num = $col['_num'];
			} else $var = '';

			$results .= "<td$this->tfoottrtd><b>" . $var . '</b></td>';
			$labels .=  "<td$this->tfoottrtd><b>" . $label . '</b></td>';
		} unset($col);

		return "<tr$this->tfoottr>" . $results . "</tr>\n<tr$this->tfoottr>" . $labels . "</tr>\n";
	}

}

