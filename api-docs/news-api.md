# News API Documentation

## Base URL

```
https://your-domain.com/api
```

## Authentication

All endpoints require authentication via Laravel Sanctum. Include the Bearer token in the Authorization header:

```
Authorization: Bearer {token}
```

---

## Endpoints

### 1. Get All News

Retrieve a paginated list of news articles.

**Endpoint:** `GET /news`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `school_id` | string (ULID) | No | Filter by school ID |
| `target_role` | string | No | Filter by target role (`admin`, `guardian`, `student`, `all`) |
| `include_unpublished` | boolean | No | Include unpublished news (default: false) |
| `sort_by` | string | No | Sort field name (default: `created_at`) |
| `sort_order` | string | No | Sort order: `asc` or `desc` (default: `desc`) |
| `per_page` | integer | No | Items per page (default: 15) |
| `page` | integer | No | Page number (default: 1) |

**Example Request:**

```http
GET /api/news?school_id=01kgjknrxmahq3axv9wcqhjb12&target_role=guardian&per_page=10&page=1
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "01kgjknrxmahq3axv9wcqhjb12",
        "school_id": "01kgjknrxmahq3axv9wcqhjb12",
        "author_id": "01kgjknrxmahq3axv9wcqhjb12",
        "title": "Pengumuman Libur Semester",
        "content": "Dengan ini kami informasikan bahwa libur semester akan dimulai...",
        "images": [
          "https://example.com/storage/news/image1.jpg",
          "https://example.com/storage/news/image2.jpg"
        ],
        "target_role": "guardian",
        "is_published": true,
        "created_at": "2026-02-06T12:00:00.000000Z",
        "updated_at": "2026-02-06T12:00:00.000000Z",
        "author": {
          "id": "01kgjknrxmahq3axv9wcqhjb12",
          "name": "Admin Utama"
        },
        "school": {
          "id": "01kgjknrxmahq3axv9wcqhjb12",
          "name": "Pesantren Al-Falah"
        }
      }
    ],
    "first_page_url": "https://your-domain.com/api/news?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "https://your-domain.com/api/news?page=5",
    "links": [...],
    "next_page_url": "https://your-domain.com/api/news?page=2",
    "path": "https://your-domain.com/api/news",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 65
  }
}
```

---

### 2. Get Single News

Retrieve a specific news article by ID.

**Endpoint:** `GET /news/{id}`

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string (ULID) | Yes | News ID |

**Example Request:**

```http
GET /api/news/01kgjknrxmahq3axv9wcqhjb12
```

**Success Response (200):**

```json
{
  "success": true,
  "data": {
    "id": "01kgjknrxmahq3axv9wcqhjb12",
    "school_id": "01kgjknrxmahq3axv9wcqhjb12",
    "author_id": "01kgjknrxmahq3axv9wcqhjb12",
    "title": "Pengumuman Libur Semester",
    "content": "Dengan ini kami informasikan...",
    "images": ["https://example.com/storage/news/image1.jpg"],
    "target_role": "guardian",
    "is_published": true,
    "created_at": "2026-02-06T12:00:00.000000Z",
    "updated_at": "2026-02-06T12:00:00.000000Z",
    "author": {
      "id": "01kgjknrxmahq3axv9wcqhjb12",
      "name": "Admin Utama"
    },
    "school": {
      "id": "01kgjknrxmahq3axv9wcqhjb12",
      "name": "Pesantren Al-Falah"
    }
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "News not found"
}
```

---

### 3. Create News

Create a new news article.

**Endpoint:** `POST /news`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `school_id` | string (ULID) | No | School ID (null for global news) |
| `title` | string | Yes | News title (max: 255 characters) |
| `content` | string | Yes | News content (HTML supported) |
| `images` | array | No | Array of image URLs or base64 strings |
| `target_role` | string | Yes | Target audience: `admin`, `guardian`, `student`, `all` |
| `is_published` | boolean | No | Publication status (default: true) |

**Example Request:**

```http
POST /api/news
Content-Type: application/json

{
  "school_id": "01kgjknrxmahq3axv9wcqhjb12",
  "title": "Pengumuman Libur Semester",
  "content": "Dengan ini kami informasikan bahwa libur semester akan dimulai pada tanggal 15 Februari 2026...",
  "images": [
    "https://example.com/storage/news/image1.jpg"
  ],
  "target_role": "guardian",
  "is_published": true
}
```

