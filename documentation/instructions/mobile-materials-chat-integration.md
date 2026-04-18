# Mobile App Integration Guide — Materials System & Enhanced Chat

This guide covers all the new API endpoints and behaviors the Flutter app needs to integrate after the Materials system, Google Drive upload flow, generic Chat, and support-chat features were implemented on the backend.

**Base URL:** `https://runways7.com/api/v1` (production) or `http://localhost:8000/api/v1` (local dev)

**Auth:** All endpoints require `Authorization: Bearer {sanctum_token}` unless stated otherwise.

---

## Table of Contents

1. [What's new — overview](#whats-new)
2. [Materials system](#materials)
   - List materials
   - Upload files directly to Google Drive (resumable)
   - Confirm upload
   - Status flows
   - Bio content
   - Moodboard responses
   - Confirm / Observe collaborative materials
3. [Enhanced chat](#chat)
   - List conversations (now includes support/material chats)
   - Start a support chat with Operations (new)
   - Send messages
   - Mark as read
4. [Profile API changes](#profile)
5. [Notifications behavior](#notifications)
6. [Implementation checklist](#checklist)

---

<a id="whats-new"></a>

## 1. What's new — overview

- **Materials screen** for designers with 10 predefined materials (Background, Music, Images, Runway Logo, Bio, Hair Mood Board, Makeup Mood Board, Brand Logo, Designer Photo, Artworks)
- **Direct-to-Google-Drive upload** for all material files (bypasses the backend; handles files up to 15GB)
- **Two status flows:** collaborative (pending → in_progress → completed → confirmed/observed) and simple (pending → completed)
- **Bio content** is now part of materials (no longer a field on designer profile)
- **Moodboard responses:** designer writes text responses per moodboard image
- **Observe action:** designer rejects a collaborative material and the backend creates/reuses a chat with Operations
- **Support chat:** any external user (designer, model, media, volunteer) can start a chat with Operations from the app
- **Generic chat:** conversations now support any pair of users — the Flutter app only needs to list and navigate, no need to create them explicitly (except for support)

---

<a id="materials"></a>

## 2. Materials system

The Materials screen is only for users with `role: "designer"`. It shows the 10 materials for the designer's event.

### 2.1 List materials

```
GET /api/v1/my-materials?event_id={event_id}
```

Query params:

| Param | Type | Required | Description |
|---|---|---|---|
| `event_id` | int | optional | Filter materials for a specific event. If omitted, returns materials from all designer's events. |

**Response 200:**

```json
{
  "materials": [
    {
      "id": 101,
      "name": "Background",
      "description": "Background video or image for the runway display",
      "status": "pending",
      "status_flow": "collaborative",
      "upload_by": "operation",
      "is_readonly": false,
      "drive_folder_url": "https://drive.google.com/drive/folders/abc123",
      "order": 1,
      "files": [
        {
          "id": 501,
          "file_name": "background_final.mp4",
          "file_type": "video",
          "file_size": 15728640,
          "drive_url": "https://drive.google.com/file/d/xyz/view",
          "note": null,
          "is_final": true,
          "created_at": "2026-04-15T14:30:00Z"
        }
      ]
    },
    {
      "id": 105,
      "name": "Bio",
      "status": "pending",
      "status_flow": "simple",
      "upload_by": "designer",
      "files": [],
      "bio": {
        "biography": "Designer María...",
        "collection_description": "The collection...",
        "additional_notes": null,
        "contact_info": "maria@example.com"
      }
    },
    {
      "id": 106,
      "name": "Hair Mood Board",
      "status": "pending",
      "status_flow": "simple",
      "upload_by": "operation",
      "files": [],
      "moodboard_items": [
        {
          "id": 301,
          "image_name": "reference_1.jpg",
          "drive_url": "https://drive.google.com/file/d/hair1/view",
          "response_text": null,
          "responded_at": null
        }
      ]
    }
  ],
  "deadline": "2026-05-01",
  "drive_url": "https://drive.google.com/drive/folders/rootFolder123"
}
```

**Notes:**

- The `status_flow` field tells you how to treat the UI:
  - `collaborative` — Background, Music → show Confirm/Observe buttons when `status === 'completed'`
  - `simple` — All others → just show upload/delete
- `upload_by`:
  - `designer` — the user can upload
  - `operation` — only operations can upload (show as read-only)
  - `tickets` — Tickets team uploads artworks
- `is_readonly` — if `true`, hide the upload button entirely
- `deadline` — if set and passed, the backend rejects uploads with 422. Display a warning on the UI when approaching/passed.
- Materials that should never have files:
  - `Bio` — use the `bio` field instead
  - `Hair Mood Board` / `Makeup Mood Board` — use `moodboard_items`
  - `Runway Logo` — read-only for designers

### 2.2 Upload files directly to Google Drive (resumable upload)

This is a 3-step flow. The backend never touches the file — it goes from the phone straight to Google Drive. This handles large files (15GB videos) without putting load on the server.

#### Step 1 — Request a resumable upload URL

```
POST /api/v1/materials/{material_id}/upload-url

Body:
{
  "file_name": "my_video.mp4",
  "mime_type": "video/mp4"
}
```

**Response 200:**

```json
{
  "upload_url": "https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&upload_id=ABC123..."
}
```

Possible errors:

- `422` — `{"message": "No Drive folder configured."}` — backend config issue, report to backend dev
- `422` — `{"message": "Upload deadline has passed."}` — the event deadline passed; hide upload UI
- `403` — material doesn't belong to the authenticated designer

#### Step 2 — Upload the file directly to Google Drive

Use the `upload_url` from step 1. Send the raw bytes of the file with a PUT request. This does NOT need authentication headers (the URL itself contains an `upload_id` token).

**Flutter example with Dio:**

```dart
Future<String?> uploadToDrive(String uploadUrl, File file, String mimeType) async {
  try {
    final bytes = await file.readAsBytes();
    final response = await Dio().put(
      uploadUrl,
      data: bytes,
      options: Options(
        headers: {'Content-Type': mimeType},
      ),
      onSendProgress: (sent, total) {
        final pct = (sent / total * 100).toStringAsFixed(0);
        debugPrint('Upload progress: $pct%');
      },
    );
    // Google Drive returns the file metadata including the id
    return response.data['id'] as String?;
  } catch (e) {
    debugPrint('Drive upload failed: $e');
    return null;
  }
}
```

**Important:**

- The content-type header must match what you sent in step 1's `mime_type`.
- Do NOT send any Authorization header — the upload URL already contains the auth token.
- The response is JSON with `id`, `name`, `webViewLink` etc. Save the `id` for step 3.
- For large files, consider using chunked upload (Drive supports it on the same URL). See [Google's resumable upload docs](https://developers.google.com/drive/api/guides/manage-uploads#resumable).

#### Step 3 — Confirm the upload with the backend

Once the upload finishes, tell the backend so it can save the Drive file ID in the database.

```
POST /api/v1/materials/{material_id}/upload-complete

Body:
{
  "drive_file_id": "1AbCdEf...",
  "file_name": "my_video.mp4",
  "file_type": "video",
  "mime_type": "video/mp4",
  "file_size": 15728640,
  "note": "Final version"
}
```

Field details:

- `drive_file_id` — the `id` returned by step 2
- `file_type` — one of `image`, `video`, `audio`, `document`, `url`
- `note` — optional, max 1000 chars

**Response 201:**

```json
{
  "message": "File uploaded.",
  "file": {
    "id": 502,
    "material_id": 101,
    "drive_file_id": "1AbCdEf...",
    "drive_url": "https://drive.google.com/file/d/1AbCdEf/view",
    "file_name": "my_video.mp4",
    "file_type": "video",
    ...
  }
}
```

**Side effects:**

- Material status auto-updates:
  - If `status === 'pending'` and `status_flow === 'collaborative'` → becomes `in_progress`
  - If `status === 'pending'` and `status_flow === 'simple'` → becomes `completed`
- All Operation users receive a push notification about the upload.

### 2.3 Status flows

#### Collaborative flow (Background, Music)

```
pending → in_progress → completed ──┬→ confirmed
                                    └→ observed → (Operation fixes it) → in_progress/completed again
```

- `pending` — nothing uploaded yet
- `in_progress` — someone is working on it (auto-set after upload)
- `completed` — Operation finished, waiting for designer to review
- `confirmed` — designer approved
- `observed` — designer rejected with a note; chat is opened with Operations

**UI rules for the designer:**

- When `status === 'completed'`, show two buttons: **Confirm** and **Observe**
- Confirm calls `POST /api/v1/materials/{id}/confirm`
- Observe requires a text reason (shows a modal with a text input)

#### Simple flow (all others)

```
pending → completed
```

Auto-transitions when content is uploaded. No further action needed.

### 2.4 Save Bio content

```
PUT /api/v1/materials/{material_id}/bio

Body:
{
  "biography": "Short biography including background, inspiration...",
  "collection_description": "About the collection...",
  "additional_notes": "Optional notes",
  "contact_info": "Email, phone, preferred contact method"
}
```

All fields are optional (nullable). Max 5000 chars each (contact_info is 2000).

**Response 200:** `{"message": "Bio saved."}`

**Side effect:** If any field is filled and the material status is `pending`, it auto-transitions to `completed`.

**Important:** The old `bio` field in `PUT /api/v1/profile` has been removed for designers. All bio content lives here now.

### 2.5 Respond to a moodboard image

For Hair Mood Board and Makeup Mood Board: Operations uploads images; the designer writes a text response per image.

```
POST /api/v1/materials/{material_id}/moodboard-respond

Body:
{
  "item_id": 301,
  "response_text": "I love this style — let's do variant with warmer tones."
}
```

**Response 200:** `{"message": "Response saved."}`

**Side effect:** When all moodboard items have `responded_at !== null`, the material auto-transitions to `completed`.

### 2.6 Confirm a collaborative material

```
POST /api/v1/materials/{material_id}/confirm
```

No body required.

**Response 200:** `{"message": "Material confirmed."}`

Errors:

- `422` — material is not `completed` yet or status_flow is not `collaborative`

### 2.7 Observe a collaborative material

```
POST /api/v1/materials/{material_id}/observe

Body:
{
  "note": "The logo looks too pixelated at the top-left corner."
}
```

**Response 200:** `{"message": "Observation sent to Operations."}`

**Side effects:**

- Material status → `observed`
- A conversation with Operations is created or reused
- The observation note is sent as a chat message prefixed with `[Material Name]`
- Operations receives a push notification

After observing, the designer should see the open chat with Operations in their Chats screen. Consider navigating the user there after a successful observe.

---

<a id="chat"></a>

## 3. Enhanced chat

The chat system now supports **any pair of users**, not just designer↔model. This enables:

- Support chats: external users ↔ Operations
- Material observation chats: designer ↔ Operations
- Internal chats: any staff role can start chats with external users

### 3.1 List conversations

```
GET /api/v1/chat/conversations
```

No body.

**Response 200:**

```json
{
  "data": [
    {
      "id": 123,
      "status": "active",
      "context_type": "casting",
      "other_participant": {
        "id": 45,
        "name": "Maria Gonzalez",
        "profile_picture": "models/45/photo.jpg",
        "role": "designer"
      },
      "show": {
        "id": 78,
        "name": "Show A - Day 1"
      },
      "last_message": {
        "body": "Hi, are you ready?",
        "type": "text",
        "sender_id": 45,
        "created_at": "2026-04-16T14:00:00Z"
      },
      "unread_count": 2,
      "last_message_at": "2026-04-16T14:00:00Z"
    },
    {
      "id": 124,
      "status": "active",
      "context_type": null,
      "other_participant": {
        "id": 10,
        "name": "Katie Corrales",
        "profile_picture": null,
        "role": "operation"
      },
      "last_message": null,
      "unread_count": 0,
      "last_message_at": null
    }
  ]
}
```

Fields:

- `context_type`:
  - `"casting"` — designer↔model chat tied to a show
  - `null` — general chat (support, material observation, admin-initiated)
  - (In the future: `"material"` may appear for material-specific threads)
- `show` is only present when `context_type === "casting"`
- `other_participant.role` — use to show a role badge in the UI (Model / Designer / Operation / Tickets / etc.)

**UI recommendations:**

- Sort by `last_message_at` desc (server already does this)
- Show a badge with `unread_count` if > 0
- If `role === 'operation'`, show a special badge like "Support" or "Operations"

### 3.2 Start a support chat with Operations (NEW)

This is the only "create conversation" endpoint the app needs. It's restricted to external users.

```
POST /api/v1/chat/conversations/support

Body (optional):
{
  "message": "Hi, I have a question about my shipment."
}
```

**Who can use this:** Only users with `role` in `["designer", "model", "media", "volunteer"]`.

**Behavior:**

- Finds an available Operations user automatically
- Reuses an existing active conversation if one already exists between the user and any operation agent — no duplicates
- If `message` is provided, it's sent as the first message

**Response 201:**

```json
{
  "conversation": {
    "id": 125,
    "status": "active",
    "context_type": null,
    "other_participant": {
      "id": 10,
      "name": "Katie Corrales",
      "profile_picture": null,
      "role": "operation"
    },
    "last_message_at": "2026-04-16T15:30:00Z"
  }
}
```

Errors:

- `403` — user role is not allowed
- `503` — no operations agents available

**UI suggestion:** Add a "Contact Support" button in the profile/settings or as a floating action in the Chats screen. Tap it → call this endpoint → navigate to the conversation using `conversation.id`.

### 3.3 Get messages in a conversation

```
GET /api/v1/chat/conversations/{conversation_id}
```

Returns paginated messages (50 per page, newest first).

**Response 200:**

```json
{
  "data": [
    {
      "id": 5001,
      "conversation_id": 123,
      "sender_id": 45,
      "body": "Hi, are you ready?",
      "type": "text",
      "image_url": null,
      "is_read": false,
      "read_at": null,
      "created_at": "2026-04-16T14:00:00Z",
      "sender": {
        "id": 45,
        "first_name": "Maria",
        "last_name": "Gonzalez",
        "profile_picture": "..."
      }
    }
  ],
  "current_page": 1,
  "last_page": 3,
  ...
}
```

### 3.4 Send a message

```
POST /api/v1/chat/conversations/{conversation_id}/messages

Body:
{
  "body": "Hello!",
  "type": "text"
}
```

For images:

```json
{
  "body": "",
  "type": "image",
  "image_url": "https://..."
}
```

Note: image uploads for chat are not yet implemented on the backend. If you need image chat uploads, coordinate with the backend dev. For now, `type: "text"` is what you'll use.

**Response 201:** the message object.

### 3.5 Mark as delivered (NEW — Phase 1 ticks)

```
POST /api/v1/chat/conversations/{conversation_id}/delivered
```

No body. Marks messages from the other participant as delivered (sets `delivered_at`).

**Response 200:** `{"delivered_count": 3}`

**When to call:**
- When a chat push notification arrives (even if app is in background), call this for the conversation.
- When the user opens the conversations list screen, call it for conversations with unread messages (optional — but helps show the second tick before the user opens the chat).

This drives the "double check" tick on the sender side.

### 3.6 Mark as read

```
POST /api/v1/chat/conversations/{conversation_id}/read
```

No body. Marks all messages from the other participant as read (sets `is_read=true`, `read_at`, and `delivered_at` if null).

**Response 200:** `{"read_count": 5}`

Call when the user opens/focuses the conversation detail screen.

### 3.7 Tick rendering (WhatsApp style)

Each message in the API response includes `delivered_at`, `read_at` and `is_read`. Render ticks on every own message:

| State | Condition | Display |
|---|---|---|
| Sent | `delivered_at == null` | Single check, gray |
| Delivered | `delivered_at != null && is_read == false` | Double check, gray |
| Read | `is_read == true` | Double check, gold (`#D4AF37`) |

### 3.8 Realtime delivery/read events (Reverb)

Listen on `private-conversation.{id}` channel for:
- `MessagesDelivered` payload: `{conversation_id, recipient_id, delivered_at}` → update own messages in that conversation to `delivered_at`
- `MessagesRead` payload: `{conversation_id, reader_id, read_at}` → update own messages to `is_read=true`

### 3.9 Typing indicator (Phase 3 — realtime only)

**Emit (when user is typing):**
```
POST /api/v1/chat/conversations/{conversation_id}/typing
Body: { "is_typing": true }
```

- Call while user is typing. Throttle: send once every ~3s while input isn't empty, don't spam every keystroke.
- Send `{"is_typing": false}` immediately when:
  - User sends the message
  - Input is cleared
  - User leaves the chat screen

No DB write — this is pure WS broadcast to the other participant.

**Listen (show "X is typing…"):**

On `private-conversation.{id}` channel, event name `.UserTyping` (note the leading dot — it's an app-level event name):

```json
{ "conversation_id": 1, "user_id": 42, "is_typing": true }
```

Rules:
- Ignore events where `user_id == your own id` (Reverb's `toOthers()` handles this but double-check).
- Show "X is typing…" pill just above the input.
- Auto-hide after 5s if no new `is_typing:true` arrives (fallback for when the other side disconnects mid-type).
- Hide immediately on `is_typing:false` or when a `NewMessage` from that user arrives.

### 3.10 Active-chat presence (supress notifications while chat open)

When the user is actively viewing a conversation, the backend should NOT send push / SMS / in-app notifications for new messages in that same conversation (the user is already seeing them in realtime).

**Tell the server when you enter / leave the chat screen:**

```
POST /api/v1/chat/conversations/{id}/focus   // on chat screen opened
POST /api/v1/chat/presence/blur              // on chat screen closed / backgrounded
```

**Heartbeat:** re-hit `focus` every 30s while the chat screen is visible. If the server doesn't see a heartbeat for 60s, it resumes sending notifications (safety net in case the app crashes or WS drops without calling `blur`).

Good triggers for these calls:
- `focus`: `onResume` / `initState` of ChatScreen, on tab switch back to this chat.
- `blur`: `onPause` / `dispose` of ChatScreen, on app going to background, on navigating away.

`markAsRead` already refreshes the same heartbeat, so the behavior works even if the client only calls `/read` and not `/focus`.

### 3.11 Per-user inbox channel (for the chat list screen)

Each of the 4 chat events (`NewMessage`, `MessagesRead`, `MessagesDelivered`, `UserTyping`) is now broadcast on **three** channels:
- `private-conversation.{conversation_id}` — use when inside a specific chat (existing)
- `private-user.{user_a_id}` — the inbox channel of the first participant
- `private-user.{user_b_id}` — the inbox channel of the second participant

**In the chat list screen**, subscribe to `private-user.{ownUserId}` once and receive events for **every conversation the user belongs to**. Each event payload includes `conversation_id`, so filter client-side to update the right row.

Authorization: the `user.{id}` channel only authorizes when `auth_user.id === id`.

Example flow on the chat list:
- `.NewMessage` on `private-user.{me}` with `conversation_id: 5` → update the preview text and unread count of conversation row 5, bump it to top.
- `.UserTyping` with `conversation_id: 5, is_typing: true, user_id: other` → show "X is typing…" under conversation 5's preview (auto-hide 5s).
- `.MessagesRead` / `.MessagesDelivered` → update ticks on the last-message preview if it's one of yours.

You can keep subscribing to `private-conversation.{id}` while inside a chat — both channels receive the same events. Simpler: subscribe ONLY to `private-user.{me}` in both screens and always filter by `conversation_id`.

### 3.12 Grouped chat notifications

In-app chat notifications are now aggregated per conversation while unread. Each notification's `data` JSON includes:

```json
{
  "title": "Modelo Joseph",
  "body": "latest message preview…",
  "screen": "chat",
  "conversation_id": 5,
  "message_id": 42,
  "sender_id": 660,
  "message_count": 3
}
```

- `body` = the LAST message received
- `message_count` = total messages grouped since the last time the user read this chat (1 means no grouping)

**Render rule in the notifications list:** show the `body` as the preview. If `message_count > 1`, show a small badge like `+2` (message_count - 1) next to the row OR render the body as `body · +2 more`. Your call — the data is there.

**Push notifications (OS-level grouping):** server now sends each push with `thread_id = "chat-{conversation_id}"`. iOS and Android group pushes by this id automatically in the system notification center — you don't need extra client code for that.

**When the user opens the chat**, the server auto-marks the aggregated notification as read (happens inside `markAsRead`), so the row disappears from the unread list next time you refresh it.

### 3.13 Presence — online / last seen (Phase 2)

**No new endpoints needed.** The server updates `users.last_seen_at` automatically on every authenticated API request (throttled to once per 30s per user).

All chat payloads now include the counterpart's presence:

```json
"other_participant": {
  "id": 42,
  "name": "...",
  "is_online": true,
  "last_seen_at": "2026-04-17T21:01:27.000000Z"
}
```

And `GET /api/v1/me` returns the authenticated user's own `last_seen_at` and `is_online` on `user`.

**Rendering rules (WhatsApp style):**
- `is_online == true` → green dot + "Online"
- `is_online == false && last_seen_at within 60 min` → "Last seen X min ago"
- same day → "Last seen today at 14:32"
- yesterday → "Last seen yesterday at 14:32"
- older → "Last seen Apr 10"

**Keep it fresh:** when the conversation detail screen is visible, the app already hits `markAsRead` / `markAsDelivered` on events — that's enough to also refresh `last_seen_at` of both sides. If you want smoother updates while idle on the chat screen, poll `GET /api/v1/chat/conversations/{id}` every 30-60s, or subscribe to a presence channel on Reverb (future improvement).

### 3.6 How conversations get created

**The app does NOT need to manually create conversations** except for the support endpoint. They get created automatically when:

1. A model accepts a casting request via `POST /api/v1/events/{event}/casting/confirm`
2. A designer observes a material via `POST /api/v1/materials/{id}/observe`
3. Operations (or another internal role) starts a chat from the web panel
4. The user calls `POST /api/v1/chat/conversations/support` (covered above)

Just call `GET /chat/conversations` to refresh the list when the user opens the screen.

---

<a id="profile"></a>

## 4. Profile API changes

### Removed: `bio` field for designers

The `bio` field has been removed from `PUT /api/v1/profile` for designers. If you have it in your edit-profile form, remove it — it will be silently ignored now.

Designers enter their bio in the **Materials → Bio** section (see section 2.4).

For reference, the designer profile PUT accepts:

```json
{
  "first_name": "...",
  "last_name": "...",
  "phone": "+1234567890",
  "brand_name": "...",
  "collection_name": "...",
  "website": "https://...",
  "instagram": "username",
  "country": "United States"
}
```

Models, media, volunteers etc. keep their existing fields — no change.

---

<a id="notifications"></a>

## 5. Notifications behavior

Existing push notification and in-app notification endpoints (covered in `10-mobile-push-notifications.md`) still work. New triggers added:

| Trigger | Recipient | Notification |
|---|---|---|
| Operation changes material status to `in_progress` or `completed` | Designer | "Your {MaterialName} is now in progress / completed and ready for review." |
| Operation uploads a Moodboard image | Designer | "A new image has been added to your {MaterialName}. Please review and respond." |
| Designer uploads a material file | All active Operation users | "{DesignerName} uploaded a file to {MaterialName}." |
| Tickets uploads Artworks | Designer | "New artwork files have been uploaded for your use." |
| Any user sends a chat message | The other conversation participant | Title: `{Sender Name}` · Body: the message text truncated to 100 chars · Image messages show `📷 Sent an image` |

### Material notification data payload

```json
{
  "screen": "home",
  "material_id": 101
}
```

**UI suggestion:** when tapped, navigate to the Materials screen. If `material_id` is included, scroll to that specific material.

### Chat message notification data payload

```json
{
  "screen": "chat",
  "conversation_id": "123"
}
```

**UI suggestions for chat notifications:**

- **Tap → open chat:** navigate directly to the chat view for `conversation_id`.
- **Avoid duplicate banners in-app:** if the user is already viewing that specific conversation, iOS' native banner shouldn't appear. Your `AppDelegate.swift` `willPresent` callback can check the current active conversation and return `[]` (no presentation options) if it matches. Alternatively, handle it in `FirebaseMessaging.onMessage.listen` in Dart and skip the in-app banner when the conversation is open.
- **Refresh the unread badge** when a chat notification arrives in foreground: call `GET /api/v1/notifications/unread-count` and update the bell badge.
- **Update the chats list** when a chat notification arrives: refresh `GET /api/v1/chat/conversations` so the new message's sender bubbles to the top with an updated `unread_count`.
- **System messages** (e.g. "User joined") do NOT trigger notifications on purpose.

---

<a id="checklist"></a>

## 6. Implementation checklist

### Materials screen

- [ ] Screen shows the 10 materials from `GET /my-materials`
- [ ] Each material shows: name, description, status badge, file count, Drive folder link
- [ ] Deadline shown prominently; disable upload when passed
- [ ] Tap a material → expandable detail view
- [ ] **Upload flow:**
  - [ ] Show picker (gallery, file browser)
  - [ ] Call `POST /upload-url` to get resumable URL
  - [ ] PUT the file bytes directly to Drive with progress indicator
  - [ ] Call `POST /upload-complete` on success
  - [ ] Show error if deadline passed or upload fails
- [ ] **Bio material:** 4 text fields (biography, collection_description, additional_notes, contact_info) + Save button → calls `PUT /bio`
- [ ] **Moodboard materials:** list items, show image preview, text input per item → `POST /moodboard-respond`
- [ ] **Collaborative materials (Background, Music):** when `status === 'completed'`, show Confirm / Observe buttons
  - Confirm → `POST /confirm`
  - Observe → prompt for reason → `POST /observe` → navigate to chat with Operations
- [ ] **Runway Logo:** read-only — show files with download button
- [ ] **Artworks:** read-only for designer (Tickets uploads these)

### Chat screen

- [ ] List all conversations with `GET /chat/conversations`
- [ ] Display participant name, role badge, last message, unread badge
- [ ] Sort by `last_message_at` desc
- [ ] Tap a conversation → messages view
- [ ] **Contact Support button:** calls `POST /chat/conversations/support` → navigates to the returned conversation
- [ ] Send text messages with `POST /conversations/{id}/messages`
- [ ] Mark as read with `POST /conversations/{id}/read` when entering
- [ ] Mark as delivered with `POST /conversations/{id}/delivered` on chat push receipt
- [ ] Render 3-state ticks on own messages (sent → delivered → read, gold on read)
- [ ] Listen to `MessagesDelivered` realtime event
- [ ] Show presence badges (Online / Last seen) in chat header using `other_participant.is_online` + `last_seen_at`
- [ ] Emit typing events via `POST /conversations/{id}/typing` (throttled 3s)
- [ ] Listen to `.UserTyping` event and show "X is typing…" with 5s auto-hide
- [ ] Call `POST /conversations/{id}/focus` on chat open + heartbeat every 30s
- [ ] Call `POST /chat/presence/blur` on chat close / app background

### Profile changes

- [ ] Remove `bio` field from the designer profile edit form

### Notifications

- [ ] Handle `material_id` in the notification data payload to deep-link to materials
- [ ] Handle chat notifications: `screen: "chat"` + `conversation_id` → open that conversation
- [ ] When the user is already viewing a conversation, suppress the banner for messages from that same conversation
- [ ] Refresh the unread-count badge when chat notifications arrive in foreground

---

## Need help?

If any endpoint returns an unexpected error or the flow is unclear, share:

1. The exact URL called
2. The request body (redact tokens)
3. The response status and body
4. The local console output (relevant excerpt)

Backend dev will help debug. Good luck!
