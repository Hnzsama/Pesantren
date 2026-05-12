# Skema Database (DBML)

Berikut adalah ringkasan skema database utama SIAP.

```dbml
Table schools {
  id ulid [pk]
  name varchar
  slug varchar
}

Table users {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  name varchar
  email varchar
  role varchar
}

Table students {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  name varchar
  nis varchar [unique]
  wallet_balance decimal
  wallet_pin varchar
}

Table wallet_transactions {
  id ulid [pk]
  student_id ulid [ref: > students.id]
  amount decimal
  type enum // CREDIT, DEBIT
  student_name_snapshot varchar
}

Table bills {
  id ulid [pk]
  student_id ulid [ref: > students.id]
  amount decimal
  status enum // PAID, UNPAID
  student_name_snapshot varchar
}

Table attendances {
  id ulid [pk]
  student_id ulid [ref: > students.id]
  status enum
  recorded_at timestamp
  student_name_snapshot varchar
}

Table products {
  id ulid [pk]
  school_id ulid [ref: > schools.id]
  name varchar
  stock int
  selling_price decimal
}
```
