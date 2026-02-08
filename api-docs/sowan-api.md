# Sowan API Documentation

Endpoints for managing sowan (visitation) schedules.

## List Schedules

**Endpoint:** `GET /api/sowan-schedules`

**Query Parameters:**

- `school_id` (optional): Filter by school ID.
- `status` (optional): Filter by status (`pending`, `confirmed`, `completed`).
- `sort_by` (optional): default `request_date`.
- `sort_order` (optional): default `desc`.
- `per_page` (optional): items per page.

**Response:**

```json
{
    "success": true,
    "data": {
        "data": [
            {
                "id": "...",
                "school_id": "...",
                "guest_name": "...",
                "request_date": "...",
                "topic": "...",
                "status": "pending",
                "created_at": "...",
                "updated_at": "..."
            }
        ],
        "total": 1,
        ...
    }
}
```

## Create Schedule

**Endpoint:** `POST /api/sowan-schedules`

**Request Body:**

```json
{
    "school_id": "...",
    "guest_name": "...",
    "request_date": "2024-02-07 10:00:00",
    "topic": "...",
    "status": "pending"
}
```

## Get Schedule Detail

**Endpoint:** `GET /api/sowan-schedules/{id}`

## Update Schedule

**Endpoint:** `PUT/PATCH /api/sowan-schedules/{id}`

**Request Body (Partial update allowed):**

```json
{
    "status": "confirmed"
}
```

## Delete Schedule

**Endpoint:** `DELETE /api/sowan-schedules/{id}`
