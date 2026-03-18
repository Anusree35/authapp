/* =============================================
   profile.js  –  Handles profile page via jQuery AJAX
   Auth guard uses localStorage token
   ============================================= */

$(document).ready(function () {

  /* ── Auth guard ── */
  var token      = localStorage.getItem('auth_token');
  var user_id    = localStorage.getItem('user_id');
  var first_name = localStorage.getItem('first_name') || '';
  var last_name  = localStorage.getItem('last_name')  || '';
  var username   = localStorage.getItem('username')   || '';
  var email      = localStorage.getItem('email')      || '';

  if (!token || !user_id) {
    window.location.href = 'login.html';
    return;
  }

  /* ── Populate static info from localStorage ── */
  var fullName = (first_name + ' ' + last_name).trim();
  var initials = ((first_name.charAt(0)) + (last_name.charAt(0))).toUpperCase() || '?';

  $('#nav-name').text('Hello, ' + first_name);
  $('#avatar-initials').text(initials);
  $('#profile-fullname').text(fullName || 'User');
  $('#profile-username').text('@' + username);
  $('#profile-email').text(email);

  /* ── Alert helper ── */
  function showAlert(message, type) {
    $('#alert-box')
      .removeClass('d-none alert-success alert-danger alert-warning')
      .addClass('alert-' + type)
      .text(message);
    setTimeout(function () { $('#alert-box').addClass('d-none'); }, 4000);
  }

  /* ── Calculate age from DOB ── */
  function calcAge(dob) {
    if (!dob) return '—';
    var today = new Date();
    var birth = new Date(dob);
    var age = today.getFullYear() - birth.getFullYear();
    var m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    return age > 0 ? age + ' years' : '—';
  }

  /* ── Format date nicely ── */
  function formatDate(d) {
    if (!d) return '—';
    var dt = new Date(d);
    if (isNaN(dt.getTime())) return d;
    return dt.toLocaleDateString('en-IN', { year: 'numeric', month: 'long', day: 'numeric' });
  }

  /* ── Render view mode fields ── */
  function renderView(p) {
    $('#view-dob').text(p.dob      ? formatDate(p.dob) : '—');
    $('#view-age').text(p.dob      ? calcAge(p.dob) : '—');
    $('#view-contact').text(p.contact  || '—');
    $('#view-gender').text(p.gender    || '—');
    $('#view-location').text(p.location || '—');
    $('#view-bio').text(p.bio          || '—');
    if (p.created_at) $('#profile-joined').text(formatDate(p.created_at));
  }

  /* ── Populate edit form ── */
  function populateEdit(p) {
    if (p.dob)      $('#edit-dob').val(p.dob);
    if (p.contact)  $('#edit-contact').val(p.contact);
    if (p.gender)   $('#edit-gender').val(p.gender);
    if (p.location) $('#edit-location').val(p.location);
    if (p.bio)      $('#edit-bio').val(p.bio);
  }

  /* ── Load profile from server (MongoDB via PHP) ── */
  function loadProfile() {
    $.ajax({
      url: 'php/profile.php',
      type: 'GET',
      headers: { 'X-Auth-Token': token },
      data: { user_id: user_id },
      dataType: 'json',
      success: function (res) {
        if (res.success) {
          renderView(res.profile);
          populateEdit(res.profile);
        } else if (res.message === 'Unauthorized') {
          localStorage.clear();
          window.location.href = 'login.html';
        } else {
          showAlert(res.message || 'Failed to load profile.', 'warning');
        }
      },
      error: function () {
        showAlert('Could not connect to server. Check your connection.', 'danger');
      }
    });
  }

  loadProfile();

  /* ── Edit / Cancel ── */
  $('#edit-btn').on('click', function () {
    $('#view-mode').addClass('d-none');
    $('#edit-mode').removeClass('d-none');
    $('html, body').animate({ scrollTop: $('#edit-mode').offset().top - 20 }, 300);
  });

  function cancelEdit() {
    $('#edit-mode').addClass('d-none');
    $('#view-mode').removeClass('d-none');
  }

  $('#cancel-btn').on('click', cancelEdit);
  $('#cancel-btn-2').on('click', cancelEdit);

  /* ── Save profile (POST to PHP → MongoDB) ── */
  $('#save-btn').on('click', function () {
    var dob      = $('#edit-dob').val();
    var contact  = $.trim($('#edit-contact').val());
    var gender   = $('#edit-gender').val();
    var location = $.trim($('#edit-location').val());
    var bio      = $.trim($('#edit-bio').val());

    var btn = $('#save-btn');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

    $.ajax({
      url: 'php/profile.php',
      type: 'POST',
      contentType: 'application/json',
      headers: { 'X-Auth-Token': token },
      data: JSON.stringify({
        user_id  : parseInt(user_id),
        dob      : dob      || null,
        contact  : contact  || null,
        gender   : gender   || null,
        location : location || null,
        bio      : bio      || null
      }),
      dataType: 'json',
      success: function (res) {
        btn.prop('disabled', false).text('Save Changes');
        if (res.success) {
          showAlert('Profile updated successfully!', 'success');
          renderView({ dob: dob, contact: contact, gender: gender, location: location, bio: bio });
          cancelEdit();
        } else {
          showAlert(res.message || 'Failed to update profile.', 'danger');
        }
      },
      error: function () {
        btn.prop('disabled', false).text('Save Changes');
        showAlert('Server error. Please try again.', 'danger');
      }
    });
  });

  /* ── Logout ── */
  $('#logout-btn').on('click', function () {
    $.ajax({
      url: 'php/login.php',
      type: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ action: 'logout', token: token }),
      dataType: 'json',
      complete: function () {
        localStorage.clear();
        window.location.href = 'login.html';
      }
    });
  });

});
