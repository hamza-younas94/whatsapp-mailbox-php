# Subscription Management System - Quick Guide

## 🎯 Overview
Complete subscription management system for multi-tenant WhatsApp CRM with edit capability, plan management, and automatic provisioning.

---

## 📋 Features Implemented

### 1. **User Profile Editor** (`user-profile.php`)
- ✅ Edit API Credentials (access token, phone number ID, API version, webhook URL)
- ✅ Edit User Preferences (timezone, date/time format, notifications)
- ✅ Edit Subscription (plan, status, message limits, contact limits, billing period)
- ✅ AJAX-powered inline editing (no page reload needed for updates)
- ✅ Accessed via "View Profile" 👁️ button in Users page

### 2. **Subscription Management Page** (`subscriptions.php`)
- ✅ Dashboard with subscription stats (total, active, trial, expired)
- ✅ Plan breakdown (Free, Starter, Professional, Enterprise)
- ✅ Complete subscription list with usage tracking
- ✅ **Reset Usage** - Reset message counter & start new billing period
- ✅ **Extend Trial** - Add X days to trial period
- ✅ **Bulk Update** - Change limits for all users on a plan
- ✅ Visual usage bars showing message consumption
- ✅ Quick actions: View Profile, Reset Usage, Extend Trial

### 3. **User Creation with Subscription** (`users.php`)
- ✅ Subscription plan selector when creating new users
- ✅ Auto-creates subscription record with plan limits
- ✅ Auto-creates user_settings with API defaults
- ✅ 14-day trial period by default
- ✅ Plans hidden when editing existing users

---

## 🚀 Usage Guide

### Creating a New Organization/User

1. **Navigate to Users page** (`users.php`)
2. **Click "+ Add User"**
3. **Fill in user details:**
   - Username (required)
   - Full Name (required)
   - Email (required)
   - Phone (optional)
   - Role (agent/admin/viewer)
   - Password (required)

4. **Select Subscription Plan:**
   - **Free**: 100 msgs/month, 50 contacts
   - **Starter**: 1,000 msgs/month, 500 contacts (default)
   - **Professional**: 10,000 msgs/month, 5,000 contacts
   - **Enterprise**: Unlimited

5. **Click "Save"**

**What Happens:**
- ✅ User account created
- ✅ Subscription record created with selected plan limits
- ✅ User settings initialized (API version: v18.0, configured: false)
- ✅ 14-day trial period started
- ✅ Billing period set (30 days)

---

### Editing User Settings/Subscription

1. **Navigate to Users page**
2. **Click 👁️ "View Profile"** on any user
3. **Edit API Credentials:**
   - Click "✏️ Edit" button on API Credentials card
   - Update access token, phone number ID, webhook URL, etc.
   - Click "💾 Save"

4. **Edit Preferences:**
   - Click "✏️ Edit" button on Preferences card
   - Change timezone, date/time format, notifications
   - Click "💾 Save"

5. **Edit Subscription:**
   - Click "✏️ Edit" button on Subscription card
   - Change plan, status, message limits, contact limits
   - Adjust billing period dates
   - Click "💾 Save"

---

### Managing Subscriptions (Admin)

1. **Navigate to Subscriptions page** (`subscriptions.php`)
2. **View Overview:**
   - Total subscriptions, active, trial, expired counts
   - Plan distribution (Free, Starter, Pro, Enterprise)

3. **Reset Usage for a User:**
   - Click 🔄 icon next to user
   - Confirms reset (resets messages_used to 0, starts new period)
   - Click OK

4. **Extend Trial:**
   - Click ⏰ icon next to user
   - Enter number of days (default: 14)
   - Trial extended, status set to 'trial'

5. **Bulk Update Plan Limits:**
   - Click "📊 Bulk Update Plans" button
   - Select plan (Free/Starter/Professional/Enterprise)
   - Enter new message limit
   - Enter new contact limit
   - Click "Update All"
   - **All users on that plan get new limits**

---

## 📊 Subscription Plans

| Plan | Message Limit | Contact Limit | Status | Notes |
|------|--------------|---------------|--------|-------|
| **Free** | 100/month | 50 | Active/Trial | Basic tier |
| **Starter** | 1,000/month | 500 | Active/Trial | Default for new users |
| **Professional** | 10,000/month | 5,000 | Active/Trial | Mid-tier |
| **Enterprise** | 999,999/month | 999,999 | Active | Unlimited |

---

## 🔑 Key Fields

### `user_subscriptions` Table
- `plan`: free, starter, professional, enterprise
- `status`: active, trial, cancelled, expired
- `message_limit`: Monthly message quota
- `messages_used`: Current period usage
- `contact_limit`: Maximum contacts allowed
- `trial_ends_at`: Trial expiration date
- `current_period_start`: Billing cycle start
- `current_period_end`: Billing cycle end

