<?php
// Check if 'runBatch=true' is set and has not already been executed (indicated by 'print=true')

if (isset($_GET['runBatch']) && $_GET['runBatch'] === 'true' && !isset($_GET['print'])) {
	//if (isset($_GET['test'])) {	
	// Get the current URL
	$currentUrl = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	echo "masuk..<br>";

	// Escape any "&" characters in the URL for Windows batch file
	$escapedUrl = str_replace('&', '^&', $currentUrl);
	$escapedUrl2 = str_replace('runBatch', 'print', $escapedUrl);
	//echo "$escapedUrl2<br>";

	// Define the batch file and scheduled task parameters
	$batFilePath = 'C:\\_runVCP\\PrintScript.bat';
	$taskName = 'xampp-temp-schtasks';

	// Use the escaped URL as an argument to the batch file
	//$taskCommand = 'schtasks /create /sc once /tn "' . $taskName . '" /tr "' . $batFilePath . ' \'' . $escapedUrl . '\' " /st ' . date("H:i", strtotime('+1 minute')) . ' /f /RU "vboxuser"';
	$taskCommand = 'schtasks /create /sc once /tn "' . $taskName . '" /tr "' . $batFilePath . ' \'' . $escapedUrl2 . '\' " /st ' . date("H:i", strtotime('+1 minute')) . ' /f /RU "Engineer';
	// Display the task command for debugging
	//echo "$taskCommand<br>";

	// Create the scheduled task
	//print_r(shell_exec($taskCommand));
	shell_exec($taskCommand);

	// Run the scheduled task immediately
	//print_r(shell_exec('schtasks /run /tn "' . $taskName . '"'));
	shell_exec('schtasks /run /tn "' . $taskName . '"');

	// Optionally, delete the task after it runs
	//print_r(shell_exec('schtasks /delete /tn "' . $taskName . '" /f'));
	shell_exec('schtasks /delete /tn "' . $taskName . '" /f');
	header("Location: " . str_replace('&runBatch=true', '', $currentUrl));
}


// SQL Server connection parameters
$serverName = "DESKTOP-BT3IPAF\\SQLEXPRESS"; // Replace with your SQL Server name or IP
$database = "VCP";                 // Replace with your database name
$username = "VCPUser";                    // Replace with your username
$password = "lrs";                    // Replace with your password
$maxTables = 30;                   // Maximum number of tables to query
$printMode = isset($_GET['print']) && $_GET['print'] == 'true';
// Set default filters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'today';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$rowsPerPage = 2000; // Maximum number of rows per page

// Handle 'Today' and 'Yesterday' filters for the title date range
if ($filter === 'today') {
	$startDate = date('Y-m-d 00:00');
	//$endDate = date('Y-m-d H:i');
	$endDate = date('Y-m-d 23:59');
} elseif ($filter === 'yesterday') {
	$startDate = date('Y-m-d 00:00', strtotime('-1 day'));
	$endDate = date('Y-m-d 23:59', strtotime('-1 day'));
} elseif ($filter === 'range') {
	// Ensure $startDate has time "00:00"
	if (strpos($startDate, ' ') === false) {
		// No time in $startDate, append "00:00"
		$startDate .= ' 00:00';
	}

	// Ensure $endDate has time "23:59"
	if (strpos($endDate, ' ') === false) {
		// No time in $endDate, append "23:59"
		$endDate .= ' 23:59';
	}
	// Convert them to datetime format if necessary (optional)
	$startDate = date('Y-m-d H:i', strtotime($startDate));
	$endDate = date('Y-m-d H:i', strtotime($endDate));

	// Convert dd-mm-yyyy to yyyy-mm-dd
	//$startDate = DateTime::createFromFormat('d-m-Y', $startDateInput)->format('Y-m-d 00:00:00');
	//$endDate = DateTime::createFromFormat('d-m-Y', $endDateInput)->format('Y-m-d 23:59:59');

}

// Connect to SQL Server using PDO
try {
	$conn = new PDO("sqlsrv:server=$serverName;Database=$database", $username, $password);
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	//echo "Connection successful! <br>";
} catch (PDOException $e) {
	die("Connection failed, tod: " . $e->getMessage());
}

// Initialize an array to collect all records from each table
$allResults = [];

