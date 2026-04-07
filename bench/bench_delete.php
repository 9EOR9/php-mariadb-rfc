<?php
require_once 'connect.inc';

$link = new mysqli($host, $user, $passwd, $db, $socket);

if ($link->connect_error) {
    die("Connection failed: " . $link->connect_error);
}

/* Setup */
$link->query("DROP TABLE IF EXISTS bench_delete");
$link->query("
        CREATE TABLE bench_delete (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50),
            score DOUBLE DEFAULT 20
            ) ENGINE=$engine;
        ");

$total = 10000; // Size of each bulk chunk
$iterations = 10; // Number of chunks
$grand_total = $total * $iterations;

/**
 * PHASE 1: PRE-POPULATE THE TABLE
 * We use executemany() to quickly fill the table for the test
 */
echo "Pre-populating table with $grand_total rows...\n";
$fill_data = [];
for ($i = 0; $i < $grand_total; $i++) {
    $fill_data[] = ["name_$i", $i * 0.1];
}

$stmt = $link->prepare("INSERT INTO bench_delete (name, score) VALUES (?, ?)");
$stmt->executemany("sd", $fill_data);
$stmt->close();

/* Prepare IDs for the delete operations */
$delete_chunks = [];
for ($i = 0; $i < $iterations; $i++) {
    $chunk = [];
    for ($j = 1; $j <= $total; $j++) {
        // Offset the IDs so each chunk targets a fresh set of rows
        $chunk[] = [($i * $total) + $j];
    }
    $delete_chunks[] = $chunk;
}

/**
 * BENCHMARK 1: executemany() DELETE
 */
$stmt = $link->prepare("DELETE FROM bench_delete WHERE id = ?");
$start = microtime(true);
$link->begin_transaction();

foreach ($delete_chunks as $chunk) {
    $stmt->executemany("i", $chunk);
    if ($stmt->affected_rows != $total) {
        die("only $stmt->affected_rows were deleted instead of $total");
    }
}

$link->commit();
$elapsed = microtime(true) - $start;
$rowsPerSec = $grand_total / $elapsed;
echo "DELETE executemany(): " . round($rowsPerSec) . " rows/sec\n";
$stmt->close();

/* Re-fill table for Benchmark 2 */
$link->query("TRUNCATE TABLE bench_delete");
$stmt = $link->prepare("INSERT INTO bench_delete (name, score) VALUES (?, ?)");
$stmt->executemany("sd", $fill_data);
$stmt->close();

/**
 * BENCHMARK 2: execute() loop DELETE
 */
$stmt = $link->prepare("DELETE FROM bench_delete WHERE id = ?");
$start = microtime(true);
$link->begin_transaction();

foreach ($delete_chunks as $chunk) {
    foreach ($chunk as $row) {
        $stmt->bind_param("i", $row[0]);
        $stmt->execute();
    }
}

$link->commit();
$elapsed = microtime(true) - $start;
$rowsPerSec = $grand_total / $elapsed;
echo "DELETE execute() loop: " . round($rowsPerSec) . " rows/sec\n";
$stmt->close();

/* Cleanup */
$link->query("DROP TABLE bench_delete");
$link->close();
