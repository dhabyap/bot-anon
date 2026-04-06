# 🤖 Telegram Bot: Secret Sender & Menfess Auto-Base (Laravel)

Bot Telegram yang dirancang khusus untuk berjalan di **Shared Hosting (cPanel)** secara sinkronous (tanpa antrean/Queue). Bot ini memiliki dua fitur utama: pengiriman pesan anonim antar pengguna (**Secret Sender**) dan pengiriman pesan anonim ke Channel (**Menfess Auto-Base**).

---

## 🌟 Fitur Utama
1.  **Personal Secret Sender**:
    -   Pengguna mendapatkan link unik (contoh: `t.me/Bot?start=abc123xyz`).
    -   Orang lain bisa mengirim pesan rahasia melalui link tersebut tanpa diketahui identitasnya.
2.  **Menfess Auto-Base**:
    -   Pengguna bisa mengirim pesan ke Channel publik secara otomatis menggunakan kata kunci (`!curhat`, `!spill`, `!tanya`).
    -   Dukungan untuk pesan **Teks** dan **Foto** (menggunakan `file_id` untuk menghemat penyimpanan server).
    -   **Profanity Filter**: Bot akan menolak pesan yang mengandung kata-kata kasar.
    -   **Automatic Watermark**: Bot akan menambahkan ajakan bergabung di setiap postingan channel.

---

## 🛠️ Panduan Penggunaan Bot (Untuk User)

### 1. Menggunakan Secret Sender
-   **Dapatkan Link**: Ketik `/start` di Bot untuk mendapatkan link profil rahasiamu.
-   **Kirim Pesan**: Buka link milik orang lain (contoh: `t.me/Bot?start=XYZ`), ketik pesan rahasiamu, lalu kirim. Bot akan memberitahu jika pesan berhasil terkirim.

### 2. Menggunakan Menfess (Auto-Base)
-   Pastikan pesanmu diawali dengan pemicu: `!curhat`, `!spill`, atau `!tanya`.
-   **Contoh (Teks)**: `!curhat capek banget pengen liburan tapi duit gak ada.`
-   **Contoh (Foto)**: Kirim foto dan tambahkan caption: `!spill spill dong harga laptop ini.`
-   Jika pesanmu mengandung kata kasar, Bot akan menolaknya secara otomatis.

---

## 💻 Panduan Setup Developer (Source Code)

### 1. Persyaratan Sistem
-   PHP 8.1+
-   Composer
-   Database (MySQL, MariaDB, atau SQLite)
-   SSL (HTTPS) Wajib untuk Telegram Webhook.

### 2. Instalasi
1.  **Clone Repositori**:
    ```bash
    git clone https://github.com/dhabyap/bot-anon.git
    cd bot-anon
    ```
2.  **Instal Dependensi**:
    ```bash
    composer install
    ```
3.  **Setup Environment**:
    Salin `.env.example` menjadi `.env` dan konfigurasikan:
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
4.  **Konfigurasi .env (PENTING)**:
    ```env
    APP_URL=https://domain-kamu.com
    
    TELEGRAM_BOT_TOKEN="kode_token_dari_botfather"
    TELEGRAM_BOT_USERNAME="UsernameBotKamu"
    MENFESS_CHANNEL_ID="-100xxxxxxxxxx" # ID Channel target menfess
    
    DB_CONNECTION=mysql
    DB_DATABASE=bot_anon
    # ... sesuaikan database lainnya
    ```

### 3. Database Migration
Jalankan perintah ini untuk membuat tabel yang diperlukan:
```bash
php artisan migrate
```

### 4. Aktivasi & Cek Webhook
- **Cek Status Bot**: Verifikasi apakah Bot terhubung ke Telegram API dengan mengakses:
  `http://localhost:8000/api/telegram/status` (Lokal) atau
  `https://domain-kamu.com/api/telegram/status` (Production)
  Jika berhasil, Anda akan menerima detail Bot dalam format JSON.
- **Set Webhook**: Hubungkan Bot dengan Telegram melalui:
  `https://domain-kamu.com/api/telegram/set-webhook`

---

## 💡 Tips UX: Mendaftarkan Menu Perintah (Suggestion Menu)
Agar pengguna mendapatkan pengalaman terbaik, Anda harus mendaftarkan perintah bot ke **@BotFather** agar muncul saat pengguna mengetik `/`.

1. Chat [@BotFather](https://t.me/BotFather) di Telegram.
2. Kirim perintah `/setcommands`.
3. Pilih bot Anda.
4. Kirim daftar perintah berikut dalam satu pesan:
   ```text
   start - Dapatkan link Secret Sender kamu
   help - Buka panduan penggunaan lengkap
   curhat - Kirim menfess curhat ke channel
   spill - Kirim menfess spill ke channel
   tanya - Kirim menfess tanya ke channel
   ```
5. Tunggu beberapa saat, dan menu `/` akan muncul di bot Anda!

---

## 🛠️ Cara Test di Lokal (Menggunakan Ngrok)

Karena Telegram membutuhkan URL publik (HTTPS) untuk mengirim Webhook, Anda tidak bisa menggunakan `localhost` secara langsung. Gunakan **Ngrok** untuk membuat tunnel publik ke komputer lokal Anda.

### 1. Persiapan Ngrok
1. Download [Ngrok](https://ngrok.com/download) dan daftar akun gratis.
2. Hubungkan akun Anda dengan perintah:
   ```bash
   ngrok config add-authtoken <TOKEN_NGROK_ANDA>
   ```

### 2. Jalankan Server & Tunnel
1. Jalankan server Laravel Anda:
   ```bash
   php artisan serve
   ```
   *(Default berjalan di http://127.0.0.1:8000)*
2. Jalankan Ngrok di terminal baru:
   ```bash
   ngrok http 8000
   ```
3. Ngrok akan memberikan URL publik seperti: `https://abcd-123.ngrok-free.app`. **Copy URL ini.**

### 3. Konfigurasi Bot
1. Buka file `.env` dan update `APP_URL` dengan URL dari Ngrok tersebut:
   ```env
   APP_URL=https://abcd-123.ngrok-free.app
   ```
2. **PENTING: Aktifkan Webhook melalui URL Ngrok tersebut** dengan mengakses alamat ini di browser:
   `https://abcd-123.ngrok-free.app/api/telegram/set-webhook`
   *(Pastikan muncul pesan status "True" atau "Success")*

### 4. Selesai!
Sekarang Anda bisa mengirim pesan ke Bot di Telegram, dan Laravel di komputer lokal Anda akan menerima dan memproses pesan tersebut secara real-time.

> [!WARNING]
> Setiap kali Anda mematikan Ngrok dan menjalankannya lagi, URL publiknya akan berubah (untuk versi gratis). Jika URL berubah, Anda **WAJIB** mengupdate `.env` dan melakukan **Langkah 3.2 (Set Webhook)** kembali.

---

## 🚀 Tips Deployment (Shared Hosting)
1.  **Root Folder**: Pastikan domain/subdomain kamu diarahkan ke folder `/public`.
2.  **Symlink Storage**: Jalankan `php artisan storage:link` jika diperlukan.
3.  **Cache Driver**: Gunakan `CACHE_DRIVER=file` di `.env` agar lebih ringan dan kompetibel dengan shared hosting.
4.  **Bypass CSRF**: Webhook sudah diatur di `api.php` sehingga otomatis melewati proteksi CSRF Laravel secara default.

---

## ⚖️ Lisensi
Project ini bersifat open-source dan berada di bawah lisensi [MIT](LICENSE).