// Build query filters based on the selected filter type
$timeFilter = "";
if ($filter == 'today') {
	//$timeFilter = "WHERE TimeString >= CONVERT(DATETIME, CONVERT(VARCHAR, GETDATE(), 101))";
	$timeFilter = "WHERE CONVERT(DATE, TimeString, 103) = CONVERT(DATE, GETDATE())";
	//echo "timeFilter: $timeFilter <br>";
} elseif ($filter == 'yesterday') {
	//$timeFilter = "WHERE TimeString >= DATEADD(DAY, -1, CONVERT(DATETIME, CONVERT(VARCHAR, GETDATE(), 101))) AND TimeString < CONVERT(DATETIME, CONVERT(VARCHAR, GETDATE(), 101))";
	$timeFilter =	"WHERE CONVERT(DATE, TimeString, 103) = CONVERT(DATE, DATEADD(DAY, -1, GETDATE()))";
} elseif ($filter == 'range' && $startDate && $endDate) {
	//echo "startDate: $startDate<br>";
	//echo "endDate  : $endDate<br>";
	//$timeFilter = "WHERE cast(TimeString as DateTime) BETWEEN '$startDate' AND '$endDate'";
	$timeFilter = 	"WHERE CONVERT(DATETIME, TimeString, 103) BETWEEN '$startDate' AND '$endDate'";
	//$timeFilter  = "WHERE CONVERT(DATETIME, TimeString, 103) BETWEEN '2024-11-28 10:00:00' AND '2024-11-28 12:59:59'";
	//echo "endDate  : test";
}


// Query each table from Datalog_Metering0 to Datalog_MeteringN
for ($i = 0; $i < $maxTables; $i++) {
	$tableName = "dbo.Datalog_Metering" . $i;
	$sql = "
			SELECT TimeString,
				MAX(CASE WHEN VarName = '20KV_INC_Voltage' THEN VarValue END) AS '20kV (V)',
				MAX(CASE WHEN VarName = '20KV_INC_Current' THEN VarValue END) AS '20kV (I)',
				MAX(CASE WHEN VarName = 'INC1_FeederVolt' THEN VarValue END) AS 'MU (V)',
				MAX(CASE WHEN VarName = 'INC1_FeederCurr' THEN VarValue END) AS 'MU (A)',
				MAX(CASE WHEN VarName = 'F1_FeederVolt' THEN VarValue END) AS 'F1 (V)',
				MAX(CASE WHEN VarName = 'F1_FeederCurr' THEN VarValue END) AS 'F1 (A)',
				MAX(CASE WHEN VarName = 'F2_FeederVolt' THEN VarValue END) AS 'F2 (V)',
				MAX(CASE WHEN VarName = 'F2_FeederCurr' THEN VarValue END) AS 'F2 (A)',
				MAX(CASE WHEN VarName = 'F3_FeederVolt' THEN VarValue END) AS 'F3 (V)',
				MAX(CASE WHEN VarName = 'F3_FeederCurr' THEN VarValue END) AS 'F3 (A)',
				MAX(CASE WHEN VarName = 'F4_FeederVolt' THEN VarValue END) AS 'F4 (V)',
				MAX(CASE WHEN VarName = 'F4_FeederCurr' THEN VarValue END) AS 'F4 (A)'
			FROM $tableName
			$timeFilter
			GROUP BY TimeString
		";

	try {
		// Execute the query and fetch data
		$stmt = $conn->prepare($sql);
		$stmt->execute();
		$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// Merge the results from the current table into the main results array
		$allResults = array_merge($allResults, $results);
	} catch (PDOException $e) {
		// Catch errors like missing tables and continue with the next table
		// echo "Error executing query : " . $e->getMessage(). "<br>";
		continue;
	}
}

// Sort the results based on the selected order
usort($allResults, function ($a, $b) use ($order) {
	$timeA = strtotime($a['TimeString']);
	$timeB = strtotime($b['TimeString']);
	return $order === 'ASC' ? $timeA - $timeB : $timeB - $timeA;
});

// Calculate pagination
$totalRows = count($allResults);
$totalPages = ceil($totalRows / $rowsPerPage);
$startIndex = ($page - 1) * $rowsPerPage;
$pagedResults = array_slice($allResults, $startIndex, $rowsPerPage);

