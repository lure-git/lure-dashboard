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
        .confidence-bar { height: 24px; border-radius: 4px; position: relative; overflow: hidden; }
        .confidence-bar .bar-fill { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
        .confidence-bar .bar-label { position: absolute; right: 8px; top: 2px; font-size: 12px; font-weight: bold; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3); }
        .breakdown-table td:nth-child(2) { font-family: monospace; text-align: right; font-weight: bold; white-space: nowrap; }
        .score-big { font-size: 3.5rem; font-weight: 700; line-height: 1; }
        .label-confirmed { color: #dc3545; }
        .label-high { color: #fd7e14; }
        .label-moderate { color: #ffc107; }
        .label-low { color: #17a2b8; }
        .label-suspected { color: #6c757d; }
        .ip-link { cursor: pointer; text-decoration: underline; color: #007bff; }
        .ip-link:hover { color: #0056b3; }
        .sensor-badge { display: inline-block; padding: 2px 6px; margin: 1px; border-radius: 3px; font-size: 0.8em; background: #e9ecef; }
        .feed-badge { display: inline-block; padding: 2px 6px; margin: 1px; border-radius: 3px; font-size: 0.8em; background: #d4edda; color: #155724; }
        #ip-search { font-size: 1.1em; font-family: monospace; }
        .dist-row { cursor: pointer; }
        .dist-row:hover { background-color: #f4f6f9; }
        .scoring-signal { border-left: 4px solid #007bff; padding: 12px 16px; margin-bottom: 12px; background: #f8f9fa; border-radius: 0 4px 4px 0; }
        .scoring-signal h6 { margin-bottom: 4px; }
        .feed-status-ok { color: #28a745; }
        .feed-status-fail { color: #dc3545; }
        .feed-status-stale { color: #ffc107; }
        .nav-tabs .nav-link { font-size: 1.05em; }
        .tab-pane { padding-top: 20px; }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include 'includes/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6"><h1 class="m-0">Enrichment</h1></div>
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
                <ul class="nav nav-tabs" id="enrichmentTabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" id="overview-tab" data-toggle="tab" href="#tab-overview" role="tab"><i class="fas fa-chart-bar mr-1"></i> Overview</a></li>
                    <li class="nav-item"><a class="nav-link" id="geo-tab" data-toggle="tab" href="#tab-geo" role="tab"><i class="fas fa-globe-americas mr-1"></i> GeoIP</a></li>
                    <li class="nav-item"><a class="nav-link" id="feeds-tab" data-toggle="tab" href="#tab-feeds" role="tab"><i class="fas fa-rss mr-1"></i> Threat Feeds</a></li>
                    <li class="nav-item"><a class="nav-link" id="scoring-tab" data-toggle="tab" href="#tab-scoring" role="tab"><i class="fas fa-calculator mr-1"></i> Scoring</a></li>
                </ul>
                <div class="tab-content" id="enrichmentTabContent">

<!-- TAB 1: OVERVIEW -->
<div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
    <div class="row"><div class="col-12"><div class="card card-outline card-primary"><div class="card-header"><h3 class="card-title"><i class="fas fa-search mr-2"></i>IP Lookup</h3></div><div class="card-body"><div class="row"><div class="col-md-6"><div class="input-group"><input type="text" id="ip-search" class="form-control" placeholder="Enter IP address..." autocomplete="off"><div class="input-group-append"><button class="btn btn-primary" onclick="lookupIP()"><i class="fas fa-search mr-1"></i> Lookup</button></div></div></div><div class="col-md-6"><small class="text-muted">Search for any IP to see its confidence score and full scoring breakdown</small></div></div></div></div></div></div>

    <div class="row" id="ip-result" style="display:none;">
        <div class="col-md-4"><div class="card"><div class="card-body text-center">
            <div id="result-score" class="score-big">--</div>
            <div id="result-label" class="h4 mt-2">--</div>
            <div id="result-ip" class="text-muted" style="font-family:monospace; font-size:1.1em;"></div>
            <div id="result-geo" class="mt-1"></div>
            <hr>
            <div class="row text-center">
                <div class="col-4"><div class="h5 mb-0" id="result-feeds">0</div><small class="text-muted">Feeds</small></div>
                <div class="col-4"><div class="h5 mb-0" id="result-sensors">0</div><small class="text-muted">Sensors</small></div>
                <div class="col-4"><div class="h5 mb-0" id="result-days">0</div><small class="text-muted">Days</small></div>
            </div>
            <div class="row text-center mt-2">
                <div class="col-4"><div class="h5 mb-0" id="result-ports">0</div><small class="text-muted">Ports</small></div>
                <div class="col-4"><div class="h5 mb-0" id="result-snares">0</div><small class="text-muted">Snares</small></div>
                <div class="col-4"><div class="h5 mb-0" id="result-novel">--</div><small class="text-muted">Source</small></div>
            </div>
        </div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-header"><h3 class="card-title">Scoring Breakdown</h3></div><div class="card-body p-0"><table class="table breakdown-table mb-0"><thead><tr><th>Signal</th><th>Score</th><th>Detail</th></tr></thead><tbody id="breakdown-table"></tbody><tfoot><tr class="font-weight-bold"><td>Total</td><td id="breakdown-total" style="font-family:monospace;text-align:right;"></td><td id="breakdown-capped"></td></tr></tfoot></table></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-header"><h3 class="card-title">Details</h3></div><div class="card-body">
            <p><strong>GeoIP:</strong> <span id="result-geo-detail">--</span></p>
            <p><strong>ASN / Org:</strong> <span id="result-asn">--</span></p><hr>
            <p><strong>First Seen:</strong> <span id="result-first-seen">--</span></p>
            <p><strong>Last Seen:</strong> <span id="result-last-seen">--</span></p>
            <p><strong>Feed Sources:</strong></p><div id="result-feed-list" class="mb-2"><em class="text-muted">None</em></div>
            <p><strong>Sensors Hit:</strong></p><div id="result-sensor-list"><em class="text-muted">None</em></div>
        </div></div></div>
    </div>

    <div class="row" id="ip-not-found" style="display:none;"><div class="col-12"><div class="callout callout-warning"><h5><i class="fas fa-info-circle mr-2"></i>IP Not Found</h5><p>This IP has not been observed by any LURE sensor and is not in the enrichment database.</p></div></div></div>

    <div class="row">
        <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3 id="stat-total">0</h3><p>Total IPs Scored</p></div><div class="icon"><i class="fas fa-database"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3 id="stat-avg">0%</h3><p>Avg Confidence</p></div><div class="icon"><i class="fas fa-percentage"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-warning"><div class="inner"><h3 id="stat-novel">0</h3><p>Novel (LURE-only)</p></div><div class="icon"><i class="fas fa-eye"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-danger"><div class="inner"><h3 id="stat-confirmed">0</h3><p>Confirmed Threats</p></div><div class="icon"><i class="fas fa-exclamation-triangle"></i></div></div></div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Confidence Distribution</h3></div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Level</th><th>Range</th><th>Count</th><th style="width:40%">Distribution</th></tr></thead><tbody id="dist-table"></tbody></table></div><div class="card-footer text-muted text-center"><small>Last enriched: <span id="last-enriched">--</span></small></div></div>
            <div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Confidence Breakdown</h3></div><div class="card-body"><div style="position:relative; height:250px;"><canvas id="distChart"></canvas></div></div></div>
        </div>
        <div class="col-lg-7"><div class="card">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-skull-crossbones mr-2"></i>Top Threats</h3><div class="card-tools"><select id="top-filter" class="form-control form-control-sm" style="width:180px;" onchange="loadTop()"><option value="">All Levels</option><option value="Confirmed">Confirmed</option><option value="High Confidence">High Confidence</option><option value="Moderate Confidence">Moderate Confidence</option><option value="Low Confidence">Low Confidence</option><option value="Suspected">Suspected</option></select></div></div>
            <div class="card-body p-0"><table class="table table-striped table-sm mb-0"><thead><tr><th>IP Address</th><th>Confidence</th><th>CC</th><th>Feeds</th><th>Sensors</th><th>Ports</th><th>Days</th><th>Snares</th><th>Source</th></tr></thead><tbody id="top-table"></tbody></table></div>
        </div></div>
    </div>
</div>

<!-- TAB 2: THREAT FEEDS -->
<div class="tab-pane fade" id="tab-feeds" role="tabpanel">
    <div class="row">
        <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3 id="feed-stat-count">0</h3><p>Active Feeds</p></div><div class="icon"><i class="fas fa-rss"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3 id="feed-stat-ips">0</h3><p>Total IPs</p></div><div class="icon"><i class="fas fa-list"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-warning"><div class="inner"><h3 id="feed-stat-cidrs">0</h3><p>Total CIDRs</p></div><div class="icon"><i class="fas fa-network-wired"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-secondary"><div class="inner"><h3 style="font-size:1.3rem;">2x Daily</h3><p>Update Frequency</p></div><div class="icon"><i class="fas fa-clock"></i></div></div></div>
    </div>
    <div class="row"><div class="col-12"><div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i>Feed Value — LURE Matches per Feed</h3><div class="card-tools"><small class="text-muted">How many snared IPs each feed corroborates</small></div></div>
        <div class="card-body"><div style="position:relative; height:300px;"><canvas id="feedMatchChart"></canvas></div></div>
    </div></div></div>
    <div class="row"><div class="col-12"><div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-rss mr-2"></i>Threat Intelligence Feeds</h3></div>
        <div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Feed</th><th>Description</th><th>Category</th><th class="text-right">Entries</th><th>Last Changed</th><th>Status</th></tr></thead><tbody id="feeds-table"></tbody></table></div>
        <div class="card-footer text-muted"><small><strong>Last published:</strong> <span id="feed-last-published">--</span> &nbsp;|&nbsp; <strong>Last synced:</strong> <span id="feed-last-synced">--</span> &nbsp;|&nbsp; Feeds are updated at 02:00 and 14:00 UTC, enrichment runs at 04:00 and 16:00 UTC.</small></div>
    </div></div></div>
    <div class="row"><div class="col-12"><div class="callout callout-info">
        <h5><i class="fas fa-info-circle mr-2"></i>About Threat Feeds</h5>
        <p>LURE aggregates <span id="feed-callout-count"></span> publicly available threat intelligence feeds into a unified feed cache. Fresh feeds are downloaded twice daily, deduplicated, and cross-referenced against LURE sensor observations during enrichment. Each feed match adds <strong>+4%</strong> to an IP's confidence score.</p>
        <p class="mb-0">CIDRs from feeds (e.g., Spamhaus DROP /24 ranges) are not expanded. If a LURE-snared IP falls within a feed CIDR, it counts as feed corroboration.</p>
    </div></div></div>
</div>

<!-- TAB 3: GEOIP -->
<div class="tab-pane fade" id="tab-geo" role="tabpanel">
    <div class="row">
        <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h3 id="geo-stat-countries">0</h3><p>Countries Seen</p></div><div class="icon"><i class="fas fa-flag"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h3 id="geo-stat-resolved">0</h3><p>IPs Resolved</p></div><div class="icon"><i class="fas fa-map-marker-alt"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-warning"><div class="inner"><h3 id="geo-stat-total">0</h3><p>Total IPs</p></div><div class="icon"><i class="fas fa-database"></i></div></div></div>
        <div class="col-lg-3 col-6"><div class="small-box bg-secondary"><div class="inner"><h3 style="font-size:1.3rem;">Monthly</h3><p>DB-IP Update Cycle</p></div><div class="icon"><i class="fas fa-sync"></i></div></div></div>
    </div>
    <div class="row">
        <div class="col-lg-7"><div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-globe-americas mr-2"></i>Threat Origins</h3></div><div class="card-body"><div id="world-map" style="height: 350px;"></div></div><div class="card-footer text-center text-muted"><small><a href="https://db-ip.com" target="_blank">IP Geolocation by DB-IP</a></small></div></div></div>
        <div class="col-lg-5"><div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-flag mr-2"></i>Top Countries</h3></div><div class="card-body p-0" style="max-height:400px; overflow-y:auto;"><table class="table table-sm table-striped mb-0"><thead><tr><th>Country</th><th class="text-right">IPs</th><th class="text-right">Avg Conf</th></tr></thead><tbody id="geo-country-table"></tbody></table></div></div></div>
    </div>
    <div class="row">
        <div class="col-lg-4"><div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-globe mr-2"></i>Continents</h3></div><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Continent</th><th class="text-right">IPs</th></tr></thead><tbody id="geo-continent-table"></tbody></table></div></div></div>
        <div class="col-lg-8"><div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-server mr-2"></i>Top ASNs</h3></div><div class="card-body p-0"><table class="table table-sm table-striped mb-0"><thead><tr><th>ASN</th><th>Organization</th><th class="text-right">IPs</th><th class="text-right">Avg Conf</th></tr></thead><tbody id="geo-asn-table"></tbody></table></div></div></div>
    </div>
    <div class="row"><div class="col-12"><div class="callout callout-info">
        <h5><i class="fas fa-info-circle mr-2"></i>About GeoIP Data</h5>
        <p>GeoIP enrichment uses the <strong>DB-IP Lite</strong> databases (Country + ASN), updated monthly. Each IP is resolved during the enrichment run using memory-mapped .mmdb files with negligible performance impact.</p>
        <p class="mb-0">GeoIP data is <strong>informational only</strong> and does not affect the confidence score. Country and ASN information supports investigation and threat attribution, not blocking decisions. GeoIP databases are refreshed on the 1st of each month; enrichment runs apply the latest data twice daily at 04:05 and 16:05 UTC.</p>
        <p><small>Last enriched: <span id="geo-last-enriched">--</span> &nbsp;|&nbsp; <a href="https://db-ip.com" target="_blank">IP Geolocation by DB-IP</a></small></p>
    </div></div></div>
</div>

<!-- TAB 4: SCORING -->
<div class="tab-pane fade" id="tab-scoring" role="tabpanel">
    <div class="row">
        <div class="col-lg-8"><div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-calculator mr-2"></i>Confidence Scoring Model v3.0</h3></div><div class="card-body">
            <p>Every IP that hits a LURE bait interface is scored with a <strong>confidence percentage (30–99%)</strong> answering: <em>"How confident are we that this IP is intentionally malicious?"</em></p>
            <p>The score is <strong>not a blocking decision</strong> — every IP that touches a bait interface should be blocked. The confidence percentage is for <strong>intelligence prioritization</strong>: investigation and dashboard visibility.</p>
            <p>Scores are computed <strong>twice daily</strong> at 04:05 and 16:05 UTC.</p><hr>
            <div class="scoring-signal"><h6><i class="fas fa-crosshairs mr-1"></i> Base (Bait Hit) — <strong>30%</strong></h6><small class="text-muted">Every IP starts at 30%. There is no legitimate traffic on bait interfaces. A single hit could be a misconfigured device, so evidence must accumulate.</small></div>
            <div class="scoring-signal" style="border-left-color:#28a745;"><h6><i class="fas fa-calendar-check mr-1"></i> Persistence — <strong>+15% to +25%</strong></h6><small class="text-muted">The strongest behavioral signal. An IP returning day after day demonstrates clear intent.</small><table class="table table-sm mt-2 mb-0" style="max-width:300px;"><tr><td>2–3 days</td><td class="text-right">+15%</td></tr><tr><td>4–7 days</td><td class="text-right">+20%</td></tr><tr><td>8–14 days</td><td class="text-right">+23%</td></tr><tr><td>15+ days</td><td class="text-right">+25%</td></tr></table></div>
            <div class="scoring-signal" style="border-left-color:#fd7e14;"><h6><i class="fas fa-satellite-dish mr-1"></i> Sensor Coverage — <strong>+2% to +20%</strong></h6><small class="text-muted">Based on percentage of total deployed sensors hit. Scales automatically to any deployment size.</small><table class="table table-sm mt-2 mb-0" style="max-width:300px;"><tr><td>&gt;1 sensor</td><td class="text-right">+2%</td></tr><tr><td>10–19%</td><td class="text-right">+5%</td></tr><tr><td>20–39%</td><td class="text-right">+8%</td></tr><tr><td>40–59%</td><td class="text-right">+12%</td></tr><tr><td>60–79%</td><td class="text-right">+16%</td></tr><tr><td>80–100%</td><td class="text-right">+20%</td></tr></table></div>
            <div class="scoring-signal" style="border-left-color:#dc3545;"><h6><i class="fas fa-bolt mr-1"></i> Volume (Snares) — <strong>+5% to +20%</strong></h6><small class="text-muted">Repeated contact with bait interfaces indicates deliberate scanning, not accidental traffic.</small><table class="table table-sm mt-2 mb-0" style="max-width:300px;"><tr><td>10–49</td><td class="text-right">+5%</td></tr><tr><td>50–199</td><td class="text-right">+10%</td></tr><tr><td>200–999</td><td class="text-right">+15%</td></tr><tr><td>1,000+</td><td class="text-right">+20%</td></tr></table></div>
            <div class="scoring-signal" style="border-left-color:#6f42c1;"><h6><i class="fas fa-shield-alt mr-1"></i> Feed Corroboration — <strong>+4% per feed</strong></h6><small class="text-muted">External threat intelligence feeds provide supporting evidence. Each feed match adds +4%. Currently <span id="scoring-feed-count"></span> feeds aggregated.</small></div>
            <div class="scoring-signal" style="border-left-color:#17a2b8;"><h6><i class="fas fa-ethernet mr-1"></i> Port Scanning — <strong>+1% per 5 ports (max +10%)</strong></h6><small class="text-muted">Breadth of reconnaissance across unique destination ports.</small></div>
            <p class="mt-3"><strong>Cap: 99%</strong> — We never claim 100% certainty.</p>
        </div></div></div>
        <div class="col-lg-4">
            <div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-tags mr-2"></i>Confidence Labels</h3></div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Label</th><th>Range</th><th>Meaning</th></tr></thead><tbody>
                <tr><td><span style="color:#dc3545;font-weight:600;">Confirmed</span></td><td>90–99%</td><td><small>Overwhelming evidence from multiple sources</small></td></tr>
                <tr><td><span style="color:#fd7e14;font-weight:600;">High Confidence</span></td><td>70–89%</td><td><small>Strong evidence across several signals</small></td></tr>
                <tr><td><span style="color:#ffc107;font-weight:600;">Moderate Confidence</span></td><td>50–69%</td><td><small>Multiple corroborating signals</small></td></tr>
                <tr><td><span style="color:#17a2b8;font-weight:600;">Low Confidence</span></td><td>35–49%</td><td><small>Some evidence beyond initial contact</small></td></tr>
                <tr><td><span style="color:#6c757d;font-weight:600;">Suspected</span></td><td>30–34%</td><td><small>Single sensor hit, minimal evidence</small></td></tr>
            </tbody></table></div></div>
            <div class="card"><div class="card-header"><h3 class="card-title"><i class="fas fa-flask mr-2"></i>Scoring Examples</h3></div><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Scenario</th><th>Score</th></tr></thead><tbody>
                <tr><td><small>1 sensor, 1 port, 1 day, 1 snare</small></td><td><span class="badge" style="background:#6c757d;color:#fff;">30%</span></td></tr>
                <tr><td><small>1 sensor, 1 port, 2 days, 10 snares</small></td><td><span class="badge" style="background:#ffc107;color:#fff;">50%</span></td></tr>
                <tr><td><small>4 sensors (24%), 10 ports, 5 days, 100 snares</small></td><td><span class="badge" style="background:#fd7e14;color:#fff;">70%</span></td></tr>
                <tr><td><small>9 feeds, 16 sensors (84%), 60 ports, 16 days</small></td><td><span class="badge" style="background:#dc3545;color:#fff;">99%</span></td></tr>
                <tr><td><small>Novel: 15 sensors, 1530 ports, 18 days, 2000 snares</small></td><td><span class="badge" style="background:#dc3545;color:#fff;">99%</span></td></tr>
            </tbody></table></div></div>
            <div class="callout callout-warning"><h5><i class="fas fa-lightbulb mr-2"></i>Key Principles</h5><ul class="mb-0">
                <li><small>LURE data is primary; feeds are supporting</small></li>
                <li><small>Sensor coverage uses percentages, not raw counts</small></li>
                <li><small>GeoIP is informational — does not affect scoring</small></li>
                <li><small>Scoring is batch (twice daily), not inline</small></li>
            </ul></div>
        </div>
    </div>
</div>

                </div>
            </div>
        </section>
    </div>
    <footer class="main-footer"><strong>LURE Dashboard &copy; 2026</strong><div class="float-right d-none d-sm-inline-block">Enrichment Engine v3.1</div></footer>
</div>

<script src="plugins/jquery/jquery.min.js"></script>
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="plugins/jqvmap/jquery.vmap.min.js"></script>
<script src="plugins/jqvmap/maps/jquery.vmap.world.js"></script>
<script>
const LABEL_COLORS = {'Confirmed':'#dc3545','High Confidence':'#fd7e14','Moderate Confidence':'#ffc107','Low Confidence':'#17a2b8','Suspected':'#6c757d'};
const LABEL_RANGES = {'Confirmed':'90-99%','High Confidence':'70-89%','Moderate Confidence':'50-69%','Low Confidence':'35-49%','Suspected':'30-34%'};
const LABEL_CSS = {'Confirmed':'label-confirmed','High Confidence':'label-high','Moderate Confidence':'label-moderate','Low Confidence':'label-low','Suspected':'label-suspected'};
const CONTINENT_NAMES = {'AF':'Africa','AN':'Antarctica','AS':'Asia','EU':'Europe','NA':'North America','OC':'Oceania','SA':'South America'};
const FEED_INFO = {
    'spamhaus_drop':{desc:'Spamhaus DROP/EDROP — hijacked netblocks',category:'Botnets / Hijacked',type:'CIDR'},
    'blocklist_de_all':{desc:'Blocklist.de — all reported attack IPs',category:'Attacks',type:'IP'},
    'blocklist_de_ssh':{desc:'Blocklist.de — SSH brute-force attackers',category:'SSH Brute-force',type:'IP'},
    'blocklist_de_strongips':{desc:'Blocklist.de — persistent high-volume attackers',category:'Persistent Attacks',type:'IP'},
    'cins_army':{desc:'CINS Army — Collective Intelligence Network bad IPs',category:'Scanning / Attacks',type:'IP'},
    'dshield':{desc:'DShield — SANS top attacking subnets',category:'Scanning',type:'CIDR'},
    'emerging_threats_compromised':{desc:'Emerging Threats — known compromised hosts',category:'Compromised',type:'IP'},
    'ipsum_level1':{desc:'IPsum — IPs seen on 1+ blocklists (broad coverage)',category:'Aggregated Intel',type:'IP'},
    'greensnow':{desc:'GreenSnow — scanning/attacking IPs (last 24h)',category:'Scanning / Attacks',type:'IP'},
    'bruteforceblocker':{desc:'BruteForceBlocker — SSH brute-force IPs',category:'SSH Brute-force',type:'IP'},
    'binarydefense':{desc:'Binary Defense — threat intelligence IP banlist',category:'Threat Intel',type:'IP'},
    'dataplane_sshpwauth':{desc:'Dataplane.org — SSH password auth attackers',category:'SSH Brute-force',type:'IP'},
    'dataplane_sshpwauth:{desc:'Dataplane.org — SSH password auth attackers',category:'SSH Brute-force',type:'IP'},
};
let distChart;

function loadSummary(){
    fetch('../api/confidence.php?action=summary').then(r=>r.json()).then(data=>{
        const t=data.totals;
        document.getElementById('stat-total').textContent=Number(t.total_ips).toLocaleString();
        document.getElementById('stat-avg').textContent=t.avg_confidence+'%';
        document.getElementById('stat-novel').textContent=Number(t.novel_count).toLocaleString();
        const confirmed=data.distribution.find(d=>d.confidence_label==='Confirmed');
        document.getElementById('stat-confirmed').textContent=confirmed?Number(confirmed.count).toLocaleString():'0';
        const tbody=document.getElementById('dist-table'); tbody.innerHTML='';
        const totalIPs=parseInt(t.total_ips)||1;
        data.distribution.forEach(d=>{
            const pct=(d.count/totalIPs*100).toFixed(1);
            const color=LABEL_COLORS[d.confidence_label]||'#6c757d';
            const range=LABEL_RANGES[d.confidence_label]||'';
            const row=document.createElement('tr'); row.className='dist-row';
            row.onclick=function(){document.getElementById('top-filter').value=d.confidence_label;loadTop();};
            row.innerHTML=`<td><span style="color:${color};font-weight:600;">${d.confidence_label}</span></td><td><small class="text-muted">${range}</small></td><td>${Number(d.count).toLocaleString()}</td><td><div class="confidence-bar" style="background:#e9ecef;"><div class="bar-fill" style="width:${pct}%;background:${color};"></div><span class="bar-label">${pct}%</span></div></td>`;
            tbody.appendChild(row);
        });
        if(data.last_enriched) document.getElementById('last-enriched').textContent=new Date(data.last_enriched).toLocaleString();
        renderDistChart(data.distribution);
    });
}
function renderDistChart(distribution){
    const ctx=document.getElementById('distChart').getContext('2d');
    if(distChart) distChart.destroy();
    distChart=new Chart(ctx,{type:'doughnut',data:{labels:distribution.map(d=>d.confidence_label),datasets:[{data:distribution.map(d=>d.count),backgroundColor:distribution.map(d=>LABEL_COLORS[d.confidence_label]||'#6c757d')}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right'}}}});
}
function loadTop(){
    const label=document.getElementById('top-filter').value;
    fetch('../api/confidence.php?action=top&limit=25'+(label?'&label='+encodeURIComponent(label):'')).then(r=>r.json()).then(data=>{
        const tbody=document.getElementById('top-table'); tbody.innerHTML='';
        data.forEach(ip=>{
            const color=LABEL_COLORS[ip.confidence_label]||'#6c757d';
            const cc=(ip.geo_country_code||'').toLowerCase();
            const ccDisplay=cc?`<span class="flag-icon flag-icon-${cc} mr-1"></span>${ip.geo_country_code}`:'--';
            const row=document.createElement('tr');
            row.innerHTML=`<td><span class="ip-link" onclick="searchIP('${ip.ip}')">${ip.ip}</span></td><td><span class="badge" style="background:${color};color:#fff;">${ip.confidence_pct}%</span></td><td>${ccDisplay}</td><td>${ip.feed_count}</td><td>${ip.sensor_count}</td><td>${ip.service_count}</td><td>${ip.day_count}</td><td>${Number(ip.attack_count).toLocaleString()}</td><td>${ip.novel_threat==1?'<span class="badge badge-warning">Novel</span>':'<span class="badge badge-success">On Feeds</span>'}</td>`;
            tbody.appendChild(row);
        });
    });
}
function searchIP(ip){document.getElementById('ip-search').value=ip;lookupIP();$('#enrichmentTabs a[href="#tab-overview"]').tab('show');window.scrollTo({top:0,behavior:'smooth'});}
function lookupIP(){
    const ip=document.getElementById('ip-search').value.trim(); if(!ip)return;
    document.getElementById('ip-result').style.display='none';
    document.getElementById('ip-not-found').style.display='none';
    fetch('../api/confidence.php?action=lookup&ip='+encodeURIComponent(ip)).then(r=>r.json()).then(data=>{
        if(!data.found){document.getElementById('ip-not-found').style.display='';return;}
        document.getElementById('ip-result').style.display='';
        const cssClass=LABEL_CSS[data.confidence_label]||'label-suspected';
        document.getElementById('result-score').textContent=data.confidence_pct+'%';
        document.getElementById('result-score').className='score-big '+cssClass;
        document.getElementById('result-label').textContent=data.confidence_label;
        document.getElementById('result-label').className='h4 mt-2 '+cssClass;
        document.getElementById('result-ip').textContent=data.ip;
        if(data.geo&&data.geo.country){
            const gcc=(data.geo.country_code||'').toLowerCase();
            document.getElementById('result-geo').innerHTML='<small><span class="flag-icon flag-icon-'+gcc+' mr-1"></span>'+(data.geo.country_code||'')+' — '+data.geo.country+'</small>';
            document.getElementById('result-geo-detail').textContent=(data.geo.country_code||'--')+' — '+(data.geo.country||'Unknown')+' ('+(data.geo.continent||'--')+')';
            document.getElementById('result-asn').textContent=(data.geo.asn?'AS'+data.geo.asn:'--')+' / '+(data.geo.org||'Unknown');
        }else{document.getElementById('result-geo').innerHTML='';document.getElementById('result-geo-detail').textContent='Unknown';document.getElementById('result-asn').textContent='Unknown';}
        document.getElementById('result-feeds').textContent=data.feed_count;
        document.getElementById('result-sensors').textContent=data.sensor_count;
        document.getElementById('result-days').textContent=data.day_count;
        document.getElementById('result-ports').textContent=data.service_count;
        document.getElementById('result-snares').textContent=data.attack_count.toLocaleString();
        document.getElementById('result-novel').textContent=data.novel_threat?'Novel':'On Feeds';
        const tbody=document.getElementById('breakdown-table'); tbody.innerHTML='';
        data.breakdown.forEach(b=>{const row=document.createElement('tr');row.innerHTML=`<td>${b.signal}</td><td>+${b.value}%</td><td><small class="text-muted">${b.detail}</small></td>`;tbody.appendChild(row);});
        document.getElementById('breakdown-total').textContent=data.total_before_cap+'%';
        document.getElementById('breakdown-capped').textContent=data.capped?'(capped at 99%)':'';
        document.getElementById('result-first-seen').textContent=data.first_seen||'Unknown';
        document.getElementById('result-last-seen').textContent=data.last_seen||'Unknown';
        const feedDiv=document.getElementById('result-feed-list');
        feedDiv.innerHTML=(data.feed_sources&&data.feed_sources.length>0)?data.feed_sources.map(f=>`<span class="feed-badge">${f}</span>`).join(' '):'<em class="text-muted">Not on any feeds (Novel threat)</em>';
        const sensorDiv=document.getElementById('result-sensor-list');
        sensorDiv.innerHTML=(data.sensors_seen&&data.sensors_seen.length>0)?data.sensors_seen.map(s=>`<span class="sensor-badge">${s}</span>`).join(' '):'<em class="text-muted">No sensor data</em>';
    });
}
document.getElementById('ip-search').addEventListener('keypress',function(e){if(e.key==='Enter')lookupIP();});

function loadFeeds(){
    fetch('../api/confidence.php?action=feeds').then(r=>r.json()).then(data=>{
        const feeds=data.feeds||{};const keys=Object.keys(feeds);
        document.getElementById('feed-stat-count').textContent=keys.length;
        document.getElementById('feed-stat-ips').textContent=Number(data.ip_count).toLocaleString();
        document.getElementById('feed-stat-cidrs').textContent=Number(data.cidr_count).toLocaleString();
        if(data.cache_generated) document.getElementById('feed-last-published').textContent=new Date(data.cache_generated).toLocaleString();
        if(data.last_loaded) document.getElementById('feed-last-synced').textContent=new Date(data.last_loaded).toLocaleString();
        const tbody=document.getElementById('feeds-table'); tbody.innerHTML='';
        keys.forEach(name=>{
            const f=feeds[name];const info=FEED_INFO[name]||{desc:name,category:'Unknown',type:'IP'};
            let statusClass, statusIcon, statusText;
            if(f.status==='ok'){statusClass='feed-status-ok';statusIcon='fa-check-circle';statusText='ok';}
            else if(f.status==='stale'){statusClass='feed-status-stale';statusIcon='fa-exclamation-triangle';statusText='stale';}
            else{statusClass='feed-status-fail';statusIcon='fa-times-circle';statusText=f.status||'unknown';}
            const lastChanged=f.last_changed?new Date(f.last_changed).toLocaleDateString():'--';
            const row=document.createElement('tr');
            row.innerHTML=`<td><strong>${name}</strong><br><small class="text-muted">${info.type}</small></td><td><small>${info.desc}</small></td><td><span class="badge badge-secondary">${info.category}</span></td><td class="text-right">${Number(f.entry_count||0).toLocaleString()}</td><td><small>${lastChanged}</small></td><td><span class="${statusClass}"><i class="fas ${statusIcon} mr-1"></i>${statusText}</span></td>`;
            tbody.appendChild(row);
        });
    });
    // Load feed matches chart
    fetch('../api/confidence.php?action=feed_matches').then(r=>r.json()).then(data=>{
        const matches=data.matches||[];
        const ctx=document.getElementById('feedMatchChart').getContext('2d');
        if(window.feedMatchChartInstance) window.feedMatchChartInstance.destroy();
        window.feedMatchChartInstance=new Chart(ctx,{
            type:'bar',
            data:{
                labels:matches.map(m=>m.feed),
                datasets:[{
                    label:'LURE Matches',
                    data:matches.map(m=>m.match_count),
                    backgroundColor:'rgba(40,167,69,0.7)',
                    borderColor:'rgba(40,167,69,1)',
                    borderWidth:1
                }]
            },
            options:{
                responsive:true,
                maintainAspectRatio:false,
                indexAxis:'y',
                plugins:{legend:{display:false},tooltip:{callbacks:{label:function(ctx){return ctx.parsed.x.toLocaleString()+' IPs (of '+data.total_scored.toLocaleString()+' scored)';}}}},
                scales:{x:{title:{display:true,text:'Snared IPs Corroborated'},ticks:{callback:function(v){return v.toLocaleString();}}},y:{ticks:{font:{size:11}}}}
            }
        });
    });
}

let geoLoaded=false;
function loadGeo(){
    if(geoLoaded) return; geoLoaded=true;
    fetch('../api/confidence.php?action=geo_stats').then(r=>r.json()).then(data=>{
        document.getElementById('geo-stat-countries').textContent=data.country_count;
        document.getElementById('geo-stat-resolved').textContent=Number(data.geo_resolved).toLocaleString();
        document.getElementById('geo-stat-total').textContent=Number(data.total_ips).toLocaleString();
        if(data.last_enriched) document.getElementById('geo-last-enriched').textContent=new Date(data.last_enriched).toLocaleString();
        const ctbody=document.getElementById('geo-country-table'); ctbody.innerHTML='';
        const mapData={};
        (data.countries||[]).forEach(c=>{
            const cc=(c.geo_country_code||'').toLowerCase();
            const row=document.createElement('tr');
            row.innerHTML=`<td><span class="flag-icon flag-icon-${cc} mr-1"></span> <strong>${c.geo_country_code}</strong> <small class="text-muted">${c.geo_country}</small></td><td class="text-right">${Number(c.count).toLocaleString()}</td><td class="text-right">${c.avg_pct}%</td>`;
            ctbody.appendChild(row);
            if(c.geo_country_code) mapData[cc]=parseInt(c.count);
        });
        $('#world-map').vectorMap({map:'world_en',backgroundColor:'#fff',borderColor:'#dee2e6',borderOpacity:0.5,borderWidth:0.5,color:'#e9ecef',hoverOpacity:0.7,selectedColor:null,enableZoom:true,showTooltip:true,values:mapData,scaleColors:['#c6dbef','#dc3545'],normalizeFunction:'polynomial',onLabelShow:function(e,el,code){var count=mapData[code];if(count)el.html(el.html()+': '+Number(count).toLocaleString()+' IPs');}});
        const contbody=document.getElementById('geo-continent-table'); contbody.innerHTML='';
        (data.continents||[]).forEach(c=>{const row=document.createElement('tr');row.innerHTML=`<td>${CONTINENT_NAMES[c.geo_continent]||c.geo_continent}</td><td class="text-right">${Number(c.count).toLocaleString()}</td>`;contbody.appendChild(row);});
        const asnbody=document.getElementById('geo-asn-table'); asnbody.innerHTML='';
        (data.top_asns||[]).forEach(a=>{const row=document.createElement('tr');row.innerHTML=`<td>AS${a.geo_asn}</td><td>${a.geo_org||'Unknown'}</td><td class="text-right">${Number(a.count).toLocaleString()}</td><td class="text-right">${a.avg_pct}%</td>`;asnbody.appendChild(row);});
    });
}

$('#enrichmentTabs a[data-toggle="tab"]').on('shown.bs.tab',function(e){
    const target=$(e.target).attr('href');
    if(target==='#tab-feeds') loadFeeds();
    if(target==='#tab-geo') loadGeo();
});

loadSummary();loadTop();
// Fetch feed count for dynamic references across tabs
fetch('../api/confidence.php?action=feeds').then(r=>r.json()).then(data=>{
    const count=Object.keys(data.feeds||{}).length;
    document.querySelectorAll('#feed-callout-count, #scoring-feed-count').forEach(el=>{el.textContent=count;});
});
</script>
</body>
</html>
