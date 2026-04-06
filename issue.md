# [Feature] Fitur Secret Sender & Menfess Auto-Base Telegram Bot

## Deskripsi Singkat
Pengembangan arsitektur Bot Telegram menggunakan framework Laravel untuk menangani dua fungsi utama: **Personal Secret Sender** (pengiriman pesan anonim antar pengguna) dan **Menfess Auto-Base** (publikasi pesan anonim ke suatu Channel Telegram).

## Batasan & Kendala Infrastruktur (Constraints)
Mengingat infrastruktur yang digunakan adalah **Shared Hosting cPanel**, maka seluruh logika harus tunduk pada batasan berikut:
- **Synchronous & Tanpa Antrean (No Queues):** Tidak menggunakan supervisor, daemon, maupun `queue:work`. Seluruh siklus eksekusi memproses request webhook harus selesai saat itu juga (sebelum HTTP Timeout).
- **Murni Webhook:** Webhook akan digunakan sebagai jembatan tunggal (tidak menggunakan skema Long-Polling).
- **Native Implementation:** Memanfaatkan `Illuminate\Support\Facades\Http` bawaan dari Laravel untuk interaksi endpoint API Telegram demi menghindari dependensi pihak ketiga/package ukuran besar.

---

## Spesifikasi Kebutuhan Fungsional (Functional Requirements)

### 1. Struktur Data & Konfigurasi Dasar
- **Tabel Database:** Butuh skema tabel `bot_users` sederhana berisikan `id`, `chat_id` (Telegram User ID), dan `unique_code` (alias untuk profil link anonim mereka).
- **Environment Context:** Penambahan key variabel lingkungan di file `.env` yaitu `TELEGRAM_BOT_TOKEN` dan `MENFESS_CHANNEL_ID`.

### 2. Fitur: Personal Secret Sender (Deep Linking)
Sistem memfasilitasi user A untuk mengirim pesan kepada User B tanpa sang penerima (User B) mengetahui siapa pengirimnya.
- **Pendaftaran / Generate Link:** Apabila user mengeksekusi `/start`, sistem memverifikasi apakah akun telah ada. Jika belum, data `chat_id` akan direkam beserta hasil *generate* string `unique_code`. Sistem akan membalas dengan format URL *deep linking* rahasia profil user.
- **Persiapan Mengirim (Drafting):** Apabila user mengakses link `/start {unique_code}`, sistem tidak akan berinteraksi dengan database tujuan lagi melainkan mengunci sesi sementara pengguna sebagai "Sedang merancang pesan" ke pemilik parameter `{unique_code}` tersebut dengan menggunakan **Laravel Cache** (*file driver*).
- **Eksekusi Pesan Anonim:** Segala balasan teks konvensional user akan diinspeksi. Jika mesin mendeteksi pengirim berada dalam *mode draf*, mesin akan membaca chat tujuan dari dalam sistem *Cache* lalu meneruskan paket pesan anonim tersebut kepada sang target sebelum mengeksekusi pembersihan riwayat *Cache*.

### 3. Fitur: Menfess Auto-Base (Channel Auto-Post)
Sistem menerima kiriman pesan dan secara berantai melakukan pemfilteran hingga menyalurkannya menuju Channel base publik.
- **Trigger Word Checker:** Mesin mengevaluasi teks awal (*prefix text* atau *caption gambar*) menggunakan parameter pemicu semisal `!curhat`, `!spill`, `!tanya`.
- **Profanity Filter (Kata Kasar):** Teks diperiksa melalui validasi array internal berisikan konstan leksikal makian standar. Terdeteksinya interupsi ini menyebabkan pengiriman auto-base ditolak secara sepihak dengan notifikasi balasan kepada pengguna.
- **Posting Logic (Text & Image Support):** 
  - Pesan lolos sensor akan disispkan dengan sebuah *watermark text* navigasi otomatis pada ujung akhir pesan (Panggilan ajakan mengirim menfess via Bot).
  - Khusus pesan bercampur gambar, sistem akan menggunakan metode *direct forwarding* menggunakan meta-data representatif `file_id` bawaan API Telegram. Sistem dilarang merender, mengunduh file media ke dalam lokal memori (demi menghemat Storage Shared Hosting).

---

## Kriteria Penerimaan (Acceptance Criteria)
- [ ] Tersedianya file *Migration* yang memetakan kolom di database.
- [ ] Controller pengelola webhook mampu memilah Request *(Command vs General Text)* dan mengambil keputusan yang tepat untuk masuk ke fitur nomor 1 atau fitur nomor 2.
- [ ] Pengiriman/Forwarding gambar ke Channel tanpa *downloading overhead* (hanya membawa ekstrak `file_id`).
- [ ] Operasional bot berjalan secara instan (*Low Latency*) yang kompatibel pada mode `sync` hosting standar.
- [ ] Menggunakan format bypass CSRF otomatis untuk kelancaran *Route Endpoint Webhook*.
