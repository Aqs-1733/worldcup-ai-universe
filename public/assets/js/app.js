(() => {
  const root = document.documentElement;
  const savedTheme = localStorage.getItem('worldcup-theme');
  if (savedTheme) root.dataset.theme = savedTheme;

  document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('worldcup-theme', root.dataset.theme);
  });

  const reveal = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) entry.target.classList.add('seen');
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.reveal').forEach((node) => reveal.observe(node));

  document.querySelectorAll('[data-bars]').forEach((chart) => {
    setTimeout(() => chart.classList.add('active'), 180);
  });

  document.querySelectorAll('[data-filter-input]').forEach((input) => {
    input.addEventListener('input', () => {
      const target = document.querySelector(input.dataset.filterTarget);
      const keyword = input.value.trim().toLowerCase();
      target?.querySelectorAll('[data-filter-text]').forEach((item) => {
        item.hidden = keyword !== '' && !item.dataset.filterText.toLowerCase().includes(keyword);
      });
    });
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      form.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.dataset.originalText = button.textContent;
        button.textContent = button.textContent?.includes('生成') ? '生成中...' : '处理中...';
        button.setAttribute('disabled', 'disabled');
      });
    });
  });

  document.querySelectorAll('.module-card, .team-card, .player-card, .news-card').forEach((card) => {
    card.addEventListener('mousemove', (event) => {
      const rect = card.getBoundingClientRect();
      card.style.setProperty('--mx', `${event.clientX - rect.left}px`);
      card.style.setProperty('--my', `${event.clientY - rect.top}px`);
    });
  });

  if (document.querySelector('[data-news-auto-refresh]')) {
    setInterval(async () => {
      try {
        const response = await fetch('/api/news/refresh', { headers: { 'Accept': 'application/json' } });
        const data = await response.json();
        if (data.ok && data.summary && Number(data.summary.inserted) > 0) {
          location.reload();
        }
      } catch (_) {
        // Keep the page readable when a source is temporarily unavailable.
      }
    }, 120000);
  }
})();
