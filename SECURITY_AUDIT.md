# 🔒 SocialNgine Security Audit Report

**Date:** February 11, 2026  
**Auditor:** Automated Security Assessment  
**Scope:** Full codebase + infrastructure + database

---

## 📋 PART 1: Credentials Inventory (Secrets to Protect)

### 🔴 CRITICAL — Currently in Codebase

| # | Secret | Location | Current Status |
|---|--------|----------|---------------|
| 1 | **DB Password** (empty) | `app/config.php` line 5 | ⚠️ No password set |
| 2 | **Encryption Key** `1208cdfd...` | `app/config.php` line 8 | ⚠️ Hardcoded, in git history |
| 3 | **Redis Password** `S0cialNgin_R3dis_Sec_2026` | `app/config/config.php` line 390 | 🔴 Hardcoded as fallback |
| 4 | **Old DB Password** `yourpass` | Git history (commit `871a4c5`) | 🔴 Leaked in git |
| 5 | **Old Encryption Key** `202dd507...` | Git history (commit `871a4c5`) | 🔴 Leaked in git |

### 🟡 STORED IN DATABASE (`general_options` table)

| # | Secret | Option Name | Status |
|---|--------|------------|--------|
| 6 | **Google OAuth Client ID** | `google_auth_client_id` | ✅ DB-stored (good) |
| 7 | **Google OAuth Client Secret** | `google_auth_client_secret` | ✅ DB-stored (good) |
| 8 | **reCAPTCHA Site Key** | `google_capcha_site_key` | ✅ DB-stored |
| 9 | **reCAPTCHA Secret Key** | `google_capcha_secret_key` | ✅ DB-stored |
| 10 | **SMTP Server** | `smtp_server` | ✅ DB-stored (empty) |
| 11 | **SMTP Username** | `smtp_username` | ✅ DB-stored (empty) |
| 12 | **SMTP Password** | `smtp_password` | ✅ DB-stored (empty) |

### 🟡 PAYMENT GATEWAY SECRETS (stored in DB per payment integration)

These are configured per-payment-provider via admin panel:

| # | Provider | Secrets |
|---|----------|---------|
| 13 | **Stripe** | `secret_key`, `publishable_key` |
| 14 | **PayPal** | `client_id`, `secret_key` |
| 15 | **Paystack** | `public_key`, `secret_key` |
| 16 | **Flutterwave** | `public_key`, `secret_key` |
| 17 | **2Checkout** | `seller_id`, `secret_key` |
| 18 | **Coinbase** | `api_key` |
| 19 | **CoinPayments** | `public_key`, `secret_key` |
| 20 | **Mollie** | `api_key` |
| 21 | **Payeer** | `merchant_id`, `secret_key` |

### 🟡 SMM PROVIDER API KEYS (stored in DB)

| # | Secret | Location |
|---|--------|----------|
| 22 | **Provider API keys** | `sp_providers` table — `key` column |
| 23 | **Provider API URLs** | `sp_providers` table — `url` column |

---

## 🛡️ PART 2: Security Vulnerability Assessment

### 🔴 CRITICAL VULNERABILITIES

#### 1. MySQL Root — No Password
- **File:** `app/config.php`
- **Issue:** `DB_PASS` is empty string. MySQL root has no password.
- **Risk:** Any local process or attacker with network access can access the entire database.
- **Fix:** Set a strong MySQL password immediately.

