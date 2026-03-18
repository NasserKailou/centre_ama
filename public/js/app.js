/**
 * CSI — Application JavaScript principal
 * Gestion de la sidebar, flash messages, modals, thème
 * Version 1.0.0
 */
(function () {
  'use strict';

  /* ============================================================
     SIDEBAR — Toggle mobile
     ============================================================ */
  const sidebar  = document.querySelector('.sidebar');
  const overlay  = document.querySelector('.sidebar-overlay');
  const btnToggle = document.querySelector('.topbar-toggle');

  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('open');
    document.body.style.overflow = '';
  }

  btnToggle?.addEventListener('click', () => {
    sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlay?.addEventListener('click', closeSidebar);

  /* ============================================================
     ACTIVE SIDEBAR LINK — Détecter la page actuelle
     ============================================================ */
  const currentPath = window.location.pathname;
  document.querySelectorAll('.sidebar-link').forEach(link => {
    const href = link.getAttribute('href');
    if (href && href !== '/' && currentPath.startsWith(href)) {
      link.classList.add('active');
    } else if (href === '/' && currentPath === '/') {
      link.classList.add('active');
    }
  });

  /* ============================================================
     TOPBAR — Date/heure en temps réel
     ============================================================ */
  const dateEl = document.getElementById('topbar-datetime');
  if (dateEl) {
    const updateTime = () => {
      const now = new Date();
      const opts = {
        weekday: 'short', day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
      };
      dateEl.textContent = now.toLocaleDateString('fr-FR', opts).replace(',', ' |');
    };
    updateTime();
    setInterval(updateTime, 30000);
  }

  /* ============================================================
     FLASH MESSAGES — Auto-dismiss avec animation
     ============================================================ */
  function createFlash(message, type) {
    const icons = { success: 'check-circle', danger: 'times-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    const colors = {
      success: { bg: '#E8F5E9', color: '#2E7D32', border: '#00C589' },
      danger:  { bg: '#FCE4EC', color: '#C2185B', border: '#E91E63' },
      warning: { bg: '#FFF3E0', color: '#E65100', border: '#FFA000' },
      info:    { bg: '#E3F2FD', color: '#1565C0', border: '#00B0FF' }
    };

    const c = colors[type] || colors.info;
    const div = document.createElement('div');
    div.className = 'flash-item';
    div.style.cssText = `background:${c.bg};color:${c.color};border-left:4px solid ${c.border};`;
    div.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'}"></i><span>${message}</span>`;

    div.addEventListener('click', () => removeFlash(div));

    let container = document.querySelector('.flash-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'flash-container';
      document.body.appendChild(container);
    }
    container.appendChild(div);

    setTimeout(() => removeFlash(div), 5000);
  }

  function removeFlash(el) {
    el.style.transition = 'all .3s';
    el.style.transform  = 'translateX(120%)';
    el.style.opacity    = '0';
    setTimeout(() => el.remove(), 300);
  }

  // Exposer globalement
  window.CSI = window.CSI || {};
  window.CSI.flash = createFlash;

  /* ============================================================
     MODALS — Système générique
     ============================================================ */
  // Ouvrir via data-modal-open="modal-id"
  document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-modal-open');
      const modal = document.getElementById(id);
      if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('open');
      }
    });
  });

  // Fermer via data-modal-close ou click sur overlay
  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-modal-close');
      const modal = document.getElementById(id);
      if (modal) closeModal(modal);
    });
  });

  // Fermer en cliquant sur l'overlay (fond)
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeModal(overlay);
    });
  });

  // Fermer avec Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m));
    }
  });

  function closeModal(el) {
    el.style.display = 'none';
    el.classList.remove('open');
  }

  window.CSI.openModal  = (id) => { const m = document.getElementById(id); if (m) { m.style.display = 'flex'; m.classList.add('open'); } };
  window.CSI.closeModal = (id) => { const m = document.getElementById(id); if (m) closeModal(m); };

  /* ============================================================
     CONFIRMATION DELETE — Formulaires de suppression
     ============================================================ */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      const msg = el.getAttribute('data-confirm') || 'Confirmer cette action ?';
      if (!confirm(msg)) e.preventDefault();
    });
  });

  /* ============================================================
     TABLE SEARCH — Recherche côté client
     ============================================================ */
  document.querySelectorAll('[data-search-table]').forEach(input => {
    const tableId = input.getAttribute('data-search-table');
    const table   = document.getElementById(tableId);
    if (!table) return;

    input.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      table.querySelectorAll('tbody tr').forEach(tr => {
        const text = tr.textContent.toLowerCase();
        tr.style.display = (!q || text.includes(q)) ? '' : 'none';
      });

      // Afficher message "aucun résultat"
      const tbody = table.querySelector('tbody');
      const visible = [...tbody.querySelectorAll('tr')].filter(tr => tr.style.display !== 'none');
      let empty = tbody.querySelector('.no-results-row');
      if (visible.length === 0) {
        if (!empty) {
          empty = document.createElement('tr');
          empty.className = 'no-results-row';
          empty.innerHTML = `<td colspan="99" style="text-align:center;padding:2rem;color:#90A4AE"><i class="fas fa-search"></i> Aucun résultat pour "<strong>${this.value}</strong>"</td>`;
          tbody.appendChild(empty);
        }
      } else {
        empty?.remove();
      }
    });
  });

  /* ============================================================
     FORM AUTO-SAVE (localStorage) — Éviter pertes de données
     ============================================================ */
  const autosaveForms = document.querySelectorAll('[data-autosave]');
  autosaveForms.forEach(form => {
    const key = 'csi_autosave_' + (form.id || form.getAttribute('data-autosave'));

    // Restaurer
    const saved = localStorage.getItem(key);
    if (saved) {
      try {
        const data = JSON.parse(saved);
        Object.entries(data).forEach(([name, value]) => {
          const el = form.elements[name];
          if (el && el.type !== 'hidden' && el.type !== 'password') {
            if (el.type === 'checkbox') el.checked = value;
            else el.value = value;
          }
        });
      } catch (e) { /* ignore */ }
    }

    // Sauvegarder à chaque changement
    form.addEventListener('change', () => {
      const data = {};
      [...form.elements].forEach(el => {
        if (el.name && el.type !== 'hidden' && el.type !== 'password') {
          data[el.name] = el.type === 'checkbox' ? el.checked : el.value;
        }
      });
      localStorage.setItem(key, JSON.stringify(data));
    });

    // Effacer après soumission
    form.addEventListener('submit', () => localStorage.removeItem(key));
  });

  /* ============================================================
     NUMBER FORMAT — Formatage des nombres en temps réel
     ============================================================ */
  document.querySelectorAll('[data-currency]').forEach(el => {
    const format = (n) => new Intl.NumberFormat('fr-FR').format(n || 0);
    el.addEventListener('blur', function () {
      const raw = parseFloat(this.value.replace(/\s/g, '').replace(',', '.')) || 0;
      this.value = raw;
    });
  });

  /* ============================================================
     LIGNE FACTURE — Calcul automatique sous-totaux
     ============================================================ */
  function recalcFacture() {
    let total = 0;
    document.querySelectorAll('.ligne-facture').forEach(row => {
      const qte = parseFloat(row.querySelector('[name$="[quantite]"]')?.value || 1);
      const pu  = parseFloat(row.querySelector('[name$="[prix_unitaire]"]')?.value || 0);
      const st  = qte * pu;
      const stEl = row.querySelector('.sous-total-display');
      if (stEl) stEl.textContent = new Intl.NumberFormat('fr-FR').format(st);
      total += st;
    });

    const totalEl = document.getElementById('total-facture');
    if (totalEl) totalEl.textContent = new Intl.NumberFormat('fr-FR').format(total);

    // Calcul part assurance
    const tauxEl = document.getElementById('taux-assurance');
    const taux   = parseFloat(tauxEl?.value || 0) / 100;
    const partAss = total * taux;
    const partPat = total - partAss;

    const partAssEl = document.getElementById('part-assurance');
    const partPatEl = document.getElementById('part-patient');
    if (partAssEl) partAssEl.textContent = new Intl.NumberFormat('fr-FR').format(partAss);
    if (partPatEl) partPatEl.textContent = new Intl.NumberFormat('fr-FR').format(partPat);
  }

  document.addEventListener('input', e => {
    if (e.target.closest('.ligne-facture') ||
        e.target.id === 'taux-assurance') {
      recalcFacture();
    }
  });

  // Init au chargement
  recalcFacture();

  /* ============================================================
     NOTIFICATIONS — Polling léger (toutes les 2 min)
     ============================================================ */
  const notifDot = document.querySelector('.topbar-notif-dot');
  let notifPanel = null;

  async function checkNotifications() {
    try {
      const res  = await fetch('/api/notifications/count', { credentials: 'same-origin' });
      if (!res.ok) return;
      const data = await res.json();
      if (notifDot) notifDot.style.display = (data.count > 0) ? 'block' : 'none';
    } catch { /* Pas de notifications si l'API n'existe pas encore */ }
  }

  // Vérifier les notifications toutes les 2 minutes
  checkNotifications();
  setInterval(checkNotifications, 120000);

  /* ============================================================
     PAGE LOADER — Masquer après chargement
     ============================================================ */
  const loader = document.querySelector('.page-loader');
  if (loader) {
    window.addEventListener('load', () => {
      setTimeout(() => {
        loader.classList.add('hidden');
        setTimeout(() => loader.remove(), 400);
      }, 200);
    });
  }

  /* ============================================================
     PRINT — Helper
     ============================================================ */
  window.CSI.print = function (selector) {
    const el = selector ? document.querySelector(selector) : null;
    if (el) {
      const clone = el.cloneNode(true);
      const win = window.open('', '_blank');
      win.document.write(`
        <html><head>
          <title>CSI — Impression</title>
          <link rel="stylesheet" href="/css/app.css">
          <link rel="stylesheet" href="/css/components.css">
        </head><body style="padding:1rem">${clone.outerHTML}</body></html>
      `);
      win.document.close();
      setTimeout(() => { win.print(); win.close(); }, 500);
    } else {
      window.print();
    }
  };

  /* ============================================================
     INIT — Log de démarrage (dev uniquement)
     ============================================================ */
  if (document.body.dataset.env === 'dev') {
    console.log('%c[CSI] Application initialisée ✓', 'color:#1A73E8;font-weight:700');
  }

})();
