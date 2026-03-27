$(function(){

  $('span#showSearchParameters').click(function(event){
    $('p#searchParameters').toggle();
  });

  // Initialize DataTables with server-side processing
  if ($('#catalogueTable').length) {
    var rootUrl   = $('#catalogueTable').data('root-url');
    var apiUrl    = $('#catalogueTable').data('api-url');

    // Helper: render a place name as a link if a Trismegistos @ref URL is present
    function tmLink(text, ref) {
      if (!text) return '';
      var safe = $('<span>').text(text).html();
      if (ref && ref.indexOf('https://www.trismegistos.org/') === 0) {
        return '<a href="' + $('<span>').text(ref).html() + '" target="_blank">' + safe + '</a>';
      }
      return safe;
    }
    var singleUrl = $('#catalogueTable').data('single-url');

    // Default visibility per column index (matches column definitions above)
    var defaultVisible = [true, true, true, true, true, true, false, false, true,
      false, false, false, false, false, false, false, false, false,
      false, false, false, false, false, false, false, false, false, false, false];

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
            var href = rootUrl + 'hgv/' + encodeURIComponent(row.hgvId);
            return '<a href="' + href + '">' + $('<span>').text(data).html() + '</a>';
          }
        },
        { data: 'dating',   title: 'Datierung',           // 2
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.dating || '').html();
            }).filter(function(s) { return s !== ''; }).join('<br>');
          }
        },
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
            return '<a href="' + rootUrl + 'tm/' + encodeURIComponent(data) + '" target="_blank">' + $('<span>').text(data).html() + '</a>';
          }
        },
        // ── Initially hidden columns (9-26) ──────────────────────────────────
        { data: 'ddb',          title: 'DDB',               visible: false }, // 9
        { data: 'hgvId',        title: 'HGV Id',            visible: false }, // 10
        { data: 'pubAbbr',      title: 'Publikation Abk.',  visible: false }, // 11
        { data: 'pubVol',       title: 'Band',              visible: false }, // 12
        { data: 'pubNr',        title: 'Nummer',            visible: false }, // 13
        { data: 'notBefore',    title: 'Nicht vor',         visible: false, // 14
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.notBefore || '').html();
            }).join('<br>');
          }
        },
        { data: 'notAfter',     title: 'Nicht nach',        visible: false, // 15
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.notAfter || '').html();
            }).join('<br>');
          }
        },
        { data: 'when',         title: 'Genaudatum',        visible: false, // 16
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.when || '').html();
            }).join('<br>');
          }
        },
        { data: 'precision',    title: 'Präzision',         visible: false, // 17
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.precision || '').html();
            }).join('<br>');
          }
        },
        { data: 'settlement',   title: 'Aufbewahrungsort',  visible: false }, // 18
        { data: 'collection',   title: 'Sammlung',          visible: false }, // 19
        { data: 'invNo',        title: 'Inv.-Nr.',          visible: false }, // 20
        { data: 'provenance',   title: 'Herkunft',          visible: false,  // 21
          render: function(data, type, row) {
            if (type !== 'display' || !row.provenances || row.provenances.length <= 1) return data || '';
            return row.provenances.map(function(p) {
              var parts = [];
              if (p.type) parts.push($('<em>').text(p.type).prop('outerHTML'));
              if (p.place) parts.push(tmLink(p.place, p.placeRef));
              if (p.nome) parts.push(tmLink(p.nome, p.nomeRef));
              if (p.region) parts.push(tmLink(p.region, p.regionRef));
              return parts.join(' \u2013 ');
            }).filter(function(s) { return s !== ''; }).join('<br>');
          }
        },
        { data: 'provenancePlace', title: 'Herkunft - Ort',    visible: false, // 21a
          render: function(data, type, row) {
            if (type !== 'display' || !row.provenances || !row.provenances.length) return data || '';
            return row.provenances.map(function(p) {
              return tmLink(p.place, p.placeRef);
            }).filter(function(s) { return s !== ''; }).join('<br>');
          }
        },
        { data: 'provenanceNome',  title: 'Herkunft - Gau',    visible: false, // 21b
          render: function(data, type, row) {
            if (type !== 'display' || !row.provenances || !row.provenances.length) return data || '';
            return row.provenances.map(function(p) {
              return tmLink(p.nome, p.nomeRef);
            }).filter(function(s) { return s !== ''; }).join('<br>');
          }
        },
        { data: 'illustrations',title: 'Abbildungen',       visible: false }, // 22
        { data: 'figureUrls',   title: 'Bild-URLs',         visible: false }, // 23
        { data: 'translations', title: 'Übersetzungen',     visible: false }, // 24
        { data: 'commentary',   title: 'Bemerkungen',       visible: false }, // 25
        { data: 'mentionedDates',title: 'Erwähnte Daten',   visible: false }  // 26
      ],

      // Pagination
      paging: true,
      pageLength: 50,
      lengthMenu: [[10, 25, 50, 100, 200, 500], [10, 25, 50, 100, 200, 500]],

      // Ordering
      ordering: true,
      order: [[1, 'asc'], [2, 'asc']], // Default: sort by date ascending

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
        },
        {
          text: 'Reset',
          action: function(e, dt, node, config) {
            // Clear global search
            dt.search('');
            // Clear all column searches and reset visibility to initial config
            dt.columns().every(function(index) {
              this.search('');
              if (index < defaultVisible.length) {
                this.visible(defaultVisible[index]);
              }
            });
            // Reset ordering to default
            dt.order([[1, 'asc'], [2, 'asc']]);
            // Reset page length
            dt.page.len(50);
            // Clear saved state
            dt.state.clear();
            // Clear footer filter inputs
            $('tfoot input').val('');
            // Redraw
            dt.draw();
          }
        }
      ],

      // State saving (remembers column order, visibility, page length, sorting)
      stateSave: true,
      stateDuration: 60 * 60 * 24 * 7, // 7 days

      // Per-column search inputs in tfoot
      initComplete: function() {
        // Searchable column indices (excludes col 0 = row-link)
        var searchable = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28];
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
