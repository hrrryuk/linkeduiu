<?php
require_once 'portal_db.php';
require_once 'university_db.php';
require_once 'sync.php'; // contains run_sync($portal, $university)

$task_name = "semester_sync";
//set_time_limit(0);

function get_sync_interval($portal, $task_name)
{
    $stmt = $portal->prepare("SELECT interval_minutes FROM scheduled_tasks WHERE task_name = ? AND enabled = 1");
    $stmt->bind_param("s", $task_name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['interval_minutes'];
    }
    return 1440; // 1 day default
}

function update_last_run($portal, $task_name)
{
    $stmt = $portal->prepare("UPDATE scheduled_tasks SET last_run = NOW() WHERE task_name = ?");
    $stmt->bind_param("s", $task_name);
    $stmt->execute();
    $stmt->close();
}

function reconnect_dbs(&$portal, &$university)
{
    // Close existing connections if open
    if ($portal) $portal->close();
    if ($university) $university->close();

    // Re-establish connections
    require 'portal_db.php';      // sets $portal
    require 'university_db.php';  // sets $university
}

while (true) {
    try {
        [$current_sem, $current_year] = get_current_semester($university);
        [$synced_sem, $synced_year] = get_synced_semester($portal);

        if (
            $current_sem && $current_year &&
            ($current_sem !== $synced_sem || $current_year !== $synced_year)
        ) {

            echo "Semester changed: syncing $current_sem $current_year...\n";
            run_sync($portal, $university);
            echo "Sync complete.\n";
            update_last_run($portal, $task_name);
        } else {
            echo "Semester already synced.\n";
        }

        $minutes = get_sync_interval($portal, $task_name);
        echo "Sleeping for $minutes minutes...\n\n";
        sleep($minutes * 60);
        reconnect_dbs($portal, $university);
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        sleep(300); // wait 5 mins before retry
        reconnect_dbs($portal, $university);
    }
}
