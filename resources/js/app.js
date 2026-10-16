import '../css/app.css';
import $ from 'jquery';
import { mountIslands } from './bridge.js';

const csrf = $('meta[name="csrf-token"]').attr('content');
const baseUrl = ($('meta[name="base-url"]').attr('content') || '/').replace(/\/$/, '');
window.APP = { csrf, baseUrl, url: (p) => baseUrl + '/' + String(p).replace(/^\//, '') };

// Every AJAX call carries the CSRF header automatically
$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });

function renderUsers(users) {
  const $list = $('#user-list').empty();
  if (!users.length) return $list.append('<li class="py-2 text-slate-400">No users yet.</li>');
  users.forEach((u) => {
    $list.append(
      $('<li class="flex justify-between py-2">')
        .append($('<span class="font-medium">').text(u.name))
        .append($('<span class="text-slate-500">').text(u.email))
    );
  });
}

function loadUsers() {
  $.getJSON(APP.url('api/users'))
    .done(renderUsers)
    .fail(() => $('#user-list').html('<li class="py-2 text-slate-400">Database not reachable (run migrations).</li>'));
}

$(function () {
  mountIslands();

  if ($('#user-form').length) {
    loadUsers();
    $('#user-form').on('submit', function (e) {
      e.preventDefault();
      const $err = $('#user-error').addClass('hidden');
      $.ajax({
        url: APP.url('api/users'),
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(Object.fromEntries(new FormData(this))),
      })
        .done(() => { this.reset(); loadUsers(); })
        .fail((xhr) => $err.text(xhr.responseJSON?.error || 'Something went wrong').removeClass('hidden'));
    });
  }
});
