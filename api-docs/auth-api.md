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

---

## 3. Verifikasi Email (Direct Link)

Endpoint ini biasanya dipanggil melalui link yang dikirimkan ke email. Merupakan Signed URL.

- **URL:** `/api/verify-email/{id}/{hash}`
- **Method:** `GET`
- **Auth:** `Authenticated`
- **Parameters:** (Automatis dari link email)
  - `id`: User ID
  - `hash`: SHA-1 hash dari email
- **Query Parameters:**
  - `expires`: Timestamp kedaluwarsa
  - `signature`: Signature hash untuk keamanan
- **Responses:**
  - **302 Redirect** (Redirect ke dashboard dengan query `?verified=1`)

---

## 4. Kirim Ulang Verifikasi (Resend Notification)

Digunakan jika pengguna belum menerima email verifikasi atau link sudah kedaluwarsa.

- **URL:** `/api/email/verification-notification`
- **Method:** `POST`
- **Auth:** `Authenticated`
- **Responses:**
  - **200 OK**

      ```json
      {
          "status": "verification-link-sent"
      }
      ```

  - **302 Redirect** (Jika email sudah terverifikasi, akan diredirect ke dashboard)
