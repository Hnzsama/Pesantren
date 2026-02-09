Berikut adalah dokumentasi API lengkap untuk fitur Feedback (termasuk GET dan POST) dalam format Markdown yang siap salin.

```markdown
# Feedback API Documentation

This API provides endpoints for submitting feedback regarding facilities, academics, or services, and retrieving the history of submitted feedback.

## 1. List User Feedback

Retrieve a history of feedback submitted by the authenticated user.

- **URL**: `/api/feedback`
- **Method**: `GET`
- **Authentication**: Bearer Token (Sanctum)
- **Query Parameters**:
  - `per_page` (Optional): Number of items per page (default: 15)

### Success Response

**Code**: `200 OK`

**Content Example**:

```json
{
    "status": "success",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 1,
                "school_id": "01jkp...",
                "user_id": "01jkt...",
                "sender_name": "Budi Santoso",
                "email": "budi@example.com",
                "phone": "08123456789",
                "category": "fasilitas",
                "message": "AC di ruang kelas 10A tidak dingin, mohon dicek.",
                "admin_reply": "Akan segera kami tindak lanjuti teknisi.",
                "replied_at": "2026-02-10T08:00:00.000000Z",
                "is_read": true,
                "created_at": "2026-02-09T10:00:00.000000Z",
                "updated_at": "2026-02-10T08:00:00.000000Z"
            },
            {
                "id": 2,
                "school_id": "01jkp...",
                "user_id": "01jkt...",
                "sender_name": "Budi Santoso",
                "email": "budi@example.com",
                "phone": "08123456789",
                "category": "akademik",
                "message": "Mohon info jadwal ujian semester.",
                "admin_reply": null,
                "replied_at": null,
                "is_read": false,
                "created_at": "2026-02-08T09:00:00.000000Z",
                "updated_at": "2026-02-08T09:00:00.000000Z"
            }
        ],
        "first_page_url": "[http://api.domain.com/api/feedback?page=1](http://api.domain.com/api/feedback?page=1)",
        "from": 1,
        "last_page": 1,
        "last_page_url": "[http://api.domain.com/api/feedback?page=1](http://api.domain.com/api/feedback?page=1)",
        "path": "[http://api.domain.com/api/feedback](http://api.domain.com/api/feedback)",
        "per_page": 15,
        "to": 2,
        "total": 2
    }
}

```

---

## 2. Submit Feedback

Create a new feedback submission.

* **URL**: `/api/feedback`
* **Method**: `POST`
* **Authentication**: Bearer Token (Sanctum)
* **Headers**:
* `Content-Type`: `application/json`
* `Accept`: `application/json`
* `X-School-Id`: (Optional, defaults to user's school_id)



### Request Body

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `category` | string | **Yes** | Options: `fasilitas`, `akademik`, `layanan` |
| `message` | string | **Yes** | The content of the feedback (min 10 chars) |
| `sender_name` | string | No | Defaults to logged-in user's name if empty |
| `email` | string | No | Defaults to logged-in user's email if empty |
| `phone` | string | No | Defaults to logged-in user's phone if empty |

### Request Example

```json
{
    "category": "fasilitas",
    "message": "Mohon diperbaiki AC di ruang kelas 10-A karena mati total.",
    "sender_name": "Budi Santoso",
    "phone": "081234567890"
}

```

### Success Response

**Code**: `201 Created`

**Content Example**:

```json
{
    "status": "success",
    "message": "Feedback submitted successfully",
    "data": {
        "id": 5,
        "school_id": "01jkp...",
        "user_id": "01jkt...",
        "sender_name": "Budi Santoso",
        "email": "budi@example.com",
        "phone": "081234567890",
        "category": "fasilitas",
        "message": "Mohon diperbaiki AC di ruang kelas 10-A karena mati total.",
        "is_read": false,
        "created_at": "2026-02-12T10:00:00.000000Z",
        "updated_at": "2026-02-12T10:00:00.000000Z"
    }
}

```

### Error Responses

**Code**: `422 Unprocessable Entity` (Validation Error)

```json
{
    "status": "error",
    "message": "Validation error",
    "errors": {
        "category": [
            "The selected category is invalid."
        ],
        "message": [
            "The message field is required."
        ]
    }
}

```

**Code**: `400 Bad Request` (Missing School ID)

```json
{
    "status": "error",
    "message": "School ID not found"
}

```

```

```