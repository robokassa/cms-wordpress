# Официальный модуль приема платежей Robokassa для WordPress WooCommerce
Данный модуль позволяет добавить на сайт способ оплаты через Робокассу. 
Для корректной работы модуля необходима регистрация в сервисе.

Порядок регистрации описан в [документации Robokassa](https://docs.robokassa.ru/#7844)

### Возможности
* Передача состава товаров в заказе для отправки чека клиенту и в налоговую (54-ФЗ)
* Выбор платежной системы при оформление заказа, до отправки на страницу оплаты
* Выбор системы налогообложения
* Выбор размера ставки НДС для товаров в заказе
* Экспорт товаров в yml
* Поддержка оплаты через iframe (при данном типе оплаты не все платежные системы доступны)
* Приём платежей в тестовом режиме;
* Автоматическая смена статуса заказа;
* Поддержка отправки второго чека и маркировки товара
* Поддержка продавцов из Казахстана

### Совместимость
Версия WordPress:5.7 или выше;

Версия PHP:5.6.32 или выше;

### Установка

1. Скачайте репозиторий в папку /wp-content/plugins/woocommerce_robokassa
2. Активируйте плагин в настройках WordPress /wp-admin/plugins.php
3. Настройте параметры подключения /wp-admin/admin.php?page=robokassa_payment_main_rb

### Настройка модуля

Настройка магазина на стороне [Робокассы](http://partner.robokassa.ru/):
1. Алгоритм расчета хеша – MD5
1. Result Url – http(s)://your-domain.ru/?robokassa=result
1. Success Url – http(s)://your-domain.ru/?robokassa=success
1. Fail Url – http(s)://your-domain.ru/?robokassa=fail
1. Метод отсылки данных по Result Url, Success Url и fail Url  – POST

Настройка на стороне сайта:
1. Указать платежные данные: Логин магазина, Пароль магазина #1, Пароль магазина #2
1. Активировать тестовый режим при необходимости, так же необходимо будет внести: Пароль магазина для тестов #1, Пароль магазина для тестов #2

### Фискализация

Для подключения автоматического формирования чеков в соответвии с ФЗ-54 необходимо подключить одну из доступных фискальных схем в Личном кабинете Robokassa ([Раздел "Фискализация"](https://partner.robokassa.ru/Fiscalization)) и указать настройки модуля:

* Система налогообложения.
* Признак способа расчёта.
* Признак предмета расчёта.
* Система налогообложения

### Changelog

= 1.3.13 =
* Устранены ошибки внутри плагина

= 1.3.12 =
* Добавлен второй способ получения номенклатуры
* Исправлена ошибка задвоения наценки

= 1.3.11 =
* Фискализация для Казахстана

= 1.3.9 =
* Устранены ошибки внутри плагина

= 1.3.6 =
* Добавлен 2 чек, срабатывает при статусе completed

= 1.3.5 =
* Изменение описания

= 1.3.4 =
* Исправлена ошибка выбора способа оплаты и внедрена поддержка следующих версий:
	WordPress 5 (5.4)

= 1.3.3 =
* Внедрена поддержка следующих версий:
	WordPress 5 (5.3.2)
	WooCommerce 4.0 (4.0.1)
	
= 1.3.2 =
* Исправлена проблема дублирования кнопки оплаты при использовании наценки

= 1.3.1 =
* Исправлена проблема выгрузки товаров в YML

= 1.3.0 =
* Добавление поддержки оплаты через iframe

= 1.2.36 =
* Исправление ответа обработчика при ошибочной подписи

= 1.2.35 =
* Добавлена поддержки работы магазинов Казахстана

= 1.2.34 =
* Исправлена ошибка обработки платежа для физического лица
* Исправлена ошибка расчета комиссии для физического лица

= 1.2.33 =
* Исправлена ошибка обработки платежа

= 1.2.32 =
* Очистка корзине после подтверждения от Робокассы

= 1.2.31 =
* Внедрена поддержка следующих версий:
	WordPress 5 (5.0.2)
	WooCommerce 3.5 (3.5.0)
	PHP 7.1

= 1.1 =
* Обновление формата отправки данных о корзине для соответствия ФФД (версия 1.05)
* Добавление передачи доставки в чек

= 1.0 =
* Добавление параметров фискализации для передачи состава корзины и формирование электронного чека

