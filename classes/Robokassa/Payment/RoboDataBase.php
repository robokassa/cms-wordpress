<?php

namespace Robokassa\Payment;

/**
 * Класс для выполнения запросов к бд, ибо неизвестно
 * как это делать нативными средствами вордпресса
 */
class RoboDataBase
{

    /** @var \mysqli $db */
    private $db;

    /** @param \mysqli $db */
    public function __construct(\mysqli $db)
    {

        $this->db = $db;
        \mysqli_set_charset($db, 'utf8');
    }

    /**
     * @param string $sql
     * @return \mysqli_result | bool
     */
    public function query($sql)
    {
        return \mysqli_query($this->db, $sql);
    }
}
