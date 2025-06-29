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

1. Скачайте репозиторий в папку /wp-content/plugins/
2. Активируйте плагин в настройках WordPress /wp-admin/plugins.php
3. Настройте параметры подключения /wp-admin/admin.php?page=robokassa_payment_main_rb

### Настройка модуля

Настройка магазина на стороне [Робокассы](http://partner.robokassa.ru/):
1. Алгоритм расчета хеша – MD5
2. Result Url – http(s)://your-domain.ru/?robokassa=result
3. Success Url – http(s)://your-domain.ru/?robokassa=success
4. Fail Url – http(s)://your-domain.ru/?robokassa=fail
5. Метод отсылки данных по Result Url, Success Url и fail Url  – POST

Настройка на стороне сайта:
1. Указать платежные данные: Логин магазина, Пароль магазина #1, Пароль магазина #2
2. Активировать тестовый режим при необходимости, так же необходимо будет внести: Пароль магазина для тестов #1, Пароль магазина для тестов #2

### Фискализация

Для подключения автоматического формирования чеков в соответвии с ФЗ-54 необходимо подключить одну из доступных фискальных схем в Личном кабинете Robokassa ([Раздел "Фискализация"](https://partner.robokassa.ru/Fiscalization)) и указать настройки модуля:

* Система налогообложения.
* Признак способа расчёта.
* Признак предмета расчёта.
* Налоговая ставка

### Changelog
= 1.7.2 =
* Добавлен выбор "Признак предмета расчёта" для доставки
* Обновлены логотипы на виджетах
* Исправлены мелкие ошибки и улучшения интерфейса

= 1.7.1 =
* Добавлен выбор статуса заказа после оплаты в настройках плагина
* Добавлен выбор статуса для автоматического выбивания второго чека
* Минорные улучшения и внутренние оптимизации

= 1.7.0 =
* Исправлены ошибки изменения статусов заказов

= 1.6.9 =
* Исправлены незначительные ошибки

= 1.6.8 =
* Добавлена поддержка плагина Smart Coupons For WooCommerce
* Добавлена поддержка плагина Advanced Dynamic Pricing
* Добавлена поддержка плагина Checkout Field Editor for WooCommerce (Pro)

= 1.6.7 =
* Исправлены незначительные ошибки

= 1.6.6 =
* Исправлены незначительные ошибки

= 1.6.5 =
* Добавлены ставки НДС 5%, 7%, 5/105, 7/107

= 1.6.4 =
* Исправлены незначительные ошибки

= 1.6.3 =
* Добавлена поддержка для Checkout Blocks on Woocommerce.
* Исправлены незначительные ошибки

= 1.6.2 =
* Исправлены незначительные ошибки
* Добавлено логирование в /data/
* Добавлена интеграция с Мой Склад
* Функция попозиционной маркировки перенесена в ЛЛК Робокассы
* Добавлен новый способ оплаты в кредит и рассрочку

= 1.6.1 =
* Исправлены незначительные ошибки

= 1.6.0 =
* Добавлена поддержка плагина ["WooCommerce Checkout Add-Ons"](https://woocommerce.com/products/woocommerce-checkout-add-ons/)
* Добавлена новая функция, позволяющая автоматически разбивать товары в корзине на отдельные позиции при количестве больше одного

= 1.5.9 =
* Исправлены незначительные ошибки

= 1.5.8 =
* Добавлена функционал отложенных платежей (холдирование)
* Изменена максимальная сумма корзины для "Подели"
* Переработана логика работы параметра OutSumCurrency

= 1.5.7 =
* Добавлена возможность установить валюту по умолчанию из настроек WooCommerce
* Устранены ошибки

= 1.5.6 =
* Добавлена поддержка плагина ["Woo Subscriptions"](https://woo.com/products/woocommerce-subscriptions/)
* Исправлено определение валюты для клиентов из Казахстана
* Добавлен параметр "sum" для формирования товарной номенклатуры
* Устранены ошибки стилей

= 1.5.5 =
* В карточку товара и корзину добавлен новый виджет «Рассрочка и кредит» через сервис «Всегда Да»
* Изменен внешний вид виджета «Подели» в карточке товара
* Добавлен функционал использования налоговых ставок
* Устранены ошибки

= 1.5.0 =
* Добавлен способ оплаты частями ["Подели"](https://robokassa.com/media/guides/wordpress_podeli.pdf)
* Устранены ошибки

= 1.4.7 =
* Доработан функционал передачи номенклатуры

= 1.4.6 =
* Иcправлена ошибка передачи номенклатуры при вариативном товаре

= 1.4.5 =
* Добавлен параметр cost для расчёта фискализации
* Устранены ошибки внутри плагина

= 1.4.0 =
* Добавлен функционал регистрации
* Изменен дизайн страницы настроек

= 1.3.15 =
* Добавлена проверка факта установки плагина WooCommerce

= 1.3.14 =
* Добавлена поддержка WordPress 5.8

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

