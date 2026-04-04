# LegitDate — PHP Backend API

Complete REST API backend for the LegitDate Flutter dating app.

---

## 📁 Folder Structure

```
dating_api/
├── config.php                  ← DB credentials, JWT secret, SMS & FCM keys
├── schema.sql                  ← Run once to create all tables
├── .htaccess                   ← CORS headers + security rules
│
├── auth/
│   ├── send_otp.php            POST  /auth/send_otp.php
│   ├── verify_otp.php          POST  /auth/verify_otp.php
│   └── firebase_login.php      POST  /auth/firebase_login.php
│
├── profile/
│   ├── setup.php               POST  /profile/setup.php
│   ├── edit_profile.php        POST  /profile/edit_profile.php
│   ├── get_profile.php         GET   /profile/get_profile.php?token=&target_id=
│   ├── upload_photo.php        POST  /profile/upload_photo.php  (multipart)
│   ├── upload_post.php         POST  /profile/upload_post.php   (multipart)
│   ├── get_users.php           GET   /profile/get_users.php?token=&page=&min_age=&max_age=&max_distance=
│   ├── swipe.php               POST  /profile/swipe.php
│   ├── matches.php             GET   /profile/matches.php?token=
│   ├── view_profile.php        POST  /profile/view_profile.php
│   ├── who_viewed_me.php       GET   /profile/who_viewed_me.php?token=
│   ├── report_user.php         POST  /profile/report_user.php
│   └── block_user.php          POST  /profile/block_user.php
│
├── chat/
│   ├── get_chats.php           GET   /chat/get_chats.php?token=
│   ├── get_messages.php        GET   /chat/get_messages.php?token=&match_id=
│   ├── send_message.php        POST  /chat/send_message.php
│   └── save_message.php        POST  /chat/save_message.php
│
├── notifications/
│   ├── send_push.php           (internal helper — not a public endpoint)
│   ├── update_token.php        POST  /notifications/update_token.php
│   └── get_notifications.php   GET   /notifications/get_notifications.php?token=
│
└── uploads/
    ├── photos/                 Profile & DP images saved here
    └── posts/                  Post images saved here
```

---

## 🚀 Setup Guide

### Step 1 — Place files
Copy the `dating_api` folder into your web server root:
- **XAMPP / WAMP (Windows):** `C:/xampp/htdocs/dating_api`
- **Linux/Mac LAMP:**          `/var/www/html/dating_api`

### Step 2 — Create the database
Open phpMyAdmin or run in terminal:
```bash
mysql -u root -p < dating_api/schema.sql
```

### Step 3 — Configure `config.php`
Open `config.php` and update:

```php
define('DB_USER', 'root');       // ← your MySQL username
define('DB_PASS', '');           // ← your MySQL password
define('JWT_SECRET', 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING_min32chars');
define('UPLOAD_URL', 'http://localhost/dating_api/uploads/');
```

### Step 4 — Set upload folder permissions (Linux/Mac)
```bash
chmod -R 755 dating_api/uploads
```
On Windows/XAMPP this is not needed.

### Step 5 — Test in browser
Open: `http://localhost/dating_api/auth/send_otp.php`
You should see: `{"status":"error","message":"Method not allowed"}`
That means the server is running correctly!

---

## 🔑 API Keys — What to Add & Where

### SMS (OTP sending)
**File:** `config.php`  
**Section:** `SMS / OTP PROVIDER`  
Currently OTPs are only logged to the server error log (dev mode).  
Choose one:
- **Twilio** — uncomment the Twilio block and fill in `TWILIO_SID`, `TWILIO_TOKEN`, `TWILIO_FROM`
- **MSG91** — uncomment the MSG91 block and fill in `MSG91_API_KEY`, `MSG91_SENDER_ID`, `MSG91_TEMPLATE_ID`
- After adding a real provider, **delete** the dev fallback `sendOtpSms()` function at the bottom of that section.

### Firebase Push Notifications (FCM)
**File:** `config.php`  
**Section:** `FIREBASE CLOUD MESSAGING`  
1. Go to Firebase Console → Project Settings → Cloud Messaging
2. Copy your **Server Key**
3. Add this line to `config.php`:
   ```php
   define('FCM_SERVER_KEY', 'YOUR_SERVER_KEY_HERE');
   ```
4. Push notifications will start working automatically — no other changes needed.

### Firebase Phone Auth (optional)
**File:** `auth/firebase_login.php`  
Only needed if you switch to Firebase Phone Auth instead of backend OTP.  
1. Download service account JSON from Firebase Console
2. Place it at `dating_api/firebase-service-account.json`
3. Run `composer require kreait/firebase-php`
4. Uncomment the Firebase verification block in `firebase_login.php`
5. Delete the dev bypass `goto handleUser` block above it.

---

## 🌐 Flutter `kBaseUrl` settings
In `lib/constants.dart`:
```dart
// Android emulator:
const String kBaseUrl = 'http://10.0.2.2/dating_api';

// Physical device (replace with your PC's local IP):
const String kBaseUrl = 'http://192.168.1.X/dating_api';

// Browser / Windows:
const String kBaseUrl = 'http://localhost/dating_api';
```

---

## 🔒 Going to Production
1. Move `dating_api` to your live server
2. Update `UPLOAD_URL` in `config.php` to your domain URL
3. Set a strong `JWT_SECRET`
4. Enable a real SMS provider and delete the dev fallback
5. Change `.htaccess` CORS origin from `*` to your app's domain
6. Enable HTTPS
