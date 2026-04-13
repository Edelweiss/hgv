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
      if (ref && ref.indexOf('trismegistos.org/') !== -1) {
        var url = ref.indexOf('://') !== -1 ? ref : 'https://' + ref;
        return '<a href="' + $('<span>').text(url).html() + '" target="_blank">' + safe + '</a>';
      }
      return safe;
    }
    var singleUrl = $('#catalogueTable').data('single-url');

    // Default visibility per column index (matches column definitions above)
    var defaultVisible = [true, true, true, true, true, true, false, false, true,
      false, false, false, false, false, false, false, false, false,
      false, false, false, false, false, false, false, false, false, false, false, false];

    // ── Parse URL parameters for initial filters and sort ────────────────────
    var urlParams = new URLSearchParams(window.location.search);
    var initialFilters = {};
    urlParams.forEach(function(value, key) {
      var match = key.match(/^filter\[(.+)\]$/);
      if (match && value.trim() !== '') {
        initialFilters[match[1]] = value.trim();
      }
    });
    var hasUrlFilters = Object.keys(initialFilters).length > 0;

    // Map DataTables data properties → column indices (must match column defs below)
    var dataToColIndex = {
      'publ': 1, 'dating': 2, 'place': 3, 'title': 4, 'material': 5,
      'keywords': 6, 'otherPub': 7, 'tm': 8, 'ddb': 9, 'hgvId': 10,
      'pubAbbr': 11, 'pubVol': 12, 'pubNr': 13, 'notBefore': 14,
      'notAfter': 15, 'when': 16, 'precision': 17, 'settlement': 18,
      'collection': 19, 'invNo': 20, 'provenance': 21,
      'provenancePlace': 22, 'provenanceNome': 23,
      'illustrations': 24, 'figureUrls': 25, 'translations': 26,
      'commentary': 27, 'mentionedDates': 28, 'blOnline': 29
    };

    // Compute initial sort order from URL params (supports multi-level: sort[1][key], sort[1][dir], sort[2][key], ...)
    var initialOrder = [[1, 'asc'], [2, 'asc']]; // default: publication, then dating
    var urlSortLevels = [];
    for (var si = 1; si <= 5; si++) {
      var sk = urlParams.get('sort[' + si + '][key]');
      if (sk && dataToColIndex[sk] !== undefined) {
        var sd = urlParams.get('sort[' + si + '][dir]') || 'asc';
        urlSortLevels.push([dataToColIndex[sk], sd === 'desc' ? 'desc' : 'asc']);
      }
    }
    // Fall back to legacy single-sort params
    if (urlSortLevels.length === 0) {
      var initialSortKey = urlParams.get('initialSort') || '';
      var initialSortDir = urlParams.get('initialSortDir') || 'asc';
      if (initialSortKey && dataToColIndex[initialSortKey] !== undefined) {
        urlSortLevels.push([dataToColIndex[initialSortKey], initialSortDir === 'desc' ? 'desc' : 'asc']);
      }
    }
    var hasUrlSort = urlSortLevels.length > 0;
    if (hasUrlSort) {
      initialOrder = urlSortLevels;
    }

    // Parse page length from URL
    var validPageLengths = [10, 25, 50, 100, 200, 500];
    var urlPageLength = parseInt(urlParams.get('pageLength'), 10);
    var initialPageLength = (validPageLengths.indexOf(urlPageLength) !== -1) ? urlPageLength : 50;

    var _dtUrlFiltersApplied = false;

    var T = window.HGV_TRANS || {};
    var tbl = T.table || {};
    var flt = T.filter || {};

    var table = $('#catalogueTable').DataTable({
      // Server-side processing
      processing: true,
      serverSide: true,
      ajax: {
        url: apiUrl,
        type: 'GET',
        data: function(d) {
          // On first draw, inject URL filter params into the AJAX request
          if (hasUrlFilters && !_dtUrlFiltersApplied) {
            for (var dataField in initialFilters) {
              var colIdx = dataToColIndex[dataField];
              if (colIdx !== undefined && colIdx < d.columns.length) {
                d.columns[colIdx].search.value = initialFilters[dataField];
              }
            }
            _dtUrlFiltersApplied = true;
          }
        }
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
          title: tbl.publication || 'Publication',
          render: function(data, type, row) {
            if (type !== 'display' || !data) return data || '';
            var href = rootUrl + 'hgv/' + encodeURIComponent(row.hgvId);
            return '<a href="' + href + '">' + $('<span>').text(data).html() + '</a>';
          }
        },
        { data: 'dating',   title: tbl.dating || 'Dating',     // 2
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.dating || '').html();
            }).filter(function(s) { return s !== ''; }).join('<br>');
          }
        },
        { data: 'place',    title: tbl.place || 'Place' },               // 3
        { data: 'title',    title: tbl.title || 'Title' },             // 4
        { data: 'material', title: tbl.material || 'Material' },          // 5
        { data: 'keywords', title: tbl.keywords || 'Keywords',// 6
          visible: false },
        { data: 'otherPub', title: tbl.other_publications || 'Other Publications',// 7
          visible: false },
        { data: 'tm',       title: tbl.tm_no || 'TM No.',              // 8
          render: function(data, type) {
            if (type !== 'display' || !data) return data || '';
            var safe = $('<span>').text(data).html();
            return '<a href="https://www.trismegistos.org/text/' + encodeURIComponent(data) + '" target="_blank" title="Trismegistos">' + safe + '</a>';
          }
        },
        // ── Initially hidden columns (9-26) ──────────────────────────────────
        { data: 'ddb',          title: tbl.ddb || 'DDB',               visible: false, // 9
          render: function(data, type) {
            if (type !== 'display' || !data) return data || '';
            var safe = $('<span>').text(data).html();
            return '<a href="https://papyri.info/ddbdp/' + encodeURIComponent(data) + '" target="_blank" title="Papyri.info">' + safe + '</a>';
          }
        },
        { data: 'hgvId',        title: tbl.hgv_id || 'HGV Id',            visible: false, // 10
          render: function(data, type) {
            if (type !== 'display' || !data) return data || '';
            var safe = $('<span>').text(data).html();
            var num = parseInt(data, 10);
            if (isNaN(num)) return safe;
            var folder = Math.floor(num / 1000) + 1;
            var href = 'https://github.com/papyri/idp.data/blob/master/HGV_meta_EpiDoc/HGV' + folder + '/' + encodeURIComponent(data) + '.xml';
            return '<a href="' + href + '" target="_blank" title="GitHub HGV XML">' + safe + '</a>';
          }
        },
        { data: 'pubAbbr',      title: tbl.publication_abbr || 'Publication Abbr.',  visible: false }, // 11
        { data: 'pubVol',       title: tbl.volume || 'Volume',              visible: false }, // 12
        { data: 'pubNr',        title: tbl.number || 'Number',            visible: false }, // 13
        { data: 'notBefore',    title: tbl.not_before || 'Not Before',         visible: false, // 14
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.notBefore || '').html();
            }).join('<br>');
          }
        },
        { data: 'notAfter',     title: tbl.not_after || 'Not After',        visible: false, // 15
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.notAfter || '').html();
            }).join('<br>');
          }
        },
        { data: 'when',         title: tbl.exact_date || 'Exact Date',        visible: false, // 16
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.when || '').html();
            }).join('<br>');
          }
        },
        { data: 'precision',    title: tbl.precision || 'Precision',         visible: false, // 17
          render: function(data, type, row) {
            if (type !== 'display' || !row.dates || row.dates.length <= 1) return data || '';
            return row.dates.map(function(d) {
              return $('<span>').text(d.precision || '').html();
            }).join('<br>');
          }
        },
        { data: 'settlement',   title: tbl.settlement || 'Repository',  visible: false }, // 18
        { data: 'collection',   title: tbl.collection || 'Collection',          visible: false }, // 19
        { data: 'invNo',        title: tbl.inv_no || 'Inv. No.',          visible: false }, // 20
        { data: 'provenance',   title: tbl.provenance || 'Provenance',          visible: false,  // 21
          render: function(data, type, row) {
            if (type !== 'display' || !row.provenances || !row.provenances.length) return data || '';
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
        { data: 'provenancePlace', title: tbl.provenance_place || 'Provenance - Place',    visible: false, // 21a
          render: function(data, type, row) {
            if (type !== 'display' || !row.provenances || !row.provenances.length) return data || '';
            return row.provenances.map(function(p) {
              return tmLink(p.place, p.placeRef);
            }).filter(function(s) { return s !== ''; }).join('<br>');
          }
        },
        { data: 'provenanceNome',  title: tbl.provenance_nome || 'Provenance - Nome',    visible: false, // 21b
          render: function(data, type, row) {
            if (type !== 'display' || !row.provenances || !row.provenances.length) return data || '';
            return row.provenances.map(function(p) {
              return tmLink(p.nome, p.nomeRef);
            }).filter(function(s) { return s !== ''; }).join('<br>');
          }
        },
        { data: 'illustrations',title: tbl.illustrations || 'Illustrations',       visible: false }, // 22
        { data: 'figureUrls',   title: tbl.figure_urls || 'Figure URLs',         visible: false, // 23
          render: function(data, type) {
            if (type !== 'display' || !data) return data || '';
            var urls = data.split(/\s*;\s*/);
            return urls.map(function(url) {
              url = url.trim();
              if (!url) return '';
              var safeUrl = $('<span>').text(url).html();
              var label = url;
              try {
                var parsed = new URL(url);
                label = parsed.hostname.replace(/^www\./, '');
              } catch(e) {}
              var safeLabel = $('<span>').text(label).html();
              return '<a href="' + safeUrl + '" target="_blank">' + safeLabel + '</a>';
            }).filter(function(s) { return s !== ''; }).join('; ');
          }
        },
        { data: 'translations', title: tbl.translations || 'Translations',     visible: false }, // 24
        { data: 'commentary',   title: tbl.commentary || 'Commentary',       visible: false }, // 25
        { data: 'mentionedDates',title: tbl.mentioned_dates || 'Mentioned Dates',   visible: false }, // 26
        { data: 'blOnline',     title: tbl.bl_online || 'BL online',          visible: false, // 27
          render: function(data, type, row) {
            if (type !== 'display' || !data) return data || '';
            var safe = $('<span>').text(data).html();
            var href = 'https://beehive.zaw.uni-heidelberg.de/hgv/' + encodeURIComponent(row.hgvId);
            return '<a href="' + href + '" target="_blank">' + safe + '</a>';
          }
        }
      ],

      // Pagination
      paging: true,
      pageLength: initialPageLength,
      lengthMenu: [[10, 25, 50, 100, 200, 500], [10, 25, 50, 100, 200, 500]],

      // Ordering
      ordering: true,
      order: initialOrder,

      // Column reordering (drag & drop)
      colReorder: true,

      // Fixed header stays visible on scroll
      fixedHeader: true,

      // Buttons for column visibility and export
      dom: '<"top"lBfr>tip',
      buttons: [
        {
          extend: 'colvis',
          text: T.dt && T.dt.buttons ? T.dt.buttons.colvis : 'Columns',
          columns: ':gt(0)' // allow toggling all columns except the row-number
        },
        {
          extend: 'collection',
          text: T.btn ? T.btn.export_label : 'Export',
          buttons: ['copy', 'csv', 'print']
        },
        {
          text: T.btn ? T.btn.reset : 'Reset',
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
        },
        {
          text: T.btn ? T.btn.tips : 'Tips',
          action: function() {
            // Build and show a tips modal
            if ($('#dt-tips-overlay').length) {
              $('#dt-tips-overlay').show();
              return;
            }
            var html = '<div id="dt-tips-overlay">' +
              '<div id="dt-tips-modal">' +
              '<button id="dt-tips-close" title="' + (flt.close || 'Close') + '">&times;</button>' +
              '<h3>' + (T.btn ? T.btn.tips : 'Tips') + '</h3>' +
              '<h4>' + (flt.heading || 'Filter Syntax') + '</h4>' +
              '<p>' + (flt.description || '') + '</p>' +
              '<table class="dt-tips-table">' +
              '<tr><th>' + (flt.input || 'Input') + '</th><th>' + (flt.meaning || 'Meaning') + '</th><th>' + (flt.example || 'Example') + '</th></tr>' +
              '<tr><td><code>text</code></td><td>' + (flt.contains || 'Contains') + '</td><td><code>Oxy</code></td></tr>' +
              '<tr><td><code>wort1 wort2</code></td><td>' + (flt.all_words || 'All words must occur') + '</td><td><code>Brief privat</code></td></tr>' +
              '<tr><td><code>^text</code></td><td>' + (flt.starts_with || 'Starts with') + '</td><td><code>^P.Oxy</code></td></tr>' +
              '<tr><td><code>text$</code></td><td>' + (flt.ends_with || 'Ends with') + '</td><td><code>Verso$</code></td></tr>' +
              '<tr><td><code>=text</code></td><td>' + (flt.exact_match || 'Exact match') + '</td><td><code>=Papyrus</code></td></tr>' +
              '<tr><td><code>!text</code></td><td>' + (flt.not_contains || 'Does not contain') + '</td><td><code>!Papyrus</code></td></tr>' +
              '<tr><td><code>&gt;n &lt;n &gt;=n &lt;=n</code></td><td>' + (flt.numeric || 'Numeric comparison') + '</td><td><code>&gt;100</code></td></tr>' +
              '<tr><td><code>n...m</code></td><td>' + (flt.date_range || 'Date range') + '</td><td><code>-300...-100</code></td></tr>' +
              '<tr><td><code>*</code></td><td>' + (flt.not_empty || 'Field not empty') + '</td><td><code>*</code></td></tr>' +
              '<tr><td><code>=</code></td><td>' + (flt.empty || 'Field empty') + '</td><td><code>=</code></td></tr>' +
              '</table>' +
              '<h4>' + (flt.sorting_heading || 'Sorting') + '</h4>' +
              '<p>' + (flt.sorting_text || '') + '<br>' +
              '<kbd>Shift</kbd> + Click ' + (flt.sorting_shift || '') + '</p>' +
              '<h4>' + (flt.columns_heading || 'Columns') + '</h4>' +
              '<p>' + (flt.columns_text || '') + '<br>' +
              (flt.columns_show_hide || '') + ' <em>' + (flt.columns_button || '') + '</em> ' + (flt.columns_toggle || '') + '</p>' +
              '</div></div>';
            $('body').append(html);
            $('#dt-tips-close, #dt-tips-overlay').on('click', function(ev) {
              if (ev.target === this) $('#dt-tips-overlay').hide();
            });
          }
        }
      ],

      // Override saved state with URL filter/sort params when coming from search form
      stateLoadParams: function(settings, data) {
        if (hasUrlFilters || hasUrlSort) {
          // Clear saved column searches so URL filters take precedence
          if (data.columns) {
            for (var i = 0; i < data.columns.length; i++) {
              data.columns[i].search.search = '';
            }
          }
          data.search.search = '';
          data.order = initialOrder;
          data.length = initialPageLength;
        }
      },

      // State saving (remembers column order, visibility, page length, sorting)
      stateSave: true,
      stateDuration: 60 * 60 * 24 * 7, // 7 days

      // Per-column search inputs in tfoot
      initComplete: function() {
        var api = this.api();

        // Apply URL filters to DataTables column search state and make columns visible
        if (hasUrlFilters) {
          for (var dataField in initialFilters) {
            var colIdx = dataToColIndex[dataField];
            if (colIdx !== undefined) {
              api.column(colIdx).search(initialFilters[dataField]);
              if (!api.column(colIdx).visible()) {
                api.column(colIdx).visible(true);
              }
            }
          }
        }

        // Searchable column indices (excludes col 0 = row-link)
        var searchable = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29];
        var filterTooltip = [
          (flt.heading || 'Filter') + ':',
          '  text \u2013 ' + (flt.contains || 'Contains'),
          '  wort1 wort2 \u2013 ' + (flt.all_words || 'All words must occur'),
          '  ^text \u2013 ' + (flt.starts_with || 'Starts with'),
          '  text$ \u2013 ' + (flt.ends_with || 'Ends with'),
          '  =text \u2013 ' + (flt.exact_match || 'Exact match'),
          '  !text \u2013 ' + (flt.not_contains || 'Does not contain'),
          '  >n  <n  >=n  <=n \u2013 ' + (flt.numeric || 'Numeric'),
          '  100...200 \u2013 ' + (flt.date_range || 'Date range'),
          '  * \u2013 ' + (flt.not_empty || 'Field not empty'),
          '  = \u2013 ' + (flt.empty || 'Field empty')
        ].join('\n');
        this.api().columns(searchable).every(function() {
          var column = this;
          var input = $('<input type="text" placeholder="' + (flt.placeholder || 'Filter \u2026') + '" title="' + filterTooltip.replace(/"/g, '&quot;') + '" />')
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

      // Translated language (from HGV_TRANS)
      language: T.dt || {}
    });
  }

});
