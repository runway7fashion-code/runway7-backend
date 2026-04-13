# Mobile Push Notifications — Integration Guide

This guide is for the Flutter mobile app developer. It explains what the backend already provides and what needs to be implemented on the mobile side so that users receive push notifications sent from the admin Communications panel.

---

## Backend endpoints (already implemented)

All endpoints require Sanctum authentication.

### List notifications (for the in-app notifications screen)

```
GET /api/v1/notifications?per_page=20&unread=1
Authorization: Bearer {token}
```

Query params:
- `per_page` — page size (default 20, max 100)
- `unread=1` — only unread notifications

Response:
```json
{
  "data": [
    {
      "id": "9e8b1234-...",
      "title": "Welcome!",
      "body": "Hi María, your fitting is tomorrow at 3 PM.",
      "screen": "profile",
      "read_at": null,
      "created_at": "2026-04-11T15:30:00-05:00"
    }
  ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 20,
  "total": 87,
  "unread_count": 12
}
```

### Unread count (for the bell badge)

```
GET /api/v1/notifications/unread-count
```

Response: `{ "count": 12 }`

### Mark one as read

```
POST /api/v1/notifications/{id}/read
```

### Mark all as read

```
POST /api/v1/notifications/read-all
```

### Delete a notification

```
DELETE /api/v1/notifications/{id}
```

### Register / update device token

```
POST /api/v1/device-tokens
Authorization: Bearer {token}

Body:
{
  "token": "fcm_token_string",
  "platform": "ios" | "android" | "web"
}
```

Creates or updates the device token for the authenticated user. If the user logs in from multiple devices, multiple tokens are stored and a single notification is delivered to all of them.

### Remove device token

```
DELETE /api/v1/device-tokens
Authorization: Bearer {token}

Body:
{
  "token": "fcm_token_string"
}
```

Deletes the token. Call this on logout.

---

## What the mobile dev must implement

### 1. Firebase Cloud Messaging (FCM) setup

If Firebase is not yet configured in the project:

1. Create the app in [Firebase Console](https://console.firebase.google.com/)
2. Download and add:
   - `google-services.json` → `android/app/`
   - `GoogleService-Info.plist` → `ios/Runner/`
3. Add to `pubspec.yaml`:

```yaml
dependencies:
  firebase_core: ^latest
  firebase_messaging: ^latest
```

4. Initialize Firebase in `main.dart`:

```dart
import 'package:firebase_core/firebase_core.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  runApp(const MyApp());
}
```

### 2. Request notification permission (iOS requirement)

```dart
import 'package:firebase_messaging/firebase_messaging.dart';

final messaging = FirebaseMessaging.instance;

final settings = await messaging.requestPermission(
  alert: true,
  badge: true,
  sound: true,
);

if (settings.authorizationStatus == AuthorizationStatus.authorized) {
  // User granted permission
}
```

Call this once at app startup or after login.

### 3. Get FCM token and register with backend

After a successful login (and each time the user logs in), obtain the FCM token and send it to the backend:

```dart
import 'dart:io';

Future<void> registerDeviceToken() async {
  final fcmToken = await FirebaseMessaging.instance.getToken();
  if (fcmToken == null) return;

  final platform = Platform.isIOS ? 'ios' : 'android';

  await dio.post('/api/v1/device-tokens', data: {
    'token': fcmToken,
    'platform': platform,
  });
}
```

### 4. Listen for token refresh

FCM periodically rotates tokens. When it does, the new token must be sent to the backend:

```dart
FirebaseMessaging.instance.onTokenRefresh.listen((newToken) async {
  final platform = Platform.isIOS ? 'ios' : 'android';
  await dio.post('/api/v1/device-tokens', data: {
    'token': newToken,
    'platform': platform,
  });
});
```

### 5. Handle received notifications

There are three states to handle:

#### a) App in foreground

Show a custom banner or snackbar since the OS won't show the notification automatically:

```dart
FirebaseMessaging.onMessage.listen((RemoteMessage message) {
  final title = message.notification?.title ?? '';
  final body = message.notification?.body ?? '';
  // Show snackbar/overlay with title + body
});
```

#### b) App in background, user taps the notification

```dart
FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
  final screen = message.data['screen'];
  if (screen != null) _navigateToScreen(screen);
});
```

#### c) App terminated, user taps the notification

Must be checked on app startup:

```dart
final initialMessage = await FirebaseMessaging.instance.getInitialMessage();
if (initialMessage != null) {
  final screen = initialMessage.data['screen'];
  if (screen != null) _navigateToScreen(screen);
}
```

### 6. Deep link navigation

The admin panel can optionally include a `screen` value in the notification `data` payload. The mobile must map these values to routes:

| `data.screen` value | Destination screen |
|---|---|
| `home`      | Home |
| `profile`   | Profile |
| `events`    | Events list |
| `tickets`   | Tickets |
| `shows`     | Shows |
| `payments`  | Payments |
| `chat`      | Chat |

Example:

```dart
void _navigateToScreen(String screen) {
  switch (screen) {
    case 'home':     navigatorKey.currentState?.pushNamed('/home'); break;
    case 'profile':  navigatorKey.currentState?.pushNamed('/profile'); break;
    case 'events':   navigatorKey.currentState?.pushNamed('/events'); break;
    case 'tickets':  navigatorKey.currentState?.pushNamed('/tickets'); break;
    case 'shows':    navigatorKey.currentState?.pushNamed('/shows'); break;
    case 'payments': navigatorKey.currentState?.pushNamed('/payments'); break;
    case 'chat':     navigatorKey.currentState?.pushNamed('/chat'); break;
  }
}
```

If a notification arrives without a `screen` value, do nothing — just dismiss it.

### 7. Remove token on logout

Before clearing the auth session on logout, tell the backend to delete the token:

```dart
Future<void> logout() async {
  final fcmToken = await FirebaseMessaging.instance.getToken();
  if (fcmToken != null) {
    await dio.delete('/api/v1/device-tokens', data: {'token': fcmToken});
  }
  // ... clear local session
}
```

This prevents the user from continuing to receive notifications after logging out.

---

## Notification payload reference

The backend sends notifications via Firebase Cloud Messaging with this shape:

```json
{
  "notification": {
    "title": "Welcome to Fashion Week",
    "body": "Hi Maria, your fitting is scheduled for tomorrow at 3 PM."
  },
  "data": {
    "screen": "shows"
  }
}
```

- `notification.title` — max 50 chars
- `notification.body`  — max 200 chars
- `data.screen` — optional deep link target

---

## Testing checklist

- [ ] iOS permission dialog appears on first run
- [ ] FCM token is registered with backend after login
- [ ] Token is updated when rotated by FCM
- [ ] Notification arrives while app is in foreground (custom banner shown)
- [ ] Notification arrives while app is in background (OS shows it, tap opens correct screen)
- [ ] Notification arrives while app is terminated (tap opens app on correct screen)
- [ ] Token is deleted from backend on logout
- [ ] User stops receiving notifications after logout
- [ ] Multi-device: user logged in on 2 devices receives on both

---

## Sending a test notification

Admin staff can send a test notification from:

```
https://runways7.com/admin/communications/notifications
```

Filter by your user, select it, click **Compose Notification**, write title + body, and send.
