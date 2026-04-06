# [Feature] Fitur Pengecekan Status Bot (Health Check)

## Deskripsi
Menambahkan endpoint atau halaman sederhana untuk memverifikasi apakah Bot Telegram sudah terkonfigurasi dengan benar dan dapat terhubung ke server Telegram, terutama saat pengujian di lingkungan lokal (localhost).

## Permasalahan
Saat melakukan pengembangan lokal, pengembang sering kali kesulitan mengetahui secara instan apakah:
1.  `TELEGRAM_BOT_TOKEN` di `.env` sudah benar dan valid.
2.  Koneksi internet/server mampu menjangkau API Telegram.
3.  Konfigurasi dasar Bot sudah terbaca oleh Laravel.

## Solusi yang Diusulkan
Menambahkan route bantuan `/api/telegram/status` yang melakukan hal berikut:
-   Memanggil method `getMe` dari Telegram Bot API.
-   Menampilkan informasi dasar bot (ID, First Name, Username) jika koneksi berhasil.
-   Menampilkan pesan error yang deskriptif jika token salah atau koneksi gagal.

## Kriteria Penerimaan
- [ ] Tersedianya route `GET /api/telegram/status` di `api.php`.
- [ ] Method `status()` di `TelegramController` yang mengembalikan respon JSON berisi detail bot.
- [ ] Penanganan error jika API Telegram tidak dapat dijangkau.
- [ ] Dokumentasi di README mengenai cara pengecekan status ini.
