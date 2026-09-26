<?php
require_once __DIR__ . '/database.php';

try {
    $tablesStmt = $db->query("SHOW TABLES");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Found " . count($tables) . " tables in database '" . DB_NAME . "':\n";
    foreach ($tables as $t) {
        $count = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo " - $t ($count rows)\n";
    }

    // Now let's generate a complete, up-to-date db.sql
    $sqlDump = "-- ========================================================\n";
    $sqlDump .= "-- HAAT Multi-Vendor E-Commerce & Logistics Platform\n";
    $sqlDump .= "-- Complete Database Schema & Seed Data\n";
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- ========================================================\n\n";
    $sqlDump .= "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    $sqlDump .= "USE `" . DB_NAME . "`;\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $t) {
        $sqlDump .= "-- --------------------------------------------------------\n";
        $sqlDump .= "-- Table structure for table `$t`\n";
        $sqlDump .= "-- --------------------------------------------------------\n\n";
        $sqlDump .= "DROP TABLE IF EXISTS `$t`;\n";

        $createStmt = $db->query("SHOW CREATE TABLE `$t`")->fetch();
        $createSql = $createStmt['Create Table'] ?? '';
        $sqlDump .= $createSql . ";\n\n";

        // Table Data
        $rowsStmt = $db->query("SELECT * FROM `$t`");
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $sqlDump .= "-- Dumping data for table `$t` (" . count($rows) . " rows)\n";
            $sqlDump .= "INSERT INTO `$t` VALUES\n";
            
            $rowStrings = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $values[] = "NULL";
                    } elseif (is_numeric($val) && !is_string($val)) {
                        $values[] = $val;
                    } else {
                        $values[] = $db->quote($val);
                    }
                }
                $rowStrings[] = "(" . implode(", ", $values) . ")";
            }
            $sqlDump .= implode(",\n", $rowStrings) . ";\n\n";
        }
    }

    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    $targetFile = dirname(__DIR__) . '/db.sql';
    file_put_contents($targetFile, $sqlDump);
    echo "\nSuccessfully generated and synchronized complete updated " . $targetFile . " (" . strlen($sqlDump) . " bytes)!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
