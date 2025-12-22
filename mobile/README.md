# BeanPrepared Mobile (Expo)

## Setup
1. Install Node.js (>= 18).
2. From this folder, run:
   - `npm install`
   - `npm run start`

## Notes
- Preferences are stored locally with AsyncStorage.
- OneSignal integration should be added once you have app IDs and API keys.

## OneSignal Setup (iOS + Android)
### 1) Create OneSignal apps
- Create two apps in OneSignal: one for iOS and one for Android.
- Copy each OneSignal App ID and REST API key.

### 2) Configure App IDs
- In `mobile/App.js`, set `ONESIGNAL_APP_ID` to the correct platform App ID.
- iOS and Android use different App IDs; use environment-specific builds or a simple platform switch if needed.

### 3) Create a development build (Expo Dev Client)
Expo Go does **not** support OneSignal push notifications. You must use a development build.

Install EAS CLI (one-time):
```
npm install -g eas-cli
```

Login and configure:
```
eas login
eas build:configure
```

Build for iOS:
```
eas build -p ios --profile development
```

Build for Android:
```
eas build -p android --profile development
```

### 4) Enable Push Credentials
Follow OneSignal's iOS and Android push configuration guides:
- iOS (APNs): https://documentation.onesignal.com/docs/ios-app-setup
- Android (FCM): https://documentation.onesignal.com/docs/android-firebase-setup

### 5) Backend Registration
The app registers devices at:
- `POST /api/users/register` with `external_user_id`, `onesignal_player_id`, and `platform`.

### 6) Cron Notifications
Run the backend cron every minute:
```
php cron/send_notifications.php
```

### Notes
- Push notifications won’t work in Expo Go.
- Use UTC in the database.
