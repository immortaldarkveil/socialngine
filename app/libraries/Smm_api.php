<?php
defined('BASEPATH') or exit('No direct script access allowed');

// Load Composer autoloader for Guzzle
if (file_exists(FCPATH . 'vendor/autoload.php')) {
    require_once FCPATH . 'vendor/autoload.php';
}

use GuzzleHttp\Client;
use GuzzleHttp\Promise;

/**
 * Include class
 */
class Smm_api
{
    protected $provider_services_dir;
    protected $provider_services_limit_update_time;
    protected $guzzle_client;

    public function __construct()
    {
        $this->provider_services_dir = $this->create_dir(['path' => "public/provider_services/"]);
        $this->provider_services_limit_update_time = 15; //minutes
        require_once 'smms/standard.php';

        // Initialize Guzzle client for async requests
        $this->guzzle_client = new Client([
            'timeout'         => 30,
            'connect_timeout' => 10,
            'verify'          => true,
            'http_errors'     => false,
            'headers'         => [
                'User-Agent' => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
            ],
        ]);
    }

    public function services($api_params = [], $option = null)
    {
        $items = null;
        switch ($option) {
            case 'directly':
                $api = new smm_standard($api_params);
                $items = $api->services();
                break;

            case 'json':
                $items = $this->crud_provider_services_json_file(['api' => $api_params], ['task' => 'read']);
                break;
            
            default:
                $items = $this->crud_provider_services_json_file(['api' => $api_params], ['task' => 'read']);
                if (empty($items)) {
                    $api = new smm_standard($api_params);
                    $items = $api->services();
                    $this->crud_provider_services_json_file(['api' => $api_params, 'data_services' => $items], ['task' => 'create']);
                }
                break;
        }
        return $items;
    }

    public function order($api_params = [], $data_post = [])
    {
        $api = new smm_standard($api_params);
        $result = $api->order($data_post);
        return $result;
    }

    /**
     * Async order - returns a Guzzle Promise
     * @param array $api_params  Provider details (url, key)
     * @param array $data_post   Order data
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function order_async($api_params = [], $data_post = [])
    {
        $post_data = array_merge(['key' => $api_params['key'], 'action' => 'add'], $data_post);

        return $this->guzzle_client->postAsync($api_params['url'], [
            'form_params' => $post_data,
        ])->then(
            function ($response) {
                $body = (string) $response->getBody();
                $decoded = json_decode($body, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    return ['error' => 'Invalid JSON response: ' . substr($body, 0, 200)];
                }
                return $decoded;
            },
            function ($exception) {
                return ['error' => 'API connection failed: ' . $exception->getMessage()];
            }
        );
    }

    /**
     * Process a batch of orders concurrently
     * @param array $jobs Array of ['order' => $order_obj, 'api' => $api_params, 'data_post' => $data_post]
     * @param int $concurrency Max simultaneous requests
     * @return array Results indexed same as input
     */
    public function process_batch($jobs = [], $concurrency = 50)
    {
        $promises = [];
        foreach ($jobs as $key => $job) {
            $promises[$key] = $this->order_async($job['api'], $job['data_post']);
        }

        // Wait for all promises to settle (fulfilled or rejected)
        $results = Promise\Utils::settle($promises)->wait();

        $output = [];
        foreach ($results as $key => $result) {
            if ($result['state'] === 'fulfilled') {
                $output[$key] = $result['value'];
            } else {
                $output[$key] = ['error' => 'Request failed: ' . $result['reason']->getMessage()];
            }
        }
        return $output;
    }

    public function status($api_params = [], $order_id)
    {
        $api = new smm_standard($api_params);
        $result = $api->status($order_id);
        return $result;
    }

