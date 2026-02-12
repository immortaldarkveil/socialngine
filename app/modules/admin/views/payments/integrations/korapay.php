<?php
  $payment_elements = [
    [
      'label'      => form_label('Public Key'),
      'element'    => form_input(['name' => "payment_params[option][public_key]", 'value' => @$payment_option->public_key, 'type' => 'text', 'class' => $class_element, 'placeholder' => 'pk_live_...']),
      'class_main' => "col-md-12 col-sm-12 col-xs-12",
    ],
    [
      'label'      => form_label('Secret Key'),
      'element'    => form_input(['name' => "payment_params[option][secret_key]", 'value' => @$payment_option->secret_key, 'type' => 'text', 'class' => $class_element, 'placeholder' => 'sk_live_...']),
      'class_main' => "col-md-12 col-sm-12 col-xs-12",
    ],
    [
      'label'      => form_label('Currency rate'),
      'element'    => form_input(['name' => "payment_params[option][rate_to_usd]", 'value' => @$payment_option->rate_to_usd, 'type' => 'text', 'class' => $class_element . ' text-right']),
      'class_main' => "col-md-12 col-sm-12 col-xs-12",
      'type'       => "exchange_option",
      'item1'      => ['name' => get_option('currecy_code', 'NGN'), 'value' => 1],
      'item2'      => ['name' => 'NGN', 'value' => 1],
    ],
  ];
  echo render_elements_form($payment_elements);
?>

<div class="form-group">
  <label class="form-label">Config:</label>
  <ol>
    <li>Copy your <strong>Public Key</strong> and <strong>Secret Key</strong> from your <a href="https://merchant.korapay.com" target="_blank">Kora Pay Dashboard</a></li>
    <li>Set the <strong>Redirect URL</strong> in Kora Pay dashboard: <code class="text-primary"><?php echo cn('add_funds/korapay/complete'); ?></code></li>
    <li>Set the <strong>Webhook URL</strong>: <code class="text-primary"><?php echo cn('add_funds/korapay/webhook'); ?></code></li>
  </ol>
</div>