// Close the connection
$conn = null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Data Report</title>
	<style>
		body {
			margin-left: 20px;
			margin-right: 20px;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		th,
		td {
			padding: 10px;
			border: 1px solid black;
			text-align: left;
			width: 120px;
		}

		th {
			background-color: #f2f2f2;
		}

		tr:nth-child(even) {
			background-color: #f9f9f9;
		}

		tr:nth-child(odd) {
			background-color: #e8f8f5;
		}

		/* Styling for layout changes */
		.container {
			display: flex;
			/* Use flexbox to layout child elements */
			justify-content: space-between;
			/* Space between filter and print */
			align-items: center;
			/* Center items vertically */
			padding: 15px;
			/* Add padding for spacing */
			background-color: #f9f9f9;
			/* Light background color for the container */
			border: 1px solid #ddd;
			/* Border to define the container */
			border-radius: 8px;
			/* Rounded corners */
			margin: 20px;
			/* Margin around the container */
		}

		.filter-container {
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		#filter-buttons {
			display: flex;
			gap: 10px;
		}


		/* Container for pagination links aligned to the top-right */
		.pagination-container {
			position: relative;
			text-align: right;
			/* Align to the top-right */
			margin-bottom: 5px;
			/* Spacing between pagination and go-to form */
		}

		.page-links {
			position: relative;
			text-align: right;
			margin-bottom: 10px;
			/* Adds space between links and the form */
		}

		.page-links a {
			margin: 0 5px;
			/* Add spacing between pagination links */
			/*text-decoration: none;
			/* Remove underline from links */
			/*text-align: center;
			/* or center depending on your preference */
			color: #007bff;
			/* Link color */
		}

		.page-links a:hover {
			color: #0056b3;
			/* Hover color for links */
		}

		.go-to-page {
			position: relative;
			text-align: right;
			/* Align to the right, just below pagination */
			margin-bottom: 10px;
			/* Add space below for the title */
		}

		.go-to-page input[type="number"] {
			width: 50px;
			/* Limit the width of the number input */
			padding: 5px;
			margin-left: 5px;
		}

		.go-to-page button {
			padding: 5px 10px;
			background-color: #28a745;
			/* Button color */
			color: white;
			border: none;
			border-radius: 3px;
			cursor: pointer;
		}

		.go-to-page button:hover {
			background-color: #218838;
			/* Darker green on hover */
		}


		.print-container {
			text-align: right;
			/* Aligns the content inside the div to the right */
			margin-right: 20px;
			/* Optional: Adjust the right margin */
			align-items: center;
		}

		#print-link {
			text-decoration: none;
			/* Remove underline from the print link */
			padding: 8px 12px;
			/* Padding for the link button */
			background-color: #28a745;
			/* Green background color */
			color: white;
			/* Text color */
			border-radius: 5px;
			/* Rounded corners */
			transition: background-color 0.3s;
			/* Smooth transition for hover effect */
		}


		#print-link:hover {
			background-color: #f0f0f0;
			/* Optional: Change background color on hover */
		}


		/* Title styling */
		.page-title {
			text-align: center;
			margin-top: 20px;
			margin-bottom: 20px;
		}

		h1 {
			margin: 0;
		}

		.subtitle {
			margin-top: 10px;
		}
	</style>

	<script>
		// Automatically trigger print dialog if URL contains 'print=true'
		window.onload = function() {
			const urlParams = new URLSearchParams(window.location.search);
			if (urlParams.get('print') === 'true') {

				window.print();
			}
		};
	</script>
</head>

