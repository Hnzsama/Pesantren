# Auth & Account Recovery API

Dokumentasi API untuk verifikasi email dan lupa password.

## 1. Lupa Password (Forgot Password)

Digunakan untuk meminta link reset password yang akan dikirimkan ke email pengguna.

- **URL:** `/api/forgot-password`
- **Method:** `POST`
- **Auth:** `Guest` (Optional)
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
          "status": "We have emailed your password reset link."
      }
      ```

  - **422 Unprocessable Entity** (Email tidak ditemukan atau error validasi)

      ```json
      {
          "message": "The email field is required.",
          "errors": {
              "email": ["We can't find a user with that email address."]
          }
      }
      ```

---

## 2. Reset Password

Digunakan untuk mengganti password setelah menerima token dari email.

- **URL:** `/api/reset-password`
- **Method:** `POST`
- **Auth:** `Guest`
- **Request Body:**

  ```json
  {
      "token": "token-dari-email",
      "email": "user@example.com",
      "password": "passwordBaru123",
      "password_confirmation": "passwordBaru123"
  }
  ```

- **Responses:**
  - **200 OK**

      ```json
      {
          "status": "Your password has been reset."
      }
      ```

  - **422 Unprocessable Entity** (Token expired atau password tidak cocok)

      ```json
      {
          "message": "The password field confirmation does not match.",
          "errors": {
              "email": ["This password reset token is invalid."]
          }
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
