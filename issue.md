# Implementasi Bot Telegram "Secret Sender" (Anonymous Message) menggunakan Laravel

Berikut adalah panduan struktur maupun cuplikan kode lengkap untuk merancang bot Telegram Secret Sender dalam environment **Shared Hosting cPanel**, berjalan secara **Synchronous via Webhook**, dan hanya mengandalkan fitur bawaan Laravel tanpa package pihak ketiga yang berat.

## Batasan & Solusi
1. **Shared Hosting:** Kode dioptimasi agar proses terjadi cepat saat HTTP request dari Telegram masuk, sebelum limit timeout hosting (umumnya 30s-60s).
2. **Tanpa Background Process:** Menggunakan *File atau Database Cache* bawaan Laravel sebagai tempat *state* saat ini (`drafting`), membiarkan logika berjalan saat webhook dipanggil, tidak butuh Queue atau Long Polling.
3. **Murni Webhook & Bebas Package:** Hanya menggunakan HTTP Facades Laravel untuk pemanggilan `sendMessage` Telegram API.

---

## 1. File Migration Database
Tabel `bot_users` digunakan untuk menyimpan koneksi antara `chat_id` Telegram dan `unique_code` sebagai parameter *deep link*.

Jalankan perintah lokal/server: `php artisan make:migration create_bot_users_table`
Lalu update isinya:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_users', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id')->unique(); // ID Telegram User 
            $table->string('unique_code')->unique(); // String unik untuk format ?start={code}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_users');
    }
};
```

---

## 2. Definisi Routes & Bypass CSRF
Karena kita merekomendasikan penempatan Webhook pada area yang tidak terkena proteksi CSRF, sebaiknya **gunakan `routes/api.php`** karena secara default, Laravel tidak memberlakukan CSRF checking pada file route API.

Edit **`routes/api.php`**:
```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;

// Webhook endpoint dari Telegram
Route::post('/telegram/webhook', [TelegramController::class, 'handleWebhook']);

