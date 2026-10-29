<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\User\Admin;
use WHMCS\Database\Capsule;

function ServerUpdateTracker_config()
{
    return [
        "name" => "Server Update Tracker",
        "description" => "A custom module to monitor and log updates for customer servers and websites.",
        "version" => "1.8",
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

    // Handle form submission using WHMCS Capsule
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addServer'])) {
        try {
            $serverName = trim($_POST['server_name']);
            $updateLog = trim($_POST['update_log']);

            Capsule::table('mod_server_updates')->insert([
                'server_name' => $serverName,
                'ip_address' => $serverName, // Duplicate to bypass NOT NULL constraint
                'update_log' => $updateLog,
                'last_updated' => Capsule::raw('NOW()'),
                'added_by' => $adminUsername
            ]);
            
            echo '<div class="alert alert-success">Server/Website/IP added successfully!</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Failed to add entry: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // Handle deletion using WHMCS Capsule
    if (isset($_GET['delete'])) {
        try {
            $deleteId = (int) $_GET['delete'];
            Capsule::table('mod_server_updates')->where('id', $deleteId)->delete();
            echo '<div class="alert alert-success">Entry deleted successfully!</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Failed to delete entry: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // Fetch all records using Capsule (returns object collection)
    $results = Capsule::table('mod_server_updates')->orderBy('last_updated', 'desc')->get();

    // Inject DataTables and Bootstrap 5 CSS/JS
    echo '
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap5.min.css"/>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap5.min.js"></script>
    ';

    echo '<div class="container-fluid" style="margin-top: 20px;">';
    
    // Explicit action attribute added to form to prevent postback timeouts
    echo '<div class="row mb-4">
            <div class="col-12">
                <h2 class="text-primary">Add New Entry</h2>
                <form method="post" action="addonmodules.php?module=ServerUpdateTracker" class="card shadow-sm p-4">
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
    
    foreach ($results as $row) {
        $displayName = htmlspecialchars($row->server_name);
        
        // Append legacy IPs if they exist and are distinct
        if (!empty($row->ip_address) && $row->ip_address !== $row->server_name) {
            $displayName .= ' - ' . htmlspecialchars($row->ip_address);
        }

        echo '<tr>
                <td>' . $displayName . '</td>
                <td>' . nl2br(htmlspecialchars($row->update_log)) . '</td>
                <td>' . htmlspecialchars($row->last_updated) . '</td>
                <td>' . htmlspecialchars($row->added_by) . '</td>
                <td>
                    <a href="?module=ServerUpdateTracker&delete=' . $row->id . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this entry?\')">Delete</a>
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
                    order: [[2, "desc"]], 
                    language: {
                        search: "Filter entries:"
                    }
                });
            }
        });
    </script>
    ';
}