#### 2. Credentials Leaked in Git History
- **Commits:** `871a4c5` (initial), `1e1f6e7`
- **Issue:** `app/config.php` was committed TWICE with real credentials (`yourpass`, encryption key `202dd507...`). Even though the file is now in `.gitignore`, the old values are permanently in git history.
- **Risk:** Anyone with repo access (it's on GitHub!) can see old credentials.
- **Fix:** 
  - Rotate ALL credentials that were ever committed
  - Consider using `git filter-repo` to scrub history
  - **Or make the repo private** (currently at `github.com/immortaldarkveil/socialngin.git`)

#### 3. Redis Password Hardcoded as Fallback
- **File:** `app/config/config.php` line 390
- **Code:** `$redis_password = getenv('REDIS_PASSWORD') ?: 'S0cialNgin_R3dis_Sec_2026';`
- **Issue:** The Redis password is in the source code as a fallback. This is committed to git.
- **Risk:** Anyone with repo access knows your Redis password.
- **Fix:** Remove the hardcoded fallback; require the env var.

#### 4. Duplicate Database Entries for Google Auth
- **Table:** `general_options`
- **Issue:** There are **2 rows each** for `google_auth_client_id` and `google_auth_client_secret` — one empty, one with values. This could cause unpredictable behavior.
- **Fix:** Delete the empty duplicate rows.

### 🟠 HIGH VULNERABILITIES

#### 5. SSL Verification Disabled in 14+ cURL Calls
- **Files:** `common_helper.php`, `smmapis_helper.php`, `file_manager_helper.php`, `MX/Controller.php`, `paytm_helper.php`, + SMM provider libraries
- **Issue:** `CURLOPT_SSL_VERIFYPEER = false` and `CURLOPT_SSL_VERIFYHOST = 0` disable SSL certificate verification, making these connections vulnerable to man-in-the-middle attacks.
- **Risk:** Attackers could intercept API keys, payment data, and user information.
- **Fix:** Enable SSL verification and bundle proper CA certificates.

#### 6. Cookies Not HttpOnly or Secure
- **File:** `app/config/config.php` lines 414-415
- **Issue:** `cookie_secure = FALSE` and `cookie_httponly = FALSE`
- **Risk:** Cookies (including session cookies) can be stolen via JavaScript (XSS) and are sent over unencrypted HTTP.
- **Fix:** Set `cookie_httponly = TRUE` immediately. Set `cookie_secure = TRUE` when using HTTPS.

#### 7. No Login Rate Limiting / Brute Force Protection
- **Issue:** No rate limiting found on login endpoints (`auth/ajax_sign_in`, `admin/auth`). No failed login attempt tracking.
- **Risk:** Attackers can brute-force passwords unlimited times.
- **Fix:** Implement login attempt tracking with exponential backoff or CAPTCHA after N failures.

#### 8. No HTTPS in Production
- **Issue:** Site runs on `http://socialngine.com` — no SSL certificate.
- **Risk:** All traffic (passwords, API keys, session cookies) is transmitted in plaintext.
- **Fix:** Install an SSL certificate (free via Let's Encrypt) and force HTTPS redirect.

### 🟡 MEDIUM VULNERABILITIES

#### 9. Raw `$_GET`/`$_POST`/`$_REQUEST` Usage
- **Files:** `api/controllers/api.php`, `paypal.php`, `admin/views/plugins/index.php` (uses `base64_decode($_GET["error"])`)
- **Issue:** Direct superglobal access bypasses CodeIgniter's input sanitization.
- **Risk:** Potential XSS and injection attacks.
- **Most concerning:** `base64_decode($_GET["error"])` in `admin/views/plugins/index.php` — decodes and displays user-controlled input directly.

#### 10. CSRF Token Not Regenerated Per Request
- **File:** `app/config/config.php` line 537
- **Issue:** `csrf_regenerate = FALSE` — the CSRF token stays the same across submissions.
- **Risk:** Reduces CSRF protection effectiveness since a captured token can be reused.
- **Fix:** Set `csrf_regenerate = TRUE` (may require AJAX handling updates).

#### 11. Session Cookie Named `csrfToken`
- **File:** `app/config/config.php` line 388
- **Issue:** The session cookie is misleadingly named `csrfToken`, which is confusing but also might leak session information if debugging tools key on this name.
- **Fix:** Rename to something appropriate like `socialngine_session`.

#### 12. Weak Password Policy
- **File:** `admin.php` lines 121-123
- **Issue:** Password validation only requires `min_length[6]|max_length[25]`. No complexity requirements.
- **Risk:** Users can set very weak passwords like `123456`.
- **Fix:** Require mixed case, numbers, and minimum 8 characters.

### 🟢 POSITIVE FINDINGS (Good Security Practices)

| Practice | Status |
|----------|--------|
| CSRF Protection enabled | ✅ Enabled globally |
| `app/config.php` in `.gitignore` | ✅ (since commit `eefb229`) |
| `config.example.php` uses placeholder values | ✅ Good practice |
| XSS filtering via `xss_clean` on form inputs | ✅ Used extensively |
| Password hashing (bcrypt via CI) | ✅ Admin password verification uses `password_verify` |
| Google Auth uses CSRF state tokens | ✅ Our implementation is secure |
| API key access control | ✅ Users have unique API keys |
| File upload restricted to `jpg|png` | ✅ Limited allowed types |

---

## 🎯 PART 3: Priority Fix List

### Immediate (Do Today)

1. **Set MySQL root password** — your DB is wide open
2. **Delete duplicate `general_options` rows** for Google Auth
3. **Set `cookie_httponly = TRUE`** in `app/config/config.php`
4. **Check if GitHub repo is public** — if so, make it private immediately (credentials are in git history)

### This Week

5. **Install SSL certificate** (Let's Encrypt) and force HTTPS
6. **Remove hardcoded Redis password** from source code
7. **Add login rate limiting** (track failed attempts in DB)
8. **Set `cookie_secure = TRUE`** (after enabling HTTPS)

### Before Production Launch

9. **Enable SSL verification** on all cURL calls
10. **Rotate all credentials** that appeared in git history
11. **Add password complexity requirements**
12. **Set `csrf_regenerate = TRUE`** and update AJAX handlers
13. **Sanitize all raw `$_GET`/`$_POST` usage**
14. **Consider using `.env` file** instead of `config.php` for secrets

---

## 📊 Overall Risk Score: 6.5/10 (Moderate-High)

The application has good foundational security (CSRF, XSS filtering, password hashing) but has significant infrastructure gaps (no HTTPS, no DB password, credentials in git history, no rate limiting) that need immediate attention before going live.
