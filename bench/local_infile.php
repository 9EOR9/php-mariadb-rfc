<?php

require_once 'connect.inc';

$csv_file = 'spieler.csv';

$link = new mysqli($host, $user, $passwd, $db, $socket);
$link->set_charset("utf8mb4");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Ensure LOAD DATA is allowed
$link->options(MYSQLI_OPT_LOCAL_INFILE, true);

/**
 * Method 1: LOAD DATA LOCAL INFILE
 */
function test_load_data($link, $file) {
  global $data_rows;

	$link->query("TRUNCATE TABLE dwz_spieler");
	$start = microtime(true);

	$query = "LOAD DATA LOCAL INFILE '" . addslashes($file) . "' 
		INTO TABLE dwz_spieler 
		FIELDS TERMINATED BY ',' 
		OPTIONALLY ENCLOSED BY '\"' 
		LINES TERMINATED BY '\\n'
		IGNORE 1 LINES"; // Adjust based on your CSV header

	$link->query($query);
  if ($link->affected_rows != $data_rows) {
		die("only $link->affected_rows were inserted");
	}


	return microtime(true) - $start;
}

/**
 * Method 2: mysqli_stmt::executemany() with Streaming Generator
 */
function stream_csv(string $file, int $chunkSize = 8192, bool $skipHeader = true): Generator {
    $handle = fopen($file, 'r');
    if (!$handle) throw new RuntimeException("Cannot open file");

    $buffer = '';
    if ($skipHeader) fgets($handle);

    while (!feof($handle)) {
        $chunk = fread($handle, $chunkSize);
        if ($chunk === false) break;

        $buffer .= $chunk;
        $lines = explode("\n", $buffer);
        $buffer = array_pop($lines);

        $batch = []; // Collect data for this 8k block
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if ($line === '') continue;

            $data = str_getcsv($line, ",", "\"", "");
            if (count($data) !== 15) continue;

            foreach ($data as $i => $v) {
                if ($v === '') $data[$i] = null;
            }
            $batch[] = $data; 
        }

        // Yield all rows found in this chunk at once
        if (!empty($batch)) {
            yield from $batch;
        }
    }

    /* Final buffer handling */
    if ($buffer !== '') {
        $data = str_getcsv(rtrim($buffer, "\r"), ",", "\"", "");
        if (count($data) === 15) {
            foreach ($data as $i => $v) {
                if ($v === '') $data[$i] = null;
            }
            yield $data;
        }
    }
    fclose($handle);
}

function test_executemany($link, $file) {
	global $data_rows;
	$link->query("TRUNCATE TABLE dwz_spieler");
	$start = microtime(true);

	$sql = "INSERT INTO dwz_spieler (
			PID, ZPS, Mgl_Nr, Status, Spielername, Geschlecht, 
			Spielberechtigung, Geburtsjahr, Letzte_Auswertung, 
			DWZ, DWZ_Index, FIDE_Elo, FIDE_Titel, FIDE_ID, FIDE_Land
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

	$stmt = $link->prepare($sql);

	// Types: PID(i), ZPS(s), Mgl(i), Status(s), Name(s), Sex(s), 
	// Auth(s), Year(i), Eval(i), DWZ(i), Idx(i), Elo(i), Title(s), FID(i), Land(s)
	$stmt->executemany("sssssssssssssss", stream_csv($file));

  if ($stmt->affected_rows != $data_rows) {
		die("only $stmt->affected_rows were inserted");
	}
	return microtime(true) - $start;
}

// Execution
if (!file_exists($csv_file)) {
    die("Error: File $csv_file not found.\n");
}

$filesize_bytes = filesize($csv_file);
$filesize_formatted = round($filesize_bytes / 1024 / 1024, 2) . " MB";

// Fast way to count lines without loading whole file into memory
$line_count = 0;
$handle = fopen($csv_file, "r");
while (!feof($handle)) {
    while (fgets($handle))
	    $line_count++;
}
fclose($handle);

// Subtract 1 for the header row
$data_rows = $line_count - 1;

echo "--------------------------------------\n";
echo "Benchmark File: $csv_file\n";
echo "File Size:      $filesize_formatted\n";
echo "Data Rows:      " . $data_rows . "\n";
echo "--------------------------------------\n";
echo "Starting Benchmark...\n";

$time_load = test_load_data($link, $csv_file);
printf("LOAD DATA LOCAL INFILE: %.4f seconds\n", $time_load);

$time_execute = test_executemany($link, $csv_file);
printf("mysqli_stmt::executemany (using stream): %.4f seconds\n", $time_execute);
