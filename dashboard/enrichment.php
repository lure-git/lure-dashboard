<?php
require_once 'includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LURE - Enrichment</title>
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="plugins/jqvmap/jqvmap.min.css">
    <link rel="stylesheet" href="plugins/flag-icon-css/css/flag-icon.min.css">
    <style>
        .confidence-bar {
            height: 24px;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        .confidence-bar .bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }
        .confidence-bar .bar-label {
            position: absolute;
            right: 8px;
            top: 2px;
            font-size: 12px;
            font-weight: bold;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        .breakdown-table td:nth-child(2) {
            font-family: monospace;
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
        }
        .score-big {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1;
        }
        .label-confirmed { color: #dc3545; }
        .label-high { color: #fd7e14; }
        .label-moderate { color: #ffc107; }
        .label-low { color: #17a2b8; }
        .label-suspected { color: #6c757d; }
        .bg-confirmed { background-color: #dc3545; }
        .bg-high-conf { background-color: #fd7e14; }
        .bg-moderate-conf { background-color: #ffc107; }
        .bg-low-conf { background-color: #17a2b8; }
        .bg-suspected { background-color: #6c757d; }
        .ip-link {
            cursor: pointer;
            text-decoration: underline;
            color: #007bff;
        }
        .ip-link:hover { color: #0056b3; }
        .sensor-badge {
            display: inline-block;
            padding: 2px 6px;
            margin: 1px;
            border-radius: 3px;
            font-size: 0.8em;
            background: #e9ecef;
        }
        .feed-badge {
            display: inline-block;
            padding: 2px 6px;
            margin: 1px;
            border-radius: 3px;
            font-size: 0.8em;
            background: #d4edda;
            color: #155724;
        }
        #ip-search {
            font-size: 1.1em;
            font-family: monospace;
        }
        .dist-row { cursor: pointer; }
        .dist-row:hover { background-color: #f4f6f9; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <?php include 'includes/navbar.php'; ?>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Enrichment</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Enrichment</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                <!-- IP Lookup -->
                <div class="row">
                    <div class="col-12">
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-search mr-2"></i>IP Lookup</h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <input type="text" id="ip-search" class="form-control" placeholder="Enter IP address..." autocomplete="off">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" onclick="lookupIP()">
                                                    <i class="fas fa-search mr-1"></i> Lookup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">Search for any IP to see its confidence score and full scoring breakdown</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- IP Result (hidden until lookup) -->
                <div class="row" id="ip-result" style="display:none;">
                    <!-- Score Card -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <div id="result-score" class="score-big">--</div>
                                <div id="result-label" class="h4 mt-2">--</div>
                                <div id="result-ip" class="text-muted font-monospace mt-1" style="font-family:monospace; font-size:1.1em;"></div>
                                <div id="result-geo" class="mt-1"><small class="text-muted"></small></div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="h5 mb-0" id="result-feeds">0</div>
                                        <small class="text-muted">Feeds</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 mb-0" id="result-sensors">0</div>
                                        <small class="text-muted">Sensors</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 mb-0" id="result-days">0</div>
                                        <small class="text-muted">Days</small>
                                    </div>
                                </div>
                                <div class="row text-center mt-2">
                                    <div class="col-4">
                                        <div class="h5 mb-0" id="result-ports">0</div>
                                        <small class="text-muted">Ports</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 mb-0" id="result-snares">0</div>
                                        <small class="text-muted">Snares</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="h5 mb-0" id="result-novel">--</div>
                                        <small class="text-muted">Source</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Scoring Breakdown -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Scoring Breakdown</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table breakdown-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Signal</th>
                                            <th>Score</th>
                                            <th>Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody id="breakdown-table">
                                    </tbody>
                                    <tfoot>
                                        <tr class="font-weight-bold">
                                            <td>Total</td>
                                            <td id="breakdown-total" style="font-family:monospace; text-align:right;"></td>
                                            <td id="breakdown-capped"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Details</h3>
                            </div>
                            <div class="card-body">
                                <p><strong>GeoIP:</strong> <span id="result-geo-detail">--</span></p>
                                <p><strong>ASN / Org:</strong> <span id="result-asn">--</span></p>
                                <hr>
                                <p><strong>First Seen:</strong> <span id="result-first-seen">--</span></p>
                                <p><strong>Last Seen:</strong> <span id="result-last-seen">--</span></p>
                                <p><strong>Feed Sources:</strong></p>
                                <div id="result-feed-list" class="mb-2"><em class="text-muted">None</em></div>
                                <p><strong>Sensors Hit:</strong></p>
                                <div id="result-sensor-list"><em class="text-muted">None</em></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Not Found (hidden until lookup) -->
                <div class="row" id="ip-not-found" style="display:none;">
                    <div class="col-12">
                        <div class="callout callout-warning">
                            <h5><i class="fas fa-info-circle mr-2"></i>IP Not Found</h5>
                            <p>This IP has not been observed by any LURE sensor and is not in the enrichment database.</p>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3 id="stat-total">0</h3>
                                <p>Total IPs Scored</p>
                            </div>
                            <div class="icon"><i class="fas fa-database"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3 id="stat-avg">0%</h3>
                                <p>Avg Confidence</p>
                            </div>
                            <div class="icon"><i class="fas fa-percentage"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3 id="stat-novel">0</h3>
                                <p>Novel (LURE-only)</p>
                            </div>
                            <div class="icon"><i class="fas fa-eye"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3 id="stat-confirmed">0</h3>
                                <p>Confirmed Threats</p>
                            </div>
                            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Distribution + Top IPs -->
                <div class="row">
                    <!-- Confidence Distribution -->
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Confidence Distribution</h3>
                            </div>
                            <div class="card-body p-0">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Level</th>
                                            <th>Range</th>
                                            <th>Count</th>
                                            <th style="width:40%">Distribution</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dist-table">
                                    </tbody>
                                </table>
                            </div>
                            <div class="card-footer text-muted text-center">
                                <small>Last enriched: <span id="last-enriched">--</span></small>
                            </div>
                        </div>

                        <!-- Chart -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Confidence Breakdown</h3>
                            </div>
                            <div class="card-body">
                                <div style="position:relative; height:250px;">
                                    <canvas id="distChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Threats -->
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-skull-crossbones mr-2"></i>Top Threats</h3>
                                <div class="card-tools">
                                    <select id="top-filter" class="form-control form-control-sm" style="width:180px;" onchange="loadTop()">
                                        <option value="">All Levels</option>
                                        <option value="Confirmed">Confirmed</option>
                                        <option value="High Confidence">High Confidence</option>
                                        <option value="Moderate Confidence">Moderate Confidence</option>
                                        <option value="Low Confidence">Low Confidence</option>
                                        <option value="Suspected">Suspected</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <table class="table table-striped table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>IP Address</th>
                                            <th>Confidence</th>
                                            <th>CC</th>
                                            <th>Feeds</th>
                                            <th>Sensors</th>
                                            <th>Ports</th>
                                            <th>Days</th>
                                            <th>Snares</th>
                                            <th>Source</th>
                                        </tr>
                                    </thead>
                                    <tbody id="top-table">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GeoIP: Map + Top Countries -->
                <div class="row">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-globe-americas mr-2"></i>Threat Origins</h3>
                            </div>
                            <div class="card-body">
                                <div id="world-map" style="height: 350px;"></div>
                            </div>
                            <div class="card-footer text-center text-muted">
                                <small><a href="https://db-ip.com" target="_blank">IP Geolocation by DB-IP</a></small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-flag mr-2"></i>Top Countries</h3>
                            </div>
                            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Country</th>
                                            <th class="text-right">IPs</th>
                                            <th class="text-right">Avg Conf</th>
                                        </tr>
                                    </thead>
                                    <tbody id="country-table">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>LURE Dashboard &copy; 2026</strong>
        <div class="float-right d-none d-sm-inline-block">
            Enrichment Engine v3.0
        </div>
    </footer>
</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="plugins/jqvmap/jquery.vmap.min.js"></script>
<script src="plugins/jqvmap/maps/jquery.vmap.world.js"></script>

<script>
const LABEL_COLORS = {
    'Confirmed': '#dc3545',
    'High Confidence': '#fd7e14',
    'Moderate Confidence': '#ffc107',
    'Low Confidence': '#17a2b8',
    'Suspected': '#6c757d'
};

const LABEL_RANGES = {
    'Confirmed': '90-99%',
    'High Confidence': '70-89%',
    'Moderate Confidence': '50-69%',
    'Low Confidence': '35-49%',
    'Suspected': '30-34%'
};

const LABEL_CSS = {
    'Confirmed': 'label-confirmed',
    'High Confidence': 'label-high',
    'Moderate Confidence': 'label-moderate',
    'Low Confidence': 'label-low',
    'Suspected': 'label-suspected'
};

let distChart;

function loadSummary() {
    fetch('../api/confidence.php?action=summary')
        .then(r => r.json())
        .then(data => {
            const t = data.totals;
            document.getElementById('stat-total').textContent = Number(t.total_ips).toLocaleString();
            document.getElementById('stat-avg').textContent = t.avg_confidence + '%';
            document.getElementById('stat-novel').textContent = Number(t.novel_count).toLocaleString();

            // Find Confirmed count
            const confirmed = data.distribution.find(d => d.confidence_label === 'Confirmed');
            document.getElementById('stat-confirmed').textContent = confirmed ? Number(confirmed.count).toLocaleString() : '0';

            // Distribution table
            const tbody = document.getElementById('dist-table');
            tbody.innerHTML = '';
            const totalIPs = parseInt(t.total_ips) || 1;

            data.distribution.forEach(d => {
                const pct = (d.count / totalIPs * 100).toFixed(1);
                const color = LABEL_COLORS[d.confidence_label] || '#6c757d';
                const range = LABEL_RANGES[d.confidence_label] || '';
                const row = document.createElement('tr');
                row.className = 'dist-row';
                row.onclick = function() {
                    document.getElementById('top-filter').value = d.confidence_label;
                    loadTop();
                };
                row.innerHTML = `
                    <td><span style="color:${color}; font-weight:600;">${d.confidence_label}</span></td>
                    <td><small class="text-muted">${range}</small></td>
                    <td>${Number(d.count).toLocaleString()}</td>
                    <td>
                        <div class="confidence-bar" style="background:#e9ecef;">
                            <div class="bar-fill" style="width:${pct}%; background:${color};"></div>
                            <span class="bar-label">${pct}%</span>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Last enriched
            if (data.last_enriched) {
                const d = new Date(data.last_enriched);
                document.getElementById('last-enriched').textContent = d.toLocaleString();
            }

            // Charts
            renderDistChart(data.distribution);

            // Country table + map
            if (data.countries && data.countries.length > 0) {
                const ctbody = document.getElementById('country-table');
                ctbody.innerHTML = '';
                const mapData = {};

                data.countries.forEach(c => {
                    // Table row
                    const row = document.createElement('tr');
                    const cc = (c.geo_country_code || '').toLowerCase();
                    row.innerHTML = `
                        <td><span class="flag-icon flag-icon-${cc} mr-1"></span> <strong>${c.geo_country_code}</strong> <small class="text-muted">${c.geo_country}</small></td>
                        <td class="text-right">${Number(c.count).toLocaleString()}</td>
                        <td class="text-right">${c.avg_pct}%</td>
                    `;
                    ctbody.appendChild(row);

                    // Map data — jqvmap uses lowercase 2-letter codes
                    if (c.geo_country_code) {
                        mapData[c.geo_country_code.toLowerCase()] = parseInt(c.count);
                    }
                });

                renderWorldMap(mapData);
            }
        })
        .catch(err => console.error('Error loading summary:', err));
}

function renderWorldMap(mapData) {
    $('#world-map').vectorMap({
        map: 'world_en',
        backgroundColor: '#fff',
        borderColor: '#dee2e6',
        borderOpacity: 0.5,
        borderWidth: 0.5,
        color: '#e9ecef',
        hoverOpacity: 0.7,
        selectedColor: null,
        enableZoom: true,
        showTooltip: true,
        values: mapData,
        scaleColors: ['#c6dbef', '#dc3545'],
        normalizeFunction: 'polynomial',
        onLabelShow: function(e, el, code) {
            var count = mapData[code];
            if (count) {
                el.html(el.html() + ': ' + Number(count).toLocaleString() + ' IPs');
            }
        }
    });
}

function renderDistChart(distribution) {
    const ctx = document.getElementById('distChart').getContext('2d');
    if (distChart) distChart.destroy();

    distChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: distribution.map(d => d.confidence_label),
            datasets: [{
                data: distribution.map(d => d.count),
                backgroundColor: distribution.map(d => LABEL_COLORS[d.confidence_label] || '#6c757d')
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' }
            }
        }
    });
}

function loadTop() {
    const label = document.getElementById('top-filter').value;
    const url = '../api/confidence.php?action=top&limit=25' + (label ? '&label=' + encodeURIComponent(label) : '');

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('top-table');
            tbody.innerHTML = '';

            data.forEach(ip => {
                const color = LABEL_COLORS[ip.confidence_label] || '#6c757d';
                const cc = (ip.geo_country_code || '').toLowerCase();
                const ccDisplay = cc ? `<span class="flag-icon flag-icon-${cc} mr-1"></span>${ip.geo_country_code}` : '--';
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><span class="ip-link" onclick="searchIP('${ip.ip}')">${ip.ip}</span></td>
                    <td>
                        <span class="badge" style="background:${color}; color:#fff;">${ip.confidence_pct}%</span>
                    </td>
                    <td>${ccDisplay}</td>
                    <td>${ip.feed_count}</td>
                    <td>${ip.sensor_count}</td>
                    <td>${ip.service_count}</td>
                    <td>${ip.day_count}</td>
                    <td>${Number(ip.attack_count).toLocaleString()}</td>
                    <td>${ip.novel_threat == 1 ? '<span class="badge badge-warning">Novel</span>' : '<span class="badge badge-success">On Feeds</span>'}</td>
                `;
                tbody.appendChild(row);
            });
        })
        .catch(err => console.error('Error loading top:', err));
}

function searchIP(ip) {
    document.getElementById('ip-search').value = ip;
    lookupIP();
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function lookupIP() {
    const ip = document.getElementById('ip-search').value.trim();
    if (!ip) return;

    document.getElementById('ip-result').style.display = 'none';
    document.getElementById('ip-not-found').style.display = 'none';

    fetch('../api/confidence.php?action=lookup&ip=' + encodeURIComponent(ip))
        .then(r => r.json())
        .then(data => {
            if (!data.found) {
                document.getElementById('ip-not-found').style.display = '';
                return;
            }

            document.getElementById('ip-result').style.display = '';

            // Score card
            const cssClass = LABEL_CSS[data.confidence_label] || 'label-suspected';
            document.getElementById('result-score').textContent = data.confidence_pct + '%';
            document.getElementById('result-score').className = 'score-big ' + cssClass;
            document.getElementById('result-label').textContent = data.confidence_label;
            document.getElementById('result-label').className = 'h4 mt-2 ' + cssClass;
            document.getElementById('result-ip').textContent = data.ip;

            // GeoIP
            if (data.geo && data.geo.country) {
                const gcc = (data.geo.country_code || '').toLowerCase();
                document.getElementById('result-geo').innerHTML =
                    '<small><span class="flag-icon flag-icon-' + gcc + ' mr-1"></span>' + (data.geo.country_code || '') + ' — ' + data.geo.country + '</small>';
                document.getElementById('result-geo-detail').textContent =
                    (data.geo.country_code || '--') + ' — ' + (data.geo.country || 'Unknown') + ' (' + (data.geo.continent || '--') + ')';
                document.getElementById('result-asn').textContent =
                    (data.geo.asn ? 'AS' + data.geo.asn : '--') + ' / ' + (data.geo.org || 'Unknown');
            } else {
                document.getElementById('result-geo').innerHTML = '';
                document.getElementById('result-geo-detail').textContent = 'Unknown';
                document.getElementById('result-asn').textContent = 'Unknown';
            }

            document.getElementById('result-feeds').textContent = data.feed_count;
            document.getElementById('result-sensors').textContent = data.sensor_count;
            document.getElementById('result-days').textContent = data.day_count;
            document.getElementById('result-ports').textContent = data.service_count;
            document.getElementById('result-snares').textContent = data.attack_count.toLocaleString();
            document.getElementById('result-novel').textContent = data.novel_threat ? 'Novel' : 'On Feeds';

            // Breakdown table
            const tbody = document.getElementById('breakdown-table');
            tbody.innerHTML = '';
            data.breakdown.forEach(b => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${b.signal}</td>
                    <td>+${b.value}%</td>
                    <td><small class="text-muted">${b.detail}</small></td>
                `;
                tbody.appendChild(row);
            });
            document.getElementById('breakdown-total').textContent = data.total_before_cap + '%';
            document.getElementById('breakdown-capped').textContent = data.capped ? '(capped at 99%)' : '';

            // Details
            document.getElementById('result-first-seen').textContent = data.first_seen || 'Unknown';
            document.getElementById('result-last-seen').textContent = data.last_seen || 'Unknown';

            // Feed sources
            const feedDiv = document.getElementById('result-feed-list');
            if (data.feed_sources && data.feed_sources.length > 0) {
                feedDiv.innerHTML = data.feed_sources.map(f => `<span class="feed-badge">${f}</span>`).join(' ');
            } else {
                feedDiv.innerHTML = '<em class="text-muted">Not on any feeds (Novel threat)</em>';
            }

            // Sensors
            const sensorDiv = document.getElementById('result-sensor-list');
            if (data.sensors_seen && data.sensors_seen.length > 0) {
                sensorDiv.innerHTML = data.sensors_seen.map(s => `<span class="sensor-badge">${s}</span>`).join(' ');
            } else {
                sensorDiv.innerHTML = '<em class="text-muted">No sensor data</em>';
            }
        })
        .catch(err => console.error('Error looking up IP:', err));
}

// Enter key triggers lookup
document.getElementById('ip-search').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') lookupIP();
});

// Initial load
loadSummary();
loadTop();
</script>
</body>
</html>
