(function () {
  'use strict';

  /* ---------- View routing ---------- */

  var views = ['login', 'signup', 'forgot', 'token', 'reset', 'success'];

  var TOKEN_RE = /^(?=.*[A-Z])(?=.*[0-9])[A-Z0-9]{6}$/;
  var RESEND_COOLDOWN_S = 120;          

  var resetFlow = { email: '', expiresAt: 0, isLogin2FA: false};
  var resendTimer = null;
  var redirectTimer = null;

  function startResendCooldown(seconds) {
    var btn = document.getElementById('token-resend');
    if (!btn) return;
    var remaining = seconds;
    clearInterval(resendTimer);
    btn.disabled = true;
    btn.textContent = 'Resend token in ' + remaining + 's';
    resendTimer = setInterval(function () {
      remaining--;
      if (remaining <= 0) {
        clearInterval(resendTimer);
        resendTimer = null;
        btn.disabled = false;
        btn.textContent = 'Resend token';
      } else {
        btn.textContent = 'Resend token in ' + remaining + 's';
      }
    }, 1000);
  }

  function clearResetFlow() {
    resetFlow = { email: '', expiresAt: 0 };
    clearInterval(resendTimer);
    resendTimer = null;
    var tokenField = document.getElementById('token-input');
    if (tokenField) tokenField.value = '';
  }
  var brandCopy = {
    login:   { heading: "Your keys.<br>Your voyage.", sub: "No custodian holds your assets. No gatekeeper approves your moves. Just you, and where you're headed." },
    signup:  { heading: "Chart your<br>own course.", sub: "Creating a wallet takes about a minute. Your recovery phrase is generated on this device and never leaves it unencrypted." },
    forgot:  { heading: "Lost your<br>bearings?", sub: "We'll help you get back to your wallet. Your keys stay exactly where they've always been — with you." },
    sent:    { heading: "Lost your<br>bearings?", sub: "We'll help you get back to your wallet. Your keys stay exactly where they've always been — with you." },
    reset:   { heading: "A fresh<br>password.", sub: "Choose a strong, unique password to keep your account secured." },
    success: { heading: "Land ho.", sub: "Welcome aboard." }
  };

  function showView(name, opts) {
    opts = opts || {};
    views.forEach(function (v) {
      var el = document.getElementById('view-' + v);
      if (!el) return;
      if (v === name) {
        el.hidden = false;
        el.classList.remove('view');
        void el.offsetWidth; 
        el.classList.add('view');
      } else {
        el.hidden = true;
      }
    });

    var copy = brandCopy[name] || brandCopy.login;
    var headingEl = document.getElementById('brand-heading');
    var subEl = document.getElementById('brand-subheading');
    if (headingEl) headingEl.innerHTML = copy.heading;
    if (subEl) subEl.textContent = copy.sub;

    if (name === 'sent' && opts.email) {
      var echo = document.getElementById('sent-email-echo');
      if (echo) echo.textContent = opts.email;
    }

    if (name === 'success') {
      var h = document.getElementById('success-heading');
      var s = document.getElementById('success-sub');
      if (opts.successHeading && h) h.textContent = opts.successHeading;
      if (opts.successSub && s) s.textContent = opts.successSub;
    }

    var url = new URL(window.location.href);
    url.searchParams.set('view', name);
    window.history.replaceState({}, '', url);
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-nav]');
    if (!trigger) return;
    e.preventDefault();
    showView(trigger.getAttribute('data-nav'));
  });

  var initialView = new URLSearchParams(window.location.search).get('view');
  showView(views.indexOf(initialView) !== -1 ? initialView : 'login');

  /* ---------- Show / hide password ---------- */

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-toggle-password]');
    if (!btn) return;
    var input = document.getElementById(btn.getAttribute('data-toggle-password'));
    if (!input) return;
    var isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.textContent = isHidden ? 'Hide' : 'Show';
  });

  /* ---------- Validation helpers ---------- */

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function setFieldError(fieldId, message) {
    var input = document.getElementById(fieldId);
    var field = input ? input.closest('.field') : null;
    var errorEl = document.querySelector('[data-error-for="' + fieldId + '"]');
    if (message) {
      if (field) { field.classList.add('field-invalid'); field.classList.remove('field-valid'); }
      if (errorEl) { errorEl.textContent = message; errorEl.classList.add('visible'); }
      return false;
    } else {
      if (field) { field.classList.remove('field-invalid'); field.classList.add('field-valid'); }
      if (errorEl) { errorEl.textContent = ''; errorEl.classList.remove('visible'); }
      return true;
    }
  }

  function clearFieldState(fieldId) {
    var input = document.getElementById(fieldId);
    var field = input ? input.closest('.field') : null;
    var errorEl = document.querySelector('[data-error-for="' + fieldId + '"]');
    if (field) { field.classList.remove('field-invalid', 'field-valid'); }
    if (errorEl) { errorEl.textContent = ''; errorEl.classList.remove('visible'); }
  }

  function showBanner(bannerId, message, tone) {
    var el = document.getElementById(bannerId);
    if (!el) return;
    el.textContent = message;
    el.hidden = !message;
    el.classList.toggle('banner-locked', tone === 'locked');
  }

  function passwordScore(pw) {
    var score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
    if (/\d/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    if (pw.length < 8) score = Math.min(score, 1);
    return score; // 0-4
  }

  var strengthWords = ['Too weak', 'Weak — add a number or symbol', 'Okay — try adding a symbol', 'Strong', 'Very strong'];

  function updateStrengthMeter(meterEl, labelEl, pw) {
    var score = passwordScore(pw);
    meterEl.className = 'strength-meter' + (pw ? ' s' + score : '');
    labelEl.textContent = pw ? strengthWords[score] : 'Use 8+ characters with a number and a symbol';
    return score;
  }

  function setLoading(button, isLoading) {
    button.classList.toggle('btn-loading', isLoading);
    button.disabled = isLoading;
  }

/* ---------- Login ---------- */
  var API = '/src/Router/Router.php';   
  var csrfToken = null;

  var loginForm = document.getElementById('form-login');
  var loginLocked = false;

  function parseJsonSafe(res) {
    return res.text().then(function (text) {
      try {
        return { httpStatus: res.status, data: JSON.parse(text) };
      } catch (e) {
        console.error('Non-JSON response from', res.url, '\n', text.slice(0, 300));
        throw new Error('Server did not return JSON');
      }
    });
  }

  function loadCsrf() {
    return fetch(API + '?action=csrf-token', { credentials: 'same-origin' })
      .then(parseJsonSafe)
      .then(function (r) {
        if (!r.data || !r.data.csrf_token) throw new Error('No csrf_token in response');
        csrfToken = r.data.csrf_token;
        return csrfToken;
      });
  }
  loadCsrf().catch(function (e) { console.error('CSRF load failed', e); });

  loginForm.addEventListener('submit', function (e) {
    e.preventDefault();
    if (loginLocked) return;

    var email = document.getElementById('login-email');
    var password = document.getElementById('login-password');
    var submit = document.getElementById('login-submit');

    var emailOk = EMAIL_RE.test(email.value.trim())
      ? setFieldError('login-email', '')
      : setFieldError('login-email', 'Enter a valid email address');
    var passOk = password.value.length > 0
      ? setFieldError('login-password', '')
      : setFieldError('login-password', 'Enter your password');

    showBanner('login-banner', '');
    if (!emailOk || !passOk) return;

    setLoading(submit, true);
    
    (csrfToken ? Promise.resolve(csrfToken) : loadCsrf())
      .then(function () {
        return fetch(API + '?action=login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({
            email: email.value.trim(),
            password: password.value,
            csrf_token: csrfToken
          })
        });
      })
      .then(parseJsonSafe)
      .then(function (result) {
        var data = result.data;
        
        if (data.status === 'success' && data.requires_2fa) {
          resetFlow.email = email.value.trim();
          resetFlow.isLogin2FA = true; 
          showView('token'); 
          return;
        }

        if (result.httpStatus === 403 && /session expired/i.test(data.message || '')) {
          csrfToken = null;
          loadCsrf().catch(function (e) { console.error(e); });
        }

        if (data.status === 'unverified') {
          var bannerEl = document.getElementById('login-banner');
          bannerEl.innerHTML = ''; 
          
          var textSpan = document.createElement('span');
          textSpan.textContent = data.message + ' ';
          
          var resendLink = document.createElement('a');
          resendLink.href = '#';
          resendLink.textContent = 'Resend verification link';
          resendLink.style.textDecoration = 'underline';
          resendLink.style.color = 'inherit';
          
          resendLink.addEventListener('click', function (ev) {
            ev.preventDefault();
            resendLink.style.pointerEvents = 'none';
            resendLink.textContent = 'Sending...';

            fetch(API + '?action=resend-verification', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ email: email.value.trim() })
            })
              .then(parseJsonSafe)
              .then(function (res) {
                showBanner('login-banner', res.data.message || 'Verification link sent successfully. Check your email.');
              })
              .catch(function () {
                showBanner('login-banner', 'Failed to resend verification link. Try again later.');
              });
          });

          bannerEl.appendChild(textSpan);
          bannerEl.appendChild(resendLink);
          bannerEl.hidden = false;
          return;
        }
        if (data.status === 'locked') {
          loginLocked = true;
          showBanner('login-banner', data.message, 'locked');
          loginForm.querySelectorAll('input, button').forEach(function (el) { el.disabled = true; });
          setTimeout(function () {
            loginLocked = false;
            loginForm.querySelectorAll('input, button').forEach(function (el) { el.disabled = false; });
            showBanner('login-banner', '');
          }, 30000);
          return;
        }

        showBanner('login-banner', data.message || 'Incorrect email or password.');
      })
      .catch(function (err) {
        console.error(err);
        showBanner('login-banner', 'Something went wrong. Please try again.');
      })
      .then(function () {
        setLoading(submit, false);
      });
  });

  ['login-email', 'login-password'].forEach(function (id) {
    document.getElementById(id).addEventListener('input', function () {
      clearFieldState(id);
      showBanner('login-banner', '');
    });
  });


 /* ---------- Signup ---------- */

