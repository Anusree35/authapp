/* =============================================
   register.js  –  Handles registration via jQuery AJAX
   No form submit used – strictly AJAX only
   ============================================= */

$(document).ready(function () {

  // If already logged in, go to profile
  if (localStorage.getItem('auth_token')) {
    window.location.href = 'profile.html';
    return;
  }

  /* ── Helper: show alert ── */
  function showAlert(message, type) {
    // type: 'success' | 'danger' | 'warning'
    $('#alert-box')
      .removeClass('d-none alert-success alert-danger alert-warning')
      .addClass('alert-' + type)
      .text(message);
  }

  function hideAlert() {
    $('#alert-box').addClass('d-none');
  }

  /* ── Helper: toggle loading state ── */
  function setLoading(isLoading) {
    var btn = $('#register-btn');
    if (isLoading) {
      btn.prop('disabled', true).html(
        '<span class="spinner-border spinner-border-sm me-2"></span>Registering...'
      );
    } else {
      btn.prop('disabled', false).text('Register');
    }
  }

  /* ── Register button click ── */
  $('#register-btn').on('click', function () {
    hideAlert();

    var first_name       = $.trim($('#first_name').val());
    var last_name        = $.trim($('#last_name').val());
    var username         = $.trim($('#username').val());
    var email            = $.trim($('#email').val());
    var password         = $('#password').val();
    var confirm_password = $('#confirm_password').val();

    /* Client-side validation */
    if (!first_name) { showAlert('First name is required.', 'warning'); return; }
    if (!last_name)  { showAlert('Last name is required.', 'warning'); return; }
    if (!username)   { showAlert('Username is required.', 'warning'); return; }
    if (!email)      { showAlert('Email address is required.', 'warning'); return; }
    if (!password)   { showAlert('Password is required.', 'warning'); return; }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showAlert('Please enter a valid email address.', 'warning');
      return;
    }
    if (!/^[a-zA-Z0-9_]{3,30}$/.test(username)) {
      showAlert('Username: 3-30 characters, letters, numbers and underscore only.', 'warning');
      return;
    }
    if (password.length < 6) {
      showAlert('Password must be at least 6 characters.', 'warning');
      return;
    }
    if (password !== confirm_password) {
      showAlert('Passwords do not match.', 'warning');
      return;
    }

    setLoading(true);

    /* jQuery AJAX POST */
    $.ajax({
      url: 'php/register.php',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({
        first_name : first_name,
        last_name  : last_name,
        username   : username,
        email      : email,
        password   : password
      }),
      dataType: 'json',
      success: function (res) {
        setLoading(false);
        if (res.success) {
          showAlert('Account created! Redirecting to login...', 'success');
          setTimeout(function () {
            window.location.href = 'login.html';
          }, 1800);
        } else {
          showAlert(res.message || 'Registration failed. Please try again.', 'danger');
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

  /* Allow Enter key on inputs */
  $('#first_name, #last_name, #username, #email, #password, #confirm_password').on('keydown', function (e) {
    if (e.key === 'Enter') { $('#register-btn').trigger('click'); }
  });

});
