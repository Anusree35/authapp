/* =============================================
   login.js  –  Handles login via jQuery AJAX
   Session stored in browser localStorage only
   No PHP sessions used
   ============================================= */

$(document).ready(function () {

  // If already logged in, redirect
  if (localStorage.getItem('auth_token') && localStorage.getItem('user_id')) {
    window.location.href = 'profile.html';
    return;
  }

  /* ── Helper: show alert ── */
  function showAlert(message, type) {
    $('#alert-box')
      .removeClass('d-none alert-success alert-danger alert-warning')
      .addClass('alert-' + type)
      .text(message);
  }

  function hideAlert() {
    $('#alert-box').addClass('d-none');
  }

  /* ── Helper: toggle loading ── */
  function setLoading(isLoading) {
    var btn = $('#login-btn');
    if (isLoading) {
      btn.prop('disabled', true).html(
        '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...'
      );
    } else {
      btn.prop('disabled', false).text('Login');
    }
  }

  /* ── Login button click ── */
  $('#login-btn').on('click', function () {
    hideAlert();

    var email    = $.trim($('#email').val());
    var password = $('#password').val();

    if (!email)    { showAlert('Email address is required.', 'warning'); return; }
    if (!password) { showAlert('Password is required.', 'warning'); return; }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showAlert('Please enter a valid email address.', 'warning');
      return;
    }

    setLoading(true);

    /* jQuery AJAX POST */
    $.ajax({
      url: 'php/login.php',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ email: email, password: password }),
      dataType: 'json',
      success: function (res) {
        setLoading(false);
        if (res.success) {
          /* Store session in localStorage – NOT PHP session */
          localStorage.setItem('auth_token',  res.token);
          localStorage.setItem('user_id',     String(res.user_id));
          localStorage.setItem('username',    res.username);
          localStorage.setItem('email',       res.email);
          localStorage.setItem('first_name',  res.first_name);
          localStorage.setItem('last_name',   res.last_name);

          showAlert('Login successful! Redirecting...', 'success');
          setTimeout(function () {
            window.location.href = 'profile.html';
          }, 1000);
        } else {
          showAlert(res.message || 'Invalid email or password.', 'danger');
        }
      },
      error: function (xhr) {
        setLoading(false);
        var msg = 'Server error. Please try again.';
        try {
          var res = JSON.parse(xhr.responseText);
          if (res.message) msg = res.message;
        } catch (e) {}
        showAlert(msg, 'danger');
      }
    });
  });

  /* Allow Enter key */
  $('#email, #password').on('keydown', function (e) {
    if (e.key === 'Enter') { $('#login-btn').trigger('click'); }
  });

});
