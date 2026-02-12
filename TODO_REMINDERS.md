# TODO Reminders — Feb 11-12, 2026

## 1. 🔐 Add Google Auth to Admin Panel Settings
- Add Google Client ID / Client Secret fields to admin settings page
- Allow enabling/disabling Google Auth from the UI
- Currently credentials are stored directly in the database (`general_options` table)

## 2. 🛡️ Fix Security Audit Issues (see SECURITY_AUDIT.md)
Priority fixes:
- [ ] Set MySQL root password
- [ ] Set `cookie_httponly = TRUE` in `app/config/config.php`
- [ ] Remove hardcoded Redis password fallback from source code
- [ ] Check if GitHub repo is public — if so, make private (credentials in git history!)
- [ ] Install SSL certificate and force HTTPS
- [ ] Add login rate limiting
- [ ] Rotate all credentials that appeared in git history

## 3. 🔑 Kora Pay / Paystack Keys
- Kora Pay business account is under verification — swap test keys for live keys once approved
- Set up Paystack business account and enter keys

## 4. 🖥️ Google Auth Admin Panel Settings
- Add Google Client ID / Secret fields to admin settings page
- Allow enabling/disabling Google Auth from the UI
