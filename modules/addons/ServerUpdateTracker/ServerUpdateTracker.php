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
        "version" => "1.7",
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
    $adminUsername = Admin::getAuthenticatedUser()['username']; 

    // Handle form submission for adding a server
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['addServer'])) {
            $serverName = mysql_real_escape_string($_POST['server_name']);
            $updateLog = mysql_real_escape_string($_POST['update_log']);

            // Insert NULL for ip_address to prevent breaking the existing DB table schema
            $query = "INSERT INTO `mod_server_updates` (`server_name`, `ip_address`, `update_log`, `last_updated`, `added_by`) 
                      VALUES ('$serverName', NULL, '$updateLog', NOW(), '$adminUsername')";
            if (!full_query($query)) {
                echo '<div class="alert alert-danger">Failed to add entry: ' . mysql_error() . '</div>';
            } else {
                echo '<div class="alert alert-success">Server/Website/IP added successfully!</div>';
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

    // Fetch all records - Pagination and filtering will be handled by DataTables
    $query = "SELECT * FROM `mod_server_updates` ORDER BY `last_updated` DESC";
    $results = full_query($query);

    // Inject DataTables and Bootstrap 5 CSS/JS to guarantee styling and responsiveness
    echo '
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css"/>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    ';

    echo '<div class="container-fluid" style="margin-top: 20px;">';
    
    // Add New Entry Form
    echo '<div class="row mb-4">
            <div class="col-12">
                <h2 class="text-primary">Add New Entry</h2>
                <form method="post" class="card shadow-sm p-4">
                    <div class="mb-3">
                        <label for="server_name" class="form-label">Server/Website/IP:</label>
                        <input type="text" class="form-control" id="server_name" name="server_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="update_log" class="form-label">Update Log:</label>
                        <textarea class="form-control" id="update_log" name="update_log" rows="3" required></textarea>
                    </div>
                    <button type="submit" name="addServer" class="btn btn-success">Add Entry</button>
                </form>
            </div>
        </div>';

    // Display Table
    echo '<div class="row">
            <div class="col-12">
                <h2 class="text-primary">Tracked Servers/Websites</h2>
                <div class="table-responsive">
                    <table id="serverUpdatesTable" class="table table-hover table-bordered dt-responsive nowrap" style="width:100%">
                        <thead class="table-light">
                            <tr>
                                <th>Server/Website/IP</th>
                                <th>Update Log</th>
                                <th>Last Updated</th>
                                <th>Added By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>';
    while ($row = mysql_fetch_assoc($results)) {
        // Merge legacy IP data into the display so you don't lose visibility of older entries
        $displayName = htmlspecialchars($row['server_name']);
        if (!empty($row['ip_address'])) {
            $displayName .= ' - ' . htmlspecialchars($row['ip_address']);
        }

        echo '<tr>
                <td>' . $displayName . '</td>
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
        </div>
    </div>';

    // Initialize the DataTable script
    echo '
    <script>
        $(document).ready(function() {
            if (!$.fn.DataTable.isDataTable("#serverUpdatesTable")) {
                $("#serverUpdatesTable").DataTable({
                    responsive: true,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    order: [[2, "desc"]], // Automatically order by Last Updated
                    language: {
                        search: "Filter entries:"
                    }
                });
            }
        });
    </script>
    ';
}