(function(){
	'use strict';

	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}

		callback();
	}

	function setOnCheckoutHandler(element) {
		if (!element || element.oncheckout === window.robokassaWidgetHandleCheckout) {
			return;
		}

		element.oncheckout = window.robokassaWidgetHandleCheckout;
	}

	function scanWidgets(root) {
		var context = root || document;
		var widgets = context.querySelectorAll('robokassa-widget[mode="checkout"], robokassa-badge[mode="checkout"]');

		widgets.forEach(function(widget){
			setOnCheckoutHandler(widget);
		});
	}

	function observeWidgets() {
		if (!window.MutationObserver) {
			return;
		}

		var observer = new MutationObserver(function(mutations){
			mutations.forEach(function(mutation){
				mutation.addedNodes.forEach(function(node){
					if (!node || node.nodeType !== 1) {
						return;
					}

					if (node.matches && node.matches('robokassa-widget[mode="checkout"], robokassa-badge[mode="checkout"]')) {
						setOnCheckoutHandler(node);
						return;
					}

					if (node.querySelectorAll) {
						scanWidgets(node);
					}
				});
			});
		});

		observer.observe(document.body, { childList: true, subtree: true });
	}

	function initBadges() {
		if (typeof window.initRobokassaBadges === 'function') {
			window.initRobokassaBadges();
		}
	}

	if (typeof window.robokassaWidgetHandleCheckout !== 'function') {
		window.robokassaWidgetHandleCheckout = async function(payload) {
			if (typeof window.CustomEvent !== 'function') {
				return true;
			}

			var event = new CustomEvent('robokassaWidgetCheckout', {
				detail: payload,
				cancelable: true
			});

			var proceed = document.dispatchEvent(event);

			if (!proceed && event.defaultPrevented) {
				return false;
			}

			return true;
		};
	}

	onReady(function(){
		scanWidgets(document);
		observeWidgets();
		initBadges();
	});
})();