### `user_settings` Table
- `whatsapp_api_version`: v18.0, v17.0, etc.
- `whatsapp_access_token`: API access token
- `whatsapp_phone_number_id`: Phone number ID
- `phone_number`: Display phone number
- `business_name`: Business/company name
- `webhook_url`: Webhook callback URL
- `is_configured`: true if API credentials set

### `user_preferences` Table
- `timezone`: Asia/Karachi, UTC, etc.
- `date_format`: Y-m-d, m/d/Y, d-m-Y
- `time_format`: H:i:s (24h), g:i A (12h)
- `language`: en, ur, ar, etc.
- `email_notifications`: 0 or 1
- `browser_notifications`: 0 or 1

---

## 🛠️ Admin Actions

### Reset Usage
**When to use:** Month end, give user fresh quota
**What it does:**
- Sets `messages_used = 0`
- Sets `current_period_start = now`
- Sets `current_period_end = +30 days`

### Extend Trial
**When to use:** Give user more trial time
**What it does:**
- Sets `status = 'trial'`
- Sets `trial_ends_at = now + X days`

### Bulk Update
**When to use:** Changing plan limits globally
**What it does:**
- Updates `message_limit` for all users on selected plan
- Updates `contact_limit` for all users on selected plan
- Returns count of updated subscriptions

---

## 📍 Navigation

**Admin Menu:**
- 👥 **Users** → User management (create, edit, view profile)
- 💳 **Subscriptions** → Subscription dashboard & bulk actions
- 📋 **Logs** → System logs

**User Profile:**
- Accessed via Users → 👁️ View Profile
- Shows: Account info, API credentials, Preferences, Subscription, Usage logs

---

## 🔄 Workflow: Onboard New Tenant

1. **Create User** (users.php)
   - Enter details
   - Select "Starter" plan (or appropriate tier)
   - Save

2. **Configure API** (user-profile.php)
   - View Profile for new user
   - Edit API Credentials
   - Add access_token, phone_number_id
   - Save

3. **Adjust Subscription** (if needed)
   - Edit Subscription card
   - Change plan, limits, or trial period
   - Save

4. **Monitor Usage** (subscriptions.php)
   - Check message consumption
   - Reset usage if needed
   - Extend trial if needed

---

## 🎨 UI Elements

**Badges:**
- Plan: FREE (gray), STARTER (blue), PROFESSIONAL (orange), ENTERPRISE (green)
- Status: ACTIVE (green), TRIAL (blue), CANCELLED/EXPIRED (red)

**Usage Bars:**
- Green: < 50% usage
- Orange: 50-80% usage
- Red: > 80% usage

**Actions:**
- 👁️ View Profile
- ✏️ Edit
- 🔄 Reset Usage
- ⏰ Extend Trial
- 💾 Save
- 📊 Bulk Update

---

## ✅ What's Working

1. ✅ User creation with automatic subscription provisioning
2. ✅ Edit forms for API credentials, preferences, subscriptions
3. ✅ AJAX updates (no page reload)
4. ✅ Subscription dashboard with stats
5. ✅ Reset usage (billing period reset)
6. ✅ Extend trial (add days to trial)
7. ✅ Bulk plan limit updates
8. ✅ Usage tracking with visual progress bars
9. ✅ Navigation links in admin menu
10. ✅ Auto-creation of user_settings on signup

---

## 🚦 Next Steps (Server Deployment)

1. **Pull latest code:**
   ```bash
   cd /path/to/whatsapp-mailbox-php
   git pull origin main
   ```

2. **Clear OPcache:**
   ```bash
   sudo service php8.1-fpm reload
   # or restart Apache/Nginx
   ```

3. **Test:**
   - Create a test user with subscription
   - Edit user profile
   - Reset usage
   - Extend trial
   - Bulk update plan

---

## 📝 Files Modified/Created

**Modified:**
- `user-profile.php` - Added edit forms + AJAX handlers
- `users.php` - Added subscription plan selector + auto-provisioning
- `includes/header.php` - Added Subscriptions link to admin menu

**Created:**
- `subscriptions.php` - Complete subscription management dashboard

**Committed:**
- Commit: `65ccc3c` - "Add complete subscription management system"
- Pushed to: `main` branch

---

## 🎯 Summary

You now have a complete subscription management system with:
- ✅ Edit capability for all user settings
- ✅ Subscription plan selection during user creation
- ✅ Automatic provisioning (subscription + user_settings)
- ✅ Admin dashboard for bulk operations
- ✅ Usage tracking and limits enforcement
- ✅ Trial management
- ✅ Reset usage + extend trial actions

**Ready to use on server after `git pull`!** 🚀
