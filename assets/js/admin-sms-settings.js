!function (a) {
	var b = {
		init: function (b) {
			var c = {
				eventType: "keyup blur copy paste cut start",
				elAlias: a(this),
				reg: "",
				translated: function (a, b, c) {
				},
				caseType: "inherit",
				status: !0,
				string: ""
			};
			b && a.extend(c, b);
			var d = a(this);
			return d.length || (d = a("<div>").text(c.string)), d.each(function () {
				function k() {
					en_to_ru = {
						"а": "a",
						"б": "b",
						"в": "v",
						"г": "g",
						"д": "d",
						"е": "e",
						"ё": "jo",
						"ж": "zh",
						"з": "z",
						"и": "i",
						"й": "j",
						"к": "k",
						"л": "l",
						"м": "m",
						"н": "n",
						"о": "o",
						"п": "p",
						"р": "r",
						"с": "s",
						"т": "t",
						"у": "u",
						"ф": "f",
						"х": "h",
						"ц": "c",
						"ч": "ch",
						"ш": "sh",
						"щ": "sch",
						"ъ": "",
						"ы": "y",
						"ь": "",
						"э": "e",
						"ю": "ju",
						"я": "ja",
						" ": " ",
						"і": "i",
						"ї": "i",
						"є": "e",
						"А": "A",
						"Б": "B",
						"В": "V",
						"Г": "G",
						"Д": "D",
						"Е": "E",
						"Ё": "Jo",
						"Ж": "Zh",
						"З": "Z",
						"И": "I",
						"Й": "J",
						"К": "K",
						"Л": "L",
						"М": "M",
						"Н": "N",
						"О": "O",
						"П": "P",
						"Р": "R",
						"С": "S",
						"Т": "T",
						"У": "U",
						"Ф": "F",
						"Х": "H",
						"Ц": "C",
						"Ч": "Ch",
						"Ш": "Sh",
						"Щ": "Sch",
						"Ъ": "",
						"Ы": "Y",
						"Ь": "",
						"Э": "E",
						"Ю": "Ju",
						"Я": "Ja",
						" ": " ",
						"І": "I",
						"Ї": "I",
						"Є": "E"
					}, f = l(f), f = f.split("");
					var a = new String;
					for (i = 0; i < f.length; i++) for (key in en_to_ru) {
						if (val = en_to_ru[key], key == f[i]) {
							a += val;
							break
						}
						"Є" == key && (a += f[i])
					}
					return a
				}

				function l(a) {
					return a
				}

				var f, b = a(this),
					e = c.elAlias.length ? c.elAlias.css({wordWrap: "break-word"}) : d.css({wordWrap: "break-word"});
				b.data({status: c.status});

				var g = function (a, d) {
					if ("upper" == c.caseType && (a = a.toUpperCase()), "lower" == c.caseType && (a = a.toLowerCase()), b.data("status") && c.elAlias && (void 0 !== e.prop("value") ? e.val(a) : e.html(a)), "" != a && void 0 !== c.translated) {
						var f;
						f = void 0 == d ? "no event" : d.type, c.translated(b, a, f)
					}
				}, h = function (a) {
					customArr = c.reg.split(",");
					for (var b = 0; b < customArr.length; b++) {
						var d = customArr[b].split("="), e = d[0].replace(/"/g, ""),
							f = d[1].replace(/"/g, ""), g = new RegExp(e, "ig");
						a = a.replace(g, f)
					}
					return a
				}, j = function (a, b) {
					f = void 0 !== a.prop("value") ? a.val() : a.text(), c.reg && "" != c.reg && (f = h(f)), g(k(f), b)
				};

				b.on(c.eventType, function (b) {
					var c = a(this);
					setTimeout(function () {
						j(c, b)
					}, 50)
				}), j(b)
			})
		}, disable: function () {
			a(this).data({status: !1})
		}, enable: function () {
			a(this).data({status: !0})
		}
	};

	a.fn.liTranslit = function (c) {
		return b[c] ? b[c].apply(this, Array.prototype.slice.call(arguments, 1)) : "object" != typeof c && c ? void a.error("Метод " + c + " в jQuery.liTranslit не существует") : b.init.apply(this, arguments)
	}
}(jQuery), jQuery(".text").liTranslit({elAlias: jQuery(".translit")});

function robokassa_payment_countDigits(n) {
	for (var i = 0; n > 1; i++) {
		n /= 10;
	}
	return i;
}

function robokassa_payment_disabler(id) {
	var e = document.getElementById("sms" + id + "_enabled");
	var value = e.checked;
	elem = document.getElementById("sms" + id + "_text");
	elem.disabled = !(value);
}

function robokassa_payment_counter(e, id) {
	cur_count = jQuery(e).val().length;
	text = jQuery(e).val();

	multipler = 1;
	transler = false;

	if (document.getElementById('sms_translit').checked) {
		multipler = 2;
		transler = true;
	} else {
		transler = false;
	}

	max = 128 * multipler;

	if (transler) {
		jQuery('#sms' + id + '_text').liTranslit({
			elAlias: jQuery('#sms' + id + '_translit')
		});
		document.getElementById('sms' + id + '_translit').style.display = 'block';
	} else {
		document.getElementById('sms' + id + '_translit').innerHTML = '';
		document.getElementById('sms' + id + '_translit').style.display = 'none';
	}
	if (text.indexOf('{address}') + 1) {
		cur_count = cur_count + 21;
	}
	if (text.indexOf('{fio}') + 1) {
		cur_count = cur_count + 25;
	}
	if (text.indexOf('{order_number}') + 1) {
		cur_count = cur_count + window.next_order - 14;
	}

	matcher = e.value.match(/[ЖжЧчШшЩщЮюЯя]/g);

	if (!(typeof matcher === undefined) && !(matcher === null)) {
		transSymbols = e.value.match(/[ЖжЧчШшЩщЮюЯя]/g).length;
	} else {
		transSymbols = 0;
	}

	if (transler) {
		cur_count += transSymbols;
	}

	document.getElementById('counterX' + id).innerHTML = cur_count;
	document.getElementById('counterX' + id).style.color = (cur_count > max) ? 'red' : 'black';

	document.getElementById('counterY' + id).innerHTML = max - cur_count;
	document.getElementById('counterZ' + id).innerHTML = Math.floor((cur_count - 1) / (70 * multipler)) + 1;
}

function robokassa_payment_refresher() {
	robokassa_payment_counter(document.getElementById('sms1_text'), '1');
	robokassa_payment_disabler('1');
	robokassa_payment_counter(document.getElementById('sms2_text'), '2');
	robokassa_payment_disabler('2');
}

jQuery(document).ready(function () {
	robokassa_payment_refresher();
});