// Helper endpoint untuk mengatur setWebhook dengan mudah
Route::get('/telegram/set-webhook', [TelegramController::class, 'setWebhook']);
```

> **INFO PENTING (Bypass CSRF Jika Mamakai Web.php):**
> Jika Anda terpaksa menggunakan `routes/web.php`, Anda wajib mengecualikan route tersebut.
> - **Laravel 10 ke bawah:** Tambahkan `/telegram/webhook` pada `$except` dalam file `app/Http/Middleware/VerifyCsrfToken.php`.
> - **Laravel 11:** Tambahkan setting ini dalam `bootstrap/app.php`: 
>   `$middleware->validateCsrfTokens(except: ['telegram/webhook']);`

---

## 3. Controller Logika Utama (`TelegramController.php`)
Ini adalah jantung aplikasi. File ini akan menangani trigger webhook dari Telegram, Deep Linking Payload, Cache status mengetik, hingga pemanggilan API Telegram melalui Facades `Http`.

Buat atau edit file: **`app/Http/Controllers/TelegramController.php`**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramController extends Controller
{
    protected $botToken;
    protected $apiUrl;
    protected $botUsername;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        // Jangan lupa ganti ini dengan Username Bot Anda!
        $this->botUsername = 'UsernameBotKamu'; 
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
    }

    public function handleWebhook(Request $request)
    {
        // Ambil data payload masuk dari Webhook telegram
        $update = $request->all();

        // Validasi payload (Pastikan pesan valid dari pengguna Telegram)
        if (isset($update['message']['text']) && isset($update['message']['chat']['id'])) {
            $chatId = $update['message']['chat']['id'];
            $text = trim($update['message']['text']);

            // 1. Apakah user memanggil command /start ?
            if (Str::startsWith($text, '/start')) {
                $this->handleStartCommand($chatId, $text);
            } 
            // 2. Jika text biasa, cek apakah user sedang ingin mengirim pesan rahasia
            else {
                $this->handleIncomingMessage($chatId, $text);
            }
        }

        // Penting: Selalu kembalikan respon 200 HTTP OK secepatnya ke server Telegram 
        // agar webhook tidak me-retry (looping pesan error)
        return response()->json(['status' => 'success'], 200);
    }

    protected function handleStartCommand($chatId, $text)
    {
        // Pisahkan command dari payload (Contoh: /start abc123def -> abc123def)
        $payload = trim(str_replace('/start', '', $text));

        if (empty($payload)) {
            // [LOGIKA 1]: User mengetik "/start" tanpa payload
            // Cek user sudah terdaftar di database?
            $user = DB::table('bot_users')->where('chat_id', $chatId)->first();

            if (!$user) {
                // User baru, buatkan unique_code random sepanjang 10 karakter
                $uniqueCode = Str::random(10);
                DB::table('bot_users')->insert([
                    'chat_id' => $chatId,
                    'unique_code' => $uniqueCode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // User lama, gunakan yang sudah ada
                $uniqueCode = $user->unique_code;
            }

            // Generate URL bot
            $link = "https://t.me/{$this->botUsername}?start={$uniqueCode}";
            
            $replyMessage = "Halo! Ini adalah link Secret Sender kamu:\n\n" .
                            "$link\n\n" .
                            "Kirimkan link ini ke Story WhatsApp/IG agar mereka bisa mengirimimu pesan rahasia!\n\n" .
                            "Contoh caption (Bisa kamu copy & paste):\n" .
                            "Kirim pesan anonim buat aku dong! Gak akan ketahuan siapa yang kirim, sikat di mari:\n$link";

            $this->sendMessage($chatId, $replyMessage);

        } else {
            // [LOGIKA 2]: User mengetik "/start {unique_code}" (Deep Link Action)
            // Cari siapa pemilik unique code tersebut
            $targetUser = DB::table('bot_users')->where('unique_code', $payload)->first();

            if ($targetUser) {
                // Cegah agar tidak mengirimi pesan pada diri sendiri
                if ($targetUser->chat_id == $chatId) {
                    $this->sendMessage($chatId, "Kamu tidak bisa mengirim pesan anonim ke dirimu sendiri!");
                    return;
                }

                // Berikan State ke si pengirim "sedang dalam status draf pesan rahasia" 
                // Set parameter di Laravel Cache, simpan target payload dan beri kadaluarsa (misal: 1 jam)
                Cache::put("draft_{$chatId}", $payload, now()->addMinutes(60));

                $this->sendMessage($chatId, "Silakan ketik pesan rahasia kamu. Identitasmu akan dirahasiakan.");
            } else {
                $this->sendMessage($chatId, "Link tidak valid atau pengguna tujuan tidak ditemukan.");
            }
        }
    }

    protected function handleIncomingMessage($chatId, $text)
    {
        // Akses Laravel Cache untuk melihat mode/status user ini (apabila ada draft)
        $targetUniqueCode = Cache::get("draft_{$chatId}");

        if ($targetUniqueCode) {
            // User memang sedang dalam mode draft, cari tujuan telegramnya
            $targetUser = DB::table('bot_users')->where('unique_code', $targetUniqueCode)->first();

            if ($targetUser) {
                // [TINDAKAN] Kirim pesan ke Tujuan
                $secretMessage = "Kamu mendapat pesan rahasia baru: \n\n" . htmlspecialchars($text);
                $this->sendMessage($targetUser->chat_id, $secretMessage);

                // [BERSIHKAN] Cabut State mode draft dari Cache agar sesi selesai
                Cache::forget("draft_{$chatId}");

                // Beritahukan si pengirim bahwa telah berhasil
                $this->sendMessage($chatId, "Pesan berhasil dikirim secara anonim!");
            } else {
                Cache::forget("draft_{$chatId}");
                $this->sendMessage($chatId, "Pengiriman gagal. Pemilik link sudah tidak merespons atau akun terhapus.");
            }
        } else {
            // Jika bukan mau kirim pesan rahasia, arahkan mereka
            $this->sendMessage($chatId, "Ketik /start untuk mendapatkan link Secret Sender kamu sendiri!");
        }
    }

    protected function sendMessage($chatId, $text)
    {
        // Pakai Guzzle Http facade defaultnya laravel, cepat & efisien.
        Http::post($this->apiUrl . 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ]);
    }

    public function setWebhook()
    {
        /* NOTE: PASTIKAN APP_URL DI .ENV ADALAH HTTPS DOMAIN ANDA */
        $webhookUrl = url('/api/telegram/webhook');
        
        $response = Http::get($this->apiUrl . 'setWebhook', [
            'url' => $webhookUrl
        ]);

        return response()->json($response->json());
    }
}
```

---

## 4. Instruksi Setup Terakhir

1. Masukkan *Bot Token* melalui `.env` Anda:
   ```env
   TELEGRAM_BOT_TOKEN="000000000:AAGXXXX-xxxxx_xxx"
   APP_URL="https://domain-website-anda.com"
   ```
2. Pastikan file Session/Cache yang digunakan berjalan (`file` sangat cocok untuk Shared Hosting):
   ```env
   CACHE_STORE=file
   ```
3. Lakukan deploy kode di Shared Hosting dan pastikan Migration ter-eksekusi pada Database Cpanel.
4. Hubungkan website dengan API Telegram!
   Buka Browser dan Akses halaman:
   **`https://domain-website-anda.com/api/telegram/set-webhook`**
   Anda harus mendapatkan tulisan respons dari Telegram `"ok":true,"result":true`.
5. Uji coba bot Telegram Anda. Semua operasi murni akan berjalan lancar dengan sangat efisien secara sinkronous. No Queue logic!
