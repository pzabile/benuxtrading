/* BENUX Trading - Chart.js dashboards */
(function () {
  function ready(fn){ if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);} }
  ready(function () {
    if (typeof Chart === 'undefined') { setTimeout(arguments.callee, 100); return; }

    var dataEl = document.getElementById('benux-data');
    if (!dataEl) return;
    var data = JSON.parse(dataEl.textContent);

    var GOLD = '#c8a44a', GOLD_T = 'rgba(200,164,74,0.18)';
    var POS  = '#36c47a', NEG = '#ef4f4f', GRID = '#2a2a2a', TEXT = '#bdb6a3';

    Chart.defaults.color = TEXT;
    Chart.defaults.borderColor = GRID;
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif';

    function signedColor(v){ return Number(v) >= 0 ? POS : NEG; }

    // --- Equity curve ---
    var eq = document.getElementById('equityChart');
    if (eq && data.equity) {
      var labels = data.equity.map(function(p,i){ return p.t === 'Start' ? 'Start' : (p.t || '').slice(0,16); });
      var values = data.equity.map(function(p){ return p.equity; });
      new Chart(eq, {
        type: 'line',
        data: { labels: labels, datasets: [{
          label: 'Equity',
          data: values,
          borderColor: GOLD,
          backgroundColor: GOLD_T,
          fill: true, tension: 0.25, pointRadius: 2, borderWidth: 2,
        }]},
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }},
          scales: {
            x: { ticks: { maxTicksLimit: 8 }, grid: { color: GRID }},
            y: { grid: { color: GRID }, ticks: { callback: function(v){ return '$'+v; }}}
          }
        }
      });
    }

    function bar(canvasId, mapObj, opts){
      var c = document.getElementById(canvasId);
      if (!c || !mapObj) return;
      var keys   = Object.keys(mapObj);
      var values = keys.map(function(k){ return Number(mapObj[k]); });
      var colors = values.map(signedColor);
      new Chart(c, {
        type: 'bar',
        data: { labels: keys, datasets: [{
          label: 'P&L',
          data: values,
          backgroundColor: colors,
          borderRadius: 6,
        }]},
        options: Object.assign({
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: {
            label: function(ctx){ return '$' + ctx.parsed.y.toFixed(2); }
          }}},
          scales: {
            x: { grid: { color: GRID }},
            y: { grid: { color: GRID }, ticks: { callback: function(v){ return '$'+v; }}}
          }
        }, opts || {})
      });
    }

    bar('pairChart',    data.pairs);
    bar('hourChart',    data.hours);
    bar('sessionChart', data.sessions);
    bar('strategyChart',data.strategy);
    bar('dayChart',     data.days);
  });
})();
