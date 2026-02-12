<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Redis_queue
{
    protected $redis;
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        
        // Load Redis configuration
        $this->CI->load->config('config');
        
        // Parse the session save path to get credentials if available
        // Expected format: tcp://127.0.0.1:6379?auth=PASSWORD
        $save_path = $this->CI->config->item('sess_save_path');
        
        $host = '127.0.0.1';
        $port = 6379;
        $password = null;

        if (preg_match('/tcp:\/\/([^:]+):(\d+)(?:\?auth=(.*))?/', $save_path, $matches)) {
            $host = $matches[1];
            $port = $matches[2];
            if (isset($matches[3])) {
                $password = $matches[3];
            }
        }

        try {
            $this->redis = new Redis();
            $this->redis->connect($host, $port);
            if ($password) {
                $this->redis->auth($password);
            }
        } catch (Exception $e) {
            log_message('error', 'Redis Queue Connection Error: ' . $e->getMessage());
            $this->redis = null;
        }
    }

    /**
     * Push job to queue
     * @param string $queue_name e.g., 'orders'
     * @param array $data Data to process
     * @return bool
     */
    public function push($queue_name, $data)
    {
        if (!$this->redis) return false;
        return $this->redis->rPush('queue:' . $queue_name, json_encode($data));
    }

    /**
     * Pop job from queue (blocking)
     * @param string $queue_name
     * @param int $timeout Seconds to wait
     * @return array|null
     */
    public function pop($queue_name, $timeout = 0)
    {
        if (!$this->redis) return null;
        
        // blPop returns array [key, value] or null
        $result = $this->redis->blPop('queue:' . $queue_name, $timeout);
        
        if ($result && isset($result[1])) {
            return json_decode($result[1], true);
        }
        
        return null;
    }

    /**
     * Get queue length
     */
    public function count($queue_name)
    {
        if (!$this->redis) return 0;
        return $this->redis->lLen('queue:' . $queue_name);
    }
}
