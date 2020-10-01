<?php

namespace Robokassa\Payment;

class RobokassaSms {

    /**
     * @var RobokassaPayAPI
     */
    private $robokassa;

    /**
     * @var
     */
    private $phone;

    /**
     * @var
     */
    private $message;

    /**
     * @var
     */
    private $translit;

    /**
     * @var
     */
    private $order_id;

    /**
     * @var
     */
    private $type;

    /**
     * RobokassaSms constructor.
     *
     * @param RoboDataBase    $dataBase
     * @param RobokassaPayAPI $roboKassa
     * @param                  $phone
     * @param                  $message
     * @param                  $translit
     * @param                  $order_id
     * @param                  $type
     */
    public function __construct(
        RoboDataBase $dataBase,
        RobokassaPayAPI $roboKassa,
        $phone,
        $message,
        $translit,
        $order_id,
        $type
    ) {
        $this->robokassa = $roboKassa;
        $this->dataBase = $dataBase;
        $this->phone = preg_replace('/[^0-9]/', '', $phone); //Удаляем всю не "телефонную" ересь
        $this->message = $message;
        $this->translit = $translit;
        $this->order_id = $order_id;
        $this->type = $type;
    }

    /**
     * @param string $log
     *
     * @return void
     */
    public function recordLog($log) {
        $smsReply = json_decode($log, true);

        if (isset($smsReply['reply'])) {
            $reply = json_decode(stripslashes($smsReply['reply']), true);

            $status = ($reply['result'] === true) ? '1' : '0';

            $this->dataBase->query("UPDATE `sms_stats` SET send_time= NOW(), status='$status', `response`='".$this->robokassa->getRequest()."', `reply`='".$this->robokassa->getReply()."' WHERE order_id='$this->order_id' AND type=".$this->type);
        }
    }

	/**
	 * Проверяем существует ли уже в таблице смс со статусом -1 (не отправлено)
	 *
	 * @return bool
	 * @throws \Exception
	 */
    private function checkIsSended() {
        $dbPrefix = \robokassa_payment_getDbPrefix();

        if (\mysqli_num_rows($this->dataBase->query("SELECT * FROM `{$dbPrefix}sms_stats` WHERE order_id='$this->order_id' AND type=".$this->type)) >= 1) {
            $this->dataBase->query("UPDATE `{$dbPrefix}sms_stats` SET send_time= NOW(), status='-1' WHERE order_id='$this->order_id'");

            return false;
        } else {
            $this->dataBase->query("INSERT INTO `{$dbPrefix}sms_stats` (`order_id`, `type`, `status`, `number`, `text`, `send_time`) VALUES ('$this->order_id', '$this->type', '-1', '$this->phone', '$this->message', NOW())");

            return false;
        }

        return true;
    }

    /**
     * Подставляет вместо {address}/{fio}/{order_number} реальные данные
     *
     * @return string
     */
    private function filterSms() {
        $order = wc_get_order($this->order_id);

        $order_address = trim($order->billing_address_1.' '.$order->billing_address_2);
        $order_fio = trim($order->billing_first_name.' '.$order->billing_last_name);
        $order_number = $this->order_id;

        if (mb_strlen($order_address, 'UTF-8') > 30) {
            $order_address = mb_strimwidth($order_address, 0, 30, '...');
        }

        if (mb_strlen($order_fio, 'UTF-8') > 30) {
            $order_fio = mb_strimwidth($order_fio, 0, 30, '...');
        }

        $mask1 = array('{address}', '{fio}', '{order_number}');
        $mask2 = array($order_address, $order_fio, $order_number);

        return str_replace($mask1, $mask2, $this->message);
    }

    /**
     * Переводит строку из кириллицы в транслит
     *
     * @return string
     */
    private function transliterate() {
        return (!$this->translit)
            ? $this->message
            : str_replace(array(
                'А',
                'Б',
                'В',
                'Г',
                'Д',
                'Е',
                'Ё',
                'Ж',
                'З',
                'И',
                'Й',
                'К',
                'Л',
                'М',
                'Н',
                'О',
                'П',
                'Р',
                'С',
                'Т',
                'У',
                'Ф',
                'Х',
                'Ц',
                'Ч',
                'Ш',
                'Щ',
                'Ъ',
                'Ы',
                'Ь',
                'Э',
                'Ю',
                'Я',
                'а',
                'б',
                'в',
                'г',
                'д',
                'е',
                'ё',
                'ж',
                'з',
                'и',
                'й',
                'к',
                'л',
                'м',
                'н',
                'о',
                'п',
                'р',
                'с',
                'т',
                'у',
                'ф',
                'х',
                'ц',
                'ч',
                'ш',
                'щ',
                'ъ',
                'ы',
                'ь',
                'э',
                'ю',
                'я',
            ), array(
                'A',
                'B',
                'V',
                'G',
                'D',
                'E',
                'E',
                'Gh',
                'Z',
                'I',
                'Y',
                'K',
                'L',
                'M',
                'N',
                'O',
                'P',
                'R',
                'S',
                'T',
                'U',
                'F',
                'H',
                'C',
                'Ch',
                'Sh',
                'Sch',
                'Y',
                'Y',
                'Y',
                'E',
                'Yu',
                'Ya',
                'a',
                'b',
                'v',
                'g',
                'd',
                'e',
                'e',
                'gh',
                'z',
                'i',
                'y',
                'k',
                'l',
                'm',
                'n',
                'o',
                'p',
                'r',
                's',
                't',
                'u',
                'f',
                'h',
                'c',
                'ch',
                'sh',
                'sch',
                'y',
                'y',
                'y',
                'e',
                'yu',
                'ya',
            ), $this->message);
    }

	/**
	 * @return void
	 * @throws \Exception
	 */
    public function send() {
        $this->message = $this->filterSms();
        $this->message = $this->transliterate();

        if (!$this->checkIsSended()) {
            $this->robokassa->sendSms($this->phone, $this->message);
            $this->recordLog($this->robokassa->getSendResult());
        }
    }

}
