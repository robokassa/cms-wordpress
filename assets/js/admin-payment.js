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

	if (
		sno.options[sno.selectedIndex].value === 'osn' ||
		sno.options[sno.selectedIndex].value === 'usn_income' ||
		sno.options[sno.selectedIndex].value === 'usn_income_outcome'
	) {
		document.getElementById("tax").style.display = 'table-row';
	} else {
		document.getElementById("tax").style.display = 'none';
	}
}


spoleer();

window.addEventListener('DOMContentLoaded', function () {
	var componentField = document.getElementById('robokassa_widget_component');

	if (!componentField) {
		return;
	}

	var widgetRows = document.querySelectorAll('.robokassa-widget-option--widget');
	var badgeRows = document.querySelectorAll('.robokassa-widget-option--badge');
	var secondLineRow = document.querySelector('.robokassa-widget-option--second-line');
	var sizeField = document.querySelector('select[name="robokassa_widget_size"]');
	var modeField = document.querySelector('select[name="robokassa_widget_mode"]');
	var checkoutField = document.querySelector('input[name="robokassa_widget_checkout_url"]');
	var checkoutRow = checkoutField ? checkoutField.closest('tr') : null;

	var toggleRows = function (rows, isVisible) {
		if (!rows) {
			return;
		}

		rows.forEach(function (row) {
			row.style.display = isVisible ? '' : 'none';
		});
	};

	var updateComponentVisibility = function () {
		var component = componentField.value;

		toggleRows(widgetRows, component !== 'badge');
		toggleRows(badgeRows, component === 'badge');
	};

	var updateSecondLineVisibility = function () {
		if (!secondLineRow || !sizeField) {
			return;
		}

		secondLineRow.style.display = sizeField.value === 'm' ? '' : 'none';
	};

	var updateCheckoutVisibility = function () {
		if (!checkoutRow || !modeField) {
			return;
		}

		checkoutRow.style.display = modeField.value === 'checkout' ? '' : 'none';
	};

	componentField.addEventListener('change', function () {
		updateComponentVisibility();
		updateSecondLineVisibility();
		updateCheckoutVisibility();
	});

	if (sizeField) {
		sizeField.addEventListener('change', updateSecondLineVisibility);
	}

	if (modeField) {
		modeField.addEventListener('change', updateCheckoutVisibility);
	}

	updateComponentVisibility();
	updateSecondLineVisibility();
	updateCheckoutVisibility();
});
