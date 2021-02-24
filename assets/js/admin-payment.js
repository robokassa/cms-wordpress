function spoleer()
{

	var e1 = document.getElementById("type_fiz");
	var e2 = document.getElementById("who_both");
	var e3 = document.getElementById("robokassa_country_code");

	if (e3.options[e3.selectedIndex].value === 'KZ') {
		document.getElementById("tax").style.display = 'table-row';
		document.getElementById("commission").style.display = 'none';
		document.getElementById("sno").style.display = 'none';
		document.getElementById("payment_method").style.display = 'none';
		document.getElementById("payment_object").style.display = 'none';
	} else {

	if (e1.checked) {
		document.getElementById("commission").style.display = 'table-row';
		document.getElementById("sno").style.display = 'none';
		document.getElementById("tax").style.display = 'none';
		document.getElementById("payment_method").style.display = 'none';
		document.getElementById("payment_object").style.display = 'none';

		if (e2.checked) {
			document.getElementById("size_commission").style.display = 'table-row';
		} else {
			document.getElementById("size_commission").style.display = 'none';
		}
	} else {
		document.getElementById("commission").style.display = 'none';
		document.getElementById("sno").style.display = 'table-row';
		document.getElementById("payment_method").style.display = 'table-row';
		document.getElementById("payment_object").style.display = 'table-row';

		var sno = document.getElementById("sno_select");

		if (sno.options[sno.selectedIndex].value === 'osn') {
			document.getElementById("tax").style.display = 'table-row';
		} else {
			document.getElementById("tax").style.display = 'none';
		}
		document.getElementById("size_commission").style.display = 'none';
	}
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