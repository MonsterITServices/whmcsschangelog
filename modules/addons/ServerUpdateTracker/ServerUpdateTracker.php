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
    try {
        if (!Capsule::schema()->hasTable('mod_server_updates')) {
            Capsule::schema()->create('mod_server_updates', function ($table) {
                $table->increments('id');
                $table->string('server_name', 255);
                $table->string('ip_address', 255)->nullable();
                $table->text('update_log');
                $table->dateTime('last_updated');
                $table->string('added_by', 255);
            });
        }
        return [
            'status' => 'success',
            'description' => 'The Server Update Tracker module has been activated successfully.'
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Unable to create table: ' . $e->getMessage()
        ];
    }
}

function ServerUpdateTracker_deactivate()
{
    try {
        Capsule::schema()->dropIfExists('mod_server_updates');
        return [
            'status' => 'success',
            'description' => 'The Server Update Tracker module has been deactivated and removed.'
        ];
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Unable to drop table: ' . $e->getMessage()
        ];
    }
}

function ServerUpdateTracker_output($vars)
{
    $moduleLink = $vars['modulelink']; // Native WHMCS variable: addonmodules.php?module=ServerUpdateTracker
    $authUser = Admin::getAuthenticatedUser();
    $adminUsername = $authUser ? $authUser['username'] : 'Admin';

    // 1. Handle Form Submission (Insert)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addServer'])) {
        $serverName = trim($_POST['server_name'] ?? '');
        $updateLog = trim($_POST['update_log'] ?? '');

        if ($serverName !== '' && $updateLog !== '') {
            try {
                Capsule::table('mod_server_updates')->insert([
                    'server_name'  => $serverName,
                    'ip_address'   => $serverName, // Duplicated to satisfy strict NOT NULL constraint
                    'update_log'   => $updateLog,
                    'last_updated' => date('Y-m-d H:i:s'),
                    'added_by'     => $adminUsername,
                ]);
                echo '<div class="alert alert-success">Entry added successfully!</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger">Database Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">Please fill in all required fields.</div>';
        }
    }

    // 2. Handle Deletion
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        try {
            Capsule::table('mod_server_updates')
                ->where('id', (int) $_GET['delete'])
                ->delete();
            echo '<div class="alert alert-success">Entry deleted successfully!</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Delete Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // 3. Server-Side Filtering & Pagination Parameters
    $search = trim($_GET['search'] ?? '');
    $filterServer = trim($_GET['filter_server'] ?? '');
    $perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 25, 50, 100]) ? (int)$_GET['per_page'] : 15;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $perPage;

    // Fetch distinct server names for the dropdown filter
    try {
        $serverNames = Capsule::table('mod_server_updates')
            ->select('server_name')
            ->distinct()
            ->orderBy('server_name', 'asc')
            ->pluck('server_name')
            ->toArray();
    } catch (\Exception $e) {
        $serverNames = [];
    }

    // Build Query with Filters
    $query = Capsule::table('mod_server_updates');

    if ($filterServer !== '') {
        $query->where('server_name', $filterServer);
    }

    if ($search !== '') {
        $query->where(function ($q) use ($search) {
            $q->where('server_name', 'LIKE', '%' . $search . '%')
              ->orWhere('ip_address', 'LIKE', '%' . $search . '%')
              ->orWhere('update_log', 'LIKE', '%' . $search . '%')
              ->orWhere('added_by', 'LIKE', '%' . $search . '%');
        });
    }

    // Get Total Count & Paginated Results (Fast & Lightweight)
    $totalRecords = $query->count();
    $totalPages = max(1, ceil($totalRecords / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $results = $query->orderBy('last_updated', 'desc')
        ->offset($offset)
        ->limit($perPage)
        ->get();

    // 4. Scoped CSS for Clean, Responsive Styling inside WHMCS Admin
    echo '
    <style>
        .sut-wrapper { margin-top: 15px; }
        .sut-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .sut-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 18px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .sut-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            background: #f9f9f9;
            padding: 12px 15px;
            border: 1px solid #e5e5e5;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .sut-filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .sut-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        .sut-table th {
            background-color: #f5f5f5;
            color: #333;
            font-weight: 600;
            padding: 12px 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .sut-table td {
            padding: 10px;
            border: 1px solid #ddd;
            vertical-align: top;
            word-break: break-word;
        }
        .sut-table tr:hover {
            background-color: #f9f9f9;
        }
        .sut-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        @media (max-width: 768px) {
            .sut-filter-bar, .sut-filter-group {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>

    <div class="sut-wrapper">
        <!-- Add New Entry Card -->
        <div class="sut-card">
            <h3>Add New Entry</h3>
            <form method="post" action="' . htmlspecialchars($moduleLink) . '">
                <div class="form-group">
                    <label for="server_name">Server/Website/IP:</label>
                    <input type="text" class="form-control" id="server_name" name="server_name" placeholder="e.g. turtletots.ie or 192.168.1.1" required>
                </div>
                <div class="form-group">
                    <label for="update_log">Update Log:</label>
                    <textarea class="form-control" id="update_log" name="update_log" rows="3" placeholder="Enter update details..." required></textarea>
                </div>
                <button type="submit" name="addServer" class="btn btn-success">Add Entry</button>
            </form>
        </div>

        <!-- Tracked Entries Card -->
        <div class="sut-card">
            <h3>Tracked Servers/Websites (' . $totalRecords . ' Total)</h3>

            <!-- Filter & Search Form (Uses GET so it never locks or times out) -->
            <form method="get" action="addonmodules.php" class="sut-filter-bar">
                <input type="hidden" name="module" value="ServerUpdateTracker">
                
                <div class="sut-filter-group">
                    <select name="filter_server" class="form-control input-sm" style="min-width: 200px;">
                        <option value="">-- All Servers/Websites --</option>';
                        foreach ($serverNames as $name) {
                            $selected = ($filterServer === $name) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($name) . '" ' . $selected . '>' . htmlspecialchars($name) . '</option>';
                        }
    echo '          </select>

                    <input type="text" name="search" class="form-control input-sm" style="min-width: 220px;" placeholder="Search logs, server, user..." value="' . htmlspecialchars($search) . '">

                    <select name="per_page" class="form-control input-sm" style="width: auto;">
                        <option value="15" ' . ($perPage == 15 ? 'selected' : '') . '>15 per page</option>
                        <option value="25" ' . ($perPage == 25 ? 'selected' : '') . '>25 per page</option>
                        <option value="50" ' . ($perPage == 50 ? 'selected' : '') . '>50 per page</option>
                        <option value="100" ' . ($perPage == 100 ? 'selected' : '') . '>100 per page</option>
                    </select>
                </div>

                <div class="sut-filter-group">
                    <button type="submit" class="btn btn-primary btn-sm">Apply Filter</button>
                    <a href="' . htmlspecialchars($moduleLink) . '" class="btn btn-default btn-sm">Reset</a>
                </div>
            </form>

            <!-- Responsive Table -->
            <div class="table-responsive">
                <table class="sut-table">
                    <thead>
                        <tr>
                            <th style="width: 22%;">Server/Website/IP</th>
                            <th style="width: 43%;">Update Log</th>
                            <th style="width: 15%;">Last Updated</th>
                            <th style="width: 12%;">Added By</th>
                            <th style="width: 8%; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>';

    if ($results->isEmpty()) {
        echo '<tr><td colspan="5" style="text-align: center; color: #888; font-style: italic; padding: 20px;">No matching update entries found.</td></tr>';
    } else {
        foreach ($results as $row) {
            $displayName = htmlspecialchars($row->server_name);
            // Append legacy IP address only if it exists and is different from the server_name
            if (!empty($row->ip_address) && $row->ip_address !== $row->server_name) {
                $displayName .= ' <span class="text-muted">(' . htmlspecialchars($row->ip_address) . ')</span>';
            }

            // Preserve current pagination/filters when deleting an entry
            $deleteUrl = $moduleLink . '&delete=' . (int)$row->id . '&page=' . $page . '&per_page=' . $perPage . '&filter_server=' . urlencode($filterServer) . '&search=' . urlencode($search);

            echo '<tr>
                    <td><strong>' . $displayName . '</strong></td>
                    <td>' . nl2br(htmlspecialchars($row->update_log)) . '</td>
                    <td style="color: #337ab7; white-space: nowrap;">' . htmlspecialchars($row->last_updated) . '</td>
                    <td>' . htmlspecialchars($row->added_by) . '</td>
                    <td style="text-align: center;">
                        <a href="' . htmlspecialchars($deleteUrl) . '" class="btn btn-danger btn-xs" onclick="return confirm(\'Are you sure you want to delete this entry?\')">Delete</a>
                    </td>
                  </tr>';
        }
    }

    echo '          </tbody>
                </table>
            </div>';

    // 5. Render Pagination Controls
    if ($totalPages > 1) {
        $baseQuery = $moduleLink . '&per_page=' . $perPage . '&filter_server=' . urlencode($filterServer) . '&search=' . urlencode($search);
        
        echo '<div class="sut-pagination">
                <div class="text-muted">Showing page ' . $page . ' of ' . $totalPages . ' (' . $totalRecords . ' entries)</div>
                <ul class="pagination" style="margin: 0;">';

        // Previous Button
        if ($page > 1) {
            echo '<li><a href="' . htmlspecialchars($baseQuery . '&page=' . ($page - 1)) . '">&laquo; Prev</a></li>';
        } else {
            echo '<li class="disabled"><span>&laquo; Prev</span></li>';
        }

        // Page Number Window (shows up to 5 pages around current page)
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);

        if ($startPage > 1) {
            echo '<li><a href="' . htmlspecialchars($baseQuery . '&page=1') . '">1</a></li>';
            if ($startPage > 2) {
                echo '<li class="disabled"><span>...</span></li>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $activeClass = ($i === $page) ? 'class="active"' : '';
            echo '<li ' . $activeClass . '><a href="' . htmlspecialchars($baseQuery . '&page=' . $i) . '">' . $i . '</a></li>';
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                echo '<li class="disabled"><span>...</span></li>';
            }
            echo '<li><a href="' . htmlspecialchars($baseQuery . '&page=' . $totalPages) . '">' . $totalPages . '</a></li>';
        }

        // Next Button
        if ($page < $totalPages) {
            echo '<li><a href="' . htmlspecialchars($baseQuery . '&page=' . ($page + 1)) . '">Next &raquo;</a></li>';
        } else {
            echo '<li class="disabled"><span>Next &raquo;</span></li>';
        }

        echo '  </ul>
              </div>';
    }

    echo '  </div>
    </div>';
}