# Firebase Cloud Messaging (FCM) API

This documentation describes the endpoints available for managing Firebase Cloud Messaging (FCM) tokens and testing push notifications.

## Authentication

All endpoints require authentication using a Bearer Token.

---

## 1. Update FCM Token

Updates the FCM token for the currently authenticated user. This token is used to target the device for push notifications.

- **URL**: `/api/user/update-fcm-token`
- **Method**: `POST`
- **Controller**: `App\Http\Controllers\Api\FcmController@updateToken`

### Request Body

```json
{
    "fcm_token": "string (required)"
}
```

### Response (Success)

- **Status**: `200 OK`

```json
{
    "success": true,
    "message": "FCM token updated successfully."
}
```

---

## 2. Test Notification

Sends a test push notification to a specific FCM token. This endpoint is useful for verifying the backend-to-Firebase connection.

- **URL**: `/api/test-notification`
- **Method**: `POST`
- **Controller**: `App\Http\Controllers\Api\FcmController@testNotification`

### Request Body

```json
{
    "token": "string (required) - The target FCM token",
    "title": "string (required) - Notification title",
    "body": "string (required) - Notification body",
    "data": "object (optional) - Additional key-value data payload"
}
```

### Example Request

```json
{
    "token": "fXy...1A",
    "title": "Test Title",
    "body": "This is a test notification body.",
    "data": {
        "click_action": "FLUTTER_NOTIFICATION_CLICK",
        "status": "done"
    }
}
```

### Response (Success)

- **Status**: `200 OK`

```json
{
    "success": true,
    "message": "Test notification sent successfully.",
    "result": "Firebase Response ID"
}
```

### Response (Error)

- **Status**: `500 Internal Server Error`

```json
{
    "success": false,
    "message": "Failed to send notification: [Firebase Error Message]"
}
```
