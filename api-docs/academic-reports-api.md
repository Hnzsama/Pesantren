# Academic & Reports API Documentation

This API provides endpoints for retrieving academic filters (years and semesters) and student report cards.

## 1. Get Academic Filters

Retrieve a list of academic years and their associated semesters for filtering.

- **URL**: `/api/reports/filters`
- **Method**: `GET`
- **Authentication**: Bearer Token (Sanctum)
- **Headers**:
  - `X-School-Id` (Optional, defaults to user's school)

### Success Response

**Code**: `200 OK`

**Content Example**:

```json
{
    "status": "success",
    "data": [
        {
            "id": "01jkp...",
            "school_id": "01jkp...",
            "name": "2025/2026",
            "start_date": "2025-07-01",
            "is_active": true,
            "semesters": [
                {
                    "id": "01jkq...",
                    "academic_year_id": "01jkp...",
                    "type": "GANJIL",
                    "start_date": "2025-07-01"
                },
                {
                    "id": "01jkr...",
                    "academic_year_id": "01jkp...",
                    "type": "GENAP",
                    "start_date": "2026-01-01"
                }
            ]
        }
    ]
}
```

---

## 2. List Student Reports

Retrieve report cards for students associated with the authenticated parent user.

- **URL**: `/api/reports`
- **Method**: `GET`
- **Authentication**: Bearer Token (Sanctum)
- **Query Parameters**:
  - `student_id` (Optional): Filter by specific student
  - `academic_year_id` (Optional): Filter by academic year
  - `semester_id` (Optional): Filter by specific semester
  - `per_page` (Optional): Number of items per page (default: 15)

### Success Response

**Code**: `200 OK`

**Content Example**:

```json
{
    "current_page": 1,
    "data": [
        {
            "id": "01jks...",
            "student": {
                "id": "01jkt...",
                "full_name": "Santri ABC",
                "nis": "2024001"
            },
            "semester": {
                "id": "01jkq...",
                "type": "GANJIL",
                "academic_year_id": "01jkp..."
            },
            "academic_year": {
                "id": "01jkp...",
                "name": "2025/2026"
            },
            "files": [
                "https://example.com/storage/reports/report1.pdf"
            ],
            "file_url": "https://example.com/storage/reports/report1.pdf",
            "uploaded_at": "2026-02-06 15:00:00"
        }
    ],
    "total": 1
}
```

### Error Responses

- **401 Unauthorized**: Authentication failed or token missing.
- **403 Forbidden**: Attempted to access a student not belonging to the parent.
- **400 Bad Request**: Missing required headers or invalid parameters.
