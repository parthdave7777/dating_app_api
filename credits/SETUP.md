# Google Play Billing Integration – Setup Guide

You have successfully replaced Razorpay with a secure, server-side verified Google Play Billing system. 

### **Step 1: Create Products in Google Play Console**
1. Log in to **Google Play Console** -> Select your app.
2. Go to **Monetize** -> **In-app products**.
3. Create 4 products with these exact **Product IDs**:
   - `credits_100` (Name: Starter Pack)
   - `credits_500` (Name: Popular Pack)
   - `credits_1200` (Name: Elite Pack)
   - `credits_3000` (Name: Whale Pack)
4. Set them as **"Managed product"** (not subscription).
5. Set your prices and click **Activate**.

### **Step 2: Link Google Play Developer API**
1. Go to **Google Play Console** -> **Setup** -> **API access**.
2. Link your account to a **Google Cloud Project**.
3. In Google Cloud Console, enable the **"Google Play Android Developer API"**.
4. Create a **Service Account** and download the **JSON Key**.
5. Go back to Play Console -> **Users and permissions**. Invite the service account email and grant **"View financial data"** and **"Manage orders"** (or Release Manager role).

### **Step 3: Update Backend Environment Variables (Railway)**
Add these variables to your Railway dashboard:
- `GOOGLE_PLAY_PACKAGE_NAME`: Your app's package name (e.g., `com.legitdate.app`).
- `GOOGLE_SERVICE_ACCOUNT_JSON`: The **entire content** of the JSON key file you downloaded in Step 2.

### **Step 4: Database Finalization**
Run this URL once to create the secure tracking tables:
`https://your-api-url.railway.app/credits/setup_billing_tables.php?secret=admin_setup_2026`

### **Step 5: Testing**
1. Add your email to **License Testing** in Google Play Console (Setup -> License testing).
2. Upload the new APK/Bundle to the **Internal Testing** track.
3. Use a real device (not emulator) to test the purchase. You should see a "Test Card" window.
4. Verify that credits are added instantly to your profile.

---
**Security Note**: Your system now uses the **Idempotency** rule. If a user tries to replay a receipt, the server will detect it (via Redis/MySQL) and block duplicate credit granting.
