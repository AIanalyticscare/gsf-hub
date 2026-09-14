( function () {
	'use strict';

	function parseDataset( canvas ) {
		try {
			return JSON.parse( canvas.dataset.chart || '{}' );
		} catch ( error ) {
			return {};
		}
	}

	function baseOptions() {
		return {
			responsive: true,
			maintainAspectRatio: false,
			plugins: {
				legend: {
					labels: {
						boxWidth: 12,
						boxHeight: 12,
						color: '#0a3742',
						font: {
							weight: '700'
						}
					}
				},
				tooltip: {
					backgroundColor: '#0a3742',
					titleColor: '#ffffff',
					bodyColor: '#ffffff',
					padding: 12,
					displayColors: true
				}
			}
		};
	}

	function renderCircular( canvas, type ) {
		var data = parseDataset( canvas );
		new Chart( canvas, {
			type: type,
			data: {
				labels: data.labels || [],
				datasets: [
					{
						data: data.values || [],
						backgroundColor: [ '#1ca6a6', '#005e7a', '#ff7a5c' ],
						borderColor: '#ffffff',
						borderWidth: 3,
						hoverOffset: 8
					}
				]
			},
			options: Object.assign( baseOptions(), {
				cutout: type === 'doughnut' ? '62%' : 0,
				plugins: Object.assign( baseOptions().plugins, {
					legend: {
						position: 'bottom',
						labels: baseOptions().plugins.legend.labels
					}
				} )
			} )
		} );
	}

	function renderDoughnut( canvas ) {
		renderCircular( canvas, 'doughnut' );
	}

	function renderPie( canvas ) {
		renderCircular( canvas, 'pie' );
	}

	function renderLine( canvas ) {
		var data = parseDataset( canvas );
		new Chart( canvas, {
			type: 'line',
			data: {
				labels: data.labels || [],
				datasets: [
					{
						label: data.label || 'Completions',
						data: data.values || [],
						borderColor: '#1ca6a6',
						backgroundColor: 'rgba(28, 166, 166, 0.14)',
						borderWidth: 3,
						fill: true,
						tension: 0.35,
						pointBackgroundColor: '#005e7a',
						pointBorderColor: '#ffffff',
						pointBorderWidth: 2,
						pointRadius: 4
					}
				]
			},
			options: Object.assign( baseOptions(), {
				scales: {
					x: {
						grid: {
							display: false
						},
						ticks: {
							color: '#54717b',
							font: {
								weight: '700'
							}
						}
					},
					y: {
						beginAtZero: true,
						grid: {
							color: 'rgba(10, 55, 66, 0.1)'
						},
						ticks: {
							color: '#54717b',
							font: {
								weight: '700'
							}
						}
					}
				}
			} )
		} );
	}

	function renderBar( canvas ) {
		var data = parseDataset( canvas );
		new Chart( canvas, {
			type: 'bar',
			data: {
				labels: data.labels || [],
				datasets: [
					{
						label: data.label || 'Projects',
						data: data.values || [],
						backgroundColor: [ '#005e7a', '#1ca6a6', '#ff7a5c', '#f8bd42', '#54717b', '#0a3742', '#89d9d3', '#e6f4f2' ],
						borderRadius: 8,
						borderSkipped: false
					}
				]
			},
			options: Object.assign( baseOptions(), {
				plugins: Object.assign( baseOptions().plugins, {
					legend: {
						display: false
					}
				} ),
				scales: {
					x: {
						grid: {
							display: false
						},
						ticks: {
							color: '#54717b',
							font: {
								weight: '800'
							}
						}
					},
					y: {
						beginAtZero: true,
						grid: {
							color: 'rgba(10, 55, 66, 0.1)'
						},
						ticks: {
							color: '#54717b'
						}
					}
				}
			} )
		} );
	}

	function renderGroupedBar( canvas ) {
		var data = parseDataset( canvas );
		var colors = [ '#005e7a', '#1ca6a6', '#ff7a5c' ];
		new Chart( canvas, {
			type: 'bar',
			data: {
				labels: data.labels || [],
				datasets: ( data.datasets || [] ).map( function ( dataset, index ) {
					return {
						label: dataset.label || '',
						data: dataset.data || [],
						backgroundColor: colors[ index % colors.length ],
						borderRadius: 8,
						borderSkipped: false
					};
				} )
			},
			options: Object.assign( baseOptions(), {
				plugins: Object.assign( baseOptions().plugins, {
					legend: {
						position: 'bottom',
						labels: baseOptions().plugins.legend.labels
					}
				} ),
				scales: {
					x: {
						grid: {
							display: false
						},
						ticks: {
							color: '#54717b',
							font: {
								weight: '800'
							}
						}
					},
					y: {
						beginAtZero: true,
						max: data.max || undefined,
						suggestedMax: data.suggestedMax || ( data.max ? undefined : 100 ),
						grid: {
							color: 'rgba(10, 55, 66, 0.1)'
						},
						ticks: {
							color: '#54717b',
							stepSize: data.stepSize || undefined
						}
					}
				}
			} )
		} );
	}

	function renderStackedBar( canvas ) {
		var data = parseDataset( canvas );
		var colors = [ '#005e7a', '#1ca6a6', '#ff7a5c', '#f8bd42', '#54717b', '#0a3742' ];
		new Chart( canvas, {
			type: 'bar',
			data: {
				labels: data.labels || [],
				datasets: ( data.datasets || [] ).map( function ( dataset, index ) {
					return {
						label: dataset.label || '',
						data: dataset.data || [],
						backgroundColor: colors[ index % colors.length ],
						borderRadius: 8,
						borderSkipped: false
					};
				} )
			},
			options: Object.assign( baseOptions(), {
				plugins: Object.assign( baseOptions().plugins, {
					legend: {
						position: 'bottom',
						labels: baseOptions().plugins.legend.labels
					}
				} ),
				scales: {
					x: {
						stacked: true,
						grid: {
							display: false
						},
						ticks: {
							color: '#54717b',
							font: {
								weight: '800'
							}
						}
					},
					y: {
						stacked: true,
						beginAtZero: true,
						suggestedMax: data.suggestedMax || undefined,
						grid: {
							color: 'rgba(10, 55, 66, 0.1)'
						},
						ticks: {
							color: '#54717b'
						}
					}
				}
			} )
		} );
	}

	function initLeafletMaps() {
		if ( typeof L === 'undefined' ) {
			return;
		}

		document.querySelectorAll( '[data-gsf-leaflet-map]' ).forEach( function ( element ) {
			var points = [];
			var bounds;

			try {
				points = JSON.parse( element.dataset.points || '[]' );
			} catch ( error ) {
				points = [];
			}

			if ( element.dataset.mapInitialized === '1' ) {
				return;
			}

			element.dataset.mapInitialized = '1';

			var map = L.map( element, {
				scrollWheelZoom: false,
				zoomControl: true
			} ).setView( [ 17.5, -72 ], 5 );

			L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 18,
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
			} ).addTo( map );

			points.forEach( function ( point ) {
				if ( typeof point.lat === 'undefined' || typeof point.lng === 'undefined' ) {
					return;
				}

				var latLng = [ Number( point.lat ), Number( point.lng ) ];
				var marker = L.circle( latLng, {
					radius: Number( point.radius ) || 15000,
					color: '#ffffff',
					weight: 2,
					fillColor: '#007c92',
					fillOpacity: 0.82
				} ).addTo( map );

				marker.bindPopup( '<strong>' + point.label + '</strong><br>' + point.count + ' project' + ( Number( point.count ) === 1 ? '' : 's' ) );

				if ( bounds ) {
					bounds.extend( latLng );
				} else {
					bounds = L.latLngBounds( latLng, latLng );
				}
			} );

			if ( bounds ) {
				map.fitBounds( bounds.pad( 0.55 ), {
					maxZoom: 6
				} );
			}

			setTimeout( function () {
				map.invalidateSize();
			}, 120 );
		} );
	}

	function initCharts() {
		if ( typeof Chart === 'undefined' ) {
			return;
		}

		document.querySelectorAll( '[data-gsf-chart="doughnut"]' ).forEach( renderDoughnut );
		document.querySelectorAll( '[data-gsf-chart="pie"]' ).forEach( renderPie );
		document.querySelectorAll( '[data-gsf-chart="line"]' ).forEach( renderLine );
		document.querySelectorAll( '[data-gsf-chart="bar"]' ).forEach( renderBar );
		document.querySelectorAll( '[data-gsf-chart="groupedbar"]' ).forEach( renderGroupedBar );
		document.querySelectorAll( '[data-gsf-chart="stackedbar"]' ).forEach( renderStackedBar );
	}

	function init() {
		initCharts();
		initLeafletMaps();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
