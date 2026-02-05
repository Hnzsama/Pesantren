# Student Card API Documentation

## Get All Student Cards

Retrieves a paginated list of student cards.

**URL** : `/api/student-cards`
**Method** : `GET`
**Auth Required** : YES (Sanctum Token)

### Query Parameters

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `page` | `integer` | Page number for pagination (default: 1) |
| `card_number` | `string` | Filter by card number (optional) |

### Response Success (200 OK)

```json
{
    "current_page": 1,
    "data": [
        {
            "id": "01jj...",
            "student_id": "01jj...",
            "issued_by": 1,
            "card_number": "1234567890123456",
            "issued_at": "2024-01-01T00:00:00.000000Z",
            "expires_at": null,
            "status": "generated",
            "created_at": "2024-01-01T10:00:00.000000Z",
            "updated_at": "2024-01-01T10:00:00.000000Z",
            "student": {
                "id": "01jj...",
                "nis": "12345",
                "full_name": "Ahmad",
                "school": { ... },
                "level": {
                    "id": 1,
                    "name": "Class 1A",
                    ...
                }
            }
        }
    ],
    "first_page_url": "...",
    "from": 1,
    "last_page": 1,
    "last_page_url": "...",
    "links": [ ... ],
    "next_page_url": null,
    "path": "...",
    "per_page": 10,
    "prev_page_url": null,
    "to": 1,
    "total": 1
}
```

---

## Get Student Card by ID

Retrieves details of a specific student card by its internal ID.

**URL** : `/api/student-cards/{id}`
**Method** : `GET`
**Auth Required** : YES

### URL Parameters

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `id` | `string` | The ULID of the student card |

### Response Success (200 OK)

```json
{
    "id": "01jj...",
    "student_id": "01jj...",
    "issued_by": 1,
    "card_number": "1234567890123456",
    ...
    "student": { ... }
}
```

### Response Error (404 Not Found)

```json
{
    "message": "Student card not found"
}
```

---

## Get Student Card by Card Number

Retrieves details of a specific student card by its card number (e.g., for scanning).

**URL** : `/api/student-cards/number/{cardNumber}`
**Method** : `GET`
**Auth Required** : YES

### URL Parameters

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `cardNumber` | `string` | The 16-digit card number |

### Response Success (200 OK)

Same as Get by ID.
