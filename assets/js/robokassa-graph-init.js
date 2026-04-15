(function(){
	'use strict';

	var initTimer = null;

	function getGraphMethod(graph) {
		return graph.getAttribute('paymentMethod') || graph.getAttribute('paymentmethod') || '';
	}

	function isGraphVisible(graph) {
		return Boolean(graph.offsetParent || graph.getClientRects().length);
	}

	function initVisibleGraphs() {
		if (typeof window.initRobokassaGraphs !== 'function') {
			return;
		}

		var methods = [];
		var graphs = document.querySelectorAll('robokassa-graph');

		graphs.forEach(function(graph){
			var paymentMethod = getGraphMethod(graph);

			if (!paymentMethod || !isGraphVisible(graph) || methods.indexOf(paymentMethod) !== -1) {
				return;
			}

			methods.push(paymentMethod);
		});

		methods.forEach(function(paymentMethod){
			window.initRobokassaGraphs(paymentMethod);
		});
	}

	function initWhenReady(attempt) {
		if (typeof window.initRobokassaGraphs === 'function') {
			initVisibleGraphs();
			return;
		}

		if (attempt >= 20) {
			return;
		}

		window.setTimeout(function(){
			initWhenReady(attempt + 1);
		}, 150);
	}

	function initSoon() {
		if (initTimer !== null) {
			window.clearTimeout(initTimer);
		}

		initTimer = window.setTimeout(function(){
			initTimer = null;
			initWhenReady(0);
		}, 150);
	}

	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}

		callback();
	}

	onReady(function(){
		initWhenReady(0);

		document.addEventListener('change', function(event){
			if (!event.target || !event.target.matches('input[type="radio"]')) {
				return;
			}

			initSoon();
		});

		if (window.jQuery) {
			window.jQuery(document.body).on('updated_checkout', function(){
				initSoon();
			});
		}
	});
})();