    /**
     * Async status check - returns a Guzzle Promise
     */
    public function status_async($api_params = [], $order_id)
    {
        $post_data = [
            'key'    => $api_params['key'],
            'action' => 'status',
            'order'  => $order_id,
        ];

        return $this->guzzle_client->postAsync($api_params['url'], [
            'form_params' => $post_data,
        ])->then(
            function ($response) {
                $body = (string) $response->getBody();
                $decoded = json_decode($body, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    return ['error' => 'Invalid JSON response: ' . substr($body, 0, 200)];
                }
                return $decoded;
            },
            function ($exception) {
                return ['error' => 'API connection failed: ' . $exception->getMessage()];
            }
        );
    }

    /**
     * Process a batch of status checks concurrently
     * @param array $jobs Array of ['item' => $item, 'api' => $api_params]
     * @return array Results indexed same as input
     */
    public function status_batch($jobs = [])
    {
        $promises = [];
        foreach ($jobs as $key => $job) {
            $promises[$key] = $this->status_async($job['api'], $job['item']['api_order_id']);
        }

        $results = Promise\Utils::settle($promises)->wait();

        $output = [];
        foreach ($results as $key => $result) {
            if ($result['state'] === 'fulfilled') {
                $output[$key] = $result['value'];
            } else {
                $output[$key] = ['error' => 'Request failed: ' . $result['reason']->getMessage()];
            }
        }
        return $output;
    }

    public function multiStatus($api_params = [], $order_ids)
    {
        $api = new smm_standard($api_params);
        $result = $api->multiStatus($order_ids);
        return $result;
    }

    public function balance($api_params = [])
    {
        $api = new smm_standard($api_params);
        $result = $api->balance();
        return $result;
    }

    public function refill($api_params = [], $order_id)
    {
        $api = new smm_standard($api_params);
        $result = $api->refill($order_id);
        return $result;
    }

    public function refill_status($api_params = [], $refill_id)
    {
        $api = new smm_standard($api_params);
        $result = $api->refill_status($refill_id);
        return $result;
    }

    public function crud_provider_services_json_file($params = null, $option = null)
    {
        // Delete old Json services  list
        $provider_services_json_path =$this->provider_services_dir . $this->provider_json_file_name($params['api']);
        //Delete
        if ($option['task'] == 'delete') {
            if (file_exists($provider_services_json_path)) {
                unlink($provider_services_json_path);
            }
        }

        // Update new services list
        if ($option['task'] == 'update') {
            $this->services($params['api']);
        }

        // Read services list
        if ($option['task'] == 'read') {
            if (!file_exists($provider_services_json_path)) {
                return false;
            }
            $data_api   = json_decode(file_get_contents($provider_services_json_path), true);
            if (!isset($data_api['data'])) {
                return false;
            }
            $last_time = strtotime(NOW) - ($this->provider_services_limit_update_time * 60);
            if (strtotime($data_api['time']) > $last_time) {
                return $data_api['data'];
            }
            return false;
        }

        // Save services list
        if ($option['task'] == 'create') {
            $mode 		= (isset($params['mode'])) ? $params['mode'] : 'w';
            $content 	= json_encode(['time' => NOW , 'data' =>  $params['data_services']], JSON_PRETTY_PRINT);
            $handle 	= fopen($provider_services_json_path, $mode);
            if ( is_writable($provider_services_json_path) ){
                fwrite($handle, $content);
            }
            fclose($handle);
        }

    }

    private function provider_json_file_name($api_params = [])
    {
        if (isset($api_params['id']) && isset($api_params['name'])) {
            $name = trim(str_replace(' ', '_', strtolower($api_params['name'])));
            return $api_params['id'] . '-' . $name . '.json';
        } else {
            // Fallback: hash the params to generate a unique filename
            return 'provider_' . md5(json_encode($api_params)) . '.json';
        }
    }
    
    private function create_dir($params = null, $option = null)
    {
        $path = FCPATH . $params['path'];
        if (!file_exists($path)) {
            $uold = umask(0);
            mkdir($path, 0755, true);
            umask($uold);
            file_put_contents($path . "index.html", "<h1>404 Not Found</h1>");
        }
        return $path;
    }
}
