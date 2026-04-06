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
    protected $channelId;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->botUsername = env('TELEGRAM_BOT_USERNAME', 'SecretSenderBot');
        $this->channelId = env('MENFESS_CHANNEL_ID');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
    }

    /**
     * Webhook endpoint from Telegram
     */
    public function handleWebhook(Request $request)
    {
        $update = $request->all();

        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            
            // Get text or caption
            $text = trim($message['text'] ?? $message['caption'] ?? '');
            $photo = $message['photo'] ?? null;

            // [FITUR 2] Menfess Auto-Base to Channel
            if (Str::startsWith(strtolower($text), ['!curhat', '!spill', '!tanya'])) {
                $this->handleMenfess($chatId, $text, $photo);
            } 
            // [FITUR 1] Personal Secret Sender (Only text, no photo)
            else if (!$photo) {
                if (Str::startsWith($text, '/start')) {
                    $this->handleStartCommand($chatId, $text);
                } else {
                    $this->handleIncomingMessage($chatId, $text);
                }
            }
        }

        // Always return 200 OK to Telegram
        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Handle Menfess feature (posting to channel)
     */
    protected function handleMenfess($chatId, $text, $photo)
    {
        // 1. Profanity Filter (Case-insensitive)
        $badWords = ['anjing', 'babi', 'bangsat', 'kontol', 'memek', 'ngentot'];
        foreach ($badWords as $word) {
            if (Str::contains(strtolower($text), $word)) {
                $this->sendMessage($chatId, "Pesan ditolak karena mengandung kata kasar.");
                return;
            }
        }

        // 2. Add Watermark
        $watermark = "\n\n---\n🤖 Kirim menfess anonimmu di @{$this->botUsername}";
        $finalText = $text . $watermark;

        // 3. Post to Channel
        if ($photo) {
            // Get highest resolution photo (the last one in the array)
            $fileId = end($photo)['file_id'];
            $this->sendPhoto($this->channelId, $fileId, $finalText);
        } else {
            $this->sendMessage($this->channelId, $finalText);
        }

        // Notify user
        $this->sendMessage($chatId, "Menfess berhasil dipublish ke channel!");
    }

    protected function handleStartCommand($chatId, $text)
    {
        $payload = trim(str_replace('/start', '', $text));

        if (empty($payload)) {
            // Logic 1: No payload - Register user or show their link
            $user = DB::table('bot_users')->where('chat_id', $chatId)->first();

            if (!$user) {
                $uniqueCode = Str::random(10);
                DB::table('bot_users')->insert([
                    'chat_id' => $chatId,
                    'unique_code' => $uniqueCode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $uniqueCode = $user->unique_code;
            }

            $link = "https://t.me/{$this->botUsername}?start={$uniqueCode}";
            
            $reply = "Halo! Ini adalah link Secret Sender kamu:\n\n" .
                     "{$link}\n\n" .
                     "Bagikan link ini untuk menerima pesan anonim!\n\n" .
                     "Atau gunakan awalan !curhat, !spill, atau !tanya untuk posting anonim ke Channel.\n\n" .
                     "Contoh caption:\n" .
                     "Kirim pesan anonim buat aku dong! Sikat di sini:\n{$link}";

            $this->sendMessage($chatId, $reply);

        } else {
            // Logic 2: With payload - Start drafting message for someone
            $targetUser = DB::table('bot_users')->where('unique_code', $payload)->first();

            if ($targetUser) {
                if ($targetUser->chat_id == $chatId) {
                    $this->sendMessage($chatId, "Kamu tidak bisa mengirim pesan rahasia ke diri sendiri!");
                    return;
                }

                // Set draft state in Cache for 1 hour
                Cache::put("draft_{$chatId}", $payload, now()->addMinutes(60));

                $this->sendMessage($chatId, "Silakan ketik pesan rahasiamu. Identitasmu akan dirahasiakan.");
            } else {
                $this->sendMessage($chatId, "Link tidak valid atau pengguna tidak ditemukan.");
            }
        }
    }

    protected function handleIncomingMessage($chatId, $text)
    {
        $targetUniqueCode = Cache::get("draft_{$chatId}");

        if ($targetUniqueCode) {
            $targetUser = DB::table('bot_users')->where('unique_code', $targetUniqueCode)->first();

            if ($targetUser) {
                $secretMessage = "Kamu mendapat pesan rahasia baru: \n\n" . htmlspecialchars($text);
                $this->sendMessage($targetUser->chat_id, $secretMessage);

                // Clear draft state
                Cache::forget("draft_{$chatId}");

                $this->sendMessage($chatId, "Pesan berhasil dikirim secara anonim!");
            } else {
                Cache::forget("draft_{$chatId}");
                $this->sendMessage($chatId, "Gagal mengirim. Pengguna tujuan sudah tidak ditemukan.");
            }
        } else {
            $this->sendMessage($chatId, "Ketik /start untuk mendapatkan link Secret Sender kamu sendiri!");
        }
    }

    protected function sendMessage($chatId, $text)
    {
        Http::post($this->apiUrl . 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ]);
    }

    /**
     * Check Bot Status (Health Check)
     */
    public function status()
    {
        try {
            $response = Http::get($this->apiUrl . 'getMe');
            $data = $response->json();

            if (isset($data['ok']) && $data['ok'] === true) {
                return response()->json([
                    'status' => 'connected',
                    'bot_details' => $data['result']
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $data['description'] ?? 'Gagal terhubung ke Telegram API'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Exception: ' . $e->getMessage()
            ], 500);
        }
    }

    public function setWebhook()
    {
        $webhookUrl = url('/api/telegram/webhook');
        
        $response = Http::get($this->apiUrl . 'setWebhook', [
            'url' => $webhookUrl
        ]);

        return response()->json($response->json());
    }
}
