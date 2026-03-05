(function(){
	'use strict';

	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}

		callback();
	}

	function toPositiveInt(value, fallback) {
		var parsed = parseInt(value, 10);

		if (isNaN(parsed) || parsed < 0) {
			return fallback;
		}

		return parsed;
	}

	function hasRedirectConfig() {
		return typeof window.robokassaRedirectConfig === 'object' && window.robokassaRedirectConfig !== null;
	}

	function requestOrderStatus(config) {
		var payload = new URLSearchParams();
		payload.append('action', 'robokassa_check_order_status');
		payload.append('orderId', config.orderId);
		payload.append('orderKey', config.orderKey);

		return fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: payload.toString()
		}).then(function(response){
			if (!response.ok) {
				return null;
			}

			return response.json();
		}).catch(function(){
			return null;
		});
	}

	function isVisibleElement(element) {
		if (!element || typeof element.getBoundingClientRect !== 'function') {
			return false;
		}

		var style = window.getComputedStyle(element);
		var rect = element.getBoundingClientRect();

		if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
			return false;
		}

		return rect.width > 1 && rect.height > 1;
	}

	function hasRobokassaIframe() {
		var selectors = [
			'iframe#robokassa_iframe',
			'iframe[name="robokassa_iframe"]',
			'iframe[id="robokassa_iframe"]'
		];

		for (var i = 0; i < selectors.length; i++) {
			var iframe = document.querySelector(selectors[i]);

			if (isVisibleElement(iframe)) {
				return true;
			}
		}

		return false;
	}

	function getFallbackUrl(config) {
		if (config.fallbackUrl) {
			return config.fallbackUrl;
		}

		var shopLink = document.querySelector('a[href*="/shop"], a[href*="/magazin"], a[href*="/магазин"]');
		if (shopLink && shopLink.href) {
			return shopLink.href;
		}

		if (document.referrer && document.referrer.indexOf(window.location.origin) === 0) {
			return document.referrer;
		}

		return '/';
	}

	function createRedirectState() {
		return {
			finished: false,
			paymentTimer: null,
			iframeTimer: null
		};
	}

	function stopWatchers(state) {
		if (state.paymentTimer) {
			window.clearInterval(state.paymentTimer);
		}

		if (state.iframeTimer) {
			window.clearInterval(state.iframeTimer);
		}

		state.finished = true;
	}

	function redirectTo(url, state) {
		if (state.finished || !url) {
			return;
		}

		stopWatchers(state);
		window.location.href = url;
	}

	function handleCloseRedirect(config, fallbackUrl, state) {
		requestOrderStatus(config).then(function(result){
			if (state.finished) {
				return;
			}

			if (result && result.success && result.data && result.data.paid) {
				redirectTo(config.successUrl, state);
				return;
			}

			redirectTo(fallbackUrl, state);
		});
	}

	function startIframeCloseWatcher(config, state) {
		var fallbackUrl = getFallbackUrl(config);
		var iframeWasVisible = false;

		state.iframeTimer = window.setInterval(function(){
			if (state.finished) {
				return;
			}

			var iframeVisible = hasRobokassaIframe();
			iframeWasVisible = iframeWasVisible || iframeVisible;

			if (iframeWasVisible && !iframeVisible) {
				handleCloseRedirect(config, fallbackUrl, state);
			}
		}, 600);
	}

	function startIframeRedirectWatcher() {
		if (!hasRedirectConfig()) {
			return;
		}

		var config = window.robokassaRedirectConfig;
		var required = ['ajaxUrl', 'orderId', 'orderKey', 'successUrl'];

		for (var i = 0; i < required.length; i++) {
			if (!config[required[i]]) {
				return;
			}
		}

		var interval = toPositiveInt(config.checkInterval, 5000);
		var maxAttempts = toPositiveInt(config.maxAttempts, 120);
		var attempts = 0;
		var state = createRedirectState();

		state.paymentTimer = window.setInterval(function(){
			if (state.finished) {
				return;
			}

			attempts += 1;

			if (maxAttempts > 0 && attempts > maxAttempts) {
				stopWatchers(state);
				return;
			}

			requestOrderStatus(config).then(function(result){
				if (!result || !result.success || !result.data) {
					return;
				}

				if (result.data.paid) {
					redirectTo(config.successUrl, state);
				}
			});
		}, interval);

		startIframeCloseWatcher(config, state);
	}

	function initWrapper(wrapper) {
		if (!wrapper || wrapper.dataset.robokassaRedirectInitialized === '1') {
			return;
		}

		wrapper.dataset.robokassaRedirectInitialized = '1';

		var form = null;
		var manual = null;
		var formId = wrapper.dataset.formId;
		var manualId = wrapper.dataset.manualId;

		if (formId) {
			form = document.getElementById(formId);
		}

		if (!form) {
			form = wrapper.querySelector('form');
		}

		if (!form) {
			return;
		}

		if (manualId) {
			manual = document.getElementById(manualId);
		}

		var manualDelay = toPositiveInt(wrapper.dataset.manualDelay, 6000);
		var submitDelay = toPositiveInt(wrapper.dataset.submitDelay, 200);

		if (manual) {
			window.setTimeout(function(){
				manual.classList.add('robokassa-visible');
			}, manualDelay);
		}

		window.setTimeout(function(){
			try {
				form.submit();
			} catch (error) {
				if (manual) {
					manual.classList.add('robokassa-visible');
				}
			}
		}, submitDelay);
	}

	function scanWrappers(root) {
		var context = root || document;
		var wrappers = context.querySelectorAll('.robokassa-redirect-wrapper');

		wrappers.forEach(function(wrapper){
			initWrapper(wrapper);
		});
	}

	onReady(function(){
		scanWrappers(document);
		startIframeRedirectWatcher();

		var observer = new MutationObserver(function(mutations){
			mutations.forEach(function(mutation){
				mutation.addedNodes.forEach(function(node){
					if (!node || node.nodeType !== 1) {
						return;
					}

					if (node.matches('.robokassa-redirect-wrapper')) {
						initWrapper(node);
						return;
					}

					scanWrappers(node);
				});
			});
		});

		observer.observe(document.body, { childList: true, subtree: true });
	});
})();
