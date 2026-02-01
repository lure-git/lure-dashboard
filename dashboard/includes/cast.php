<?php
/**
 * Cast System Helper Functions
 * Extends the base db.php with cast-specific functions
 */

require_once __DIR__ . '/db.php';

// ============================================
// Configuration Helpers
// ============================================

function cast_get_config($category, $key, $default = null) {
    $row = db_fetch_one(
        'SELECT value FROM cast_config WHERE category = :cat AND key = :key',
        [':cat' => $category, ':key' => $key]
    );
    return $row ? $row['value'] : $default;
}

function cast_set_config($category, $key, $value, $description = null) {
    $db = get_db();
    $stmt = $db->prepare('
        INSERT INTO cast_config (category, key, value, description, updated_at)
        VALUES (:cat, :key, :val, :desc, datetime("now"))
        ON CONFLICT(category, key) DO UPDATE SET 
            value = excluded.value,
            updated_at = datetime("now")
    ');
    $stmt->bindValue(':cat', $category, SQLITE3_TEXT);
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->bindValue(':val', $value, SQLITE3_TEXT);
    $stmt->bindValue(':desc', $description, SQLITE3_TEXT);
    return $stmt->execute() !== false;
}

function cast_get_config_category($category) {
    $rows = db_fetch_all(
        'SELECT key, value, description FROM cast_config WHERE category = :cat ORDER BY key',
        [':cat' => $category]
    );
    $config = [];
    foreach ($rows as $row) {
        $config[$row['key']] = $row['value'];
    }
    return $config;
}

// ============================================
// Resource Helpers
// ============================================

function cast_get_subnets($type = null) {
    $sql = 'SELECT * FROM cast_subnets WHERE is_active = 1';
    $params = [];
    if ($type) {
        $sql .= ' AND subnet_type = :type';
        $params[':type'] = $type;
    }
    $sql .= ' ORDER BY name';
    return db_fetch_all($sql, $params);
}

function cast_get_subnet_pairs() {
    return db_fetch_all('
        SELECT sp.*, 
               ms.name as mgt_name, ms.cidr as mgt_cidr,
               bs.name as bait_name, bs.cidr as bait_cidr
        FROM cast_subnet_pairs sp
        LEFT JOIN cast_subnets ms ON sp.mgt_subnet_id = ms.subnet_id
        LEFT JOIN cast_subnets bs ON sp.bait_subnet_id = bs.subnet_id
        WHERE sp.is_active = 1
        ORDER BY sp.name
    ');
}

function cast_get_security_groups($type = null) {
    $sql = 'SELECT * FROM cast_security_groups WHERE is_active = 1';
    $params = [];
    if ($type) {
        $sql .= ' AND sg_type = :type';
        $params[':type'] = $type;
    }
    $sql .= ' ORDER BY name';
    return db_fetch_all($sql, $params);
}

function cast_get_route_tables() {
    return db_fetch_all('SELECT * FROM cast_route_tables WHERE is_active = 1 ORDER BY name');
}

function cast_get_vpc_endpoints() {
    return db_fetch_all('SELECT * FROM cast_vpc_endpoints WHERE is_active = 1 ORDER BY service');
}

function cast_get_eips($type = null, $available_only = false) {
    $sql = 'SELECT * FROM cast_eips WHERE is_active = 1';
    $params = [];
    if ($type) {
        $sql .= ' AND eip_type = :type';
        $params[':type'] = $type;
    }
    if ($available_only) {
        $sql .= ' AND (assigned_to IS NULL OR assigned_to = "")';
    }
    $sql .= ' ORDER BY eip';
    return db_fetch_all($sql, $params);
}

function cast_get_instance_types() {
    return db_fetch_all('SELECT * FROM cast_instance_types WHERE is_active = 1 ORDER BY is_default DESC, instance_type');
}

// ============================================
// Deployment Helpers
// ============================================

function cast_get_next_hostname() {
    $prefix = cast_get_config('general', 'name_prefix', 'lure-');
    $start = (int)cast_get_config('general', 'name_start', 100);
    
    $db = get_db();
    $result = $db->querySingle("
        SELECT MAX(CAST(REPLACE(hostname, '$prefix', '') AS INTEGER)) as max_num 
        FROM lure_health 
        WHERE hostname LIKE '$prefix%'
    ");
    
    $next = max($start, ($result ?? $start - 1) + 1);
    return $prefix . $next;
}

function cast_get_aws_region() {
    return cast_get_config('aws', 'region', 'us-east-2');
}

// ============================================
// Badge Helpers for UI
// ============================================

function cast_subnet_type_badge($type) {
    $badges = [
        'mgt' => 'primary',
        'bait' => 'danger', 
        'em' => 'info',
        'public' => 'success'
    ];
    return $badges[$type] ?? 'secondary';
}

function cast_sg_type_badge($type) {
    $badges = [
        'lure-mgt' => 'primary',
        'lure-bait' => 'danger',
        'em' => 'info',
        'bastion' => 'warning',
        'proxy' => 'success'
    ];
    return $badges[$type] ?? 'secondary';
}
