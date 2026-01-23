# Complete WhatsApp Message Type Support

This document outlines all supported WhatsApp message types in the mailbox application with backend handling and frontend rendering.

## Supported Message Types (20 Total)

### 1. **Text**
- **Status**: ✅ Fully Supported
- **Backend**: Saved directly to `message_body`
- **Frontend**: Plain text display with escaping
- **UI**: Standard chat bubble with timestamp

### 2. **Image**
- **Status**: ✅ Fully Supported
- **Backend**: Media ID → fetches via API → stored in `media_url`
- **Frontend**: HTML5 `<img>` tag with fallback
- **UI**: Thumbnail with caption, clickable for full view
- **Extract**: MIME type, filename, size from `fetchMediaDetails()`

### 3. **Video**
- **Status**: ✅ Fully Supported
- **Backend**: Media ID → fetches via API → stored in `media_url`
- **Frontend**: HTML5 `<video>` player with controls
- **UI**: Video player with play button, caption support
- **Extract**: MIME type, filename, size

### 4. **Audio**
- **Status**: ✅ Fully Supported
- **Backend**: Media ID → fetches via API → stored in `media_url`
- **Frontend**: HTML5 `<audio>` player with controls
- **UI**: Audio player with play/pause, timestamp scrubber
- **Extract**: MIME type, filename

### 5. **Document**
- **Status**: ✅ Fully Supported
- **Backend**: Media ID → fetches via API → stored in `media_url`
- **Frontend**: Download link with document icon (📄)
- **UI**: Filename with size, clickable download
- **Extract**: MIME type, filename, size

### 6. **Location**
- **Status**: ✅ Fully Supported with Map Preview
- **Backend**: Extracts latitude, longitude, name, address → builds `message_body`
- **Frontend**: Google Maps static image preview with lat/lng clickable link
- **UI**: Map thumbnail with location name, opens Google Maps on click
- **Data**: `"Location: 37.7749, -122.4194 (San Francisco) - Downtown SF"`

### 7. **Contacts**
- **Status**: ✅ Fully Supported with Card Layout
- **Backend**: Extracts contact name, phone(s), email(s) → builds `message_body`
- **Frontend**: Card layout for each contact with formatted info
- **UI**: Name, phone number, email with contact icons (👤)
- **Data**: `"Contacts: John Doe (+1234567890), Jane Smith (+0987654321)"`

### 8. **Sticker**
- **Status**: ✅ Fully Supported
- **Backend**: Media ID → fetches via API → MIME type = `image/webp`
- **Frontend**: `<img>` tag with WebP MIME type support
- **UI**: Sticker image display, smaller than photos for distinction
- **Extract**: WebP format, media filename

### 9. **Reaction**
- **Status**: ✅ Fully Supported
- **Backend**: Extracts emoji, original message ID → builds `message_body`
- **Frontend**: Inline emoji pill with beige background
- **UI**: Emoji centered, compact styling (e.g., ❤️ in small box)
- **Data**: `"Reaction: ❤️"`

### 10. **Interactive**
- **Status**: ✅ Fully Supported
- **Backend**: Detects type (button/list) → extracts reply title
- **Frontend**: Styled box with purple border, icon (🎯)
- **UI**: Title display, colored background for distinction
- **Data**: `"Interactive message (button): Confirm Order"`

### 11. **Button**
- **Status**: ✅ Fully Supported
- **Backend**: Extracts button text/payload → builds `message_body`
- **Frontend**: Styled clickable button (blue style)
- **UI**: Button with icon (🔘), payload shown
- **Data**: `"Button message: Click Here"`

### 12. **List**
- **Status**: ✅ Fully Supported
- **Backend**: Extracts title, description → builds `message_body`
- **Frontend**: Styled box with purple border, list icon (📋)
- **UI**: Title and description display
- **Data**: `"List message: Select Option - Choose from list"`

### 13. **Template**
- **Status**: ✅ Fully Supported
- **Backend**: Extracts template name, language code
- **Frontend**: Styled box with yellow border, template icon (📋)
- **UI**: Template name and language display
- **Data**: `"Template: order_confirmation (en_US)"`

