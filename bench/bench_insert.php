<?php

require_once 'connect.inc';


$link = new mysqli($host, $user, $passwd, $db, $socket);

if ($link->connect_error) {
    die("Connection failed: " . $link->connect_error);
}

/* Setup */
$link->query("DROP TABLE IF EXISTS bench_exec");
$link->query("
    CREATE TABLE bench_exec (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50),
        score DOUBLE DEFAULT 20
    ) ENGINE=$engine;
");

$total = 100000;
$iterations = 20;

// Prepare data once
$rows = [];
for ($i = 0; $i < $total; $i++) {
    $rows[] = ["name_$i", $i * 0.1];
}

/* Benchmark 1: executemany() */
$link->begin_transaction();
$stmt = $link->prepare("INSERT INTO bench_exec (name, score) VALUES (?, ?)");
$start = microtime(true);
for ($i=0; $i < $iterations; $i++) {
    $stmt->executemany("sd", $rows);
	  if ($stmt->affected_rows != $total) {
			die("Only $stmt->affected_rows inserted. Expected $total rows");
		}
}
$link->commit();
$elapsed = microtime(true) - $start;
$rowsPerSec = ($total * $iterations) / $elapsed;
echo "executemany(): {$rowsPerSec} rows/sec\n";
$stmt->close();

// Reset table
$link->query("TRUNCATE TABLE bench_exec");

/* Benchmark 2: execute() loop */
$stmt = $link->prepare("INSERT INTO bench_exec (name, score) VALUES (?, ?)");
$link->begin_transaction();
$start = microtime(true);
for ($i=0; $i < $iterations; $i++) {
	foreach ($rows as $row) {
  	$stmt->bind_param("sd", $row[0], $row[1]);
    $stmt->execute();
	}
}
$link->commit();
$elapsed = microtime(true) - $start;
$rowsPerSec = ($total * $iterations) / $elapsed;
echo "execute() loop: {$rowsPerSec} rows/sec\n";
$stmt->close();

/* DROP TABLE */
$link->query("DROP TABLE bench_exec");


$link->close();

?>
