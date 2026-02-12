<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Kora Pay Payment Gateway Controller
 * 
 * Handles: Initialize → Redirect to Korapay checkout → Callback → Verify → Credit balance
 * API Docs: https://docs.korapay.com/
 */
class korapay extends MX_Controller
{
    public $tb_users;
    public $tb_transaction_logs;
    public $tb_payments;
    public $tb_payments_bonuses;
    public $payment_type;
    public $payment_id;
    public $currency_code;
    public $public_key;
    public $secret_key;
    public $take_fee_from_user;
    public $rate_to_usd;

    public function __construct($payment = "")
    {
        parent::__construct();
        $this->load->model('add_funds_model', 'model');

        $this->tb_users            = USERS;
        $this->payment_type        = 'korapay';
        $this->tb_transaction_logs = TRANSACTION_LOGS;
        $this->tb_payments         = PAYMENTS_METHOD;
        $this->tb_payments_bonuses = PAYMENTS_BONUSES;
        $this->currency_code       = get_option("currency_code", "NGN");

        if (!$payment) {
            $payment = $this->model->get('id, type, name, params', $this->tb_payments, ['type' => $this->payment_type]);
        }

        if ($payment) {
            $this->payment_id         = $payment->id;
            $params                   = $payment->params;
            $option                   = get_value($params, 'option');
            $this->public_key         = trim(get_value($option, 'public_key'));
            $this->secret_key         = trim(get_value($option, 'secret_key'));
            $this->take_fee_from_user = get_value($params, 'take_fee_from_user');
            $this->rate_to_usd        = get_value($option, 'rate_to_usd');
        }
    }

    public function index()
    {
        redirect(cn("add_funds"));
    }

    /**
     * Initialize a Korapay transaction and redirect to checkout
     */
    public function create_payment($data_payment = "")
    {
        _is_ajax($data_payment['module']);
        $amount = $data_payment['amount'];

        if (!$amount) {
            _validation('error', lang('There_was_an_error_processing_your_request_Please_try_again_later'));
        }

        if (!$this->public_key || !$this->secret_key) {
            _validation('error', lang('this_payment_is_not_active_please_choose_another_payment_or_contact_us_for_more_detail'));
        }

        $users = session('user_current_info');
        $reference = 'KP_' . ids() . '_' . time();

        // Apply exchange rate only if site currency is NOT NGN
        $korapay_amount = $amount;
        
        // If system is USD (or other) and needs conversion to NGN
        if ($this->currency_code !== 'NGN' && $this->rate_to_usd && $this->rate_to_usd > 0) {
            $korapay_amount = $amount * $this->rate_to_usd;
        }

        // Korapay expects amount as a number (based on observed behavior, Major units)
        // Ensure strictly 2 decimal places
        $korapay_amount = round($korapay_amount, 2);

        // Get customer name
        $customer_name = isset($users['first_name']) ? $users['first_name'] : 'Customer';
        if (isset($users['last_name'])) {
            $customer_name .= ' ' . $users['last_name'];
        }

        // Initialize charge via Korapay API (Checkout Redirect method)
        $payload = [
            'reference'      => $reference,
            'amount'         => $korapay_amount,
            'currency'       => 'NGN',
            'redirect_url'   => cn('add_funds/korapay/complete'),
            'customer'       => [
                'name'  => $customer_name,
                'email' => $users['email'],
            ],
            'notification_url' => cn('add_funds/korapay/webhook'),
            'merchant_bears_cost' => $this->take_fee_from_user ? false : true,
        ];

        $response = $this->korapay_request(
            'POST',
            'https://api.korapay.com/merchant/api/v1/charges/initialize',
            $payload
        );

        if ($response && isset($response['status']) && $response['status'] === true) {
            // Log the transaction
            $data_tnx_log = [
                "ids"            => ids(),
                "uid"            => session("uid"),
                "type"           => $this->payment_type,
                "transaction_id" => $reference,
                "amount"         => $amount, // Log original amount in site currency
                "status"         => 0,
                "created"        => NOW,
            ];
            $this->db->insert($this->tb_transaction_logs, $data_tnx_log);

            // Redirect to Korapay checkout
            $checkout_url = $response['data']['checkout_url'];
            ms([
                'status'       => 'success',
                'redirect_url' => $checkout_url
            ]);
        } else {
            $error_msg = isset($response['message']) ? $response['message'] : 'Payment initialization failed';
            
            // Check for specific error data
            if (isset($response['data']) && is_string($response['data'])) {
                $error_msg .= ': ' . $response['data'];
            }
            
            log_message('error', 'Korapay Init Error: ' . json_encode($response));
            _validation('error', $error_msg);
        }
    }