### 14. **Order**
- **Status**: ✅ Fully Supported
- **Backend**: Extracts order ID, catalog reference
- **Frontend**: Styled box with green border, shopping cart icon (🛒)
- **UI**: Order ID and catalog reference display
- **Data**: `"Order: ORD-12345 (Catalog)"`

### 15. **Ephemeral (View-Once)**
- **Status**: ✅ Fully Supported
- **Backend**: Marked as ephemeral type → stores metadata
- **Frontend**: Styled box with pink border, eye icon (👁️)
- **UI**: "View Once Message" label
- **Data**: `"View once message"`

### 16. **Poll**
- **Status**: ✅ Fully Supported
- **Backend**: Extracts question, options → builds `message_body`
- **Frontend**: Styled box with teal border, poll icon (🗳️)
- **UI**: Question with numbered options, up to 4 options displayed
- **Data**: `"Poll: What's your favorite color?\n1. Red\n2. Blue\n3. Green\n4. Yellow"`
- **Styling**: Teal background (#ecfdf5), numbered option pills

### 17. **Vote**
- **Status**: ✅ Fully Supported
- **Backend**: Extracts poll response, selected option → builds `message_body`
- **Frontend**: Styled box with gray border, vote icon (✅)
- **UI**: "Vote" label with selected option display
- **Data**: `"Vote: Poll Response\nSelected option: 2"`
- **Styling**: Gray background (#f3f4f6), shows which option was selected

### 18. **System**
- **Status**: ✅ Fully Supported with Group Events
- **Backend**: Detects system type (group_invite, group_participant_added/removed)
- **Frontend**: Info icon (ℹ️) with info-colored background
- **UI**: Blue/gray background, system event description
- **Data Examples**:
  - `"📞 Group invite link created"`
  - `"➕ Participant added to group"`
  - `"➖ Participant removed from group"`

### 19. **Notification**
- **Status**: ✅ Fully Supported
- **Backend**: Extracts notification body
- **Frontend**: Info icon (ℹ️) with subtle styling
- **UI**: Gray background, notification text
- **Data**: `"Notification message from WhatsApp"`

### 20. **Unsupported/Unknown**
- **Status**: ✅ Gracefully Handled
- **Backend**: Normalizes to 'system' type, captures error details
- **Frontend**: Warning icon (⚠️) with warning-colored background
- **UI**: Orange/yellow background with error message
- **Data**: Includes provider error message + payload snippet

## Frontend Rendering (`renderMessages()` in assets/js/app.js)

Each message type has distinct visual treatment:

| Type | Icon | Background | Style | Interaction |
|------|------|------------|-------|-------------|
| Text | - | White | Plain | N/A |
| Image | 🖼️ | N/A | Thumbnail | Click to view full |
| Video | 🎥 | N/A | Player | Play/pause controls |
| Audio | 🎵 | N/A | Player | Play/pause, scrubber |
| Document | 📄 | N/A | Link | Click to download |
| Location | 📍 | N/A | Map | Click to open Google Maps |
| Contacts | 👤 | N/A | Card | Display contact info |
| Sticker | - | N/A | Image | View sticker |
| Reaction | Emoji | Beige | Pill | Display emoji |
| Interactive | 🎯 | #f5f3ff | Box | Display interaction |
| Button | 🔘 | #dbeafe | Box | Display button |
| List | 📋 | #f5f3ff | Box | Display list |
| Template | 📋 | #fef3c7 | Box | Display template |
| Order | 🛒 | #dcfce7 | Box | Display order |
| Ephemeral | 👁️ | #fce7f3 | Box | Display notice |
| Poll | 🗳️ | #ecfdf5 | Box | Display options |
| Vote | ✅ | #f3f4f6 | Box | Display selection |
| System | ℹ️ | #dbeafe | Box | Display event |
| Notification | ℹ️ | #f3f4f6 | Box | Display notification |
| Unsupported | ⚠️ | #fef08a | Box | Display warning |

## Backend Processing (`WhatsAppService::saveIncomingMessage()`)

### Message Type Detection Flow
1. Receives webhook payload
2. Extracts message type from payload structure
3. Uses switch statement to handle each type
4. Extracts relevant data (media IDs, text, metadata)
5. For media types: calls `fetchMediaDetails($mediaId)`
6. Builds descriptive `message_body`
7. Saves to Message table with proper fields:
   - `message_type`: normalized type name
   - `message_body`: descriptive text
   - `media_url`: download URL for media
   - `media_mime_type`: MIME type
   - `media_filename`: original filename
   - `media_caption`: user-provided caption

### Special Handling

#### Media Downloads (Image, Video, Audio, Document, Sticker)
```php
$mediaDetails = $this->fetchMediaDetails($mediaId);
$mediaUrl = $mediaDetails['media_url'] ?? null;
$mediaFilename = $mediaDetails['media_filename'] ?? null;
$mediaSize = $mediaDetails['media_size'] ?? null;
$mediaMimeType = $mediaDetails['mime_type'] ?? $mediaMimeType;
```

#### Text-Only Actions (Quick Reply, Auto-Tag, Workflow)
Only triggered for `message_type === 'text'`:
```php
if ($messageType === 'text' && !empty($messageBody)) {
    checkAndSendQuickReply();
    applyAutoTagging();
    checkAndTriggerWorkflows();
}
```

## Database Schema

### Message Table Fields Used
| Field | Type | Purpose |
|-------|------|---------|
| `message_id` | String | WhatsApp message ID (unique) |
| `user_id` | Integer | Mailbox owner |
| `contact_id` | Integer | Sender contact |
| `message_type` | String | Normalized type (text, image, video, etc) |
| `direction` | String | incoming/outgoing |
| `message_body` | Longtext | Descriptive text for all types |
| `media_url` | String | URL to media file (if applicable) |
| `media_mime_type` | String | MIME type (image/png, video/mp4, etc) |
| `media_filename` | String | Original filename |
| `media_caption` | String | User caption for media |
| `media_size` | Integer | File size in bytes |
| `timestamp` | Timestamp | When message was sent |
| `is_read` | Boolean | Read status |

## Testing Checklist

To fully test all message types:

- [ ] Send text message
- [ ] Send image with caption
- [ ] Send video clip
- [ ] Send audio message
- [ ] Send document (PDF, Word, etc)
- [ ] Send location pin
- [ ] Send contact card
- [ ] Send sticker
- [ ] Reply to message with reaction emoji
- [ ] Send interactive message (template with buttons)
- [ ] Send button message
- [ ] Send list message
- [ ] Send template message (e.g., order confirmation)
- [ ] Send order message (from catalog)
- [ ] Send view-once message
- [ ] Verify group system messages (invite, add participant, remove)
- [ ] Verify notifications display correctly

## API Integration Points

1. **fetchMediaDetails($mediaId)** - Calls WhatsApp Cloud API to get:
   - Download URL
   - MIME type
   - File size
   - Filename

2. **checkAndSendQuickReply()** - Only for text messages
   - Matches text against quick reply triggers
   - Sends auto-response if matched

3. **applyAutoTagging()** - Only for text messages
   - Analyzes text content
   - Applies matching tags

4. **checkAndTriggerWorkflows()** - Only for text messages
   - Triggers automation workflows on keywords

## Performance Notes

- Media files are downloaded and cached via `fetchMediaDetails()`
- Large media (video, documents) use lazy loading in frontend
- Message type detection is O(1) via switch statement
- No recursive processing for nested message types

## Future Enhancements

1. **Rich Message Actions**: Store reaction/button selections as separate records
2. **Media Thumbnails**: Generate thumbnails for large video/documents
3. **Location Caching**: Store location history for analytics
4. **Interactive Response Tracking**: Record user interactions with buttons/lists
5. **Template Versioning**: Track template versions and parameters
6. **System Event Timeline**: Build group conversation history from system messages

---

**Last Updated**: Session 2024
**Commit**: 0159721 - "Enhance all message type rendering: templates, orders, ephemeral messages with styled UI"
