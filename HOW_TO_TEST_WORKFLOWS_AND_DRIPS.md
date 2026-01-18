# 🧪 How to Test Workflows and Drip Campaigns

## 📋 Workflows Testing

### **What are Workflows?**
Workflows automatically perform actions when specific triggers occur. For example:
- **Trigger:** New message received with keyword "hello"
- **Actions:** Send welcome message + Add tag "Interested"

### **How to Test Workflows:**

#### **Option 1: Manual Testing (Recommended)**
1. Go to **Test Workflow** page: `test_workflow.php`
2. Select a workflow from the dropdown
3. Choose trigger type (e.g., "New Message", "Stage Change")
4. Select a test contact
5. Click **"Test Workflow"**
6. See execution results and actions performed

#### **Option 2: Real Event Testing**
Workflows trigger automatically on real events:

**New Message Workflow:**
1. Create a workflow with trigger: "New Message Received"
2. Set keyword condition (e.g., "hello")
3. Send a WhatsApp message with that keyword to your number
4. Workflow will execute automatically!

**Stage Change Workflow:**
1. Create a workflow with trigger: "Stage Change"
2. Set conditions (e.g., from "NEW" to "CONTACTED")
3. In CRM, change a contact's stage manually
4. Workflow will execute automatically!

**Tag Added/Removed Workflow:**
1. Create a workflow with trigger: "Tag Added"
2. Set which tag to listen for
3. Add that tag to a contact
4. Workflow will execute automatically!

### **Checking Workflow Executions:**
- View execution history in `test_workflow.php` (bottom of page)
- Or check `workflow_executions` table in database
- Each execution logs:
  - Which workflow ran
  - Which contact it ran for
  - Status (success/failed)
  - Actions performed
  - Timestamp

---

## 💧 Drip Campaigns Testing

### **What are Drip Campaigns?**
Drip campaigns send a series of messages over time. For example:
- **Step 1:** Welcome message (sends immediately)
- **Step 2:** Product overview (sends 1 hour later)
- **Step 3:** Get started guide (sends 24 hours later)

### **How to Test Drip Campaigns:**

#### **Option 1: Manual Testing (Recommended)**
1. Go to **Test Drip Campaign** page: `test_drip_campaign.php`
2. **Add a Subscriber:**
   - Select a campaign
   - Select a contact
   - Click "Add Subscriber & Start Campaign"
   - First step sends immediately (if delay is 0)
3. **Send Next Steps:**
   - Find the subscriber in the "Active Subscribers" table
   - Click "Send Next Step" when it's due
   - Or wait for automatic processing

#### **Option 2: Automatic Processing**
Drip campaigns process automatically via cron job:

**Setup Cron Job:**
```bash
# Edit crontab
crontab -e

# Add this line (runs every minute):
* * * * * cd /path/to/whatsapp-mailbox && php process_jobs.php >> /path/to/logs/cron.log 2>&1
```

**What Cron Does:**
- Checks for subscribers whose `next_send_at` time has arrived
- Sends the next step in the campaign
- Updates subscriber to next step
- Calculates next send time based on step delay

### **Manual Processing (Testing):**
Run this command to process due drip steps immediately:
```bash
php process_jobs.php
```

### **Checking Drip Campaign Progress:**
- View active subscribers in `test_drip_campaign.php`
- Check `drip_subscribers` table in database
- Each subscriber shows:
  - Current step number
  - Status (active/completed/paused)
  - Next send time
  - Started/completed dates

---

## 🔧 Setup for Automatic Processing

### **1. Set Up Cron Job**
Add to crontab (`crontab -e`):
```bash
* * * * * cd /home/pakmfguk/whatsapp.nexofydigital.com && php process_jobs.php >> /home/pakmfguk/logs/cron.log 2>&1
```

This processes:
- ✅ Scheduled messages
- ✅ Broadcast messages
- ✅ **Drip campaign steps**

### **2. Verify Cron is Running**
```bash
# Check cron log
tail -f /home/pakmfguk/logs/cron.log

# Or check if cron job exists
crontab -l
```

---

## 📊 Example Test Scenarios

### **Workflow Test Scenario:**
1. **Create Workflow:**
   - Name: "Welcome New Contacts"
   - Trigger: "New Message"
   - Condition: Keyword = "hello"
   - Actions:
     - Send Message: "Hello! Thanks for contacting us."
     - Add Tag: "Interested"

2. **Test It:**
   - Go to `test_workflow.php`
   - Select the workflow
   - Select trigger: "New Message"
   - Select a test contact
   - Click "Test Workflow"
   - ✅ Should see: Message sent + Tag added

3. **Real Test:**
   - Send WhatsApp message "hello" to your number
   - Workflow should execute automatically!

### **Drip Campaign Test Scenario:**
1. **Create Campaign:**
   - Name: "Welcome Series"
   - Steps:
     - Step 1: "Welcome" (delay: 0 minutes)
     - Step 2: "Features" (delay: 60 minutes)
     - Step 3: "Get Started" (delay: 1440 minutes = 24 hours)

2. **Test It:**
   - Go to `test_drip_campaign.php`
   - Add a subscriber (select campaign + contact)
   - Step 1 sends immediately
   - Use "Send Next Step" to manually send steps
   - Or wait for cron to process automatically

---

## ⚠️ Important Notes

1. **Workflows** trigger automatically on events (no cron needed for most triggers)
2. **Drip Campaigns** require cron job for automatic processing
3. **Test pages** let you test manually without waiting for events/cron
4. **All executions** are logged in database for tracking

---

## 🔗 Related Files

- **Workflow Execution Logic:** `app/Services/WhatsAppService.php` (executeWorkflow, executeWorkflowAction)
- **Drip Campaign Logic:** `app/Services/WhatsAppService.php` (sendDripCampaignStep)
- **Cron Processor:** `process_jobs.php` (processes drip campaigns)
- **Test Pages:** `test_workflow.php`, `test_drip_campaign.php`

---

**Need Help?** Check the test pages for detailed instructions and execution logs!

