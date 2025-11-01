function spoleer()
{
	var country = document.getElementById("robokassa_country_code");
	var sno = document.getElementById("sno_select");
	var taxRow = document.getElementById("tax");
	var snoRow = document.getElementById("sno");
	var paymentMethodRow = document.getElementById("payment_method");
	var paymentObjectRow = document.getElementById("payment_object");
	var creditRow = document.getElementById("robokassa_payment_credit");
	var isKazakhstan = country && country.value === 'KZ';

	if (creditRow) {
		creditRow.style.display = isKazakhstan ? 'none' : '';
	}

	if (snoRow) {
		snoRow.style.display = isKazakhstan ? 'none' : 'table-row';
	}

	if (paymentMethodRow) {
		paymentMethodRow.style.display = isKazakhstan ? 'none' : 'table-row';
	}

	if (paymentObjectRow) {
		paymentObjectRow.style.display = isKazakhstan ? 'none' : 'table-row';
	}

	if (taxRow) {
		taxRow.style.display = isKazakhstan ? 'table-row' : 'none';
	}

	if (!sno) {
		return;
	}

	var snoValue = sno.options[sno.selectedIndex].value;
	var shouldShowTax = (
		snoValue === 'osn' ||
		snoValue === 'usn_income' ||
		snoValue === 'usn_income_outcome'
	);

	if (taxRow && !isKazakhstan) {
		taxRow.style.display = shouldShowTax ? 'table-row' : 'none';
	}
}

var toggleOptionalMethods = function () {
	var optionalMethods = document.querySelector('.robokassa-optional-methods');
	var country = document.getElementById('robokassa_country_code');

	if (!optionalMethods || !country) {
		return;
	}

	optionalMethods.style.display = country.value === 'KZ' ? 'none' : '';
};

var toggleWidgetTab = function () {
	var country = document.getElementById('robokassa_country_code');
	var tabLink = document.querySelector('.robokassa-admin-nav__item[href*="robokassa_payment_credit"]');

	if (!country || !tabLink) {
		return;
	}

	var tabItem = tabLink.parentElement;

	if (!tabItem) {
		return;
	}

	tabItem.style.display = country.value === 'KZ' ? 'none' : '';
};

var countryField = document.getElementById('robokassa_country_code');

if (countryField) {
	countryField.addEventListener('change', function () {
		spoleer();
		toggleOptionalMethods();
		toggleWidgetTab();
	});
}

spoleer();
toggleOptionalMethods();
toggleWidgetTab();

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
