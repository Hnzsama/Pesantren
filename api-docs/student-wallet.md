# Student Wallet Settings API

Dokumentasi untuk pembaharuan PIN Wallet dan Limit Tarik Tunai Harian Santri.

## Middleware

`auth:sanctum` (Wali Santri only)

---

## 1. Detail Profil Santri

Mendapatkan detail profil santri, sekarang menyertakan status PIN.

**Endpoint:** `GET /api/students/{id}`

### Response (200 OK)

```json
{
    "student": {
        "id": "...",
        "has_pin": true,
        "daily_cash_limit": "50000.00",
        ...
    }
}
```

---

## 2. Update Wallet PIN

Digunakan untuk mengatur atau mengubah PIN transaksi wallet santri (6 digit).

**Endpoint:** `POST /api/students/{id}/wallet/pin`

### Request Body

```json
{
    "pin": "123456",
    "pin_confirmation": "123456"
}
```

### Parameters

| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `pin` | string | required, numeric, 6 digits | PIN baru (6 angka) |
| `pin_confirmation` | string | required | Harus sama dengan field `pin` |

### Success Response (200 OK)

```json
{
    "message": "Wallet PIN berhasil diperbarui."
}
```

---

## 3. Update Daily Cash Limit

Digunakan untuk membatasi nominal maksimal tarik tunai harian santri.

**Endpoint:** `POST /api/students/{id}/wallet/limit`

### Request Body

```json
{
    "daily_cash_limit": 100000
}
```

### Parameters

| Field | Type | Rules | Description |
| :--- | :--- | :--- | :--- |
| `daily_cash_limit` | number | required, numeric, min 0 | Nominal limit harian |

### Success Response (200 OK)

```json
{
    "message": "Limit tarik tunai harian berhasil diperbarui."
}
```

### Response Check (Wallet)

Endpoint `GET /api/students/{id}/wallet` juga sekarang mengembalikan `has_pin`:

```json
{
    "wallet_balance": "200000.00",
    "has_pin": true
}
```