var signupForm = document.getElementById('form-signup');
var signupMeter = signupForm.querySelector('.strength-meter');
var signupStrengthLabel = document.getElementById('signup-strength-label');
var signupScore = 0;

document.getElementById('signup-password').addEventListener('input', function (e) {
  signupScore = updateStrengthMeter(signupMeter, signupStrengthLabel, e.target.value);
  clearFieldState('signup-password');
});

signupForm.addEventListener('submit', function (e) {
  e.preventDefault();

  var name = document.getElementById('signup-name');
  var email = document.getElementById('signup-email');
  var password = document.getElementById('signup-password');
  var confirm = document.getElementById('signup-confirm');
  var pin = document.getElementById('signup-pin');
  var pinConfirm = document.getElementById('signup-pin-confirm');
  var terms = document.getElementById('signup-terms');
  var submit = document.getElementById('signup-submit');

  var PIN_RE = /^\d{6}$/;

  var nameOk = name.value.trim().length >= 2
    ? setFieldError('signup-name', '')
    : setFieldError('signup-name', 'Enter your name');

  var emailOk = EMAIL_RE.test(email.value.trim())
    ? setFieldError('signup-email', '')
    : setFieldError('signup-email', 'Enter a valid email address');

  var passOk = passwordScore(password.value) >= 2
    ? setFieldError('signup-password', '')
    : setFieldError('signup-password', 'Password is too weak');

  var confirmOk = confirm.value.length > 0 && confirm.value === password.value
    ? setFieldError('signup-confirm', '')
    : setFieldError('signup-confirm', 'Passwords don\'t match');
    
  var pinOk = PIN_RE.test(pin.value)
    ? setFieldError('signup-pin', '')
    : setFieldError('signup-pin', 'Enter a 6-digit PIN');

  var pinConfirmOk = pinConfirm.value.length > 0 && pinConfirm.value === pin.value
    ? setFieldError('signup-pin-confirm', '')
    : setFieldError('signup-pin-confirm', 'PINs don\'t match');

  var termsOk = terms.checked
    ? setFieldError('signup-terms', '')
    : setFieldError('signup-terms', 'You must accept this to continue');

  showBanner('signup-banner', '');
  if (!nameOk || !emailOk || !passOk || !confirmOk || !pinOk || !pinConfirmOk || !termsOk) return;
  setLoading(submit, true);

  fetch('/src/Router/Router.php?action=register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      full_name: name.value.trim(),
      email: email.value.trim(),
      password: password.value,
      confirm_password: confirm.value,
      wallet_transaction_pin: pin.value
    })
  })
    .then(function (res) {
      return res.json().then(function (data) {
        return { httpStatus: res.status, data: data };
      });
    })
    .then(function (result) {
      setLoading(submit, false);
      var data = result.data;

      if (data.status === 'success') {
        showView('success', {
          successHeading: 'Wallet created',
          successSub: 'A link has been sent to your email for email verification.'
        });
        return;
      }

      if (result.httpStatus === 409) {
        setFieldError('signup-email', data.message || 'An account with that email already exists');
          return;
      }

        showBanner('signup-banner', data.message || 'Something went wrong. Please try again.');
      })
      .catch(function () {
        setLoading(submit, false);
        showBanner('signup-banner', 'Something went wrong. Please try again.');
      });
  });

  ['signup-name', 'signup-email', 'signup-confirm', 'signup-pin', 'signup-pin-confirm'].forEach(function (id) {
  document.getElementById(id).addEventListener('input', function () { clearFieldState(id); });
  });

