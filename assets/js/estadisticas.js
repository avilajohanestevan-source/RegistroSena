// Gráficos de estadisticas.php con amCharts 5 (se carga desde su CDN, así
// que necesita internet). Los datos los deja la página en
// window.datosEstadisticas (ver datosGraficos() en includes/estadisticas.php)
// y los colores son los de la paleta del SENA.
(function () {
  var datos = window.datosEstadisticas;
  if (!datos) return;

  var COLORES = {
    verde: 0x39A900, verdeOscuro: 0x007832, azul: 0x00304D, violeta: 0x71277A,
    oro: 0xFDC300, gris: 0xB9C2BC, rojo: 0xB3261E
  };

  function mensaje(id, texto) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = '<p class="grafico-vacio">' + texto + '</p>';
  }

  if (!window.am5 || !window.am5xy || !window.am5percent) {
    document.querySelectorAll('.grafico').forEach(function (el) {
      mensaje(el.id, 'No se pudieron cargar los gráficos: amCharts necesita conexión a internet.');
    });
    return;
  }

  function nuevaRaiz(id) {
    var root = am5.Root.new(id);
    root.setThemes([am5themes_Animated.new(root)]);
    if (window.am5locales_es_ES) root.locale = am5locales_es_ES;
    root.numberFormatter.set('numberFormat', '#,###');
    return root;
  }

  function hayDatos(filas, campos) {
    return filas.some(function (f) {
      return campos.some(function (c) { return f[c] > 0; });
    });
  }

  // Dona con el total en el centro. filas: [{categoria, valor}]
  function dona(id, filas, colores) {
    if (!document.getElementById(id)) return;
    if (!hayDatos(filas, ['valor'])) return mensaje(id, 'Sin datos en este rango.');
    var root = nuevaRaiz(id);
    var chart = root.container.children.push(am5percent.PieChart.new(root, {
      innerRadius: am5.percent(58), layout: root.verticalLayout
    }));
    var serie = chart.series.push(am5percent.PieSeries.new(root, {
      valueField: 'valor', categoryField: 'categoria',
      legendLabelText: '{category}', legendValueText: '{value}'
    }));
    serie.get('colors').set('colors', colores.map(function (c) { return am5.color(c); }));
    serie.slices.template.setAll({
      stroke: am5.color(0xffffff), strokeWidth: 2,
      tooltipText: '{category}: {value} ({valuePercentTotal.formatNumber("0.#")}%)'
    });
    serie.labels.template.set('forceHidden', true);
    serie.ticks.template.set('forceHidden', true);
    serie.data.setAll(filas);

    var total = filas.reduce(function (suma, f) { return suma + f.valor; }, 0);
    chart.seriesContainer.children.push(am5.Label.new(root, {
      text: String(total), centerX: am5.p50, centerY: am5.p50,
      fontSize: 28, fontWeight: '700', fill: am5.color(COLORES.azul)
    }));

    var leyenda = chart.children.push(am5.Legend.new(root, { centerX: am5.p50, x: am5.p50, marginTop: 10 }));
    leyenda.labels.template.set('fontSize', 13);
    leyenda.valueLabels.template.setAll({ fontSize: 13, fontWeight: '600' });
    leyenda.data.setAll(serie.dataItems);
    serie.appear(800, 100);
  }

  // Columnas agrupadas. filas: [{categoria, <campo>...}]; series: [{nombre, campo, color}]
  function columnas(id, filas, series) {
    if (!document.getElementById(id)) return;
    var campos = series.map(function (s) { return s.campo; });
    if (!hayDatos(filas, campos)) return mensaje(id, 'Sin datos en este rango.');
    var root = nuevaRaiz(id);
    var chart = root.container.children.push(am5xy.XYChart.new(root, {
      panX: false, panY: false, wheelX: 'none', wheelY: 'none', layout: root.verticalLayout, paddingLeft: 0
    }));

    var rendererX = am5xy.AxisRendererX.new(root, { minGridDistance: 20, cellStartLocation: 0.12, cellEndLocation: 0.88 });
    rendererX.labels.template.setAll({ fontSize: 12, oversizedBehavior: 'wrap', maxWidth: 80, textAlign: 'center' });
    rendererX.grid.template.set('forceHidden', true);
    var ejeX = chart.xAxes.push(am5xy.CategoryAxis.new(root, { categoryField: 'categoria', renderer: rendererX }));
    ejeX.data.setAll(filas);

    var rendererY = am5xy.AxisRendererY.new(root, {});
    rendererY.labels.template.set('fontSize', 12);
    var ejeY = chart.yAxes.push(am5xy.ValueAxis.new(root, { min: 0, maxPrecision: 0, renderer: rendererY }));

    series.forEach(function (s) {
      var serie = chart.series.push(am5xy.ColumnSeries.new(root, {
        name: s.nombre, xAxis: ejeX, yAxis: ejeY, valueYField: s.campo, categoryXField: 'categoria',
        fill: am5.color(s.color), stroke: am5.color(s.color),
        tooltip: am5.Tooltip.new(root, { labelText: '{name}: {valueY}' })
      }));
      serie.columns.template.setAll({ width: am5.percent(90), cornerRadiusTL: 4, cornerRadiusTR: 4, strokeOpacity: 0 });
      serie.data.setAll(filas);
      serie.appear(800);
    });

    if (series.length > 1) {
      var leyenda = chart.children.push(am5.Legend.new(root, { centerX: am5.p50, x: am5.p50 }));
      leyenda.data.setAll(chart.series.values);
    }
    var cursor = chart.set('cursor', am5xy.XYCursor.new(root, { behavior: 'none' }));
    cursor.lineX.set('visible', false);
    cursor.lineY.set('visible', false);
    chart.appear(800, 100);
  }

  // Barras horizontales con el valor al final. filas: [{categoria, valor}]
  function barras(id, filas, color) {
    if (!document.getElementById(id)) return;
    if (!hayDatos(filas, ['valor'])) return mensaje(id, 'Sin datos en este rango.');
    var root = nuevaRaiz(id);
    var chart = root.container.children.push(am5xy.XYChart.new(root, {
      panX: false, panY: false, wheelX: 'none', wheelY: 'none', paddingLeft: 0, paddingRight: 30
    }));

    var rendererY = am5xy.AxisRendererY.new(root, { inversed: true, minGridDistance: 16, cellStartLocation: 0.15, cellEndLocation: 0.85 });
    rendererY.labels.template.setAll({ fontSize: 12, oversizedBehavior: 'truncate', maxWidth: 160 });
    rendererY.grid.template.set('forceHidden', true);
    var ejeY = chart.yAxes.push(am5xy.CategoryAxis.new(root, { categoryField: 'categoria', renderer: rendererY }));
    ejeY.data.setAll(filas);

    var rendererX = am5xy.AxisRendererX.new(root, { minGridDistance: 50 });
    rendererX.labels.template.set('fontSize', 12);
    var ejeX = chart.xAxes.push(am5xy.ValueAxis.new(root, { min: 0, maxPrecision: 0, renderer: rendererX }));

    var serie = chart.series.push(am5xy.ColumnSeries.new(root, {
      xAxis: ejeX, yAxis: ejeY, valueXField: 'valor', categoryYField: 'categoria',
      fill: am5.color(color), stroke: am5.color(color),
      tooltip: am5.Tooltip.new(root, { labelText: '{categoryY}: {valueX}' })
    }));
    serie.columns.template.setAll({
      height: am5.percent(80), cornerRadiusTR: 4, cornerRadiusBR: 4, strokeOpacity: 0,
      tooltipText: '{categoryY}: {valueX}'
    });
    serie.bullets.push(function () {
      return am5.Bullet.new(root, {
        locationX: 1,
        sprite: am5.Label.new(root, {
          text: '{valueX}', populateText: true, centerY: am5.p50, dx: 6, fontSize: 12, fontWeight: '600'
        })
      });
    });
    serie.data.setAll(filas);
    serie.appear(800);
    chart.appear(800, 100);
  }

  var entradasYSalidas = [
    { nombre: 'Entradas', campo: 'entradas', color: COLORES.verde },
    { nombre: 'Salidas', campo: 'salidas', color: COLORES.oro }
  ];

  dona('graficoAsistencia', datos.asistencia, [COLORES.verde, COLORES.gris]);
  dona('graficoSalida', datos.salida, [COLORES.azul, COLORES.oro]);
  columnas('graficoTipos', datos.tipos, [
    { nombre: 'Registrados', campo: 'registrados', color: COLORES.azul },
    { nombre: 'Asistieron', campo: 'asistieron', color: COLORES.verde }
  ]);
  barras('graficoFallidos', datos.fallidos, COLORES.rojo);
  columnas('graficoHoras', datos.horas, entradasYSalidas);
  barras('graficoVeces', datos.veces, COLORES.violeta);
  barras('graficoPorteros', datos.porteros, COLORES.verdeOscuro);
  columnas('graficoDias', datos.dias, entradasYSalidas);
})();
