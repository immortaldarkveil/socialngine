<?php
defined('BASEPATH') OR exit('No direct script access allowed');
 
class cron extends MX_Controller 
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model(get_class($this).'_model', 'main_model');
        $this->provider = new Smm_api();
    }

    public function index()
    {
        redirect(cn());
    }

    public function status()
    {
        lock_file(['file_name' => 'status', 'title_message' => 'Status (single) already running!']);
        $params = [
            'limit' => 100,
            'start' => 0,
        ];
        $items = $this->main_model->list_items($params, ['task' => 'list-items-status']);
        if (!$items) {
            echo "There is no order at the present.<br>";
            exit();
        }

        // Prepare batch jobs grouped by API provider
        $jobs = [];
        foreach ($items as $key => $item) {
            $api = $this->main_model->get_item(['id' => $item['api_provider_id']], ['task' => 'get-item-provider']);
            if (!$api) {
                $response = ['error' => "API Provider does not exists"];
                $this->main_model->save_item(['item' => $item, 'response' => $response], ['task' => 'item-status']);
                continue;
            }
            $jobs[$key] = ['item' => $item, 'api' => $api];
        }

        if (!empty($jobs)) {
            // Fire all status checks concurrently
            $results = $this->provider->status_batch($jobs);
            foreach ($results as $key => $response) {
                $this->main_model->save_item(['item' => $jobs[$key]['item'], 'response' => $response], ['task' => 'item-status']);
            }
        }

        echo "Successfully";
    }

    public function dripfeed()
    {
        lock_file(['file_name' => 'dripfeed', 'title_message' => 'Dripfeed status already running!']);
        $params = [
            'limit' => 100,
            'start' => 0,
        ];
        $items = $this->main_model->list_items($params, ['task' => 'list-items-dripfeed-status']);
        if (!$items) {
            echo "There is no order at the present.<br>";
            exit();
        }

        $jobs = [];
        foreach ($items as $key => $item) {
            $api = $this->main_model->get_item(['id' => $item['api_provider_id']], ['task' => 'get-item-provider']);
            if (!$api) {
                $response = ['error' => "API Provider does not exists"];
                $this->main_model->save_item(['order_id' => $item['id'], 'response' => $response], ['task' => 'item-dripfeed-status']);
                continue;
            }
            $jobs[$key] = ['item' => $item, 'api' => $api];
        }

        if (!empty($jobs)) {
            $results = $this->provider->status_batch($jobs);
            foreach ($results as $key => $response) {
                $this->main_model->save_item(['item' => $jobs[$key]['item'], 'item_api' => $jobs[$key]['api'], 'response' => $response], ['task' => 'item-dripfeed-status']);
            }
        }

        echo "Successfully";
    }

    public function subscriptions()
    {
        $params = [
            'limit' => 100,
            'start' => 0,
        ];
        $items = $this->main_model->list_items($params, ['task' => 'list-items-subscriptions-status']);
        if (!$items) {
            echo "There is no order at the present.<br>";
            exit();
        }

        $jobs = [];
        foreach ($items as $key => $item) {
            $api = $this->main_model->get_item(['id' => $item['api_provider_id']], ['task' => 'get-item-provider']);
            if (!$api) {
                $response = ['error' => "API Provider does not exists"];
                $this->main_model->save_item(['order_id' => $item['id'], 'response' => $response], ['task' => 'item-subscriptions-status']);
                continue;
            }
            $jobs[$key] = ['item' => $item, 'api' => $api];
        }

        if (!empty($jobs)) {
            $results = $this->provider->status_batch($jobs);
            foreach ($results as $key => $response) {
                $this->main_model->save_item(['item' => $jobs[$key]['item'], 'item_api' => $jobs[$key]['api'], 'response' => $response], ['task' => 'item-subscriptions-status']);
            }
        }

        echo "Successfully";
    }

    public function multiple_status()
    {
        lock_file(['file_name' => 'multiple_status', 'title_message' => 'Multiple status already running!']);
        $params = [
            'limit' => 100,
            'start' => 0,
        ];
        $items = $this->main_model->list_items($params, ['task' => 'list-items-multiple-status']);
        if (!$items) {
            echo "There is no order at the present.<br>";
            exit();
        }
        
        $items_group_by_api = group_by_criteria($items, 'api_provider_id');
        foreach ($items_group_by_api as $api_id => $items_group) {
            $api = $this->main_model->get_item(['id' => $api_id], ['task' => 'get-item-provider']);
            if (!$api) {
                $response = ['error' => "API Provider does not exists"];
                $params = [
                    'order_ids'          => array_column($items_group, 'id'),
                    'response'           => $response,
                ];
                $this->main_model->save_item($params, ['task' => 'item-multiple_status']);
                continue;
            }
            $response = $this->provider->multiStatus($api, array_column($items_group, 'api_order_id'));
            if ($response) {
                $exist_items = [];
                foreach ($items_group as $key => $item) {
                    if (isset($response[$item['api_order_id']]) && !in_array($item['api_order_id'], $exist_items)) {
                        $this->main_model->save_item(['item' => $item, 'response' => $response[$item['api_order_id']]], ['task' => 'item-status']);
                        $exist_items[] = $item['api_order_id'];
                    }
                }
            }
        }
        echo "Successfully";
    }

    //Send
    public function order()
    {
        lock_file(['file_name' => 'order', 'title_message' => 'Order already running!']);
        
        // Limit to 15 orders per run to prevent timeouts on Shared Hosting
        $params = [
            'limit' => 15,
            'start' => 0,
        ];
        
        $items = $this->main_model->list_items($params, ['task' => 'list-items-new-order']);
        
        if (!$items) {
            echo "There is no order at the present.<br>";
            exit();
        }

        // Direct Processing (Bypassing Redis for cPanel compatibility)
        foreach ($items as $key => $row) {
            $this->process_single_order($row);
            echo "Order ID {$row->id} processed.<br>";
        }
        echo "Successfully processed " . count($items) . " orders.";
    }

    // New worker endpoint (run via CLI)
    // php index.php cron worker_order
    public function worker_order()
    {
        if (!is_cli()) {
            echo "This script can only be accessed via the command line.";
            return;
        }

        $this->load->library('redis_queue');
        echo "Worker started. Waiting for orders...\n";

        while (true) {
            // Wait for job (blocking for 5 seconds to reduce CPU)
            $job = $this->redis_queue->pop('orders', 5);
            
            if ($job) {
                echo "Processing Order ID: " . $job['id'] . "\n";
                $this->process_single_order((object)$job);
            }
            
            // Keep connection alive or handle signals here if needed
            // usleep(100); 
        }
    }

    private function process_single_order($row)
    {
        $api = $this->main_model->get_item(['id' => $row->api_provider_id], ['task' => 'get-item-provider']);
        if (!$api) {
            $response = ['error' => "API Provider does not exists"];
            $this->main_model->save_item(['order_id' => $row->id, 'response' => $response], ['task' => 'item-new-update']);
            return;
        }
        $data_post = [
            'action'   => 'add',
            'service'  => $row->api_service_id,
        ];
        switch ($row->service_type) {
            case 'subscriptions':
                $data_post["username"] = $row->username;
                $data_post["min"]      = $row->sub_min;
                $data_post["max"]      = $row->sub_max;
                $data_post["posts"]    = ($row->sub_posts == -1) ? 0 : $row->sub_posts ;
                $data_post["delay"]    = $row->sub_delay;
                $data_post["expiry"]   = (!empty($row->sub_expiry))? date("d/m/Y",  strtotime($row->sub_expiry)) : "";//change date format dd/mm/YYYY
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
                }else{
                    $data_post["quantity"] = $row->quantity;
                }
                break;
        }
        $response = $this->provider->order($api, $data_post);
        $this->main_model->save_item(['order_id' => $row->id, 'response' => $response], ['task' => 'item-new-update']);
    }
}