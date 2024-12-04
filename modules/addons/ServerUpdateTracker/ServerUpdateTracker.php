<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function ServerUpdateTracker_config()
{
    return [
        "name" => "Server Update Tracker",
        "description" => "A custom module to monitor and log updates for customer servers and websites.",
        "version" => "1.2",
        "author" => "Monster IT Services Ltd",
        "fields" => [],
    ];
}

function ServerUpdateTracker_activate()
{
    $query = "CREATE TABLE IF NOT EXISTS `mod_server_updates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `server_name` VARCHAR(255) NOT NULL,
        `ip_address` VARCHAR(255) DEFAULT NULL,
        `update_log` TEXT NOT NULL,
        `last_updated` DATETIME NOT NULL,
        `added_by` VARCHAR(255) NOT NULL
    )";
    full_query($query);
    return [
        'status' => 'success',
        'description' => 'The Server Update Tracker module has been activated successfully.'
    ];
}

function ServerUpdateTracker_deactivate()
{
    $query = "DROP TABLE IF EXISTS `mod_server_updates`";
    full_query($query);
    return [
        'status' => 'success',
        'description' => 'The Server Update Tracker module has been deactivated and removed.'
    ];
}

function ServerUpdateTracker_output($vars)
{
    $adminUsername = $_SESSION['adminusername']; // Get the WHMCS admin username

    // Handle form submission for adding a server
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['addServer'])) {
            $serverName = mysql_real_escape_string($_POST['server_name']);
            $ipAddress = !empty($_POST['ip_address']) ? mysql_real_escape_string($_POST['ip_address']) : null;
            $updateLog = mysql_real_escape_string($_POST['update_log']);

            $query = "INSERT INTO `mod_server_updates` (`server_name`, `ip_address`, `update_log`, `last_updated`, `added_by`) 
                      VALUES ('$serverName', '$ipAddress', '$updateLog', NOW(), '$adminUsername')";
            full_query($query);
            echo '<div class="successbox">Server/Website added successfully!</div>';
        }
    }

    // Handle deletion of a record
    if (isset($_GET['delete'])) {
        $deleteId = intval($_GET['delete']);
        full_query("DELETE FROM `mod_server_updates` WHERE `id` = $deleteId");
        echo '<div class="infobox">Record deleted successfully!</div>';
    }

    // Fetch all unique server names for the dropdown
    $dropdownResults = full_query("SELECT DISTINCT `server_name` FROM `mod_server_updates`");
    $serverNames = [];
    while ($row = mysql_fetch_assoc($dropdownResults)) {
        $serverNames[] = $row['server_name'];
    }

    // Filtering logic
    $filter = isset($_POST['filter_server']) ? mysql_real_escape_string($_POST['filter_server']) : null;
    $query = "SELECT * FROM `mod_server_updates`";
    if ($filter) {
        $query .= " WHERE `server_name` = '$filter'";
    }
    $query .= " ORDER BY `server_name` ASC";
    $results = full_query($query);

    // Display form for adding new entries
    echo '<form method="post" action="">
            <h2>Add Server/Website</h2>
            <label>Server/Website Name:</label><br>
            <input type="text" name="server_name" required><br><br>
            <label>IP Address (Optional):</label><br>
            <input type="text" name="ip_address"><br><br>
            <label>Update Log:</label><br>
            <textarea name="update_log" required></textarea><br><br>
            <input type="submit" name="addServer" value="Add Server/Website">
          </form>';

    // Display filtering dropdown
    echo '<form method="post" action="">
            <h2>Filter by Server/Website</h2>
            <label>Select Server/Website:</label><br>
            <select name="filter_server">
                <option value="">-- Show All --</option>';
    foreach ($serverNames as $name) {
        $selected = $filter === $name ? 'selected' : '';
        echo "<option value=\"$name\" $selected>$name</option>";
    }
    echo '  </select>
            <br><br>
            <input type="submit" value="Filter">
          </form>';

    // Display table
    echo '<h2>Tracked Servers/Websites</h2>';
    echo '<table border="1" cellspacing="0" cellpadding="5">
            <tr>
                <th>Server/Website Name</th>
                <th>IP Address</th>
                <th>Update Log</th>
                <th>Last Updated</th>
                <th>Added By</th>
                <th>Actions</th>
            </tr>';
    while ($row = mysql_fetch_assoc($results)) {
        echo '<tr>
                <td>' . htmlspecialchars($row['server_name']) . '</td>
                <td>' . ($row['ip_address'] ?: 'Not Provided') . '</td>
                <td>' . nl2br(htmlspecialchars($row['update_log'])) . '</td>
                <td>' . htmlspecialchars($row['last_updated']) . '</td>
                <td>' . htmlspecialchars($row['added_by']) . '</td>
                <td>
                    <a href="?module=ServerUpdateTracker&delete=' . $row['id'] . '" onclick="return confirm(\'Are you sure you want to delete this record?\')">Delete</a>
                </td>
              </tr>';
    }
    echo '</table>';
}
?>
