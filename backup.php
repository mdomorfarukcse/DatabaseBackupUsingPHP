<?php
// Database connection parameters
$host = 'localhost';     // Replace with your database host
$username = ''; // Replace with your database username
$password = ''; // Replace with your database password
$database = ''; // Replace with your database name

// Tables to backup
$tablesToBackup = ['table1', 'table2']; // Replace with the tables you want to backup

// Backup directory
$backupDir = 'backup_files/'; // Replace with the path to your backup directory

// Create a timestamp for the backup file
$timestamp = date('Y-m-d-H-i-s');

// Create a directory for today's backups
$todayBackupDir = $backupDir . date('Y-m-d') . '/';
if (!is_dir($todayBackupDir)) {
    mkdir($todayBackupDir, 0755, true);
}

// Create a MySQLi database connection
$mysqli = new mysqli($host, $username, $password, $database);

// Check for connection errors
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Initialize the zip archive
$zip = new ZipArchive();
$zipFileName = $todayBackupDir . 'backup_' . $timestamp . '.zip';

if ($zip->open($zipFileName, ZipArchive::CREATE) === true) {
    // Loop through the tables and create backup files
    foreach ($tablesToBackup as $table) {
        // Retrieve data from the table
        $result = $mysqli->query("SELECT * FROM $table");
        
        if ($result) {
            $fp = fopen("php://temp", 'w');
            
            // Write SQL statements to the temporary file
            while ($row = $result->fetch_assoc()) {
                $sql = "INSERT INTO $table (";
                $values = "VALUES (";
                
                foreach ($row as $key => $value) {
                    $sql .= "`$key`, ";
                    $values .= "'" . $mysqli->real_escape_string($value) . "', ";
                }
                
                $sql = rtrim($sql, ', ') . ") ";
                $values = rtrim($values, ', ') . ");\n";
                
                fwrite($fp, $sql . $values);
            }
            
            // Rewind the temporary file pointer
            rewind($fp);
            
            // Add the file to the zip archive
            $zip->addFromString("$table.sql", stream_get_contents($fp));
            
            fclose($fp);
            
            echo "Backup of table $table completed successfully.\n";
        } else {
            echo "Backup of table $table failed. Error: " . $mysqli->error . "\n";
        }
        
        // Free the result set
        $result->free_result();
    }
    
    // Close the zip archive
    $zip->close();
    
    echo "Backup zip file created: $zipFileName\n";
} else {
    echo "Failed to create the zip archive.\n";
}

// Close the MySQLi connection
$mysqli->close();

// Delete backup folders older than 30 days
$oldBackupThreshold = strtotime('-30 days');
$backupFolders = glob($backupDir . '*', GLOB_ONLYDIR);

foreach ($backupFolders as $folder) {
    $folderTimestamp = strtotime(basename($folder));
    if ($folderTimestamp < $oldBackupThreshold) {
        // Delete the old backup folder
        if (is_dir($folder)) {
            array_map('unlink', glob("$folder/*.*"));
            rmdir($folder);
            echo "Deleted old backup folder: $folder\n";
        }
    }
}
?>
