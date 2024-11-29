<?php
// Define the task name and command
$taskName = 'WriteToFileTest';
$scriptPath = 'C:\\_runVCP\\Script.bat';
$startTime = date('H:i', strtotime('+1 minute'));

$scheduleCommand = 'schtasks /create /sc once /tn "' . $taskName . '" /tr "' . $scriptPath . '" /st ' . $startTime . ' /f /RU "Engineer"';
exec($scheduleCommand, $output, $returnVar);
print_r($output);
echo "Return code: $returnVar";
// Display output and status
if ($returnVar === 0) {
    echo "Task successfully created!\n";
    echo implode("\n", $output);
} else {
    echo "Failed to create task.\n";
    echo implode("\n", $output);
}
