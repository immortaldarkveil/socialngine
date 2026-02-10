<?php
class smm_standard{
    public $api_url;
    public $api_key;
    public $ci;

    public function __construct($api_params = ""){
      $this->api_url = $api_params['url'];
      $this->api_key = $api_params['key'];
      $this->ci      = &get_instance();
    }

    /**
     * Safely execute an API request and return decoded JSON.
     * Returns ['error' => '...'] on connection failure instead of null.
     */
    private function safe_request($post) {
      $raw = $this->connect($post);
      if ($raw === false) {
        return ['error' => 'API connection failed for: ' . $this->api_url];
      }
      $decoded = json_decode($raw, true);
      if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Invalid JSON response from API: ' . substr($raw, 0, 200)];
      }
      return $decoded;
    }

    public function order($data) {
      $post = array_merge(array('key' => $this->api_key, 'action' => 'add'), $data);
      return $this->safe_request($post);
    }

    public function status($order_id) {
      return $this->safe_request(array(
          'key' => $this->api_key,
          'action' => 'status',
          'order' => $order_id
      ));
    }

    public function multiStatus($order_ids) {
      return $this->safe_request(array(
          'key' => $this->api_key,
          'action' => 'status',
          'orders' => implode(",", $order_ids)
      ));
    }

    public function services() { 
      return $this->safe_request(array(
          'key' => $this->api_key,
          'action' => 'services',
      ));
    }

    public function balance() {
      return $this->safe_request(array(
          'key' => $this->api_key,
          'action' => 'balance',
      ));
    }

    public function refill($order_id) {
      return $this->safe_request(array(
          'key' => $this->api_key,
          'action' => 'refill',
          'order'  => $order_id,
      ));
    }

    public function refill_status($refill_id) {
      return $this->safe_request(array(
          'key' => $this->api_key,
          'action' => 'refill_status',
          'refill'  => $refill_id,
      ));
    }

    public function cancel($order_id) {
      return $this->safe_request(array(
          'key' => $this->api_key,
          'action' => 'cancel',
          'order'  => $order_id,
      ));
    }

    // private function connect($post) {
    //   $_post = Array();

    //   if (is_array($post)) {
    //     foreach ($post as $name => $value) {
    //       $_post[] = $name.'='.urlencode($value);
    //     }
    //   }

    //   if (is_array($post)) {
    //     $url_complete = join('&', $_post);
    //   }
    //   $url = $this->api_url."?".$url_complete;
    //   $ch = curl_init($url);
    //   curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //   curl_setopt($ch, CURLOPT_HEADER, 0);
    //   curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    //   curl_setopt($ch, CURLOPT_USERAGENT, 'API (compatible; MSIE 5.01; Windows NT 5.0)');
    //   $result = curl_exec($ch);
    //   if (curl_errno($ch) != 0 && empty($result)) {
    //     $result = false;
    //   }
    //   curl_close($ch);
    //   return $result;
    // }

    private function connect($post) {
      $_post = Array();
      if (is_array($post)) {
          foreach ($post as $name => $value) {
            $_post[] = $name.'='.urlencode($value);
          }
      }

      $ch = curl_init($this->api_url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
      if (is_array($post)) {
          curl_setopt($ch, CURLOPT_POSTFIELDS, join('&', $_post));
      }
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)');
      $result = curl_exec($ch);
      if (curl_errno($ch) != 0 && empty($result)) {
          $result = false;
      }
      curl_close($ch);
      return $result;
    }
}
