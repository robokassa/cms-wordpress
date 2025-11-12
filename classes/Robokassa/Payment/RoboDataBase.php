<?php

namespace Robokassa\Payment;

/**
 * Класс-обертка над $wpdb для выполнения запросов.
 */
class RoboDataBase {
	/**
	 * @var \wpdb
	 */
	private $db;

	/**
	 * @param \wpdb $db
	 */
	public function __construct(\wpdb $db) {
		$this->db = $db;
	}

	/**
	 * Выполняет произвольный SQL-запрос.
	 *
	 * @param string $sql
	 * @param array $args
	 *
	 * @return int|false
	 */
	public function query($sql, array $args = array()) {
		$prepared = $this->prepare($sql, $args);
		$result = $this->db->query($prepared);

		if ($result === false) {
			$this->logError($prepared);
		}

		return $result;
	}

	/**
	 * Возвращает одиночное значение.
	 *
	 * @param string $sql
	 * @param array $args
	 *
	 * @return string|null
	 */
	public function getVar($sql, array $args = array()) {
		$prepared = $this->prepare($sql, $args);
		$value = $this->db->get_var($prepared);

		if ($value === null && $this->db->last_error !== '') {
			$this->logError($prepared);
		}

		return $value;
	}

	/**
	 * Выполняет INSERT-запрос.
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $format
	 *
	 * @return int|false
	 */
	public function insert($table, array $data, array $format = array()) {
		$result = $this->db->insert($table, $data, $format ?: null);

		if ($result === false) {
			$this->logError(wp_json_encode(array('table' => $table, 'data' => $data)));
		}

		return $result;
	}

	/**
	 * Выполняет UPDATE-запрос.
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $where
	 * @param array $data_format
	 * @param array $where_format
	 *
	 * @return int|false
	 */
	public function update($table, array $data, array $where, array $data_format = array(), array $where_format = array()) {
		$result = $this->db->update($table, $data, $where, $data_format ?: null, $where_format ?: null);

		if ($result === false) {
			$this->logError(wp_json_encode(array(
				'table' => $table,
				'data' => $data,
				'where' => $where,
			)));
		}

		return $result;
	}

	/**
	 * Подготавливает запрос к выполнению.
	 *
	 * @param string $sql
	 * @param array $args
	 *
	 * @return string
	 */
	private function prepare($sql, array $args) {
		return empty($args) ? $sql : $this->db->prepare($sql, $args);
	}

	/**
	 * Логирует ошибки базы данных.
	 *
	 * @param string $context
	 *
	 * @return void
	 */
	private function logError($context) {
		if ($this->db->last_error === '') {
			return;
		}

		error_log('Robokassa DB ошибка: ' . $this->db->last_error . '. Контекст: ' . $context);
	}
}
