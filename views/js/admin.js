/**
 * ProofAge Age Verification for PrestaShop
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
(function () {
  'use strict';

  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = String(value);
    return div.innerHTML;
  }

  function initTabs() {
    var anchor = document.querySelector('[data-proofage-connection]');
    var form = anchor ? anchor.closest('form') : null;
    if (!form) { return; }
    var panels = form.querySelectorAll(':scope > .panel');
    if (panels.length < 2) { return; }
    var nav = document.createElement('ul');
    nav.className = 'nav nav-tabs proofage-tabs';
    Array.prototype.forEach.call(panels, function (panel, index) {
      var heading = panel.querySelector('.panel-heading');
      var item = document.createElement('li');
      var link = document.createElement('a');
      link.href = '#';
      link.textContent = heading ? heading.textContent.trim() : String(index + 1);
      link.addEventListener('click', function (event) {
        event.preventDefault();
        show(index);
      });
      item.appendChild(link);
      nav.appendChild(item);
    });
    form.insertBefore(nav, form.firstChild);

    function show(active) {
      Array.prototype.forEach.call(panels, function (panel, index) {
        panel.style.display = index === active ? '' : 'none';
        nav.children[index].className = index === active ? 'active' : '';
      });
    }
    show(0);
  }

  function initPicker(root) {
    var hidden = root.querySelector('[data-proofage-picker-value]');
    var search = root.querySelector('[data-proofage-picker-search]');
    var results = root.querySelector('[data-proofage-picker-results]');
    var selected = root.querySelector('[data-proofage-picker-selected]');
    var timer = null;

    function ids() {
      try { return JSON.parse(hidden.value || '[]'); } catch (e) { return []; }
    }
    function sync() {
      hidden.value = JSON.stringify(Array.prototype.map.call(selected.children, function (li) { return parseInt(li.getAttribute('data-id'), 10); }));
    }
    function add(product) {
      if (ids().indexOf(product.id) !== -1) { return; }
      var li = document.createElement('li');
      li.setAttribute('data-id', product.id);
      li.innerHTML = escapeHtml(product.name) + ' <span class="text-muted">#' + product.id + '</span> <button type="button" class="btn btn-link btn-xs" data-proofage-picker-remove>&times;</button>';
      selected.appendChild(li);
      sync();
    }

    selected.addEventListener('click', function (event) {
      if (event.target.hasAttribute('data-proofage-picker-remove')) {
        event.target.parentNode.remove();
        sync();
      }
    });
    search.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') { event.preventDefault(); }
    });
    search.addEventListener('input', function () {
      clearTimeout(timer);
      var query = search.value.trim();
      if (query.length < 2) { results.innerHTML = ''; return; }
      timer = setTimeout(function () {
        fetch(root.getAttribute('data-search-url') + '&q=' + encodeURIComponent(query), { credentials: 'same-origin' })
          .then(function (response) { return response.json(); })
          .then(function (products) {
            results.innerHTML = '';
            products.forEach(function (product) {
              var li = document.createElement('li');
              li.className = 'list-group-item';
              li.textContent = product.name + (product.reference ? ' (' + product.reference + ')' : '') + ' #' + product.id;
              li.addEventListener('click', function () {
                add(product);
                results.innerHTML = '';
                search.value = '';
              });
              results.appendChild(li);
            });
          });
      }, 250);
    });
  }

  function initConnection(root) {
    var copy = root.querySelector('[data-proofage-copy]');
    var input = root.querySelector('[data-proofage-webhook-url]');
    var test = root.querySelector('[data-proofage-test]');
    var output = root.querySelector('[data-proofage-test-result]');

    copy.addEventListener('click', function () {
      input.select();
      if (navigator.clipboard) { navigator.clipboard.writeText(input.value); } else { document.execCommand('copy'); }
    });
    test.addEventListener('click', function () {
      test.disabled = true;
      output.innerHTML = '';
      fetch(root.getAttribute('data-test-url'), { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data.ok) {
            output.innerHTML = '<div class="alert alert-danger">' + escapeHtml(data.error) + '</div>';
            return;
          }
          var ws = data.workspace;
          var rows = Object.keys(ws).map(function (key) {
            return '<dt>' + escapeHtml(key) + '</dt><dd>' + escapeHtml(ws[key]) + '</dd>';
          }).join('');
          var warning = ws.mode === 'test' ? '<div class="alert alert-warning">TEST</div>' : '';
          output.innerHTML = warning + '<div class="alert alert-success"><dl class="proofage-workspace">' + rows + '</dl></div>';
        })
        .catch(function () { output.innerHTML = '<div class="alert alert-danger">Network error</div>'; })
        .then(function () { test.disabled = false; });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    Array.prototype.forEach.call(document.querySelectorAll('[data-proofage-picker]'), initPicker);
    var connection = document.querySelector('[data-proofage-connection]');
    if (connection) { initConnection(connection); }
  });
})();
