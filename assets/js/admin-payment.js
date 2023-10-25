function spoleer()
{

	var e1 = document.getElementById("robokassa_country_code");
	var sno = document.getElementById("sno_select");

	if (e1.options[e1.selectedIndex].value === 'KZ') {
		document.getElementById("robokassa_payment_credit").style.display = 'none';
		document.getElementById("tax").style.display = 'table-row';
		document.getElementById("sno").style.display = 'none';
		document.getElementById("payment_method").style.display = 'none';
		document.getElementById("payment_object").style.display = 'none';
	}

	if (sno.options[sno.selectedIndex].value === 'osn') {
		document.getElementById("tax").style.display = 'table-row';
	} else {
		document.getElementById("tax").style.display = 'none';
	}
}


spoleer();

jQuery(document).ready(
	function ()
	{

		// jQuery("#size_commission1").mask("99");

		jQuery('.spoiler_links').click(
			function (e)
			{
				jQuery(this).next('.spoiler_body').toggle('normal');
				e.preventDefault();
			}
		);
	}
);

function updateDescription() {
	var selectedValue = document.getElementById("robokassa_podeli_widget_style").value;
	var description = document.getElementById("description");

	if (selectedValue === "0") {
		description.innerText = "Доступны 2 варианта оформления виджета:\n" +
			"                1. Упрощенная версия виджета для карточки товара с графиком платежей, но без кнопки «Оплатить»;\n" +
			"                2. Развернутая версия виджета для корзины.";
	} else if (selectedValue === "1") {
		description.innerText = "Доступны 2 варианта оформления виджета:\n" +
			"                1. Версия виджета для карточки товара с графиком платежей и кнопкой «Оплатить»;\n" +
			"                2. Версия виджета для корзины.";
	}
}