/* ---------- Forgot password ---------- */

var forgotForm = document.getElementById('form-forgot');

  forgotForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var email = document.getElementById('forgot-email');
    var submit = document.getElementById('forgot-submit');

    var emailOk = EMAIL_RE.test(email.value.trim())
      ? setFieldError('forgot-email', '')
      : setFieldError('forgot-email', 'Enter a valid email address');

    showBanner('forgot-banner', '');
    if (!emailOk) return;

    setLoading(submit, true);

    fetch(API + '?action=forgot-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email.value.trim() })
    })
      .then(parseJsonSafe)
      .then(function (result) {
        setLoading(submit, false);
        var data = result.data;

        resetFlow.email = email.value.trim();
        resetFlow.expiresAt = data.expires_at ? data.expires_at : (Date.now() + (10 * 60 * 1000));

        startResendCooldown(RESEND_COOLDOWN_S);
        showView('token');
      })
      .catch(function () {
        setLoading(submit, false);
        showBanner('forgot-banner', 'Something went wrong. Please try again.');
      });
  });

  document.getElementById('forgot-email').addEventListener('input', function () {
    clearFieldState('forgot-email');
    showBanner('forgot-banner', '');
  });

  /* ---------- Token verification ---------- */

  var tokenForm = document.getElementById('form-token');
  var tokenInput = document.getElementById('token-input');

  tokenInput.addEventListener('input', function (e) {
    e.target.value = e.target.value
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, '')
      .slice(0, 6);
  
    clearFieldState('token-input');
    showBanner('token-banner', '');
  });
  
  tokenForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var submit = document.getElementById('token-submit');

    showBanner('token-banner', '');

    var formatOk = TOKEN_RE.test(tokenInput.value)
      ? setFieldError('token-input', '')
      : setFieldError('token-input', 'Enter the 6-digit token');
    if (!formatOk) return;

    if (resetFlow.expiresAt && Date.now() > resetFlow.expiresAt) {
      showBanner('token-banner', 'This token has expired. Request a new one.');
      return;
    }

    setLoading(submit, true);

    
    var endpoint = resetFlow.isLogin2FA ? '?action=verify-login' : '?action=verify-token';

    fetch(API + endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({
        email: resetFlow.email,
        token: tokenInput.value
      })
    })
      .then(parseJsonSafe)
      .then(function (result) {
        setLoading(submit, false);
        var data = result.data;

        if (data.status === 'success') {
          if (resetFlow.isLogin2FA) {
            if (data.token) {
              localStorage.setItem('jwt_token', data.token);
            }
            if (data.user) {
              localStorage.setItem('user', JSON.stringify(data.user));
            }

            window.location.href = 'dashboard.html';
          } else {
            resetFlow.token = tokenInput.value;
            showView('reset');
          }
          return;
        }

        showBanner('token-banner', data.message || 'Incorrect or expired token.');
      })
      .catch(function () {
        setLoading(submit, false);
        showBanner('token-banner', 'Something went wrong. Please try again.');
      });
  });

  document.getElementById('token-resend').addEventListener('click', function () {
    if (!resetFlow.email || resendTimer) return;

    var actionUrl = resetFlow.isLogin2FA ? '?action=login' : '?action=forgot-password';

    fetch(API + actionUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: resetFlow.email })
    })
      .then(parseJsonSafe)
      .then(function (result) {
        var data = result.data;
        if (data.expires_at) resetFlow.expiresAt = data.expires_at;
        startResendCooldown(RESEND_COOLDOWN_S);
        tokenInput.value = '';
        clearFieldState('token-input');
        showBanner('token-banner', 'A new token has been sent.', 'locked');
      })
      .catch(function () {
        showBanner('token-banner', 'Could not resend token. Try again later.');
      });
  });

  /* ---------- Reset password  ---------- */

  var resetForm = document.getElementById('form-reset');
  var resetMeter = resetForm.querySelector('.strength-meter');
  var resetStrengthLabel = document.getElementById('reset-strength-label');

  document.getElementById('reset-password').addEventListener('input', function (e) {
    updateStrengthMeter(resetMeter, resetStrengthLabel, e.target.value);
    clearFieldState('reset-password');
  });

  resetForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var password = document.getElementById('reset-password');
    var confirm = document.getElementById('reset-confirm');
    var submit = document.getElementById('reset-submit');

    var passOk = passwordScore(password.value) >= 2
      ? setFieldError('reset-password', '')
      : setFieldError('reset-password', 'Password is too weak');

    var confirmOk = confirm.value.length > 0 && confirm.value === password.value
      ? setFieldError('reset-confirm', '')
      : setFieldError('reset-confirm', 'Passwords don\'t match');

    showBanner('reset-banner', '');
    if (!passOk || !confirmOk) return;

    setLoading(submit, true);

    fetch(API + '?action=reset-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email: resetFlow.email,
        token: tokenInput.value,
        password: password.value,
        confirm_password: confirm.value
      })
    })
      .then(parseJsonSafe)
      .then(function (result) {
        setLoading(submit, false);
        var data = result.data;

        if (data.status === 'success') {
          clearResetFlow();
          resetForm.reset();
          resetMeter.className = 'strength-meter';
          resetStrengthLabel.textContent = 'Use 8+ characters with a number and a symbol';
          clearFieldState('reset-password');
          clearFieldState('reset-confirm');


          showView('success', {
            successHeading: 'Password updated',
            successSub: 'Routing to login\u2026'
          });
          redirectTimer = setTimeout(function () { showView('login'); }, 2200);
          return;
        }

        showBanner('reset-banner', data.message || 'Failed to update password.');
      })
      .catch(function () {
        setLoading(submit, false);
        showBanner('reset-banner', 'Something went wrong. Please try again.');
      });
  });

  document.getElementById('reset-confirm').addEventListener('input', function () { clearFieldState('reset-confirm'); });

})();