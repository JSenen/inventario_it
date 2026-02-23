// Global table search enhancer for all list views.
document.addEventListener('DOMContentLoaded', function () {
  var tables = document.querySelectorAll('table.table');
  if (!tables.length) return;

  tables.forEach(function (table, idx) {
    if (table.dataset.searchEnabled === '1') return;
    if (table.dataset.noSearch === '1') return;

    var tbody = table.querySelector('tbody');
    if (!tbody) return;

    var rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length) return;

    var wrapper = document.createElement('div');
    wrapper.className = 'table-search-wrap';

    var input = document.createElement('input');
    input.type = 'search';
    input.className = 'form-control table-search-input';
    input.placeholder = 'Buscar por cualquier campo...';
    input.setAttribute('aria-label', 'Buscar en tabla');
    input.autocomplete = 'off';
    input.id = 'table-search-' + idx;

    var info = document.createElement('small');
    info.className = 'table-search-info text-muted';
    info.textContent = rows.length + ' resultados';

    wrapper.appendChild(input);
    wrapper.appendChild(info);

    table.parentNode.insertBefore(wrapper, table);

    var applyFilter = function () {
      var term = input.value.trim().toLowerCase();
      var visible = 0;

      rows.forEach(function (row) {
        var haystack = (row.innerText || row.textContent || '').toLowerCase();
        var match = term === '' || haystack.indexOf(term) !== -1;
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });

      info.textContent = visible + ' resultado' + (visible === 1 ? '' : 's');
    };

    input.addEventListener('input', applyFilter);
    table.dataset.searchEnabled = '1';
  });
});