<body>
	<div id="pagination">
		<div class="page-links">
			<?php if ($page > 1): ?>
				<!-- First Page Link -->
				<a
					href="index.php?filter=<?= $filter ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&order=<?= $order ?>&page=1">First</a>

				<!-- Previous Page Link -->
				<a
					href="index.php?filter=<?= $filter ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&order=<?= $order ?>&page=<?= $page - 1 ?>">Previous</a>
			<?php endif; ?>

			<!-- Current Page and Total Pages Display -->
			Page <?= $page ?> of <?= $totalPages ?>

			<?php if ($page < $totalPages): ?>
				<!-- Next Page Link -->
				<a
					href="index.php?filter=<?= $filter ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&order=<?= $order ?>&page=<?= $page + 1 ?>">Next</a>

				<!-- Last Page Link -->
				<a
					href="index.php?filter=<?= $filter ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&order=<?= $order ?>&page=<?= $totalPages ?>">Last</a>
			<?php endif; ?>
		</div>
		<div class="go-to-page">
			<!-- Go to Page Form -->
			<form action="index.php" method="get" style="display: inline;">
				<input type="hidden" name="filter" value="<?= $filter ?>">
				<input type="hidden" name="start_date" value="<?= $startDate ?>">
				<input type="hidden" name="end_date" value="<?= $endDate ?>">
				<input type="hidden" name="order" value="<?= $order ?>">

				<label for="gotoPage">Go to Page:</label>
				<input type="number" id="gotoPage" name="page" min="1" max="<?= $totalPages ?>" required>
				<input type="submit" value="Go">
			</form>
		</div>
	</div>

	<!-- Title and Date Range Section -->
	<div class="page-title">
		<h1>Data Report</h1>
		<div class="subtitle">
			From: <?= htmlspecialchars($startDate) ? $startDate : 'N/A' ?>
			To: <?= htmlspecialchars($endDate) ? $endDate : 'N/A' ?>
		</div>
	</div>
	<div class="container">
		<div class="filter-container">
			<div id="filter-buttons">
				<a href="index.php?filter=today&order=<?= $order ?>">Today</a>
				<a href="index.php?filter=yesterday&order=<?= $order ?>">Yesterday</a>
				<form method="get" action="index.php" style="display: inline;">
					<input type="hidden" name="filter" value="range">
					<label>Start Date:</label>
					<input type="datetime" name="start_date" placeholder="YYYY-MM-DD HH:MM">
					<label>End Date:</label>
					<input type="datetime" name="end_date" placeholder="YYYY-MM-DD HH:MM">
					<input type="hidden" name="order" value="<?= $order ?>">
					<button type="submit">Apply Range</button>
				</form>

			</div>
		</div>

		<!-- Link to trigger the batch file, appending ?runBatch=true to the URL -->
		<div class="print-container">
			<!-- Link to trigger the batch file, appending ?runBatch=true to the URL -->
			<a href="index.php?filter=<?= $filter ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&order=<?= $order ?>&page=<?= $page ?>&runBatch=true"
				id="print-link">PRINT</a>

		</div>
	</div>

	<!-- Pagination Section -->



	<!-- Display the data table -->
	<table>
		<thead>
			<tr>
				<th>#</th>
				<th>TimeString</th>
				<th>20kV (V)</th>
				<th>20kV (I)</th>
				<th>MU (V)</th>
				<th>MU (A)</th>
				<th>F1 (V)</th>
				<th>F1 (A)</th>
				<th>F2 (V)</th>
				<th>F2 (A)</th>
				<th>F3 (V)</th>
				<th>F3 (A)</th>
				<th>F4 (V)</th>
				<th>F4 (A)</th>
			</tr>
		</thead>
		<tbody>
			<?php if (!empty($pagedResults)): ?>
				<?php foreach ($pagedResults as $index => $row): ?>
					<tr>
						<td><?php echo $index + 1 + ($page - 1) * $rowsPerPage; ?></td>
						<td><?php echo $row['TimeString']; ?></td>
						<td><?php echo is_numeric($row['20kV (V)']) ? number_format($row['20kV (V)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['20kV (I)']) ? number_format($row['20kV (I)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['MU (V)']) ? number_format($row['MU (V)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['MU (A)']) ? number_format($row['MU (A)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['F1 (V)']) ? number_format($row['F1 (V)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['F1 (A)']) ? number_format($row['F1 (A)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['F2 (V)']) ? number_format($row['F2 (V)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['F2 (A)']) ? number_format($row['F2 (A)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['F3 (V)']) ? number_format($row['F3 (V)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['F3 (A)']) ? number_format($row['F3 (A)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['F4 (V)']) ? number_format($row['F4 (V)'], 2) : 'N/A'; ?></td>
						<td><?php echo is_numeric($row['F4 (A)']) ? number_format($row['F4 (A)'], 2) : 'N/A'; ?></td>
					</tr>
				<?php endforeach; ?>
			<?php else: ?>
				<tr>
					<td colspan="14">No data available for the selected range.</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>


</body>

</html>