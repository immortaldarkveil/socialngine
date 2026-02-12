<?php
  $option           = get_value($payment_params, 'option');
  $min_amount       = get_value($payment_params, 'min');
  $max_amount       = get_value($payment_params, 'max');
  $type             = get_value($payment_params, 'type');
  $tnx_fee          = get_value($option, 'tnx_fee');
  $currency_code    = get_option("currency_code",'NGN');
  $currency_symbol  = get_option("currency_symbol",'₦');
?>

<div class="add-funds-form-content">
  <form class="form actionAddFundsForm" action="#" method="POST">
    <div class="row">
      <div class="col-md-12">
        <div class="for-group text-center">
          <div style="margin-bottom: 12px;">
            <span style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #0ba4db 0%, #0068a5 100%); border-radius: 8px; color: #fff; font-size: 22px; font-weight: 700; letter-spacing: 1px;">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="6" fill="#fff" fill-opacity=".18"/><path d="M6 12h12M12 6v12" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/></svg>
              Paystack
            </span>
          </div>
          <p class="p-t-5"><small><?=sprintf(lang("you_can_deposit_funds_with_paypal_they_will_be_automaticly_added_into_your_account"), 'Paystack')?></small></p>
        </div>

        <div class="form-group">
          <label><?=sprintf(lang("amount_usd"), $currency_code)?></label>
          <input class="form-control square" type="number" name="amount" placeholder="<?php echo $min_amount; ?>" step="0.01" min="<?php echo $min_amount; ?>">
        </div>

        <div class="form-group">
          <label><?php echo lang("note"); ?></label>
          <ul>
            <?php if ($tnx_fee > 0) { ?>
            <li><?=lang("transaction_fee")?>: <strong><?php echo $tnx_fee; ?>%</strong></li>
            <?php } ?>
            <li><?=lang("Minimal_payment")?>: <strong><?php echo $currency_symbol.$min_amount; ?></strong></li>
            <?php if ($max_amount > 0) { ?>
            <li><?=lang("Maximal_payment")?>: <strong><?php echo $currency_symbol.$max_amount; ?></strong></li>
            <?php } ?>
            <li>You will be redirected to Paystack's secure checkout page to complete payment.</li>
          </ul>
        </div>

        <div class="form-group">
          <label class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" name="agree" value="1">
            <span class="custom-control-label text-uppercase"><strong><?=lang("yes_i_understand_after_the_funds_added_i_will_not_ask_fraudulent_dispute_or_chargeback")?></strong></span>
          </label>
        </div>

        <div class="form-actions left">
          <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
          <input type="hidden" name="payment_method" value="<?php echo $type; ?>">
          <button type="submit" class="btn btn-primary btn-lg btn-block" style="background: linear-gradient(135deg, #0ba4db, #0068a5); border: none; border-radius: 8px; font-weight: 600;">
            <i class="fa fa-lock"></i> <?=lang("Pay")?> with Paystack
          </button>
        </div>
      </div>
    </div>
  </form>
</div>
