<?php
// cli_worker.php

/*
|--------------------------------------------------------------------------
| Order Processing Worker (Standalone)
|--------------------------------------------------------------------------
|
| This script processes orders from the Redis queue.
| It is intended to be run via the command line (CLI) only.
|
| Usage: php cli_worker.php
|
*/

if (php_sapi_name() !== 'cli') {
    exit("Run this script via CLI only.\n");
}

// --------------------------------------------------------------------
// 1. BOOTSTRAP CODEIGNITER ENVIRONMENT
// --------------------------------------------------------------------

define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');

$system_path = 'app/core/system';
$application_folder = 'app';
$view_folder = '';

// Resolve system path
if (($_temp = realpath($system_path)) !== FALSE) {
    $system_path = $_temp . '/';
} else {
    $system_path = strtr(rtrim($system_path, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
}

// Resolve application path
if (is_dir($application_folder)) {
    if (($_temp = realpath($application_folder)) !== FALSE) {
        $application_folder = $_temp;
    }
} else {
    if (($_temp = realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.$application_folder)) !== FALSE) {
        $application_folder = $_temp;
    }
}

// Define Constants
define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', $system_path);
define('FCPATH', dirname(__FILE__).DIRECTORY_SEPARATOR);
define('SYSDIR', trim(strrchr(trim(BASEPATH, '/\\'), DIRECTORY_SEPARATOR), '/\\'));
define('APPPATH', $application_folder.DIRECTORY_SEPARATOR);

// Resolve View Path (Crucial Fix)
if (!isset($view_folder[0]) && is_dir(APPPATH.'views'.DIRECTORY_SEPARATOR)) {
    $view_folder = APPPATH.'views';
} elseif (is_dir($view_folder)) {
    if (($_temp = realpath($view_folder)) !== FALSE) {
        $view_folder = $_temp;
    }
} elseif (is_dir(APPPATH.$view_folder.DIRECTORY_SEPARATOR)) {
    $view_folder = APPPATH.$view_folder;
}
define('VIEWPATH', $view_folder.DIRECTORY_SEPARATOR);

// Capture and discard CI's initial output (default controller HTML)
ob_start();
require_once 'app/config.php';
require_once BASEPATH.'core/CodeIgniter.php';
ob_end_clean();

// --------------------------------------------------------------------
// 2. WORKER LOGIC (BATCH MODE)
// --------------------------------------------------------------------

$CI =& get_instance();

// Load required models and libraries
$CI->load->model('cron/cron_model', 'main_model');
$CI->load->library('redis_queue');

// Include the external API library manually if not autoloaded
if (!class_exists('Smm_api')) {
    require_once APPPATH . 'libraries/Smm_api.php';
}
$CI->provider = new Smm_api();

// Configuration
$BATCH_SIZE = 50;  // Max orders to process concurrently
$IDLE_WAIT  = 3;   // Seconds to wait when queue is empty

echo "--------------------------------------------------\n";
echo "Worker Started (PID: " . getmypid() . ")\n";
echo "Batch Size: {$BATCH_SIZE} | Mode: Async (Guzzle)\n";
echo "Waiting for Orders in Redis Queue: 'orders'...\n";
echo "--------------------------------------------------\n";

while (true) {
    // 1. Collect a batch of orders from Redis
    $batch = [];
    for ($i = 0; $i < $BATCH_SIZE; $i++) {
        // Non-blocking pop (0 timeout) for fast collection
        $job = $CI->redis_queue->pop('orders', ($i === 0) ? $IDLE_WAIT : 0);
        if ($job) {
            $batch[] = $job;
        } else {
            break; // No more jobs available right now
        }
    }

    if (empty($batch)) {
        continue; // Nothing to do, loop back and wait
    }

    $start_time = microtime(true);
    echo "[" . date('H:i:s') . "] Batch of " . count($batch) . " orders collected. Processing...\n";

    // 2. Prepare jobs for async processing
    $jobs = [];
    $order_map = []; // Track order IDs for result handling

    foreach ($batch as $key => $order) {
        $row = (object)$order;
        $api = $CI->main_model->get_item(['id' => $row->api_provider_id], ['task' => 'get-item-provider']);

        if (!$api) {
            // Handle missing API provider immediately
            $response = ['error' => "API Provider does not exists"];
            $CI->main_model->save_item(['order_id' => $row->id, 'response' => $response], ['task' => 'item-new-update']);
            echo "  Order #{$row->id}: API Provider missing - SKIPPED\n";
            continue;
        }

        $data_post = build_data_post($row);
        $jobs[$key] = [
            'order' => $row,
            'api'   => $api,
            'data_post' => $data_post,
        ];
        $order_map[$key] = $row->id;
    }

    if (!empty($jobs)) {
        // 3. Fire all requests concurrently!
        $results = $CI->provider->process_batch($jobs, $BATCH_SIZE);

        // 4. Save results back to database
        foreach ($results as $key => $response) {
            $order_id = $order_map[$key];
            $CI->main_model->save_item(['order_id' => $order_id, 'response' => $response], ['task' => 'item-new-update']);

            $status = isset($response['order']) ? 'OK' : 'ERR';
            echo "  Order #{$order_id}: {$status}\n";
        }
    }

    $duration = round(microtime(true) - $start_time, 2);
    echo "[" . date('H:i:s') . "] Batch complete in {$duration}s\n\n";

    // Keep database connection alive
    if (isset($CI->db->conn_id) && !mysqli_ping($CI->db->conn_id)) {
        $CI->db->reconnect();
    }
}

/**
 * Build data_post array based on service type
 */
function build_data_post($row) {
    $data_post = [
        'action'   => 'add',
        'service'  => $row->api_service_id,
    ];

    switch ($row->service_type) {
        case 'subscriptions':
            $data_post["username"] = $row->username;
            $data_post["min"]      = $row->sub_min;
            $data_post["max"]      = $row->sub_max;
            $data_post["posts"]    = ($row->sub_posts == -1) ? 0 : $row->sub_posts;
            $data_post["delay"]    = $row->sub_delay;
            $data_post["expiry"]   = (!empty($row->sub_expiry)) ? date("d/m/Y", strtotime($row->sub_expiry)) : "";
            break;

        case 'custom_comments':
            $data_post["link"]     = $row->link;
            $data_post["comments"] = json_decode($row->comments);
            break;

        case 'mentions_with_hashtags':
            $data_post["link"]         = $row->link;
            $data_post["quantity"]     = $row->quantity;
            $data_post["usernames"]    = $row->usernames;
            $data_post["hashtags"]     = $row->hashtags;
            break;

        case 'mentions_custom_list':
            $data_post["link"]         = $row->link;
            $data_post["usernames"]    = json_decode($row->usernames);
            break;

        case 'mentions_hashtag':
            $data_post["link"]         = $row->link;
            $data_post["quantity"]     = $row->quantity;
            $data_post["hashtag"]      = $row->hashtag;
            break;

        case 'mentions_user_followers':
            $data_post["link"]         = $row->link;
            $data_post["quantity"]     = $row->quantity;
            $data_post["username"]     = $row->username;
            break;

        case 'mentions_media_likers':
            $data_post["link"]         = $row->link;
            $data_post["quantity"]     = $row->quantity;
            $data_post["media"]        = $row->media;
            break;

        case 'package':
            $data_post["link"]         = $row->link;
            break;

        case 'custom_comments_package':
            $data_post["link"]         = $row->link;
            $data_post["comments"]     = json_decode($row->comments);
            break;

        case 'comment_likes':
            $data_post["link"]         = $row->link;
            $data_post["quantity"]     = $row->quantity;
            $data_post["username"]     = $row->username;
            break;

        default:
            $data_post["link"] = $row->link;
            $data_post["quantity"] = $row->quantity;
            if (isset($row->is_drip_feed) && $row->is_drip_feed == 1) {
                $data_post["runs"]     = $row->runs;
                $data_post["interval"] = $row->interval;
                $data_post["quantity"] = $row->dripfeed_quantity;
            } else {
                $data_post["quantity"] = $row->quantity;
            }
            break;
    }

    return $data_post;
}

