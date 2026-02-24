<?php
// scripts/stress_test_concurrency.php
declare(strict_types=1);

$datasetId = 23;
$concurrentRequests = 5;
$url = "http://localhost/api/optimizations"; // Adjust if needed, but we can call the controller directly to avoid web server dependency in CLI

// Since we are in CLI, calling the web API might be tricky without a full URL.
// Let's use the DatasetController directly if possible, or just mock the concurrent execution.
// Actually, the objective is to test "Session fixes", which implies testing the web entry points.

// We will use curl to the local web server.
$baseUrl = "http://localhost/tdt-optimization/public/dataset/run/$datasetId";

echo "Starting stress test with $concurrentRequests concurrent requests to $baseUrl...
";

$mh = curl_multi_init();
$handles = [];

for ($i = 0; $i < $concurrentRequests; $i++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

$successCount = 0;
$failCount = 0;

foreach ($handles as $ch) {
    $response = curl_multi_getcontent($ch);
    $info = curl_getinfo($ch);
    $data = json_decode($response, true);
    
    if ($info['http_code'] === 200 && ($data['success'] ?? false)) {
        $successCount++;
    } else {
        $failCount++;
        echo "Request failed with code {$info['http_code']}: $response
";
    }
    curl_multi_remove_handle($mh, $ch);
}

curl_multi_close($mh);

echo "
Stress Test Results:
";
echo " - Success: $successCount
";
echo " - Fail: $failCount
";

if ($failCount === 0) {
    echo "✅ PASS: Concurrency stability confirmed.
";
} else {
    echo "❌ FAIL: Concurrency issues detected.
";
}

// Update report
$report_path = __DIR__ . '/../docs/validation/phase4_validation_report.md';
$report_content = file_get_contents($report_path);
$report_content .= "
## 5. Concurrency Stability Validation

";
$report_content .= "### A. Stress Protocol

";
$report_content .= "**Objective:** Confirm that the system handles multiple rapid optimization triggers without deadlocks or UI hanging.

";
$report_content .= "**Method:** Five concurrent POST requests were sent to the optimization trigger endpoint for the same dataset. The system's ability to process these requests (either by queueing, parallel execution, or safe rejection) was monitored.

";

if ($failCount === 0) {
    $report_content .= "**Conclusion: ✅ PASS**

";
    $report_content .= "All $concurrentRequests concurrent requests completed successfully. The session locking fixes and non-blocking pipe reads in `DatasetController` successfully prevent hanging.
";
} else {
    $report_content .= "**Conclusion: ❌ FAIL**

";
    $report_content .= "Some concurrent requests failed. This indicates potential race conditions or resource exhaustion.
";
}

file_put_contents($report_path, $report_content);
