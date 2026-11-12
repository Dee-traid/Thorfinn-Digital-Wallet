(function () {
  'use strict';

  const  API = '/src/Router/Router.php';
  const CURRENCY = '₦';

  /* ---------- App state  ---------- */
  var state = {
    user: null,
    wallets: [],        
    transactions: [], 
    totalBalance: 0,
    activeWalletFilter: null
  };

  var detailsWalletId = null;
  var pending = null;         

  /* ---------- helpers ---------- */
  function esc(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function money(n) {
    return CURRENCY + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function formatWhen(iso) {
    var d = new Date(iso);
    if (isNaN(d)) return '';
    var now = new Date();
    var time = d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
    var startOf = function (x) { return new Date(x.getFullYear(), x.getMonth(), x.getDate()).getTime(); };
    var days = Math.round((startOf(now) - startOf(d)) / 86400000);
    if (days === 0) return 'Today, ' + time;
    if (days === 1) return 'Yesterday, ' + time;
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  }

  /* ---------- API client ---------- */
  function api(action, options) {
    options = options || {};
    var url = API + '?action=' + encodeURIComponent(action);
    if (options.params) {
      Object.keys(options.params).forEach(function (k) {
        if (options.params[k] != null) url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(options.params[k]);
      });
    }
    var init = {
      method: options.body !== undefined ? 'POST' : (options.method || 'GET'),
      headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + (localStorage.getItem('jwt_token') || '') },
      credentials: 'include'
    };
    if (options.body !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(options.body);
    }
    return fetch(url, init).then(function (response) {
      return response.text().then(function (text) {
        var body = null;
        try { body = JSON.parse(text); } catch (e) { }
        if (response.status === 401 && action !== 'change-password') {
          localStorage.removeItem('jwt_token');
          window.location.href = 'auth.html';
        }
        if (!body) body = { success: false, message: 'Unexpected server response (' + response.status + ').', errors: {} };
        return { ok: response.ok && body.success !== false, status: response.status, body: body };
      });
    });
  }

  /* ---------- Date in topbar ---------- */
  var dateEl = document.getElementById('topbar-date');
  if (dateEl) {
    dateEl.textContent = new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' });
  }

  /* ---------- Sidebar toggle (mobile) ---------- */
  var shell = document.getElementById('dashboard-shell');
  var toggleBtn = document.getElementById('sidebar-toggle');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      var isOpen = shell.classList.toggle('sidebar-open');
      toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }
  document.querySelectorAll('[data-nav-item]').forEach(function (link) {
    link.addEventListener('click', function () {
      shell.classList.remove('sidebar-open');
      document.querySelectorAll('.nav-item').forEach(function (n) { n.classList.remove('is-active'); });
      link.classList.add('is-active');
    });
  });

  /* ---------- Logout ---------- */
  var logoutBtn = document.getElementById('logout-btn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function (e) {
      e.preventDefault();
      api('logout', { body: {} }).then(finish, finish);
      function finish() {
        localStorage.removeItem('jwt_token');
        window.location.href = 'auth.html';
      }
    });
  }

  /* ---------- Feedback helpers  ---------- */
  function setFieldError(fieldId, message) {
    var input = document.getElementById(fieldId);
    var field = input ? input.closest('.field') : null;
    var errorEl = document.querySelector('[data-error-for="' + fieldId + '"]');
    if (message) {
      if (field) field.classList.add('field-invalid');
      if (errorEl) { errorEl.textContent = message; errorEl.classList.add('visible'); }
      return false;
    }
    if (field) field.classList.remove('field-invalid');
    if (errorEl) { errorEl.textContent = ''; errorEl.classList.remove('visible'); }
    return true;
  }

  
  function showBanner(id, message, type) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('form-banner--error', 'form-banner--success');
    if (!message) { el.textContent = ''; el.hidden = true; return; }
    el.textContent = message;
    el.classList.add(type === 'success' ? 'form-banner--success' : 'form-banner--error');
    el.setAttribute('role', type === 'success' ? 'status' : 'alert');
    el.hidden = false;
  }

  /** Error handling */
  function clearFeedback(container) {
    container.querySelectorAll('.field-error').forEach(function (e) { e.textContent = ''; e.classList.remove('visible'); });
    container.querySelectorAll('.field-invalid').forEach(function (f) { f.classList.remove('field-invalid'); });
    container.querySelectorAll('.form-banner').forEach(function (b) {
      b.hidden = true; b.textContent = '';
      b.classList.remove('form-banner--error', 'form-banner--success');
    });
  }

  function showFailure(res, bannerId, fieldMap) {
    var errors = (res.body && res.body.errors) || {};
    var shown = false;
    Object.keys(errors).forEach(function (key) {
      if (fieldMap && fieldMap[key]) { setFieldError(fieldMap[key], errors[key]); shown = true; }
    });
    if (!shown) showBanner(bannerId, res.body.message || 'Something went wrong. Please try again.', 'error');
  }

  function showNetworkError(bannerId) {
    showBanner(bannerId, 'A network error occurred. Please check your connection and try again.', 'error');
  }

  function isValidAmount(value) {
    return /^\d+(\.\d{1,2})?$/.test(value.trim()) && parseFloat(value) > 0;
  }

  function setLoading(button, isLoading) {
    button.classList.toggle('btn-loading', isLoading);
    button.disabled = isLoading;
  }

  /* ---------- Modals ---------- */
  function openModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal._returnFocus = document.activeElement;
    clearFeedback(modal);
    modal.hidden = false;
    shell.classList.remove('sidebar-open');
    if (id === 'transfer-modal') syncTransferForm();
    if (id === 'fund-wallet-modal') syncFundForm();
    var firstInput = modal.querySelector('input:not([type=hidden]), select');
    if (firstInput) firstInput.focus();
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.hidden = true;
    clearFeedback(modal);

    if (modal.id === 'pin-modal') {
      var pinForm = document.getElementById('form-pin');
      if (pinForm) pinForm.reset();
      if (pending) {
        var p = pending;
        pending = null;
        p.resolve(null);
      }
    }
    if (modal.id === 'wallet-details-modal') detailsWalletId = null;

    var back = modal._returnFocus;
    if (back && typeof back.focus === 'function') back.focus();
  }

  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-open-modal]');
    if (opener) { e.preventDefault(); openModal(opener.getAttribute('data-open-modal')); return; }
    var closer = e.target.closest('[data-close-modal]');
    if (closer) { closeModal(closer.closest('.modal-overlay')); return; }
    if (e.target.classList.contains('modal-overlay')) closeModal(e.target);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var pinModal = document.getElementById('pin-modal');
    if (pinModal && !pinModal.hidden) { closeModal(pinModal); return; }
    document.querySelectorAll('.modal-overlay').forEach(function (m) { if (!m.hidden) closeModal(m); });
  });

  /* ---------- PIN authorization (shared by every protected action) ----------   */
  function authorize(summary, run) {
    return new Promise(function (resolve) {
      pending = { run: run, resolve: resolve };
      var summaryEl = document.getElementById('pin-modal-summary');
      if (summaryEl) summaryEl.textContent = summary;
      var pinForm = document.getElementById('form-pin');
      if (pinForm) pinForm.reset();
      openModal('pin-modal');
    });
  }

  (function wirePinForm() {
    var pinForm = document.getElementById('form-pin');
    if (!pinForm) return;
    var pinInput = document.getElementById('pin-input');
    var pinSubmit = document.getElementById('pin-submit');
    var pinModal = document.getElementById('pin-modal');

    pinForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!pending) return;
      clearFeedback(pinForm);

      var pin = pinInput.value.trim();
      if (!/^\d{4,6}$/.test(pin)) { setFieldError('pin-input', 'Enter your 4–6 digit PIN'); return; }

      var current = pending;
      setLoading(pinSubmit, true);
      current.run(pin).then(function (res) {
        setLoading(pinSubmit, false);
        var pinRejected = !res.ok && res.body && res.body.errors && res.body.errors.pin;
        if (pinRejected) {
          showFailure(res, 'pin-banner', { pin: 'pin-input' });
          pinInput.value = '';
          pinInput.focus();
          return;
        }
        pending = null;
        closeModal(pinModal);
        current.resolve(res);
      }).catch(function (err) {
        console.error(err);
        setLoading(pinSubmit, false);
        showNetworkError('pin-banner');
      });
    });

    pinInput.addEventListener('input', function () {
      pinInput.value = pinInput.value.replace(/\D/g, '');
      setFieldError('pin-input', '');
    });
  })();

  /* ---------- Rendering ---------- */
  function renderHeader() {
    if (!state.user) return;
    var greeting = document.getElementById('topbar-greeting');
    if (greeting) greeting.textContent = 'Welcome back, ' + state.user.first_name;
    var initials = document.getElementById('avatar-initials');
    if (initials) initials.textContent = state.user.initials;
  }

  function renderWallets() {
    var totalEl = document.getElementById('total-balance-figure');
    if (totalEl) totalEl.textContent = money(state.totalBalance);

    var container = document.getElementById('wallet-cards-container');
    if (!container) return;

    if (state.wallets.length === 0) {
      container.innerHTML = '<p class="empty-note">You have no wallets yet. Use “+ Add wallet” to create your first one.</p>';
      return;
    }

    container.innerHTML = state.wallets.map(function (w, idx) {
      return '<div class="wallet-card' + (idx === 0 ? ' wallet-card-primary' : '') + '" data-wallet-id="' + esc(w.id) + '">' +
        '<div class="wallet-card-top">' +
          '<p class="wallet-card-label">' + esc(w.name.toUpperCase()) + '</p>' +
          '<div class="wallet-card-actions">' +
            '<button class="icon-btn-sm" title="Edit Wallet" data-action="edit-wallet" data-id="' + esc(w.id) + '">✏️</button>' +
            '<button class="icon-btn-sm" title="Delete Wallet" data-action="delete-wallet" data-id="' + esc(w.id) + '">🗑️</button>' +
          '</div>' +
        '</div>' +
        '<p class="wallet-card-number">' + money(w.balance) + '</p>' +
        '<p style="font-size:.78rem;color:var(--text-muted);margin:4px 0 0">Tag: ' + esc(w.tag) + '</p>' +
        '<div class="wallet-card-foot">' +
          '<span>Transactions: ' + w.tx_count + '</span>' +
          '<button class="link-btn" data-action="view-wallet" data-id="' + esc(w.id) + '">View details (Last 10)</button>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  function fillSelect(select, wallets, withBalance, keep) {
    if (!select) return;
    var previous = keep ? select.value : '';
    select.innerHTML = wallets.map(function (w) {
      return '<option value="' + esc(w.id) + '">' + esc(w.name) + (withBalance ? ' (' + money(w.balance) + ')' : '') + '</option>';
    }).join('');
    if (previous && wallets.some(function (w) { return w.id === previous; })) select.value = previous;
  }

  function renderDropdowns() {
    fillSelect(document.getElementById('transfer-from-wallet'), state.wallets, true, true);
    syncFundForm();
    syncTransferForm();
  }

  function syncFundForm() {
    var src = document.getElementById('fund-source');
    var dest = document.getElementById('fund-wallet-select');
    if (!src || !dest) return;

    var previous = src.value;
    src.innerHTML = '<option value="external">Outside Thorfinn (bank / card deposit)</option>' +
      state.wallets.map(function (w) {
        return '<option value="' + esc(w.id) + '">' + esc(w.name) + ' (' + money(w.balance) + ')</option>';
      }).join('');
    if (previous && (previous === 'external' || state.wallets.some(function (w) { return w.id === previous; }))) {
      src.value = previous;
    }

    fillSelect(dest, state.wallets.filter(function (w) { return w.id !== src.value; }), true, true);
  }

  /** "To wallet" */
  function syncTransferForm() {
    var isInternal = document.getElementById('transfer-type').value === 'internal';
    var fromId = document.getElementById('transfer-from-wallet').value;
    fillSelect(document.getElementById('transfer-to-wallet'),
      state.wallets.filter(function (w) { return w.id !== fromId; }), false, true);
    document.getElementById('destination-field-internal').hidden = !isInternal;
    document.getElementById('destination-field-external').hidden = isInternal;
  }

  function txRowHtml(tx) {
    var isIn = tx.direction === 'in';
    var arrow = isIn
      ? '<path d="M17 7 L7 17 M7 9 V17 H15" stroke-linecap="round" stroke-linejoin="round"/>'
      : '<path d="M7 17 L17 7 M9 7 H17 V15" stroke-linecap="round" stroke-linejoin="round"/>';
    return '<li class="tx-row">' +
      '<span class="tx-icon ' + (isIn ? 'tx-icon-in' : 'tx-icon-out') + '"><svg viewBox="0 0 24 24" width="16" height="16">' + arrow + '</svg></span>' +
      '<span class="tx-info"><span class="tx-name">' + esc(tx.description) + '</span>' +
        '<span class="tx-meta">' + esc(tx.wallet_name || 'Wallet') + ' · ' + esc(formatWhen(tx.created_at)) + '</span></span>' +
      '<span class="tx-amount ' + (isIn ? 'tx-amount-in' : 'tx-amount-out') + '">' + (isIn ? '+' : '-') + money(tx.amount) + '</span>' +
    '</li>';
  }

  function renderTxList(list, transactions, emptyMessage) {
    if (!list) return;
    if (!transactions || transactions.length === 0) {
      list.innerHTML = '<li class="empty-note">' + esc(emptyMessage) + '</li>';
      return;
    }
    list.innerHTML = transactions.map(txRowHtml).join('');
  }

  function renderTransactions() {
    var list = document.getElementById('tx-list');
    var label = document.getElementById('active-wallet-label');
    if (!list) return;

    var active = state.activeWalletFilter
      ? state.wallets.find(function (w) { return w.id === state.activeWalletFilter; }) : null;
    if (label) {
      label.textContent = active ? active.name + ' · Show all' : 'All Wallets';
      label.classList.toggle('is-filtered', !!active);
      label.style.cursor = active ? 'pointer' : 'default';
    }

    renderTxList(list, state.transactions, 'No transactions recorded yet for this view.');
  }

  function renderAll() {
    renderHeader();
    renderWallets();
    renderDropdowns();
    renderTransactions();
  }

  /* ---------- Data loading ---------- */
  function loadDashboard() {
    return api('dashboard').then(function (res) {
      if (!res.ok) {
        var list = document.getElementById('tx-list');
        var wallets = document.getElementById('wallet-cards-container');
        var msg = '<p class="empty-note">' + esc(res.body.message) + '</p>';
        if (wallets) wallets.innerHTML = msg;
        if (list) list.innerHTML = '<li class="empty-note">' + esc(res.body.message) + '</li>';
        return;
      }
      var d = res.body.data;
      state.user = d.user;
      state.wallets = d.wallets;
      state.totalBalance = d.total_balance;
      state.transactions = d.transactions;

      if (state.activeWalletFilter && !state.wallets.some(function (w) { return w.id === state.activeWalletFilter; })) {
        state.activeWalletFilter = null;
      }
      if (state.activeWalletFilter) return loadTransactions().then(renderAll);
      renderAll();
    }).catch(function (err) {
      console.error(err);
      var wallets = document.getElementById('wallet-cards-container');
      if (wallets) wallets.innerHTML = '<p class="empty-note">Could not load your data. Check your connection and refresh.</p>';
    });
  }

  function loadTransactions() {
    return api('transactions', { params: { wallet_id: state.activeWalletFilter } }).then(function (res) {
      if (res.ok) state.transactions = res.body.data.transactions;
    });
  }

  /* ---------- Wallet details modal ---------- */
  function openWalletDetails(w) {
    detailsWalletId = w.id;

    document.getElementById('wd-balance').textContent = money(w.balance);
    document.getElementById('wd-tag').textContent = w.tag;
    document.getElementById('wd-count').textContent = w.tx_count;

    var createdBox = document.getElementById('wd-created-box');
    var createdDate = w.created_at ? new Date(w.created_at) : null;
    if (createdDate && !isNaN(createdDate)) {
      document.getElementById('wd-created').textContent =
        createdDate.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
      createdBox.hidden = false;
    } else {
      createdBox.hidden = true;
    }

    var list = document.getElementById('wd-tx-list');
    list.innerHTML = '<li class="empty-note">Loading transactions…</li>';
    openModal('wallet-details-modal');

    api('transactions-details', { params: { wallet_id: w.id } }).then(function (res) {
      if (detailsWalletId !== w.id) return;
      if (!res.ok) {
        list.innerHTML = '<li class="empty-note">' + esc(res.body.message || 'Could not load transactions.') + '</li>';
        return;
      }
      renderTxList(list, res.body.data.transactions, 'No transactions recorded yet for this wallet.');
    }).catch(function (err) {
      console.error(err);
      if (detailsWalletId === w.id) list.innerHTML = '<li class="empty-note">A network error occurred. Please try again.</li>';
    });
  }

  var wdFilterBtn = document.getElementById('wd-filter');
  if (wdFilterBtn) {
    wdFilterBtn.addEventListener('click', function () {
      if (!detailsWalletId) return;
      state.activeWalletFilter = detailsWalletId;
      closeModal(document.getElementById('wallet-details-modal'));
      loadTransactions().then(renderTransactions);
      document.getElementById('transactions').scrollIntoView({ behavior: 'smooth' });
    });
  }

  /* ---------- Wallet card actions (view / edit / delete) ---------- */
  document.addEventListener('click', function (e) {
    var actionBtn = e.target.closest('[data-action]');
    if (!actionBtn) return;
    var action = actionBtn.getAttribute('data-action');
    var id = actionBtn.getAttribute('data-id');
    var w = state.wallets.find(function (item) { return item.id === id; });
    if (!w) return;

    if (action === 'view-wallet') {
      openWalletDetails(w);
    } else if (action === 'edit-wallet') {
      document.getElementById('edit-wallet-id').value = w.id;
      document.getElementById('edit-wallet-name-input').value = w.name;
      openModal('edit-wallet-modal');
    } else if (action === 'delete-wallet') {
      if (w.tx_count > 0) {
        alert('Cannot delete "' + w.name + '" because it has ' + w.tx_count + ' transaction(s). Wallets must have 0 transactions before deletion.');
        return;
      }
      if (!confirm('Are you sure you want to delete the empty wallet "' + w.name + '"?')) return;

      authorize('Enter your PIN to delete the wallet "' + w.name + '".', function (pin) {
        return api('delete-wallet', { body: { id: id, pin: pin } });
      }).then(function (res) {
        if (!res) return;
        if (!res.ok) { alert(res.body.message); return; }
        loadDashboard();
      }).catch(function () { alert('A network error occurred. Please try again.'); });
    }
  });

  var filterLabel = document.getElementById('active-wallet-label');
  if (filterLabel) {
    filterLabel.addEventListener('click', function () {
      if (!state.activeWalletFilter) return;
      state.activeWalletFilter = null;
      loadDashboard();
    });
  }

  /* ---------- Generic form submit  ---------- */
  function wireForm(opts) {
    var form = document.getElementById(opts.formId);
    if (!form) return;
    var submitBtn = document.getElementById(opts.submitId);
    var modal = form.closest('.modal-overlay');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearFeedback(form);
      var payload = opts.validate();
      if (!payload) return;

      setLoading(submitBtn, true);
      var request = opts.send ? opts.send(payload) : api(opts.action, { body: payload });

      request.then(function (res) {
        setLoading(submitBtn, false);
        if (!res) return;
        if (!res.ok) { showFailure(res, opts.bannerId, opts.fieldMap); return; }
        form.reset();
        if (opts.afterReset) opts.afterReset();
        showBanner(opts.bannerId, res.body.message, 'success');
        loadDashboard();
        setTimeout(function () { if (!modal.hidden) closeModal(modal); }, 1000);
      }).catch(function (err) {
        console.error(err);
        setLoading(submitBtn, false);
        showNetworkError(opts.bannerId);
      });
    });

    form.querySelectorAll('input, select').forEach(function (input) {
      input.addEventListener('input', function () { if (input.id) setFieldError(input.id, ''); });
    });
  }

  /* ---------- Add wallet ---------- */
  wireForm({
    formId: 'form-add-wallet', submitId: 'add-wallet-submit', bannerId: 'add-wallet-banner',
    action: 'add-wallet', fieldMap: { wallet_name: 'wallet-name-input' },
    validate: function () {
      var name = document.getElementById('wallet-name-input').value.trim();
      if (!name) { setFieldError('wallet-name-input', 'Enter a valid wallet name'); return null; }
      return { wallet_name: name };
    }
  });

  /* ---------- Edit wallet ---------- */
  wireForm({
    formId: 'form-edit-wallet', submitId: 'edit-wallet-submit', bannerId: 'edit-wallet-banner',
    action: 'edit-wallet', fieldMap: { name: 'edit-wallet-name-input', wallet_name: 'edit-wallet-name-input' },
    validate: function () {
      var name = document.getElementById('edit-wallet-name-input').value.trim();
      if (!name) { setFieldError('edit-wallet-name-input', 'Enter a wallet name'); return null; }
      return { id: document.getElementById('edit-wallet-id').value, wallet_name: name };
    }
  });

  /* ---------- Fund wallet ---------- */
  var fundSource = document.getElementById('fund-source');
  if (fundSource) fundSource.addEventListener('change', syncFundForm);

  wireForm({
    formId: 'form-fund-wallet', submitId: 'fund-wallet-submit', bannerId: 'fund-wallet-banner',
    action: 'fund-wallet',
    fieldMap: {
      amount: 'fund-amount',
      sender_wallet_id: 'fund-source',
      beneficiary_wallet_id: 'fund-wallet-select',
      wallet_id: 'fund-wallet-select'
    },
    afterReset: syncFundForm,
    validate: function () {
      var source = document.getElementById('fund-source').value;
      var beneficiaryId = document.getElementById('fund-wallet-select').value;
      var amount = document.getElementById('fund-amount').value;
      var ok = true;

      if (!source) { setFieldError('fund-source', 'Select funding wallet '); ok = false; }
      if (!beneficiaryId) { setFieldError('fund-wallet-select', state.wallets.length ? 'Select beneficiary wallet' : 'Create a wallet first'); ok = false; }
      if (!isValidAmount(amount)) { setFieldError('fund-amount', 'Enter a valid  amount'); ok = false; }
      else if (source && source !== 'external') {
        var src = state.wallets.find(function (w) { return w.id === source; });
        if (src && src.balance < parseFloat(amount)) { setFieldError('fund-amount', 'Insufficient balance in the source wallet'); ok = false; }
      }
      if (!ok) return null;

      return {
        funding_source: source === 'external' ? 'external' : 'wallet',
        sender_wallet_id: source === 'external' ? null : source,
        beneficiary_wallet_id: beneficiaryId,
        amount: amount.trim()
      };
    },
    send: function (payload) {
      var to = state.wallets.find(function (w) { return w.id === payload.beneficiary_wallet_id; });
      var from = payload.sender_wallet_id
        ? state.wallets.find(function (w) { return w.id === payload.sender_wallet_id; })
        : null;
      var summary = 'Enter your PIN to fund ' + (to ? to.name : 'this wallet') + ' with ' + money(payload.amount) +
        ' from ' + (from ? from.name : 'an outside source') + '.';
      return authorize(summary, function (pin) {
        payload.pin = pin;
        return api('fund-wallet', { body: payload });
      });
    }
  });

  /* ---------- Transfer ---------- */
  function validateInternalTransfer() {
    var from = document.getElementById('transfer-from-wallet').value;
    var to = document.getElementById('transfer-to-wallet').value;
    var amount = document.getElementById('transfer-amount').value;
    var ok = true;

    if (!from) { setFieldError('transfer-from-wallet', 'Select a source wallet'); ok = false; }
    if (!to) { setFieldError('transfer-to-wallet', 'You need a second wallet to transfer between your own wallets'); ok = false; }
    if (from && to && from === to) { setFieldError('transfer-to-wallet', 'Cannot transfer to the same wallet'); ok = false; }

    if (!isValidAmount(amount)) { 
      setFieldError('transfer-amount', 'Enter a valid amount'); 
      ok = false; 
    } else {
      var src = state.wallets.find(function (w) { return w.id === from; });
      if (src && src.balance < parseFloat(amount)) { 
        setFieldError('transfer-amount', 'Insufficient balance in source wallet'); 
        ok = false; 
      }
    }

    if (!ok) return null;

    var payload = {
      type: 'internal',
      from_wallet: from,
      to_wallet: to,
      amount: amount.trim()
    };

    console.log('Validated Internal Transfer Payload:', payload);
    return payload;
  }

  // 2. Logic & Validation for External Transfer (Different User)
  function validateExternalTransfer() {
    var from = document.getElementById('transfer-from-wallet').value;
    var recipient = document.getElementById('transfer-external-recipient').value.trim();
    var amount = document.getElementById('transfer-amount').value;
    var ok = true;

    if (!from) { setFieldError('transfer-from-wallet', 'Select a source wallet'); ok = false; }
    if (!recipient) { setFieldError('transfer-external-recipient', 'Enter a recipient wallet tag or email'); ok = false; }

    if (!isValidAmount(amount)) { 
      setFieldError('transfer-amount', 'Enter a valid amount'); 
      ok = false; 
    } else {
      var src = state.wallets.find(function (w) { return w.id === from; });
      if (src && src.balance < parseFloat(amount)) { 
        setFieldError('transfer-amount', 'Insufficient balance in source wallet'); 
        ok = false; 
      }
    }

    if (!ok) return null;

    var payload = {
      type: 'external',
      from_wallet: from,
      recipient: recipient,
      amount: amount.trim()
    };

    console.log('Validated External Transfer Payload:', payload);
    return payload;
  }

  /* ---------- Wire Transfer Form ---------- */
  function validateInternalTransfer() {
    var from = document.getElementById('transfer-from-wallet').value;
    var to = document.getElementById('transfer-to-wallet').value;
    var amount = document.getElementById('transfer-amount').value;
    var ok = true;

    if (!from) { setFieldError('transfer-from-wallet', 'Select a source wallet'); ok = false; }
    if (!to) { setFieldError('transfer-to-wallet', 'You need a second wallet to transfer between your own wallets'); ok = false; }
    if (from && to && from === to) { setFieldError('transfer-to-wallet', 'Cannot transfer to the same wallet'); ok = false; }

    if (!isValidAmount(amount)) { 
      setFieldError('transfer-amount', 'Enter a valid amount'); 
      ok = false; 
    } else {
      var src = state.wallets.find(function (w) { return w.id === from; });
      if (src && src.balance < parseFloat(amount)) { 
        setFieldError('transfer-amount', 'Insufficient balance in source wallet'); 
        ok = false; 
      }
    }

    if (!ok) return null;

    var payload = {
      type: 'internal',
      from_wallet: from,
      to_wallet: to,
      amount: amount.trim()
    };

    console.log('Validated Internal Transfer Payload:', payload);
    return payload;
  }

  // 2. Validation for External Transfer (Different User)
  function validateExternalTransfer() {
    var from = document.getElementById('transfer-from-wallet').value;
    var recipient = document.getElementById('transfer-external-recipient').value.trim();
    var amount = document.getElementById('transfer-amount').value;
    var ok = true;

    if (!from) { setFieldError('transfer-from-wallet', 'Select a source wallet'); ok = false; }
    if (!recipient) { setFieldError('transfer-external-recipient', 'Enter a recipient wallet tag or email'); ok = false; }

    if (!isValidAmount(amount)) { 
      setFieldError('transfer-amount', 'Enter a valid amount'); 
      ok = false; 
    } else {
      var src = state.wallets.find(function (w) { return w.id === from; });
      if (src && src.balance < parseFloat(amount)) { 
        setFieldError('transfer-amount', 'Insufficient balance in source wallet'); 
        ok = false; 
      }
    }

    if (!ok) return null;

    var payload = {
      type: 'external',
      from_wallet: from,
      recipient: recipient,
      amount: amount.trim()
    };

    console.log('Validated External Transfer Payload:', payload);
    return payload;
  }

  /* ---------- Wire Transfer Form with Separate API Calls ---------- */
  ['transfer-type', 'transfer-from-wallet'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('change', syncTransferForm);
  });

  wireForm({
    formId: 'form-transfer', 
    submitId: 'transfer-submit', 
    bannerId: 'transfer-banner',
    action: 'transfer',
    fieldMap: {
      amount: 'transfer-amount', 
      recipient: 'transfer-external-recipient',
      from_wallet: 'transfer-from-wallet', 
      to_wallet: 'transfer-to-wallet'
    },
    afterReset: syncTransferForm,
    
    validate: function () {
      var type = document.getElementById('transfer-type').value;
      if (type === 'internal') {
        return validateInternalTransfer();
      } else {
        return validateExternalTransfer();
      }
    },
   
    send: function (payload) {
      if (payload.type === 'internal') {
        var targetWallet = state.wallets.find(function (w) { return w.id === payload.to_wallet; });
        var targetName = targetWallet ? targetWallet.name : 'destination wallet';
        var summary = 'Enter your PIN to transfer ' + money(payload.amount) + ' to your "' + targetName + '".';

        return authorize(summary, function (pin) {
          payload.pin = pin;
          
          return api('transfer-internal', { body: payload }).then(function (res) {
            console.log('Internal Transfer API Response:', res);
            return res;
          });
        });

      } else {
        var summary = 'Enter your PIN to send ' + money(payload.amount) + ' to ' + payload.recipient + '.';

        return authorize(summary, function (pin) {
          payload.pin = pin;
          
          return api('transfer-external', { body: payload }).then(function (res) {
            console.log('External Transfer API Response:', res);
            return res;
          });
        });
      }
    }
  });


  /* ---------- Change password ---------- */
  var passwordChangeStep = 1;
  var changePasswordForm = document.getElementById('form-change-password');

  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', function (e) {
      e.preventDefault();
      clearFeedback(changePasswordForm);
      var bannerId = 'settings-banner';
      var submitBtn = document.getElementById('settings-submit');

      if (passwordChangeStep === 1) {
        var cur = document.getElementById('current-password').value;
        var pw = document.getElementById('new-password').value;
        var conf = document.getElementById('confirm-password').value;
        var ok = true;

        if (!cur) { setFieldError('current-password', 'Enter current password'); ok = false; }
        if (pw.length < 8) { setFieldError('new-password', 'Password must be at least 8 characters'); ok = false; }
        if (pw !== conf) { setFieldError('confirm-password', 'Passwords do not match'); ok = false; }
        if (cur === pw && ok) { setFieldError('new-password', 'New password must be different from current password'); ok = false; }
        if (!ok) return;

        var payload = { current_password: cur, new_password: pw, confirm_password: conf };
        setLoading(submitBtn, true);

        api('change-password', { body: payload }).then(function (res) {
          setLoading(submitBtn, false);
          if (!res.ok) {
            showFailure(res, bannerId, { current_password: 'current-password', new_password: 'new-password', confirm_password: 'confirm-password' });
            return;
          }

          passwordChangeStep = 2;
          document.getElementById('password-step-1').hidden = true;
          document.getElementById('password-step-2').hidden = false;
          document.getElementById('settings-submit-label').textContent = 'Confirm & Update Password';
          showBanner(bannerId, res.body.message || 'Verification token sent to your email.', 'success');
        }).catch(function (err) {
          console.error(err);
          setLoading(submitBtn, false);
          showNetworkError(bannerId);
        });

      } else {
        var token = document.getElementById('password-token').value.trim();
        var pw = document.getElementById('new-password').value;
        if (!token) { setFieldError('password-token', 'Enter the verification token'); return; }

        var payload = { token: token, new_password: pw };
        setLoading(submitBtn, true);

        api('confirm-change-password', { body: payload }).then(function (res) {
          setLoading(submitBtn, false);
          if (!res.ok) {
            showFailure(res, bannerId, { token: 'password-token' });
            return;
          }
          showBanner(bannerId, res.body.message || 'Password successfully updated!', 'success');
          setTimeout(function () {
            localStorage.removeItem('jwt_token');
            window.location.href = 'auth.html'; 
          }, 1500);
        }).catch(function (err) {
          console.error(err);
          setLoading(submitBtn, false);
          showNetworkError(bannerId);
        });
      }
    });
  }

  function resetPasswordModalState() {
    passwordChangeStep = 1;
    var step1 = document.getElementById('password-step-1');
    var step2 = document.getElementById('password-step-2');
    var submitLabel = document.getElementById('settings-submit-label');
    if (step1) step1.hidden = false;
    if (step2) step2.hidden = true;
    if (submitLabel) submitLabel.textContent = 'Verify Current Password';
  }


  loadDashboard();

})();