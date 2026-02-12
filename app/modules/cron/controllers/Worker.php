<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Worker extends MX_Controller 
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('cron_model', 'main_model');
        $this->provider = new Smm_api();
        $this->load->library('redis_queue');
    }

    public function index()
    {
        if (!is_cli()) {
            echo "This script can only be accessed via the command line.";
            return;
        }

        echo "Worker started. Waiting for orders... (Press Ctrl+C to stop)\n";

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
            echo "Error: API Provider for Order {$row->id} mismatch.\n";
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
                $data_post["expiry"]   = (!empty($row->sub_expiry))? date("d/m/Y",  strtotime($row->sub_expiry)) : "";
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
        
        // Log processed
        echo "Finished Order ID: " . $row->id . "\n";
    }
}