    /**
     * Handle redirect back from Korapay after payment
     */
    public function complete()
    {
        $reference = $this->input->get('reference');

        if (!$reference) {
            redirect(cn("add_funds/unsuccess"));
        }

        // Verify the transaction with Korapay
        $response = $this->korapay_request(
            'GET',
            'https://api.korapay.com/merchant/api/v1/charges/' . rawurlencode($reference)
        );

        if (!$response || !isset($response['status']) || $response['status'] !== true) {
            log_message('error', 'Korapay Verify Error: ' . json_encode($response));
            redirect(cn("add_funds/unsuccess"));
        }

        $data = $response['data'];
        $transaction = $this->model->get('*', $this->tb_transaction_logs, [
            'transaction_id' => $reference,
            'type'           => $this->payment_type,
            'status'         => 0
        ]);

        if (!$transaction) {
            redirect(cn("add_funds"));
        }

        if ($data['status'] === 'success') {
            // Calculate fees
            $korapay_fee = 0;
            if (isset($data['fee'])) {
                $korapay_fee = (float) $data['fee'];
                // Convert fee back to site currency
                if ($this->rate_to_usd && $this->rate_to_usd > 0) {
                    $korapay_fee = $korapay_fee / $this->rate_to_usd;
                }
            }

            $data_tnx_log = [
                "transaction_id" => $reference,
                'txn_fee'        => ($this->take_fee_from_user && $korapay_fee > 0) ? round($korapay_fee, 4) : 0,
                'payer_email'    => isset($data['customer']['email']) ? $data['customer']['email'] : '',
                "status"         => 1,
            ];

            $this->db->update($this->tb_transaction_logs, $data_tnx_log, ['id' => $transaction->id]);

            // Add funds to user balance
            if ($this->take_fee_from_user) {
                $transaction->txn_fee = $data_tnx_log['txn_fee'];
            } else {
                $transaction->txn_fee = 0;
            }

            $this->model->add_funds_bonus_email($transaction, $this->payment_id);

            set_session("transaction_id", $transaction->id);
            redirect(cn("add_funds/success"));
        } else {
            $this->db->update($this->tb_transaction_logs, ['status' => 2], ['id' => $transaction->id]);
            redirect(cn("add_funds/unsuccess"));
        }
    }

    /**
     * Korapay Webhook handler
     */
    public function webhook()
    {
        $input = file_get_contents('php://input');
        $event = json_decode($input, true);

        // Verify webhook - Korapay sends a hash in the header
        $korapay_signature = isset($_SERVER['HTTP_X_KORAPAY_SIGNATURE']) ? $_SERVER['HTTP_X_KORAPAY_SIGNATURE'] : '';

        if ($this->secret_key && $korapay_signature) {
            $computed = hash_hmac('sha256', $input, $this->secret_key);
            if (!hash_equals($computed, $korapay_signature)) {
                http_response_code(401);
                exit('Unauthorized');
            }
        }

        if ($event && isset($event['event']) && $event['event'] === 'charge.success') {
            $data = $event['data'];
            $reference = $data['reference'];

            $transaction = $this->model->get('*', $this->tb_transaction_logs, [
                'transaction_id' => $reference,
                'type'           => $this->payment_type,
                'status'         => 0
            ]);

            if ($transaction) {
                $korapay_fee = isset($data['fee']) ? (float) $data['fee'] : 0;
                if ($this->rate_to_usd && $this->rate_to_usd > 0) {
                    $korapay_fee = $korapay_fee / $this->rate_to_usd;
                }

                $data_tnx_log = [
                    'txn_fee'     => ($this->take_fee_from_user && $korapay_fee > 0) ? round($korapay_fee, 4) : 0,
                    'payer_email' => isset($data['customer']['email']) ? $data['customer']['email'] : '',
                    'status'      => 1,
                ];

                $this->db->update($this->tb_transaction_logs, $data_tnx_log, ['id' => $transaction->id]);

                if ($this->take_fee_from_user) {
                    $transaction->txn_fee = $data_tnx_log['txn_fee'];
                } else {
                    $transaction->txn_fee = 0;
                }

                $this->model->add_funds_bonus_email($transaction, $this->payment_id);
            }
        }

        http_response_code(200);
        echo json_encode(['status' => 'success']);
    }

    /**
     * Make HTTP request to Korapay API
     */
    private function korapay_request($method, $url, $data = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $headers = [
            'Authorization: Bearer ' . $this->secret_key,
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message('error', 'Korapay cURL Error: ' . $error);
            return false;
        }

        return json_decode($result, true);
    }
}
