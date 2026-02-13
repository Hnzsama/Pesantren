# Auth & Account Recovery API

Dokumentasi API untuk verifikasi email dan lupa password.

## 1. Lupa Password (Forgot Password)

Digunakan untuk meminta kode OTP reset password yang akan dikirimkan ke email pengguna.

- **URL:** `/api/forgot-password`
- **Method:** `POST`
- **Auth:** `Guest`
- **Request Body:**

  ```json
  {
      "email": "user@example.com"
  }
  ```

- **Responses:**
  - **200 OK**

      ```json
      {
          "status": "otp-sent",
          "message": "Kode OTP reset password telah dikirim ke email Anda."
      }
      ```

  - **404 Not Found** (Email tidak terdaftar)

      ```json
      {
          "message": "Email tidak ditemukan."
      }
      ```

---

## 2. Reset Password (OTP)

Digunakan untuk mengganti password menggunakan kode OTP yang diterima melalui email.

- **URL:** `/api/reset-password`
- **Method:** `POST`
- **Auth:** `Guest`
- **Request Body:**

  ```json
  {
      "email": "user@example.com",
      "otp_code": "123456",
      "password": "passwordBaru123",
      "password_confirmation": "passwordBaru123"
  }
  ```

- **Responses:**
  - **200 OK**

      ```json
      {
          "message": "Password berhasil diubah. Silakan login kembali."
      }
      ```

  - **422 Unprocessable Entity** (OTP salah, expired, atau password tidak valid)

      ```json
      {
          "message": "Kode OTP tidak valid."
      }
      ```

## 3. Verifikasi Email (OTP)

Endpoint ini digunakan untuk memverifikasi alamat email menggunakan kode OTP 6-digit.

### a. Verifikasi OTP

- **URL:** `/api/email/verify-otp`
- **Method:** `POST`
- **Auth:** `Authenticated (Sanctum)`
- **Request Body:**

  ```json
  {
      "otp_code": "123456"
  }
  ```

- **Responses:**
  - **200 OK**

    ```json
    {
        "message": "Email berhasil diverifikasi."
    }
    ```

  - **422 Unprocessable Entity** (Kode salah atau expired)

    ```json
    {
        "message": "Kode OTP tidak valid."
    }
    ```

---

## 4. Kirim Ulang OTP (Resend Notification)

Digunakan untuk meminta kode OTP baru.

- **URL:** `/api/email/verification-notification`
- **Method:** `POST`
- **Auth:** `Authenticated (Sanctum)`
- **Responses:**
  - **200 OK**

    ```json
    {
        "status": "otp-sent",
        "message": "Kode OTP baru telah dikirim ke email Anda."
    }
    ```
