# WhatsApp Mailbox - CRM Features Guide

## 🎉 What's Been Added

Your WhatsApp Mailbox now includes a **complete CRM system** with two integrated views:

### 1. **Enhanced Mailbox** (index.php)
- ✅ Stage badges on contacts (New, Contacted, Qualified, etc.)
- ✅ Lead score indicators (0-100)
- ✅ Company information display
- ✅ CRM action button in chat header
- ✅ Quick access to CRM modal

### 2. **Full CRM Dashboard** (crm_dashboard.php)
- ✅ Statistics cards (Total Contacts, Qualified Leads, Deal Value, Avg Score)
- ✅ Stage pipeline filters
- ✅ Comprehensive contact table with all CRM data
- ✅ Search functionality
- ✅ Direct access to manage CRM details

---

## 📊 CRM Features

### **Stage Management**
Contacts can move through your sales pipeline:
- **New** - Fresh contact
- **Contacted** - Initial outreach made
- **Qualified** - Identified as potential customer
- **Proposal** - Proposal sent
- **Negotiation** - In negotiation phase
- **Customer** - Converted to customer
- **Lost** - Deal lost

### **Lead Scoring (Automatic)**
Smart scoring algorithm (0-100) based on:
- Email provided: +10 points
- Company provided: +15 points
- Message engagement: up to +20 points
- Recent activity: +10 points
- Deal value: up to +20 points
- Stage progression: bonus points

### **Contact Information**
- Company name
- Email address
- City/Location
- Phone number
- Custom tags

### **Deal Tracking**
- Deal value (monetary)
- Expected close date
- Deal currency

### **Notes & Activities**
- Add notes with types: General, Call, Meeting, Email
- Automatic activity logging
- Timeline view of all interactions
- Track last activity date

---

## 🚀 How to Use

### **In Mailbox View (index.php)**

1. **View CRM Data on Contacts:**
   - Stage badges appear next to contact names
   - Lead scores shown as colored circles
   - Company names displayed below contact names

2. **Manage CRM:**
   - Select a contact
   - Click the **CRM button** (clipboard icon) in chat header
   - Modal opens with full CRM management

3. **Update Contact:**
   - Change stage → Select from dropdown
   - Update company info → Fill in company, email, city
   - Add deal info → Enter deal value and expected close date
   - Add notes → Write note and select type

### **In CRM Dashboard (crm_dashboard.php)**

1. **View Statistics:**
   - Top cards show key metrics
   - Auto-updates every 10 seconds

2. **Filter by Stage:**
   - Click stage buttons (All, New, Contacted, etc.)
   - Table updates to show only selected stage

3. **Search Contacts:**
   - Search by name, phone, company, or email
   - Real-time filtering

4. **Manage Contacts:**
   - Click **Edit** button (pencil icon) to open CRM modal
   - Click **Message** button to go to mailbox conversation

---

## 🔄 Navigation

Top navigation bar appears on both pages:
- **Mailbox** - WhatsApp conversations view
- **CRM Dashboard** - Full CRM analytics view

Switch between views anytime with one click!

---

## 📥 Deployment Instructions

### **Upload New Files:**
Upload these new files to your server:

```
/crm_dashboard.php
/templates/crm_dashboard.html.twig
/assets/js/crm.js
```

### **Updated Files:**
Replace these files with updated versions:

```
/templates/dashboard.html.twig
/assets/js/app.js
/assets/css/style.css
```

### **Via Git (Recommended):**
```bash
# SSH into your server
ssh your_username@whatsapp.nexofydigital.com

# Navigate to project
cd public_html

# Pull latest changes
git pull origin main
```

### **Via FTP:**
1. Download all files from GitHub repository
2. Upload to your Namecheap hosting via FTP
3. Ensure file permissions are correct (644 for files, 755 for directories)

---

## ✅ Testing Checklist

After deployment, test these features:

### **Mailbox View:**
- [ ] Open index.php - see stage badges on contacts
- [ ] See lead scores (colored circles with numbers)
- [ ] See company names below contact names
- [ ] Select contact - see CRM info in header
- [ ] Click CRM button - modal opens
- [ ] Change stage - updates successfully
- [ ] Add company info - saves correctly
- [ ] Add deal value - updates properly
- [ ] Add note - appears in list
- [ ] Close modal - data persists

### **CRM Dashboard:**
- [ ] Open crm_dashboard.php
- [ ] See 4 statistics cards with correct numbers
- [ ] Table shows all contacts with CRM data
- [ ] Click stage filters - table updates
- [ ] Search functionality works
- [ ] Click Edit button - modal opens
- [ ] Click Message button - goes to mailbox
- [ ] Update data in modal - reflects in table

### **API Endpoints:**
Test these URLs:
- `GET /api.php/contacts` - Should include CRM fields
- `PUT /crm.php/contact/{id}/crm` - Updates CRM data
- `POST /crm.php/contact/{id}/note` - Adds note
- `GET /crm.php/contact/{id}/notes` - Lists notes
- `GET /crm.php/stats` - Shows statistics

---

## 🎨 CRM Stage Colors

Visual reference for stage badges:
- **New**: Blue (#1976d2)
- **Contacted**: Purple (#7b1fa2)
- **Qualified**: Orange (#f57c00)
- **Proposal**: Green (#388e3c)
- **Negotiation**: Yellow (#f9a825)
- **Customer**: Dark Green (#2e7d32)
- **Lost**: Red (#c62828)

---

## 🔧 Troubleshooting

### **CRM modal not opening:**
- Check browser console for JavaScript errors
- Ensure `crm.php` API endpoint is accessible
- Verify session is authenticated

### **Stage badges not showing:**
- Check if `api.php/contacts` returns CRM fields
- Verify database has CRM columns (run `php check_database.php`)
- Clear browser cache

### **Notes not loading:**
- Check `crm.php/contact/{id}/notes` endpoint
- Verify notes table exists in database
- Check server error logs in `storage/logs/app.log`

### **Statistics showing zeros:**
- Ensure contacts have CRM data populated
- Check `crm.php/stats` endpoint response
- Verify lead_score, deal_value fields are not null

---

## 📝 Database Schema

CRM fields added to `contacts` table:
```sql
- stage (varchar)
- lead_score (integer 0-100)
- assigned_to (integer, foreign key to admin_users)
- source (varchar)
- company_name (varchar)
- email (varchar)
- city (varchar)
- country (varchar)
- tags (text, JSON)
- last_activity_at (datetime)
- last_activity_type (varchar)
- deal_value (decimal)
- deal_currency (varchar)
- expected_close_date (date)
- custom_fields (text, JSON)
```

New tables:
- **notes**: Contact notes with type and creator
- **activities**: Activity timeline with metadata

---

## 🚀 Next Steps

### **Recommended Enhancements:**
1. **Automation:**
   - Auto-move contacts based on message count
   - Auto-score based on engagement
   - Scheduled reminders for follow-ups

2. **Reporting:**
   - Export contacts by stage
   - Deal pipeline analytics
   - Conversion rate tracking

3. **Integrations:**
   - Email integration for notes
   - Calendar sync for meetings
   - Webhook notifications for stage changes

4. **Team Features:**
   - Assign contacts to team members
   - Activity notifications
   - Permission levels

---

## 📞 Support

If you encounter any issues:

1. Check `storage/logs/app.log` for errors
2. Run `php check_database.php` to verify schema
3. Test API endpoints with Postman or curl
4. Clear browser cache and reload

---

**Your CRM system is ready! 🎉**

Transform your WhatsApp conversations into a complete sales pipeline with lead tracking, notes, and deal management.
