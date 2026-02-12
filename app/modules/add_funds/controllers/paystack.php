<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Paystack Payment Gateway Controller
 * 
 * Handles: Initialize → Redirect to Paystack → Callback → Verify → Credit balance
 * API Docs: https://paystack.com/docs/api/transaction/
 */
class paystack extends MX_Controller
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
        $this->payment_type        = 'paystack';
        $this->tb_transaction_logs = TRANSACTION_LOGS;
        $this->tb_payments         = PAYMENTS_METHOD;
        $this->tb_payments_bonuses = PAYMENTS_BONUSES;
        $this->currency_code       = get_option("currency_code", "NGN");

        if (!$payment) {
            $payment = $this->model->get('id, type, name, params', $this->tb_payments, ['type' => $this->payment_type]);
        }

        if ($payment) {
            $this->payment_id        = $payment->id;
            $params                  = $payment->params;
            $option                  = get_value($params, 'option');
            $this->public_key        = get_value($option, 'public_key');
            $this->secret_key        = get_value($option, 'secret_key');
            $this->take_fee_from_user = get_value($params, 'take_fee_from_user');
            $this->rate_to_usd       = get_value($option, 'rate_to_usd');
        }
    }

    public function index()
    {
        redirect(cn("add_funds"));
    }

    /**
     * Initialize a Paystack transaction and redirect to checkout
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
        $reference = 'PS_' . ids() . '_' . time();

        // Convert amount to kobo (Paystack expects amount in smallest currency unit)
        // If site currency is NGN, multiply by 100 for kobo
        // Only apply exchange rate if site currency is NOT NGN
        $paystack_amount = $amount;
        
        if ($this->currency_code !== 'NGN' && $this->rate_to_usd && $this->rate_to_usd > 0) {
            $paystack_amount = $amount * $this->rate_to_usd;
        }
        $amount_in_kobo = (int) round($paystack_amount * 100);

        // Initialize transaction via Paystack API
        $payload = [
            'email'        => $users['email'],
            'amount'       => $amount_in_kobo,
            'currency'     => 'NGN',
            'reference'    => $reference,
            'callback_url' => cn('add_funds/paystack/complete'),
            'metadata'     => json_encode([
                'user_id'      => session('uid'),
                'site_amount'  => $amount,
                'custom_fields' => [
                    [
                        'display_name'  => 'User',
                        'variable_name' => 'user_email',
                        'value'         => $users['email']
                    ]
                ]
            ])
        ];

        $response = $this->paystack_request('POST', 'https://api.paystack.co/transaction/initialize', $payload);

        if ($response && isset($response['status']) && $response['status'] === true) {
            // Log the transaction
            $data_tnx_log = [
                "ids"            => ids(),
                "uid"            => session("uid"),
                "type"           => $this->payment_type,
                "transaction_id" => $reference,
                "amount"         => $amount,
                "status"         => 0,
                "created"        => NOW,
            ];
            $this->db->insert($this->tb_transaction_logs, $data_tnx_log);

            // Redirect to Paystack checkout
            ms([
                'status'       => 'success',
                'redirect_url' => $response['data']['authorization_url']
            ]);
        } else {
            $error_msg = isset($response['message']) ? $response['message'] : 'Payment initialization failed';
            log_message('error', 'Paystack Init Error: ' . json_encode($response));
            _validation('error', $error_msg);
        }
    }

    /**
     * Handle callback from Paystack after payment
     */
    public function complete()
    {
        $reference = $this->input->get('reference') ?: $this->input->get('trxref');

        if (!$reference) {
            redirect(cn("add_funds/unsuccess"));
        }

        // Verify the transaction with Paystack
        $response = $this->paystack_request('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference));

        if (!$response || !isset($response['status']) || $response['status'] !== true) {
            log_message('error', 'Paystack Verify Error: ' . json_encode($response));
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
            $paystack_fee = 0;
            if (isset($data['fees'])) {
                // Paystack fees are in kobo, convert back
                $paystack_fee = $data['fees'] / 100;
                // Convert fee back to site currency if exchange rate exists
                if ($this->rate_to_usd && $this->rate_to_usd > 0) {
                    $paystack_fee = $paystack_fee / $this->rate_to_usd;
                }
            }

            $data_tnx_log = [
                "transaction_id" => $reference,
                'txn_fee'        => ($this->take_fee_from_user && $paystack_fee > 0) ? round($paystack_fee, 4) : 0,
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
            // Payment was not successful
            $this->db->update($this->tb_transaction_logs, ['status' => 2], ['id' => $transaction->id]);
            redirect(cn("add_funds/unsuccess"));
        }
    }

    /**
     * Paystack Webhook (IPN) handler
     * Called by Paystack servers to confirm payment
     */
    public function webhook()
    {
        // Verify webhook signature
        $input = file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '';

        if (!$this->secret_key || !hash_equals(hash_hmac('sha512', $input, $this->secret_key), $signature)) {
            http_response_code(401);
            exit('Unauthorized');
        }

        $event = json_decode($input, true);

        if ($event && $event['event'] === 'charge.success') {
            $data = $event['data'];
            $reference = $data['reference'];

            $transaction = $this->model->get('*', $this->tb_transaction_logs, [
                'transaction_id' => $reference,
                'type'           => $this->payment_type,
                'status'         => 0
            ]);

            if ($transaction) {
                $paystack_fee = isset($data['fees']) ? ($data['fees'] / 100) : 0;
                if ($this->rate_to_usd && $this->rate_to_usd > 0) {
                    $paystack_fee = $paystack_fee / $this->rate_to_usd;
                }

                $data_tnx_log = [
                    'txn_fee'        => ($this->take_fee_from_user && $paystack_fee > 0) ? round($paystack_fee, 4) : 0,
                    'payer_email'    => isset($data['customer']['email']) ? $data['customer']['email'] : '',
                    'status'         => 1,
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
     * Make HTTP request to Paystack API
     */
    private function paystack_request($method, $url, $data = null)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $headers = [
            'Authorization: Bearer ' . $this->secret_key,
            'Content-Type: application/json',
            'Cache-Control: no-cache',
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
            log_message('error', 'Paystack cURL Error: ' . $error);
            return false;
        }

        return json_decode($result, true);
    }
}