**Success Response (201):**

```json
{
  "success": true,
  "message": "News created successfully",
  "data": {
    "id": "01kgjknrxmahq3axv9wcqhjb12",
    "school_id": "01kgjknrxmahq3axv9wcqhjb12",
    "author_id": "01kgjknrxmahq3axv9wcqhjb12",
    "title": "Pengumuman Libur Semester",
    "content": "Dengan ini kami informasikan...",
    "images": ["https://example.com/storage/news/image1.jpg"],
    "target_role": "guardian",
    "is_published": true,
    "created_at": "2026-02-06T12:00:00.000000Z",
    "updated_at": "2026-02-06T12:00:00.000000Z",
    "author": {
      "id": "01kgjknrxmahq3axv9wcqhjb12",
      "name": "Admin Utama"
    },
    "school": {
      "id": "01kgjknrxmahq3axv9wcqhjb12",
      "name": "Pesantren Al-Falah"
    }
  }
}
```

**Error Response (422):**

```json
{
  "success": false,
  "message": "Validation error",
  "errors": {
    "title": ["The title field is required."],
    "target_role": ["The selected target role is invalid."]
  }
}
```

---

### 4. Update News

Update an existing news article.

**Endpoint:** `PUT /news/{id}` or `PATCH /news/{id}`

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string (ULID) | Yes | News ID |

**Request Body:** (All fields are optional for PATCH, only send fields to update)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `school_id` | string (ULID) | No | School ID |
| `title` | string | No | News title (max: 255 characters) |
| `content` | string | No | News content |
| `images` | array | No | Array of image URLs |
| `target_role` | string | No | Target audience |
| `is_published` | boolean | No | Publication status |

**Example Request:**

```http
PATCH /api/news/01kgjknrxmahq3axv9wcqhjb12
Content-Type: application/json

{
  "title": "Pengumuman Libur Semester (Updated)",
  "is_published": false
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "News updated successfully",
  "data": {
    "id": "01kgjknrxmahq3axv9wcqhjb12",
    "title": "Pengumuman Libur Semester (Updated)",
    "is_published": false,
    ...
  }
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "News not found"
}
```

---

### 5. Delete News

Delete a news article.

**Endpoint:** `DELETE /news/{id}`

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | string (ULID) | Yes | News ID |

**Example Request:**

```http
DELETE /api/news/01kgjknrxmahq3axv9wcqhjb12
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "News deleted successfully"
}
```

**Error Response (404):**

```json
{
  "success": false,
  "message": "News not found"
}
```

---

## Error Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 201 | Created |
| 401 | Unauthorized (missing or invalid token) |
| 404 | Resource not found |
| 422 | Validation error |
| 500 | Internal server error |

---

## Data Schema

### News Object

```typescript
{
  id: string (ULID)
  school_id: string | null (ULID)
  author_id: string (ULID)
  title: string
  content: string
  images: string[] | null
  target_role: 'admin' | 'guardian' | 'student' | 'all'
  is_published: boolean
  created_at: string (ISO 8601)
  updated_at: string (ISO 8601)
  author?: {
    id: string
    name: string
  }
  school?: {
    id: string
    name: string
  }
}
```

---

## Usage Examples

### Flutter/Dart Example

```dart
import 'package:http/http.dart' as http;
import 'dart:convert';

Future<List<News>> getNews(String token) async {
  final response = await http.get(
    Uri.parse('https://your-domain.com/api/news'),
    headers: {
      'Authorization': 'Bearer $token',
      'Accept': 'application/json',
    },
  );

  if (response.statusCode == 200) {
    final data = json.decode(response.body);
    return (data['data']['data'] as List)
        .map((item) => News.fromJson(item))
        .toList();
  } else {
    throw Exception('Failed to load news');
  }
}
```

### JavaScript/React Example

```javascript
const getNews = async (token) => {
  const response = await fetch('https://your-domain.com/api/news', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error('Failed to fetch news');
  }

  const data = await response.json();
  return data.data.data; // Pagination data
};
```
