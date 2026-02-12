    <?php 
      include_once 'blocks/head.blade.php';
    ?>
    <section class="sign-up-form">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h1>Sign Up</h1>
                    <div class="form-container">
                        <form class="actionFormWithoutToast" action="<?=cn("auth/ajax_sign_up")?>" data-redirect="<?=cn('statistics')?>" method="POST" id="signUpForm" data-focus="false">
                            <div class="form-group">
                                <input type="email" class="form-control-input" name="email" required>
                                <label class="label-control" for="semail"><?php echo lang("Email"); ?></label>
                                <div class="help-block with-errors"></div>
                            </div>

                            <div class="form-group">
                                <input type="text" class="form-control-input" name="first_name" required>
                                <label class="label-control" for="sname"><?php echo lang("first_name"); ?></label>
                                <div class="help-block with-errors"></div>
                            </div>

                            <div class="form-group">
                                <input type="text" class="form-control-input" name="last_name" required>
                                <label class="label-control" for="sname"><?php echo lang("last_name"); ?></label>
                                <div class="help-block with-errors"></div>
                            </div>

                            <?php
                                if (get_option('enable_signup_skype_field')) {
                            ?>

                            <div class="form-group">
                                <input type="text" class="form-control-input" name="skype_id" required>
                                <label class="label-control" for="sname"><?php echo lang("Skype_id"); ?></label>
                            </div>
                            <?php } ?>

                            <div class="form-group">
                                <input type="password" class="form-control-input" name="password" required>
                                <label class="label-control" for="spassword"><?php echo lang("Password"); ?></label>
                                <div class="help-block with-errors"></div>
                            </div>

                            <div class="form-group">
                                <input type="password" class="form-control-input" name="re_password" required>
                                <label class="label-control" for="spassword"><?php echo lang("Confirm_password"); ?></label>
                                <div class="help-block with-errors"></div>
                            </div>

                            <div class="form-group">
                              <select  name="timezone" class="form-control square">
                                <?php $time_zones = tz_list();
                                  if (!empty($time_zones)) {
                                    $location = get_location_info_by_ip(get_client_ip());
                                    $user_timezone = $location->timezone;
                                    if ($user_timezone == "" || $user_timezone == 'Unknow') {
                                      $user_timezone = get_option("default_timezone", 'UTC');
                                    }
                                    foreach ($time_zones as $key => $time_zone) {
                                ?>
                                <option value="<?=$time_zone['zone']?>" <?=($user_timezone == $time_zone["zone"])? 'selected': ''?>><?=$time_zone['time']?></option>
                                <?php }}?>
                              </select>
                            </div>

                            <div class="form-group mt-20">
                                <div id="alert-message" class="alert-message-reponse"></div>
                            </div>

                             <?php
                              if (get_option('enable_goolge_recapcha') &&  get_option('google_capcha_site_key') != "" && get_option('google_capcha_secret_key') != "") {
                            ?>
                            <div class="form-group">
                              <div class="g-recaptcha" data-sitekey="<?=get_option('google_capcha_site_key')?>"></div>
                            </div>
                            <?php } ?> 

                            <div class="form-group">
                              <label class="custom-control custom-checkbox">
                                <input type="checkbox" name="terms" class="custom-control-input" />
                                <span class="custom-control-label"><?=lang("i_agree_the")?> <a href="<?=cn('terms')?>"><?=lang("terms__policy")?></a></span>
                              </label>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="form-control-submit-button btn-submit">SIGN UP</button>
                            </div>
                            
                        </form>
                        <div class="text-center text-muted">
                          <?=lang("already_have_account")?> <a href="<?=cn()?>"><?=lang("Login")?></a>
                        </div>

                        <?php if(get_option('google_auth_client_id', '') != ''){ ?>
                        <div style="text-align:center; margin-top:15px;">
                            <div style="position:relative; display:flex; align-items:center; justify-content:center; margin:8px 0 4px;">
                                <div style="flex:1; height:1px; background:rgba(0,0,0,0.15);"></div>
                                <span style="padding:0 12px; color:rgba(0,0,0,0.4); font-size:13px;">or</span>
                                <div style="flex:1; height:1px; background:rgba(0,0,0,0.15);"></div>
                            </div>
                            <a href="<?=cn('auth/google_login')?>" style="display:inline-flex; align-items:center; justify-content:center; gap:10px; width:100%; padding:10px 20px; background:#fff; color:#444; border:1px solid #ddd; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none; transition:box-shadow 0.2s; box-shadow:0 1px 4px rgba(0,0,0,0.1); margin-top:10px;">
                                <svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
                                Sign up with Google
                            </a>
                        </div>
                        <?php } ?>
                    </div>

                </div>
            </div>
        </div>
    </section>
   
    <?php 
      include_once 'blocks/script.blade.php';
    ?>