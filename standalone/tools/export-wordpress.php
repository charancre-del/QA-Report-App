<?php
/**
 * Restricted QA snapshot export. Run with WP-CLI --skip-plugins --skip-themes.
 * Does not load the QA plugin, invoke its activation hook, or modify source data.
 * Output must be outside the WordPress document root and is created exclusively.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('WP-CLI required'); }

global $wpdb;
$output = getenv('CQA_EXPORT_PATH');
$instance = getenv('CQA_SOURCE_INSTANCE');
$expected_url = getenv('CQA_EXPECTED_SITE_URL');
$expected_database = getenv('CQA_EXPECTED_DATABASE_NAME');
if (!$output || !$instance || !$expected_url || !$expected_database || !preg_match('/^[a-zA-Z0-9._:-]{1,128}$/', $instance)) {
    WP_CLI::error('Set private output, stable source instance, expected site URL and expected database name.');
}
$directory = realpath(dirname($output));
$root = realpath(ABSPATH);
if (!$directory || !$root || $directory === $root || strpos($directory . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) === 0 || file_exists($output)) {
    WP_CLI::error('Use a new file in an existing private directory outside the WordPress root.');
}

// Errors stay generic: database messages, rows, credentials and paths never reach stdout.
$wpdb->suppress_errors(true);
$query = function ($sql) use ($wpdb) {
    $rows = $wpdb->get_results($sql, ARRAY_A);
    if ($wpdb->last_error) { throw new RuntimeException('Snapshot query failed'); }
    return $rows;
};
$execute = function ($sql) use ($wpdb) {
    if ($wpdb->query($sql) === false) { throw new RuntimeException('Snapshot transaction failed'); }
};
$handle = null;
try {
    $prefix = $wpdb->prefix . 'cqa_';
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) { throw new RuntimeException('Invalid table prefix'); }
    $tables = $query($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($prefix) . '%'));
    if (!$tables) { throw new RuntimeException('No QA tables found'); }
    foreach ($tables as $table) {
        if (strtoupper($table['Engine'] ?? '') !== 'INNODB') { throw new RuntimeException('Transactional QA tables required'); }
    }
    // Users/options participate in the same snapshot; reject nontransactional identity stores.
    foreach ([$wpdb->users, $wpdb->usermeta, $wpdb->options] as $table) {
        $status = $query($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($table)));
        if (count($status) !== 1 || strtoupper($status[0]['Engine'] ?? '') !== 'INNODB') { throw new RuntimeException('Transactional identity tables required'); }
    }
    $execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $execute('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    $option = function ($name) use ($wpdb, $query) {
        $rows = $query($wpdb->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s", $name));
        return $rows ? $rows[0]['option_value'] : null;
    };
    if (!defined('DB_NAME') || DB_NAME !== $expected_database || $option('siteurl') !== $expected_url) {
        throw new RuntimeException('Source identity does not match the approved environment');
    }
    $payload = [
        'source' => ['instance' => $instance, 'url' => $option('siteurl'), 'table_prefix' => $wpdb->prefix,
            'database_name' => DB_NAME, 'plugin_db_version' => $option('cqa_db_version')],
        'exported_at' => gmdate('c'), 'consistency' => 'repeatable-read',
        'tables' => [], 'principals' => [], 'media' => [],
    ];
    $principal_ids = [];
    foreach ($tables as $table) {
        $name = $table['Name'];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) { throw new RuntimeException('Invalid table name'); }
        $short = substr($name, strlen($prefix));
        $columns = $query("SHOW COLUMNS FROM `{$name}`");
        $schema = $query("SHOW CREATE TABLE `{$name}`");
        if (!in_array('id', array_column($columns, 'Field'), true)) { throw new RuntimeException('Unreviewed table without source ID'); }
        $rows = $query("SELECT * FROM `{$name}` ORDER BY id");
        // MySQL connector variants may return integers. Preserve exact values as strings.
        foreach ($rows as &$row) {
            foreach ($row as &$value) { if ($value !== null) { $value = (string) $value; } }
            unset($value);
            foreach (['user_id', 'created_by'] as $field) {
                if (!empty($row[$field])) { $principal_ids[$row[$field]] = true; }
            }
        }
        unset($row);
        $payload['tables'][$short] = ['columns' => array_column($columns, 'Field'), 'schema' => $schema[0]['Create Table'], 'count' => count($rows), 'rows' => $rows];
    }
    // Include QA principals with no authored report so reviewers can receive explicit new grants.
    $capability_rows = $query($wpdb->prepare("SELECT user_id, meta_value FROM `{$wpdb->usermeta}` WHERE meta_key = %s", $wpdb->prefix . 'capabilities'));
    $roles = [];
    foreach ($capability_rows as $capability_row) {
        $caps = @unserialize($capability_row['meta_value'], ['allowed_classes' => false]);
        if (!is_array($caps)) { continue; }
        $qa_caps = array_filter($caps, function ($value, $key) { return $value === true && (strpos($key, 'cqa_') === 0 || $key === 'administrator'); }, ARRAY_FILTER_USE_BOTH);
        if ($qa_caps) {
            $principal_ids[$capability_row['user_id']] = true;
            $roles[$capability_row['user_id']] = array_keys($qa_caps);
        }
    }
    // Only immutable IDs and QA grant inventory; no names, emails, passwords or OAuth tokens.
    foreach (array_keys($principal_ids) as $principal_id) {
        $user = $query($wpdb->prepare("SELECT ID FROM `{$wpdb->users}` WHERE ID = %s", (string) $principal_id));
        $scope = $query($wpdb->prepare("SELECT meta_key, meta_value FROM `{$wpdb->usermeta}` WHERE user_id = %s AND meta_key IN ('cqa_region', 'cqa_school_id')", (string) $principal_id));
        $payload['principals'][] = ['id' => (string) $principal_id, 'exists' => count($user) === 1, 'source_scope' => $scope, 'source_grants' => $roles[$principal_id] ?? []];
    }
    foreach ($payload['tables']['photos']['rows'] ?? [] as $photo) {
        $ref = $photo['drive_file_id'];
        $kind = !$ref ? 'missing' : (strpos($ref, 'wp_') === 0 ? 'wordpress' : 'drive');
        $payload['media'][] = ['photo_id' => $photo['id'], 'source_ref' => $ref, 'kind' => $kind,
            'bytes_copied' => false, 'access_verified' => false];
    }
    // Checklists are versioned with the source artifact; runtime settings/secrets are intentionally excluded.
    $execute('COMMIT');
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $envelope = json_encode(['format' => 'chroma-qa-snapshot/v1', 'sha256' => hash('sha256', $json), 'payload_json' => $json], JSON_THROW_ON_ERROR);
    $previous_umask = umask(0077);
    $handle = fopen($output, 'xb');
    umask($previous_umask);
    if (!$handle) { throw new RuntimeException('Cannot create private output'); }
    if (fwrite($handle, $envelope) !== strlen($envelope) || !fflush($handle)) { throw new RuntimeException('Snapshot write incomplete'); }
    if (function_exists('fsync') && !fsync($handle)) { throw new RuntimeException('Snapshot sync failed'); }
    fclose($handle);
    $handle = null;
    WP_CLI::success('Private QA snapshot created. Media bytes, offline drafts and cutover remain unverified.');
} catch (Throwable $error) {
    $wpdb->query('ROLLBACK');
    if (is_resource($handle)) { fclose($handle); }
    WP_CLI::error('Snapshot export failed; no source records were changed. Review the private environment; do not publish partial output.');
}
