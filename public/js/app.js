/**
 * CSI — Centre de Santé Intégré
 * JavaScript principal — v2.0
 */

const CSI = (() => {

  /* ─── Init ─────────────────────────────────────────────────────────── */
  function init() {
    hidePL();
    initSidebar();
    initActiveNav();
    initAutoClose();
    initClocks();
    initTooltips();
  }

  /* ─── Page loader ───────────────────────────────────────────────────── */
  function hidePL() {
    const pl = document.getElementById('page-loader');
    if (pl) {
      setTimeout(() => pl.classList.add('hidden'), 300);
      setTimeout(() => pl.remove(), 750);
    }
  }

  /* ─── Sidebar (mobile toggle) ───────────────────────────────────────── */
  function initSidebar() {
    const toggle  = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      overlay && overlay.classList.toggle('open');
    });
    overlay && overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
    });
  }

  /* ─── Active nav link ───────────────────────────────────────────────── */
  function initActiveNav() {
    const path = window.location.pathname;
    document.querySelectorAll('.sidebar-link').forEach(link => {
      const href = link.getAttribute('href');
      if (!href) return;
      // Active si la route correspond exactement ou commence par le préfixe
      if (path === href || (href !== '/' && path.startsWith(href))) {
        link.classList.add('active');
      }
    });
  }

  /* ─── Auto-close alertes (data-dismiss) ────────────────────────────── */
  function initAutoClose() {
    document.querySelectorAll('[data-dismiss]').forEach(el => {
      const ms = parseInt(el.dataset.dismiss) || 5000;
      setTimeout(() => {
        el.style.transition = 'opacity .4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
      }, ms);
    });
  }

  /* ─── Horloge topbar ─────────────────────────────────────────────────── */
  function initClocks() {
    const el = document.getElementById('topbar-datetime');
    if (!el) return;
    function update() {
      const now = new Date();
      const days   = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
      const months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
      const d = days[now.getDay()];
      const m = months[now.getMonth()];
      const date = now.getDate().toString().padStart(2,'0');
      const y = now.getFullYear();
      const h = now.getHours().toString().padStart(2,'0');
      const min = now.getMinutes().toString().padStart(2,'0');
      el.textContent = `${d} ${date} ${m} ${y} | ${h}:${min}`;
    }
    update();
    setInterval(update, 30000);
  }

  /* ─── Modals ─────────────────────────────────────────────────────────── */
  function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
  }
  function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  }
  // Fermer en cliquant sur l'overlay
  document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
      e.target.classList.remove('open');
    }
  });
  // Fermer avec Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    }
  });

  /* ─── Tooltips ───────────────────────────────────────────────────────── */
  function initTooltips() {
    document.querySelectorAll('[title]').forEach(el => {
      el.setAttribute('data-tip', el.getAttribute('title'));
      el.removeAttribute('title');
    });
  }

  /* ─── Autocomplete patient ───────────────────────────────────────────── */
  function initPatientAutocomplete(inputEl, onSelect) {
    let dropdown = null;
    let timer    = null;

    function showDropdown(patients) {
      closeDropdown();
      if (!patients.length) return;

      dropdown = document.createElement('ul');
      dropdown.className = 'autocomplete-dropdown';

      patients.forEach(p => {
        const li = document.createElement('li');
        const initials = ((p.prenom||'').charAt(0) + (p.nom||'').charAt(0)).toUpperCase();
        li.innerHTML = `
          <div class="autocomplete-avatar">${initials}</div>
          <div>
            <div class="autocomplete-name">${p.nom} ${p.prenom}</div>
            <div class="autocomplete-sub">📞 ${p.telephone||'—'} • N° ${p.numeroDossier||'—'}</div>
          </div>`;
        li.addEventListener('mousedown', e => {
          e.preventDefault();
          inputEl.value = p.nom + ' ' + p.prenom;
          onSelect(p);
          closeDropdown();
        });
        dropdown.appendChild(li);
      });

      const wrapper = inputEl.closest('[style*="position"]') || inputEl.parentElement;
      if (!wrapper.style.position) wrapper.style.position = 'relative';
      wrapper.appendChild(dropdown);
    }

    function closeDropdown() {
      dropdown && dropdown.remove();
      dropdown = null;
    }

    inputEl.addEventListener('input', () => {
      clearTimeout(timer);
      const q = inputEl.value.trim();
      if (q.length < 2) { closeDropdown(); return; }
      timer = setTimeout(() => {
        fetch('/api/patients/search?q=' + encodeURIComponent(q))
          .then(r => r.json())
          .then(data => showDropdown(data.patients || data || []))
          .catch(() => closeDropdown());
      }, 300);
    });

    inputEl.addEventListener('blur', () => {
      setTimeout(closeDropdown, 200);
    });

    inputEl.addEventListener('keydown', e => {
      if (!dropdown) return;
      const items = dropdown.querySelectorAll('li');
      const active = dropdown.querySelector('li.active');
      let idx = Array.from(items).indexOf(active);

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        active && active.classList.remove('active');
        items[Math.min(idx + 1, items.length - 1)].classList.add('active');
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active && active.classList.remove('active');
        items[Math.max(idx - 1, 0)].classList.add('active');
      } else if (e.key === 'Enter' && active) {
        e.preventDefault();
        active.dispatchEvent(new Event('mousedown'));
      } else if (e.key === 'Escape') {
        closeDropdown();
      }
    });
  }

  /* ─── Dashboard Charts ───────────────────────────────────────────────── */
  function initDashboardCharts(data) {
    if (typeof Chart === 'undefined') return;

    // Couleurs
    const P = '#1A73E8', S = '#00C589', W = '#FFA000', D = '#E53935', I = '#00B0FF', U = '#7C3AED';
    const colors = [P, S, W, D, I, U, '#FF6D00', '#00BCD4', '#8BC34A', '#9C27B0'];

    // 1. Courbe recettes
    initChart('chart-revenus', {
      type: 'line',
      data: {
        labels: data.revenus?.labels || [],
        datasets: [{
          label: 'Recettes (FCFA)',
          data: data.revenus?.values || [],
          borderColor: P,
          backgroundColor: 'rgba(26,115,232,.08)',
          borderWidth: 2.5,
          pointBackgroundColor: P,
          pointRadius: 3,
          fill: true,
          tension: 0.4,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: v => v.toLocaleString('fr-FR') + ' F' },
            grid: { color: '#F0F4F8' }
          },
          x: { grid: { display: false } }
        }
      }
    });

    // 2. Donut actes
    initChart('chart-actes', {
      type: 'doughnut',
      data: {
        labels: data.actes?.labels || [],
        datasets: [{
          data: data.actes?.values || [],
          backgroundColor: colors,
          borderWidth: 2,
          borderColor: '#fff',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { font: { size: 11 }, padding: 10, boxWidth: 12 }
          }
        },
        cutout: '55%'
      }
    });

    // 3. Barres médecins
    initChart('chart-medecins', {
      type: 'bar',
      data: {
        labels: data.medecins?.labels || [],
        datasets: [{
          label: 'Consultations',
          data: data.medecins?.values || [],
          backgroundColor: colors,
          borderRadius: 6,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: '#F0F4F8' } },
          x: { grid: { display: false } }
        }
      }
    });

    // 4. Stock (horizontal bar)
    if (data.stock && data.stock.labels && data.stock.labels.length) {
      initChart('chart-stock', {
        type: 'bar',
        data: {
          labels: data.stock.labels,
          datasets: [{
            label: 'Stock disponible',
            data: data.stock.values || [],
            backgroundColor: (data.stock.values || []).map(v => v <= 0 ? D : v <= 10 ? W : S),
            borderRadius: 5,
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { beginAtZero: true, grid: { color: '#F0F4F8' } },
            y: { grid: { display: false }, ticks: { font: { size: 11 } } }
          }
        }
      });
    }
  }

  function initChart(id, config) {
    const canvas = document.getElementById(id);
    if (!canvas) return;
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();
    return new Chart(canvas, config);
  }

  /* ─── Confirm dialog ─────────────────────────────────────────────────── */
  function confirmAction(msg, callback) {
    if (window.confirm(msg || 'Confirmer cette action ?')) {
      callback && callback();
    }
  }

  /* ─── Toast / notification ───────────────────────────────────────────── */
  function toast(message, type = 'success', duration = 4000) {
    const icons = { success:'check-circle', danger:'times-circle', warning:'exclamation-triangle', info:'info-circle' };
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    div.style.cssText = 'position:fixed;top:70px;right:1.5rem;z-index:9999;max-width:380px;min-width:260px;box-shadow:0 4px 16px rgba(0,0,0,.15);cursor:pointer';
    div.innerHTML = `<i class="fas fa-${icons[type]||'info-circle'}"></i><span>${message}</span>`;
    div.addEventListener('click', () => div.remove());
    document.body.appendChild(div);
    setTimeout(() => {
      div.style.transition = 'opacity .4s';
      div.style.opacity = '0';
      setTimeout(() => div.remove(), 400);
    }, duration);
  }

  /* ─── Formatage monnaie ──────────────────────────────────────────────── */
  function formatMoney(val, currency = 'FCFA') {
    return new Intl.NumberFormat('fr-FR').format(Math.round(val || 0)) + ' ' + currency;
  }

  /* ─── Exposer API publique ───────────────────────────────────────────── */
  return {
    init,
    openModal,
    closeModal,
    toast,
    formatMoney,
    confirmAction,
    initPatientAutocomplete,
    initDashboardCharts,
    initChart,
  };

})();

/* ─── Démarrer au chargement du DOM ─────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', CSI.init);
