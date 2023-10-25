<?php

if(!\current_user_can('activate_plugins'))
{

    echo '<br /><br />
				<div class="error notice">
	                <p>У Вас не хватает прав на настройку компонента</p>
				</div>
			';
    return;
}


?>

<div class="content_holder sms-settings">
    <p class="big_title_rb">Скачать оферту</p>

    <table class="form-table">
        <p>
        <h4>
            ВНИМАНИЕ!
            Общество с ограниченной ответственностью «РОБОКАССА» (ОГРН: 1055009302215; ИНН: 5047063929) сообщает, что указанные формы оферт не являются официальным предложением Общества, и носят исключительно информативный характер для заинтересованного круга лиц.
            Лицо, использовавшее формы указанных оферт для личных целей, и, адресовавшее их третьим лицам с целью получения акцепта, несет самостоятельную ответственность за возникновение каких-либо правовых последствий, а также за возникновение каких-либо деликтных обязательств в результате действий третьих лиц.
        </h4>
        </p>

        <select onchange="window.open(this.value)">
            <option>Укажите сферу деятельности</option>
            <option value="https://docs.robokassa.ru/media/offer/%D0%9F%D1%83%D0%B1%D0%BB%D0%B8%D1%87%D0%BD%D0%B0%D1%8F%20%D0%BE%D1%84%D0%B5%D1%80%D1%82%D0%B0%20%D0%BE%D0%B1%20%D0%BE%D0%BA%D0%B0%D0%B7%D0%B0%D0%BD%D0%B8%D0%B8%20%D1%82%D1%83%D1%80%D0%B8%D1%81%D1%82%D0%B8%D1%87%D0%B5%D1%81%D0%BA%D0%B8%D1%85%20%D1%83%D1%81%D0%BB%D1%83%D0%B3%20(%D0%B4%D0%BB%D1%8F%20%D0%BC%D0%B5%D1%80%D1%87%D0%B0%D0%BD%D1%82%D0%BE%D0%B2).docx">Публичная оферта о заключении договора об оказании туристических услуг</option>
            <option value="https://docs.robokassa.ru/media/offer/%D0%9F%D1%83%D0%B1%D0%BB%D0%B8%D1%87%D0%BD%D0%B0%D1%8F%20%D0%BE%D1%84%D0%B5%D1%80%D1%82%D0%B0%20%D0%BE%20%D0%B7%D0%B0%D0%BA%D0%BB%D1%8E%D1%87%D0%B5%D0%BD%D0%B8%D0%B8%20%D0%B4%D0%BE%D0%B3%D0%BE%D0%B2%D0%BE%D1%80%D0%B0%20%D0%BF%D0%BE%D1%81%D1%82%D0%B0%D0%B2%D0%BA%D0%B8%20%D1%82%D0%BE%D0%B2%D0%B0%D1%80%D0%B0%20(%D0%B4%D0%BB%D1%8F%20%D0%BC%D0%B5%D1%80%D1%87%D0%B0%D0%BD%D1%82%D0%BE%D0%B2).docx">Публичная оферта о заключении договора поставки товара</option>
            <option value="https://docs.robokassa.ru/media/offer/%D0%9F%D1%83%D0%B1%D0%BB%D0%B8%D1%87%D0%BD%D0%B0%D1%8F%20%D0%BE%D1%84%D0%B5%D1%80%D1%82%D0%B0%20%D0%BE%20%D0%B7%D0%B0%D0%BA%D0%BB%D1%8E%D1%87%D0%B5%D0%BD%D0%B8%D0%B8%20%D0%B4%D0%BE%D0%B3%D0%BE%D0%B2%D0%BE%D1%80%D0%B0%20%D0%BA%D1%83%D0%BF%D0%BB%D0%B8-%D0%BF%D1%80%D0%BE%D0%B4%D0%B0%D0%B6%D0%B8%20(%D0%B4%D0%BB%D1%8F%20%D0%BC%D0%B5%D1%80%D1%87%D0%B0%D0%BD%D1%82%D0%BE%D0%B2).docx">Публичная оферта о заключении договора купли-продажи</option>
            <option value="https://docs.robokassa.ru/media/offer/%D0%9F%D1%83%D0%B1%D0%BB%D0%B8%D1%87%D0%BD%D0%B0%D1%8F%20%D0%BE%D1%84%D0%B5%D1%80%D1%82%D0%B0%20%D0%BE%D0%B1%20%D0%BE%D0%BA%D0%B0%D0%B7%D0%B0%D0%BD%D0%B8%D0%B8%20%D0%B0%D0%B3%D0%B5%D0%BD%D1%82%D1%81%D0%BA%D0%B8%D1%85%20%D1%83%D1%81%D0%BB%D1%83%D0%B3%20(%D0%B4%D0%BB%D1%8F%20%D0%BC%D0%B5%D1%80%D1%87%D0%B0%D0%BD%D1%82%D0%BE%D0%B2).docx">Публичная оферта о заключении договора об оказании агентских услуг</option>
            <option value="https://docs.robokassa.ru/media/offer/%D0%9F%D1%83%D0%B1%D0%BB%D0%B8%D1%87%D0%BD%D0%B0%D1%8F%20%D0%BE%D1%84%D0%B5%D1%80%D1%82%D0%B0%20%D0%BE%20%D0%B7%D0%B0%D0%BA%D0%BB%D1%8E%D1%87%D0%B5%D0%BD%D0%B8%D0%B8%20%D0%B4%D0%BE%D0%B3%D0%BE%D0%B2%D0%BE%D1%80%D0%B0%20%D0%BE%D0%B1%20%D0%BE%D0%BA%D0%B0%D0%B7%D0%B0%D0%BD%D0%B8%D0%B8%20%D0%B8%D0%BD%D1%84%D0%BE%D1%80%D0%BC%D0%B0%D1%86%D0%B8%D0%BE%D0%BD%D0%BD%D0%BE-%D0%BA%D0%BE%D0%BD%D1%81%D1%83%D0%BB%D1%8C%D1%82.%20%D1%83%D1%81%D0%BB%D1%83%D0%B3.docx">Публичная оферта о заключении договора информационно - консультативных услуг</option>
            <option value="https://docs.robokassa.ru/media/offer/%D0%9F%D1%83%D0%B1%D0%BB%D0%B8%D1%87%D0%BD%D0%B0%D1%8F%20%D0%BE%D1%84%D0%B5%D1%80%D1%82%D0%B0%20%D0%BE%D0%B1%20%D0%BE%D0%BA%D0%B0%D0%B7%D0%B0%D0%BD%D0%B8%D0%B8%20%D1%83%D1%81%D0%BB%D1%83%D0%B3%20(%D0%B4%D0%BB%D1%8F%20%D0%BC%D0%B5%D1%80%D1%87%D0%B0%D0%BD%D1%82%D0%BE%D0%B2).docx">Публичная оферта о заключении договора об оказании услуг</option>
        </select>


    </table>
    </form>

</div>