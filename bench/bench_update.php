<?php
require_once 'connect.inc';

$link = new mysqli($host, $user, $passwd, $db, $socket);

if ($link->connect_error) {
    die("Connection failed: " . $link->connect_error);
}

/* Setup */
$link->query("DROP TABLE IF EXISTS bench_update");
$link->query("
    CREATE TABLE bench_update (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50),
        score DOUBLE DEFAULT 20
    ) ENGINE=$engine;
");

$total = 10000; 
$iterations = 10; 
$grand_total = $total * $iterations;

/**
 * PHASE 1: PRE-POPULATE
 */
echo "Pre-populating table with $grand_total rows...\n";
$fill_data = [];
for ($i = 0; $i < $grand_total; $i++) {
    $fill_data[] = ["name_$i", $i * 0.1];
}

$stmt = $link->prepare("INSERT INTO bench_update (name, score) VALUES (?, ?)");
$stmt->executemany("sd", $fill_data);
$stmt->close();

/* Prepare data for the update operations [[new_score, id], ...] */
$update_chunks = [];
for ($i = 0; $i < $iterations; $i++) {
    $chunk = [];
    for ($j = 1; $j <= $total; $j++) {
        $id = ($i * $total) + $j;
        $chunk[] = [$id * 1.5, $id]; // [score, id]
    }
    $update_chunks[] = $chunk;
}

/**
 * BENCHMARK 1: executemany() UPDATE
 */
$stmt = $link->prepare("UPDATE bench_update SET score = ? WHERE id = ?");
$start = microtime(true);
$link->begin_transaction();

foreach ($update_chunks as $chunk) {
    $stmt->executemany("di", $chunk);
    if ($stmt->affected_rows != $total) {
        die("Only $stmt->affected_rows updated. Expected $total.");
    }
}

$link->commit();
$elapsed = microtime(true) - $start;
$rowsPerSec = $grand_total / $elapsed;
echo "UPDATE executemany(): " . round($rowsPerSec) . " rows/sec\n";
$stmt->close();

/**
 * BENCHMARK 2: execute() loop UPDATE
 * Note: No need to truncate/refill since we're just changing existing values
 */
$stmt = $link->prepare("UPDATE bench_update SET score = ? WHERE id = ?");
$start = microtime(true);
$link->begin_transaction();

foreach ($update_chunks as $chunk) {
    foreach ($chunk as $row) {
        $stmt->bind_param("di", $row[0], $row[1]);
        $stmt->execute();
    }
}

$link->commit();
$elapsed = microtime(true) - $start;
$rowsPerSec = $grand_total / $elapsed;
echo "UPDATE execute() loop: " . round($rowsPerSec) . " rows/sec\n";
$stmt->close();

/* Cleanup */
$link->query("DROP TABLE bench_update");
$link->close();
