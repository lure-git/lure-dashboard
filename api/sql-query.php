<?php
require_once 'config.php';

// Get POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

$query = $data['query'] ?? '';
$format = $data['format'] ?? 'json';

if (empty($query)) {
    echo json_encode(['error' => 'No query provided']);
    exit;
}

// Security: Only allow SELECT queries (must start with SELECT)
if (!preg_match('/^\s*SELECT/i', $query)) {
    echo json_encode(['error' => 'Only SELECT queries are allowed. No writes permitted.']);
    exit;
}

// Security: Block dangerous keywords (even in subqueries)
$dangerous = [
    'DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER', 'CREATE', 
    'PRAGMA', 'ATTACH', 'DETACH', 'REPLACE', 'TRUNCATE',
    'GRANT', 'REVOKE', 'COMMIT', 'ROLLBACK', 'SAVEPOINT',
    'VACUUM', 'REINDEX', 'ANALYZE', 'EXPLAIN', 'BEGIN', 'END'
];

foreach ($dangerous as $keyword) {
    if (preg_match('/\b' . $keyword . '\b/i', $query)) {
        echo json_encode(['error' => 'Query contains forbidden keyword: ' . $keyword . '. Read-only access only.']);
        exit;
    }
}

// Security: Block file operations and command execution
$fileOperations = [
    // Write operations
    'writefile', 'file_put_contents', 'fwrite', 'fputs',
    
    // Read operations
    'readfile', 'file_get_contents', 'fread', 'fgets', 'fgetc', 
    'file(', 'fpassthru', 'readgzfile', 'gzfile', 'readlink',
    
    // Extensions and loading
    'load_extension', 'sqllog', 'import ',
    
    // Output operations
    'edit(', 'output(', '.output', '.once', 'export',
    'into outfile', 'into dumpfile', 'load data',
    
    // Command execution
    'system(', 'shell(', 'exec(', 'popen(', 'proc_open(',
    'passthru(', 'pcntl_exec', 'shell_exec', '`',
    
    // Code execution
    'eval(', 'assert(', 'create_function', 'include', 'require',
    
    // File system operations
    'fopen', 'tmpfile', 'tempnam', 'fsockopen', 'pfsockopen',
    'glob', 'scandir', 'opendir', 'readdir', 'realpath',
    'pathinfo', 'basename', 'dirname', 'file_exists', 'is_file',
    'is_dir', 'is_readable', 'is_writable', 'chmod', 'chown',
    'unlink', 'rmdir', 'mkdir', 'rename', 'copy', 'move_uploaded_file',
    
    // Network operations
    'curl', 'wget', 'file_get_contents(', 'fopen(http', 'socket',
    
    // SQLite specific file operations
    'sqlite3_load_extension', 'backup', 'restore', '.backup', '.restore',
    '.save', '.read', '.import', '.excel'
];

foreach ($fileOperations as $fileOp) {
    if (stripos($query, $fileOp) !== false) {
        echo json_encode(['error' => 'File operations and command execution are not permitted.']);
        exit;
    }
}

// Security: Block path traversal attempts
$pathPatterns = [
    '../', '..\\', '/etc/', '/var/', '/tmp/', '/usr/', '/home/',
    'c:\\', 'd:\\', '\\windows\\', '\\system32\\', '/proc/', '/dev/'
];

foreach ($pathPatterns as $pattern) {
    if (stripos($query, $pattern) !== false) {
        echo json_encode(['error' => 'Path references are not permitted.']);
        exit;
    }
}

// Security: Only allow querying from lure_logs table
if (preg_match('/\bFROM\b/i', $query)) {
    if (!preg_match('/\bFROM\s+lure_logs\b/i', $query)) {
        echo json_encode(['error' => 'Only queries against the lure_logs table are permitted.']);
        exit;
    }
}

// Security: Block semicolons (prevents multiple statements)
if (substr_count($query, ';') > 1) {
    echo json_encode(['error' => 'Multiple statements not allowed. Only single SELECT queries permitted.']);
    exit;
}

// Security: Block access to system tables and special schemas
$systemTables = ['sqlite_master', 'sqlite_temp_master', 'sqlite_sequence', 'sqlite_stat1', 'sqlite_stat4'];
foreach ($systemTables as $table) {
    if (stripos($query, $table) !== false) {
        echo json_encode(['error' => 'Access to system tables not permitted.']);
        exit;
    }
}

// Security: Block dot commands (SQLite CLI commands)
if (preg_match('/^\s*\./', $query)) {
    echo json_encode(['error' => 'SQLite dot commands are not permitted.']);
    exit;
}

// Security: Block comments that could hide malicious code
if (preg_match('/\/\*.*?\*\/|--/s', $query)) {
    echo json_encode(['error' => 'Comments are not permitted in queries.']);
    exit;
}

// Security: Limit query length (prevents resource exhaustion)
if (strlen($query) > 10000) {
    echo json_encode(['error' => 'Query too long. Maximum 10,000 characters.']);
    exit;
}

try {
    $db = getDB();
    
    // Set database to read-only mode for this connection
    $db->exec('PRAGMA query_only = ON');
    
    // Set timeout to prevent long-running queries
    $db->setAttribute(PDO::ATTR_TIMEOUT, 30);
    
    // Execute with time limit
    set_time_limit(30);
    
    $stmt = $db->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Limit result size (prevent memory exhaustion)
    if (count($rows) > 50000) {
        echo json_encode(['error' => 'Result set too large. Maximum 50,000 rows. Use LIMIT clause.']);
        exit;
    }
    
    if ($format === 'csv') {
        // Export as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="query_results.csv"');
        
        $output = fopen('php://output', 'w');
        
        if (count($rows) > 0) {
            // Write header
            fputcsv($output, array_keys($rows[0]));
            
            // Write rows
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
    } else {
        // Return JSON
        $columns = count($rows) > 0 ? array_keys($rows[0]) : [];
        
        echo json_encode([
            'columns' => $columns,
            'rows' => $rows,
            'count' => count($rows)
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Query error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>
