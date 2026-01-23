# Quick Message Type Testing Guide

## How to Test All Message Types

This guide provides quick steps to verify each WhatsApp message type is rendering correctly in your mailbox.

### Quick Test Sequence

Send these messages from a WhatsApp contact to your mailbox number:

```
1. Text: "Hello, this is a test"

2. Image: Send any photo with caption "Test image"

3. Video: Send a short video clip

4. Audio: Send a voice message (hold microphone)

5. Document: Send a PDF or Word file

6. Location: Send a live location or saved location

7. Contact: Forward someone's contact card

8. Sticker: Send any sticker from sticker pack

9. Reaction: Reply to one of the messages with an emoji reaction (long-press on mobile)

10. Button Message: (If you have template access) Send a button template message

11. List Message: Send a list selection message

12. Location Reaction: Send emoji reaction to a location message
```

### Expected UI Output

| Message Type | Expected Rendering | Icon | Background |
|--------------|-------------------|------|-----------|
| **Text** | Plain message text | - | White |
| **Image** | Thumbnail image with caption | 🖼️ | Natural |
| **Video** | Video player with play button | 🎥 | Black player |
| **Audio** | Audio player with timeline | 🎵 | Gray player |
| **Document** | Download link with filename | 📄 | White |
| **Location** | Map preview + coordinates | 📍 | Map image |
| **Contact** | Card with name/phone | 👤 | White card |
| **Sticker** | Sticker image display | - | Transparent |
| **Reaction** | Emoji in beige pill | Emoji | Beige box |
| **Button** | Colored box with button title | 🔘 | Blue (#dbeafe) |
| **List** | Styled list box | 📋 | Purple (#f5f3ff) |
| **Template** | Template info box | 📋 | Yellow (#fef3c7) |
| **Order** | Order info box | 🛒 | Green (#dcfce7) |
| **Ephemeral** | View-once notice | 👁️ | Pink (#fce7f3) |
| **System** | Group event notification | ℹ️ | Blue (#dbeafe) |

### Browser DevTools Inspection

For each message, inspect the DOM in Chrome DevTools (F12):

1. Open Developer Tools in your mailbox
2. Right-click on the message → Inspect Element
3. Verify the expected HTML structure exists:

```html
<!-- Text Message -->
<div class="message-text">Hello, this is a test</div>

<!-- Image Message -->
<img src="media_url" alt="Image" style="...">

<!-- Location Message -->
<img src="https://maps.googleapis.com/maps/api/staticmap?..." alt="Location">

<!-- Sticker Message -->
<img src="media_url" alt="Sticker" style="...">

<!-- Reaction Message -->
<div class="message-text" style="...">❤️</div>

<!-- System Message -->
<div class="message-text" style="...">ℹ️ Group invite link created</div>

<!-- Unsupported Message -->
<div class="message-text" style="...">⚠️</div>
```

### Console Logging

Check browser console for any errors:

1. Open DevTools Console (F12 → Console tab)
2. Send test messages
3. Watch for errors - should be empty

Expected log output (if logging is enabled):
```
[renderMessages] Processing 15 messages
[renderMessages] Processing message type: text
[renderMessages] Processing message type: image
[renderMessages] Processing message type: location
...
```

### Network Inspection

For media messages, check Network tab:

1. Open DevTools → Network tab
2. Send image/video/audio/document
3. You should see requests to:
   - `media_url` (media file download)
   - `maps.googleapis.com` (for location maps)

### Testing Groups & System Messages

To test system messages:

1. Create a new WhatsApp group
2. Add a contact to the group (generates "➕ Participant added")
3. Remove that contact (generates "➖ Participant removed")
4. Share group invite link (generates "📞 Group invite link created")

These system messages should show in your mailbox with proper icons and blue background.

### Performance Testing

Monitor performance while receiving messages:

1. Send 10 messages of different types rapidly
2. Check browser DevTools → Performance tab
3. Look for smooth rendering (60 FPS target)
4. No memory leaks in Console

### Troubleshooting

**Image/Video/Audio not showing?**
- Check Network tab for failed media downloads
- Verify WhatsApp API token has media access
- Check `media_url` in browser console

**Location map not showing?**
- Verify Google Maps static API is accessible
- Check that latitude/longitude are valid
- Look for CORS errors in console

**Message won't render at all?**
- Check browser console for JS errors
- Verify message type detection in `renderMessages()`
- Look at raw message payload in Network tab

**Styling looks wrong?**
- Clear browser cache (Ctrl+Shift+Delete)
- Hard refresh (Ctrl+F5 or Cmd+Shift+R)
- Check CSS media query rules

### Automated Testing (Optional)

For automated testing, you can post webhook payloads:

```bash
# Test text message
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "entry": [{
      "changes": [{
        "value": {
          "messages": [{
            "from": "1234567890",
            "id": "msg_1",
            "timestamp": "'$(date +%s)'",
            "type": "text",
            "text": {"body": "Test message"}
          }]
        }
      }]
    }]
  }'

# Test image message
curl -X POST http://localhost:8000/api.php \
  -H "Content-Type: application/json" \
  -d '{
    "entry": [{
      "changes": [{
        "value": {
          "messages": [{
            "from": "1234567890",
            "id": "msg_2",
            "timestamp": "'$(date +%s)'",
            "type": "image",
            "image": {
              "id": "media_id_123",
              "mime_type": "image/jpeg"
            }
          }]
        }
      }]
    }]
  }'
```

### Success Criteria

✅ All 18 message types render without errors
✅ Each type has distinctive visual styling
✅ Media downloads work (images, videos, audio, documents)
✅ Location maps display correctly
✅ No console errors
✅ Smooth performance with multiple messages
✅ System messages show correctly in groups
✅ Reactions display as emoji
✅ Interactive messages show styled boxes

---

**Last Updated**: Session 2024
**Tested With**: Chrome, Firefox, Safari
