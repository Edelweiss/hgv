$(function(){

  $('span#showSearchParameters').click(function(event){
    $('p#searchParameters').toggle();
  });

  // Initialize DataTables with server-side processing
  if ($('#catalogueTable').length) {
    var apiUrl    = $('#catalogueTable').data('api-url');
    var singleUrl = $('#catalogueTable').data('single-url');

    var table = $('#catalogueTable').DataTable({
      // Server-side processing
      processing: true,
      serverSide: true,
      ajax: {
        url: apiUrl,
        type: 'GET'
      },

      // Column definitions
      // Column indices 0-8 — must match BrowseController::$sortableColumns / $searchableColumns
      columns: [
        {
          // 0 — row-number link to single view
          data: 'hgvId',
          title: '#',
          orderable: false,
          searchable: false,
          render: function(data, type, row, meta) {
            var rowNum = meta.row + meta.settings._iDisplayStart + 1;
            var href   = singleUrl + '?show[skip]=' + (meta.row + meta.settings._iDisplayStart) + '&show[max]=1';
            return '<a href="' + href + '" title="HGV ' + $('<span>').text(data).html() + '">' + rowNum + '</a>';
          }
        },
        {
          // 1 — Publikation
          data: 'publ',
          title: 'Publikation',
          render: function(data, type, row) {
            if (type !== 'display' || !data) return data || '';
            var href = '/hgv/' + encodeURIComponent(row.hgvId);
            return '<a href="' + href + '">' + $('<span>').text(data).html() + '</a>';
          }
        },
        { data: 'dating',   title: 'Datierung' },        // 2
        { data: 'place',    title: 'Ort' },               // 3
        { data: 'title',    title: 'Titel' },             // 4
        { data: 'material', title: 'Material' },          // 5
        { data: 'keywords', title: 'Inhalt / Schlagworte',// 6
          visible: false },
        { data: 'otherPub', title: 'Andere Publikationen',// 7
          visible: false },
        { data: 'tm',       title: 'TM Nr.',              // 8
          render: function(data, type) {
            if (type !== 'display' || !data) return data || '';
            return '<a href="/tm/' + encodeURIComponent(data) + '" target="_blank">' + $('<span>').text(data).html() + '</a>';
          }
        }
      ],

      // Pagination
      paging: true,
      pageLength: 25,
      lengthMenu: [[10, 25, 50, 100, 200], [10, 25, 50, 100, 200]],

      // Ordering
      ordering: true,
      order: [[2, 'asc']], // Default: sort by date ascending

      // Column reordering (drag & drop)
      colReorder: true,

      // Fixed header stays visible on scroll
      fixedHeader: true,

      // Buttons for column visibility and export
      dom: '<"top"lBfr>tip',
      buttons: [
        {
          extend: 'colvis',
          text: 'Spalten',
          columns: ':gt(0)' // allow toggling all columns except the row-number
        },
        {
          extend: 'collection',
          text: 'Export',
          buttons: ['copy', 'csv', 'print']
        }
      ],

      // State saving (remembers column order, visibility, page length, sorting)
      stateSave: true,
      stateDuration: 60 * 60 * 24 * 7, // 7 days

      // Per-column search inputs in tfoot
      initComplete: function() {
        // Searchable column indices
        var searchable = [1, 2, 3, 4, 5, 6, 7, 8];
        this.api().columns(searchable).every(function() {
          var column = this;
          var input = $('<input type="text" placeholder="Filter …" />')
            .appendTo($(column.footer()).empty())
            .on('keyup change clear', $.fn.dataTable.util.debounce(function() {
              if (column.search() !== this.value) {
                column.search(this.value).draw();
              }
            }, 400));

          // Restore saved search value
          if (column.search()) {
            input.val(column.search());
          }
        });
      },

      // German language
      language: {
        lengthMenu: 'Zeige _MENU_ Einträge pro Seite',
        zeroRecords: 'Keine passenden Einträge gefunden',
        info: 'Einträge _START_ bis _END_ von _TOTAL_',
        infoEmpty: 'Keine Einträge verfügbar',
        infoFiltered: '(gefiltert von _MAX_ Einträgen)',
        search: 'Suchen:',
        processing: 'Daten werden geladen …',
        paginate: {
          first: 'Erste',
          last: 'Letzte',
          next: 'Weiter',
          previous: 'Zurück'
        },
        buttons: {
          colvis: 'Spalten',
          copy: 'Kopieren',
          csv: 'CSV',
          print: 'Drucken'
        }
      }
    });
  }

});
