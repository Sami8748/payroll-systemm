</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
	(function () {
		var html = document.documentElement;
		var btn = document.getElementById('themeToggle');
		var icon = document.getElementById('themeToggleIcon');

		function paint() {
			if (!btn || !icon) {
				return;
			}

			var theme = html.getAttribute('data-theme') || 'light';
			var dark = theme === 'dark';
			btn.classList.toggle('active', dark);
			icon.className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
			btn.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
		}

		paint();

		if (btn) {
			btn.addEventListener('click', function () {
				var current = html.getAttribute('data-theme') || 'light';
				var next = current === 'dark' ? 'light' : 'dark';
				html.classList.add('theme-switching');
				html.setAttribute('data-theme', next);
				localStorage.setItem('payroll_theme', next);
				paint();
				window.setTimeout(function () {
					html.classList.remove('theme-switching');
				}, 420);
			});
		}
	})();

	(function () {
		var clock = document.getElementById('liveClock');
		if (!clock) {
			return;
		}

		function updateClock() {
			var locale = clock.getAttribute('data-locale') === 'th' ? 'th-TH' : 'en-US';
			var now = new Date();
			clock.textContent = now.toLocaleString(locale, {
				year: 'numeric',
				month: '2-digit',
				day: '2-digit',
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
				hour12: false
			});
		}

		updateClock();
		window.setInterval(updateClock, 1000);
	})();
</script>
</body>
</html>
