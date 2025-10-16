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