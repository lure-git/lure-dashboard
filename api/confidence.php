<?php
/**
 * LURE Enrichment / Confidence API
 * 
 * Endpoints:
 *   ?action=summary     — Confidence distribution overview
 *   ?action=lookup&ip=X — Full scoring breakdown for a single IP
 *   ?action=top&limit=N — Top N IPs by confidence
 *   ?action=search&q=X  — Search IPs by prefix or label
 */

require_once 'config.php';

header('Content-Type: application/json');

define('ENRICHMENT_DB', '/var/log/lures/enrichment.db');

function getEnrichmentDB() {
    if (!file_exists(ENRICHMENT_DB)) {
        http_response_code(503);
        echo json_encode(['error' => 'Enrichment database not available']);
        exit;
    }
    try {
	    $db = new PDO('sqlite:file:' . ENRICHMENT_DB . '?mode=ro&immutable=1', null, null, [
        ]);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA query_only = ON');
        return $db;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Enrichment DB connection failed']);
        exit;
    }
}

$action = $_GET['action'] ?? 'summary';

switch ($action) {

    case 'summary':
        $db = getEnrichmentDB();

        // Get distribution
        $stmt = $db->query("
            SELECT confidence_label, COUNT(*) as count,
                   ROUND(AVG(confidence_pct), 1) as avg_pct,
                   MIN(confidence_pct) as min_pct,
                   MAX(confidence_pct) as max_pct
            FROM enrichment_results
            GROUP BY confidence_label
        ");
        $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get totals
        $totals = $db->query("
            SELECT COUNT(*) as total_ips,
                   ROUND(AVG(confidence_pct), 1) as avg_confidence,
                   SUM(CASE WHEN novel_threat = 1 THEN 1 ELSE 0 END) as novel_count,
                   SUM(CASE WHEN on_feeds = 1 THEN 1 ELSE 0 END) as on_feeds,
                   SUM(CASE WHEN sensor_count >= 2 THEN 1 ELSE 0 END) as multi_sensor
            FROM enrichment_results
        ")->fetch(PDO::FETCH_ASSOC);

        // Get top countries
        $countries = $db->query("
            SELECT geo_country_code, geo_country, COUNT(*) as count,
                   ROUND(AVG(confidence_pct), 1) as avg_pct
            FROM enrichment_results
            WHERE geo_country_code IS NOT NULL
            GROUP BY geo_country_code
            ORDER BY count DESC
            LIMIT 15
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Get metadata
        $meta = [];
        $stmt = $db->query("SELECT key, value FROM feed_metadata");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $meta[$row['key']] = $row['value'];
        }

        // Order distribution by confidence level
        $order = ['Confirmed' => 0, 'High Confidence' => 1, 'Moderate Confidence' => 2, 'Low Confidence' => 3, 'Suspected' => 4];
        usort($distribution, function($a, $b) use ($order) {
            return ($order[$a['confidence_label']] ?? 99) - ($order[$b['confidence_label']] ?? 99);
        });

        echo json_encode([
            'totals' => $totals,
            'distribution' => $distribution,
            'countries' => $countries,
            'last_enriched' => $meta['last_enriched'] ?? null,
            'cache_generated' => $meta['cache_generated_at'] ?? null
        ]);
        break;

    case 'lookup':
        $ip = trim($_GET['ip'] ?? '');
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid IP address required']);
            exit;
        }

        $db = getEnrichmentDB();
        $stmt = $db->prepare("SELECT * FROM enrichment_results WHERE ip = ?");
        $stmt->execute([$ip]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            echo json_encode(['found' => false, 'ip' => $ip]);
            break;
        }

        // Get total sensors for coverage calculation
        $meta = $db->query("SELECT value FROM feed_metadata WHERE key = 'enrichment_stats'")->fetch(PDO::FETCH_ASSOC);
        $stats = $meta ? json_decode($meta['value'], true) : [];

        // Build scoring breakdown
        $breakdown = [
            ['signal' => 'Base (bait hit)', 'value' => 30, 'detail' => 'Touched bait interface'],
        ];

        if ($result['feed_bonus'] > 0) {
            $breakdown[] = [
                'signal' => 'Feed corroboration',
                'value' => (int)$result['feed_bonus'],
                'detail' => $result['feed_count'] . ' feed(s) × 4%'
            ];
        }
        if ($result['sensor_bonus'] > 0) {
            $total_sensors = $stats['total_sensors'] ?? 19;
            $coverage = round(($result['sensor_count'] / max($total_sensors, 1)) * 100, 1);
            $breakdown[] = [
                'signal' => 'Sensor coverage',
                'value' => (int)$result['sensor_bonus'],
                'detail' => $result['sensor_count'] . '/' . $total_sensors . ' sensors (' . $coverage . '%)'
            ];
        }
        if ($result['port_bonus'] > 0) {
            $breakdown[] = [
                'signal' => 'Port scanning',
                'value' => (int)$result['port_bonus'],
                'detail' => $result['service_count'] . ' unique ports'
            ];
        }
        if ($result['persistence_bonus'] > 0) {
            $breakdown[] = [
                'signal' => 'Persistence',
                'value' => (int)$result['persistence_bonus'],
                'detail' => $result['day_count'] . ' days seen'
            ];
        }
        if ($result['volume_bonus'] > 0) {
            $breakdown[] = [
                'signal' => 'Volume',
                'value' => (int)$result['volume_bonus'],
                'detail' => number_format($result['attack_count']) . ' snares'
            ];
        }

        $total_bonus = array_sum(array_column($breakdown, 'value'));

        echo json_encode([
            'found' => true,
            'ip' => $result['ip'],
            'confidence_pct' => (int)$result['confidence_pct'],
            'confidence_label' => $result['confidence_label'],
            'feed_count' => (int)$result['feed_count'],
            'feed_sources' => $result['feed_sources'] ? json_decode($result['feed_sources']) : [],
            'sensor_count' => (int)$result['sensor_count'],
            'sensors_seen' => $result['sensors_seen'] ? explode(',', $result['sensors_seen']) : [],
            'service_count' => (int)$result['service_count'],
            'day_count' => (int)$result['day_count'],
            'attack_count' => (int)$result['attack_count'],
            'on_feeds' => (bool)$result['on_feeds'],
            'novel_threat' => (bool)$result['novel_threat'],
            'first_seen' => $result['first_seen_lure'],
            'last_seen' => $result['last_seen_lure'],
            'geo' => [
                'country_code' => $result['geo_country_code'],
                'country' => $result['geo_country'],
                'continent' => $result['geo_continent'],
                'asn' => $result['geo_asn'] ? (int)$result['geo_asn'] : null,
                'org' => $result['geo_org'],
            ],
            'breakdown' => $breakdown,
            'total_before_cap' => $total_bonus,
            'capped' => $total_bonus > 99
        ]);
        break;

    case 'top':
        $limit = min(max((int)($_GET['limit'] ?? 25), 1), 100);
        $label = $_GET['label'] ?? '';

        $db = getEnrichmentDB();

        $where = '';
        $params = [];
        if (!empty($label)) {
            $where = 'WHERE confidence_label = :label';
            $params[':label'] = $label;
        }

        $stmt = $db->prepare("
            SELECT ip, confidence_pct, confidence_label,
                   feed_count, sensor_count, service_count,
                   day_count, attack_count, sensors_seen,
                   on_feeds, novel_threat,
                   first_seen_lure, last_seen_lure,
                   geo_country_code, geo_org
            FROM enrichment_results
            $where
            ORDER BY confidence_pct DESC, feed_weight DESC
            LIMIT :limit
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'search':
        $q = trim($_GET['q'] ?? '');
        if (empty($q)) {
            echo json_encode([]);
            break;
        }

        $db = getEnrichmentDB();

        // Search by IP prefix
        $stmt = $db->prepare("
            SELECT ip, confidence_pct, confidence_label,
                   feed_count, sensor_count, service_count,
                   day_count, attack_count, novel_threat
            FROM enrichment_results
            WHERE ip LIKE :q
            ORDER BY confidence_pct DESC
            LIMIT 50
        ");
        $stmt->execute([':q' => $q . '%']);

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action. Use: summary, lookup, top, search']);
}
?>
