# Laravel Telegram Shop

Telegram Shop yang di buat khusus untuk produk digital.

## Requirement

-   \>= PHP 8.2
-   MySQL / MariaDB
-   Composer
    Aplikasi ini bisa diinstall di CPanel atau DirectAdmin, tapi pastikan bisa akses terminal atau ssh

## Deployment

Untuk deployment silahkan clone/download source code nya di repo ini

```bash
  git clone https://github.com/AchmadBudy/laravel-telegram-shop.git
```

Masuk ke dalam foldernya dan lakukan instalasi modul dengan composer

```bash
    composer install --no-dev
```

Buat .env file

```bash
    cp .env.example .env
```

Generate Key

```bash
    php artisan key:generate
```

Ubah value di .env

```bash
APP_NAME = nama aplikasi
APP_ENV = production
APP_DEBUG = false
APP_URL=
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

Migrasi database

```bash
    php artisan migrate
```

Buat akun admin untuk akses admin panel

```bash
    php artisan make:filament-user
```

Cofigurasi setelah itu harus setting paydisini dan telegram di admin panel

## todo

-   [x] Perbaiki View pada admin transaksi
-   [ ] Perbaiki navigasi pada admin panel
-   [x] Menambahkan halaman utama untuk menampilkan produk dan link ke bot telegram

todo akan bertambah kalau kepirinan dan untuk mempercantik aplikasi
