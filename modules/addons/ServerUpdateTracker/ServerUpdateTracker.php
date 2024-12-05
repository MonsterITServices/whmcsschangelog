<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\User\Admin;

function ServerUpdateTracker_config()
{
    return [
        "name" => "Server Update Tracker",
        "description" => "A custom module to monitor and log updates for customer servers and websites.",
        "version" => "1.6",
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
    $adminUsername = Admin::getAuthenticatedUser()['username']; // Correctly retrieve WHMCS admin username

    // Handle form submission for adding a server
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['addServer'])) {
            $serverName = mysql_real_escape_string($_POST['server_name']);
            $ipAddress = !empty($_POST['ip_address']) ? mysql_real_escape_string($_POST['ip_address']) : null;
            $updateLog = mysql_real_escape_string($_POST['update_log']);

            $query = "INSERT INTO `mod_server_updates` (`server_name`, `ip_address`, `update_log`, `last_updated`, `added_by`) 
                      VALUES ('$serverName', '$ipAddress', '$updateLog', NOW(), '$adminUsername')";
            if (!full_query($query)) {
                echo '<div class="alert alert-danger">Failed to add entry: ' . mysql_error() . '</div>';
            } else {
                echo '<div class="alert alert-success">Server/Website added successfully!</div>';
            }
        }
    }

    // Handle deletion of an entry
    if (isset($_GET['delete'])) {
        $deleteId = intval($_GET['delete']);
        $deleteQuery = "DELETE FROM `mod_server_updates` WHERE `id` = $deleteId";
        full_query($deleteQuery);
        echo '<div class="alert alert-success">Entry deleted successfully!</div>';
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
    $query .= " ORDER BY `last_updated` DESC";
    $results = full_query($query);

    // Add New Entry Form
    echo '<div class="container-fluid">';
    echo '<div class="row mb-4">
            <div class="col-12">
                <h2 class="text-primary">Add New Server/Website</h2>
                <form method="post" class="card shadow-sm p-4">
                    <div class="mb-3">
                        <label for="server_name" class="form-label">Server/Website Name:</label>
                        <input type="text" class="form-control" id="server_name" name="server_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="ip_address" class="form-label">IP Address (Optional):</label>
                        <input type="text" class="form-control" id="ip_address" name="ip_address">
                    </div>
                    <div class="mb-3">
                        <label for="update_log" class="form-label">Update Log:</label>
                        <textarea class="form-control" id="update_log" name="update_log" rows="3" required></textarea>
                    </div>
                    <button type="submit" name="addServer" class="btn btn-success">Add Server/Website</button>
                </form>
            </div>
        </div>';

    // Filter Dropdown
    echo '<div class="row mb-4">
            <div class="col-12">
                <form method="post" class="d-flex align-items-center">
                    <select name="filter_server" class="form-select me-2">
                        <option value="">-- Show All --</option>';
    foreach ($serverNames as $name) {
        $selected = $filter === $name ? 'selected' : '';
        echo "<option value=\"$name\" $selected>$name</option>";
    }
    echo '      </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>';

    // Display Table
    echo '<div class="row">
            <div class="col-12">
                <h2 class="text-primary">Tracked Servers/Websites</h2>
                <table class="table table-hover table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Server/Website Name</th>
                            <th>IP Address</th>
                            <th>Update Log</th>
                            <th>Last Updated</th>
                            <th>Added By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>';
    while ($row = mysql_fetch_assoc($results)) {
        echo '<tr>
                <td>' . htmlspecialchars($row['server_name']) . '</td>
                <td>' . ($row['ip_address'] ?: '<em class="text-muted">Not Provided</em>') . '</td>
                <td>' . nl2br(htmlspecialchars($row['update_log'])) . '</td>
                <td>' . htmlspecialchars($row['last_updated']) . '</td>
                <td>' . htmlspecialchars($row['added_by']) . '</td>
                <td>
                    <a href="?module=ServerUpdateTracker&delete=' . $row['id'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this entry?\')">Delete</a>
                </td>
              </tr>';
    }
    echo '      </tbody>
                </table>
            </div>
        </div>
    </div>';
}
