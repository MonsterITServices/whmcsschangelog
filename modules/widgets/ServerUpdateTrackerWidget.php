<?php

namespace WHMCS\Module\Widget;

use WHMCS\Database\Capsule;
use WHMCS\Module\AbstractWidget;

class ServerUpdateTrackerWidget extends AbstractWidget
{
    protected $title = 'Server Update Tracker';
    protected $description = 'Displays recent updates for servers/websites and links to add new entries.';
    protected $weight = 50;
    protected $cache = false; // Disable default caching for auto-refreshing
    protected $requiredPermission = '';

    public function getData()
    {
        // Fetch the 5 most recent updates using Capsule
        return Capsule::table('mod_server_updates')
            ->orderBy('last_updated', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function generateOutput($data)
    {
        // Handle AJAX request and only return the widget's content
        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            return $this->generateTable($data);
        }

        // Add link to the module page
        $moduleLink = 'addonmodules.php?module=ServerUpdateTracker';

        $output = '<div class="widget-header" style="margin-bottom: 15px;">';
        $output .= '<a href="' . $moduleLink . '" class="btn btn-primary" style="background-color: #337ab7; border-color: #2e6da4;">Add New Entry</a>';
        $output .= '</div>';

        // Placeholder for auto-refreshing content
        $output .= '<div id="server-update-tracker-content">';
        $output .= $this->generateTable($data);
        $output .= '</div>';

        // Add JavaScript for auto-refreshing
        $output .= <<<HTML
<script>
    function refreshServerUpdateTracker() {
        fetch('index.php?widget=ServerUpdateTrackerWidget&ajax=1')
            .then(response => response.text())
            .then(data => {
                document.getElementById('server-update-tracker-content').innerHTML = data;
            })
            .catch(error => console.error('Error refreshing widget:', error));
    }

    // Refresh every 30 seconds
    setInterval(refreshServerUpdateTracker, 30000);
</script>
HTML;

        return $output;
    }

    public function generateTable($data)
    {
        // Styling for the table
        $output = '<style>
            .server-update-tracker-table {
                width: 100%;
                border-collapse: collapse;
            }
            .server-update-tracker-table th {
                background-color: #f7f7f7;
                padding: 10px;
                text-align: left;
                border-bottom: 2px solid #ddd;
                color: #333;
            }
            .server-update-tracker-table td {
                padding: 10px;
                border-bottom: 1px solid #ddd;
            }
            .server-update-tracker-table tr:hover {
                background-color: #f1f1f1;
            }
            .server-update-tracker-table td:first-child {
                font-weight: bold;
            }
            .server-update-tracker-table .not-provided {
                color: #999;
                font-style: italic;
            }
            .server-update-tracker-table .last-updated {
                color: #337ab7;
            }
        </style>';

        // Table HTML
        $output .= '<table class="server-update-tracker-table">
            <thead>
                <tr>
                    <th>Server/Website</th>
                    <th>IP Address</th>
                    <th>Update Log</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>';

        if (empty($data)) {
            $output .= '<tr><td colspan="4" style="text-align: center; font-style: italic; color: #999;">No updates available.</td></tr>';
        } else {
            foreach ($data as $update) {
                $output .= '<tr>
                    <td>' . htmlspecialchars($update->server_name) . '</td>
                    <td>' . ($update->ip_address ? htmlspecialchars($update->ip_address) : '<span class="not-provided">Not Provided</span>') . '</td>
                    <td>' . nl2br(htmlspecialchars($update->update_log)) . '</td>
                    <td class="last-updated">' . htmlspecialchars($update->last_updated) . '</td>
                </tr>';
            }
        }

        $output .= '</tbody></table>';

        return $output;
    }

    public function getAjaxContent()
    {
        // Fetch and return updated data for AJAX requests
        $data = $this->getData();
        return $this->generateTable($data);
    }
}
