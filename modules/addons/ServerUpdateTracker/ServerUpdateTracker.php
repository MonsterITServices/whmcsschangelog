<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function ServerUpdateTracker_config()
{
    return [
        "name" => "Server Update Tracker",
        "description" => "A custom module to monitor and log updates for customer servers and websites.",
        "version" => "1.9",
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
                $table->string('ip_address', 255)->default('');
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
            'description' => 'Unable to activate module: ' . $e->getMessage()
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
            'description' => 'Unable to deactivate module: ' . $e->getMessage()
        ];
    }
}

function ServerUpdateTracker_output($vars)
{
    $moduleLink = $vars['modulelink']; // Native WHMCS link: addonmodules.php?module=ServerUpdateTracker

    // Safely retrieve the logged-in WHMCS admin username without deprecated methods
    $adminUsername = 'Admin';
    if (!empty($_SESSION['adminid'])) {
        $adminName = Capsule::table('tbladmins')->where('id', (int)$_SESSION['adminid'])->value('username');
        if ($adminName) {
            $adminUsername = $adminName;
        }
    }

    // 1. Handle Form Submission (Add New Entry)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addServer'])) {
        $serverName = trim($_POST['server_name'] ?? '');
        // Pass empty string '' instead of NULL so strict NOT NULL columns never fail
        $ipAddress  = trim($_POST['ip_address'] ?? '');
        $updateLog  = trim($_POST['update_log'] ?? '');

        if ($serverName !== '' && $updateLog !== '') {
            try {
                Capsule::table('mod_server_updates')->insert([
                    'server_name'  => $serverName,
                    'ip_address'   => $ipAddress,
                    'update_log'   => $updateLog,
                    'last_updated' => date('Y-m-d H:i:s'),
                    'added_by'     => $adminUsername,
                ]);
                echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Server/Website added successfully!</div>';
            } catch (\Exception $e) {
                echo '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to add entry: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            echo '<div class="alert alert-warning">Please fill in both the Server/Website Name and Update Log.</div>';
        }
    }

    // 2. Handle Deletion of an Entry
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        try {
            Capsule::table('mod_server_updates')->where('id', (int)$_GET['delete'])->delete();
            echo '<div class="alert alert-success"><i class="fas fa-trash"></i> Entry deleted successfully!</div>';
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Failed to delete entry: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    // 3. Filter & Pagination Inputs
    $filterServer = trim($_GET['filter_server'] ?? '');
    $searchQuery  = trim($_GET['search'] ?? '');
    $perPage      = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], [10, 25, 50, 100]) ? (int)$_GET['per_page'] : 15;
    $currentPage  = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;

    // Fetch unique Server Names for the filter dropdown
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

    // Build Database Query
    $query = Capsule::table('mod_server_updates');

    if ($filterServer !== '') {
        $query->where('server_name', $filterServer);
    }

    if ($searchQuery !== '') {
        $query->where(function ($q) use ($searchQuery) {
            $q->where('server_name', 'LIKE', '%' . $searchQuery . '%')
              ->orWhere('ip_address', 'LIKE', '%' . $searchQuery . '%')
              ->orWhere('update_log', 'LIKE', '%' . $searchQuery . '%')
              ->orWhere('added_by', 'LIKE', '%' . $searchQuery . '%');
        });
    }

    // Calculate Pagination
    $totalRecords = $query->count();
    $totalPages   = max(1, (int)ceil($totalRecords / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $perPage;

    // Fetch Only the Current Page's Records
    $results = $query->orderBy('last_updated', 'desc')
        ->offset($offset)
        ->limit($perPage)
        ->get();

    // 4. Render Native WHMCS Bootstrap 3 UI + Custom Styling
    echo '
    <style>
        .sut-panel {
            background: #ffffff;
            border: 1px solid #dcdcdc;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .sut-panel h3 {
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            color: #337ab7;
            font-size: 18px;
        }
        .sut-filter-box {
            background: #f8f9fa;
            border: 1px solid #e2e6ea;
            border-radius: 4px;
            padding: 12px 15px;
            margin-bottom: 15px;
        }
        .sut-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        .sut-table th {
            background-color: #f7f7f7;
            color: #333;
            font-weight: bold;
            padding: 10px 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .sut-table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            vertical-align: top;
            word-break: break-word;
        }
        .sut-table tr:hover {
            background-color: #f5f5f5;
        }
        .sut-not-provided {
            color: #999;
            font-style: italic;
        }
        .sut-last-updated {
            color: #337ab7;
            white-space: nowrap;
        }
    </style>

    <!-- Add New Server/Website Form -->
    <div class="sut-panel">
        <h3>Add New Server/Website</h3>
        <form method="post" action="' . htmlspecialchars($moduleLink) . '">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="server_name">Server/Website Name:</label>
                        <input type="text" class="form-control" id="server_name" name="server_name" placeholder="e.g. turtletots.ie" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ip_address">IP Address (Optional):</label>
                        <input type="text" class="form-control" id="ip_address" name="ip_address" placeholder="e.g. 192.168.1.1">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="update_log">Update Log:</label>
                <textarea class="form-control" id="update_log" name="update_log" rows="3" placeholder="Enter update log details..." required></textarea>
            </div>
            <button type="submit" name="addServer" class="btn btn-success">Add Server/Website</button>
        </form>
    </div>

    <!-- Tracked Servers/Websites Table & Filters -->
    <div class="sut-panel">
        <h3>Tracked Servers/Websites <span class="badge" style="background-color: #337ab7;">' . $totalRecords . '</span></h3>

        <form method="get" action="addonmodules.php" class="sut-filter-box">
            <input type="hidden" name="module" value="ServerUpdateTracker">
            <div class="row">
                <div class="col-md-4 col-sm-6" style="margin-bottom: 8px;">
                    <select name="filter_server" class="form-control">
                        <option value="">-- Show All Servers/Websites --</option>';
                        foreach ($serverNames as $name) {
                            $selected = ($filterServer === $name) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($name) . '" ' . $selected . '>' . htmlspecialchars($name) . '</option>';
                        }
    echo '          </select>
                </div>
                <div class="col-md-4 col-sm-6" style="margin-bottom: 8px;">
                    <input type="text" name="search" class="form-control" placeholder="Search IP, logs, or admin..." value="' . htmlspecialchars($searchQuery) . '">
                </div>
                <div class="col-md-2 col-sm-6" style="margin-bottom: 8px;">
                    <select name="per_page" class="form-control">
                        <option value="15" ' . ($perPage == 15 ? 'selected' : '') . '>15 per page</option>
                        <option value="25" ' . ($perPage == 25 ? 'selected' : '') . '>25 per page</option>
                        <option value="50" ' . ($perPage == 50 ? 'selected' : '') . '>50 per page</option>
                        <option value="100" ' . ($perPage == 100 ? 'selected' : '') . '>100 per page</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6" style="margin-bottom: 8px;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="' . htmlspecialchars($moduleLink) . '" class="btn btn-default">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="sut-table">
                <thead>
                    <tr>
                        <th style="width: 18%;">Server/Website Name</th>
                        <th style="width: 14%;">IP Address</th>
                        <th style="width: 40%;">Update Log</th>
                        <th style="width: 13%;">Last Updated</th>
                        <th style="width: 9%;">Added By</th>
                        <th style="width: 6%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>';

    if ($results->isEmpty()) {
        echo '<tr><td colspan="6" style="text-align: center; padding: 20px;" class="sut-not-provided">No updates found matching your criteria.</td></tr>';
    } else {
        foreach ($results as $row) {
            $ipDisplay = (!empty($row->ip_address))
                ? htmlspecialchars($row->ip_address)
                : '<span class="sut-not-provided">Not Provided</span>';

            $deleteUrl = $moduleLink
                . '&delete=' . (int)$row->id
                . '&page=' . $currentPage
                . '&per_page=' . $perPage
                . '&filter_server=' . urlencode($filterServer)
                . '&search=' . urlencode($searchQuery);

            echo '<tr>
                    <td><strong>' . htmlspecialchars($row->server_name) . '</strong></td>
                    <td>' . $ipDisplay . '</td>
                    <td>' . nl2br(htmlspecialchars($row->update_log)) . '</td>
                    <td class="sut-last-updated">' . htmlspecialchars($row->last_updated) . '</td>
                    <td>' . htmlspecialchars($row->added_by) . '</td>
                    <td style="text-align: center;">
                        <a href="' . htmlspecialchars($deleteUrl) . '" class="btn btn-danger btn-xs" onclick="return confirm(\'Are you sure you want to delete this entry?\')">Delete</a>
                    </td>
                  </tr>';
        }
    }

    echo '      </tbody>
            </table>
        </div>';

    // 5. Render Pagination Controls
    if ($totalPages > 1) {
        $baseQuery = $moduleLink
            . '&per_page=' . $perPage
            . '&filter_server=' . urlencode($filterServer)
            . '&search=' . urlencode($searchQuery);

        echo '<div class="row" style="margin-top: 15px; align-items: center;">
                <div class="col-sm-6 text-muted" style="padding-top: 8px;">
                    Showing page <strong>' . $currentPage . '</strong> of <strong>' . $totalPages . '</strong> (' . $totalRecords . ' total entries)
                </div>
                <div class="col-sm-6 text-right">
                    <ul class="pagination" style="margin: 0;">';

        // Previous Button
        if ($currentPage > 1) {
            echo '<li><a href="' . htmlspecialchars($baseQuery . '&page=' . ($currentPage - 1)) . '">&laquo; Prev</a></li>';
        } else {
            echo '<li class="disabled"><span>&laquo; Prev</span></li>';
        }

        // Page Numbers
        $startPage = max(1, $currentPage - 2);
        $endPage   = min($totalPages, $currentPage + 2);

        if ($startPage > 1) {
            echo '<li><a href="' . htmlspecialchars($baseQuery . '&page=1') . '">1</a></li>';
            if ($startPage > 2) {
                echo '<li class="disabled"><span>...</span></li>';
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $active = ($i === $currentPage) ? 'class="active"' : '';
            echo '<li ' . $active . '><a href="' . htmlspecialchars($baseQuery . '&page=' . $i) . '">' . $i . '</a></li>';
        }

        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) {
                echo '<li class="disabled"><span>...</span></li>';
            }
            echo '<li><a href="' . htmlspecialchars($baseQuery . '&page=' . $totalPages) . '">' . $totalPages . '</a></li>';
        }

        // Next Button
        if ($currentPage < $totalPages) {
            echo '<li><a href="' . htmlspecialchars($baseQuery . '&page=' . ($currentPage + 1)) . '">Next &raquo;</a></li>';
        } else {
            echo '<li class="disabled"><span>Next &raquo;</span></li>';
        }

        echo '      </ul>
                </div>
              </div>';
    }

    echo '</div>';
}