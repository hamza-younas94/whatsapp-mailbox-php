# Quick Replies UI Update Guide

The UI needs to be updated to include all 12 advanced features. Here's what needs to be added to the modal form:

## Backend Changes Needed in quick-replies.php:

1. **Update data array in create/update handler** to include:
   - `priority` (integer)
   - `shortcuts` (JSON array - convert from comma-separated or array input)
   - `business_hours_start`, `business_hours_end`, `timezone`, `outside_hours_message`
   - `conditions` (JSON array)
   - `use_regex` (boolean)
   - `delay_seconds` (integer)
   - `media_url`, `media_type`, `media_filename`
   - `excluded_contact_ids`, `included_contact_ids` (JSON arrays)
   - `sequence_messages` (JSON array)
   - `sequence_delay_seconds` (integer)
   - `allow_groups` (boolean)

2. **Fetch tags and contacts** for dropdowns:
   - Add `use App\Models\Tag;` and `use App\Models\Contact;`
   - Fetch tags: `$tags = Tag::orderBy('name')->get();`
   - Fetch contacts: `$contacts = Contact::orderBy('name')->get();`

## UI Form Fields to Add (in modal):

### Basic Section (already exists):
- Shortcut
- Title  
- Message
- Active toggle

### Priority & Matching:
- Priority (number input, default 0)
- Multiple Shortcuts (textarea or multiple inputs, comma-separated)
- Use Regex (checkbox)
- Allow Groups (checkbox)

### Business Hours:
- Enable Business Hours (checkbox)
- Start Time (time input)
- End Time (time input)
- Timezone (select dropdown)
- Outside Hours Message (textarea)

### Conditions:
- Add Condition button
- Dynamic condition builder with:
  - Field (tag, stage, message_count, last_message_days)
  - Operator (equals, contains, greater_than, etc.)
  - Value (input/dropdown based on field)

### Reply Options:
- Delay Seconds (number input, default 0)
- Media Upload (file input)
- Media Type (select: image, document, video)

### Contact Filtering:
- Excluded Contacts (multi-select)
- Included Contacts (multi-select) 

### Sequences:
- Use Sequence (checkbox)
- Sequence Messages (textarea or JSON editor)
- Sequence Delay (number input)

The form should be organized in tabs or collapsible sections for better UX.

