<?php
$taskName = "TestTaskFromPHP";
$taskCommand = 'notepad.exe';
$scheduleCommand = 'schtasks /create /sc once /tn "' . $taskName . '" /tr "' . $taskCommand . '" /st ' . date("H:i", strtotime('+1 minute')) . ' /f /RU SYSTEM';
$output = shell_exec($scheduleCommand . ' 2>&1'); // Capture errors as well
echo "Command executed:\n$scheduleCommand\n";
echo "Output:\n$output\n";
