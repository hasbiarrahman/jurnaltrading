<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\TelegramRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SettingController extends Controller
{
    /**
     * Display the settings form.
     */
    public function index()
    {
        $apiKey = Setting::where('key', 'tokocrypto_api_key')->value('value') ?? '';
        $apiSecret = Setting::where('key', 'tokocrypto_api_secret')->value('value') ?? '';

        $telegramBotToken = Setting::where('key', 'telegram_bot_token')->value('value') ?? '';
        $telegramAlertThreshold = Setting::where('key', 'telegram_alert_threshold')->value('value') ?? '2.0';
        $telegramAlertEnabled = Setting::where('key', 'telegram_alert_enabled')->value('value') ?? '1';
        
        $telegramRecipients = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('telegram_recipients')) {
            $telegramRecipients = TelegramRecipient::all();
        }

        return view('setting.index', compact(
            'apiKey',
            'apiSecret',
            'telegramBotToken',
            'telegramAlertThreshold',
            'telegramAlertEnabled',
            'telegramRecipients'
        ));
    }

    /**
     * Update the settings in database.
     */
    public function update(Request $request)
    {
        $request->validate([
            'tokocrypto_api_key' => 'nullable|string|max:255',
            'tokocrypto_api_secret' => 'nullable|string|max:255',
        ]);

        Setting::updateOrCreate(
            ['key' => 'tokocrypto_api_key'],
            ['value' => trim($request->tokocrypto_api_key ?? '')]
        );

        Setting::updateOrCreate(
            ['key' => 'tokocrypto_api_secret'],
            ['value' => trim($request->tokocrypto_api_secret ?? '')]
        );

        return redirect()->route('setting.index')->with('success', 'Konfigurasi API Tokocrypto berhasil diperbarui.');
    }

    /**
     * Export database tables to a downloadable JSON file.
     */
    public function exportDatabase()
    {
        $data = [
            'users' => DB::table('users')->get()->toArray(),
            'settings' => DB::table('settings')->get()->toArray(),
            'watchlists' => DB::table('watchlists')->get()->toArray(),
            'trades' => DB::table('trades')->get()->toArray(),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $filename = 'database_backup_' . date('Y-m-d_H-i-s') . '.json';

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Import database tables from an uploaded JSON file.
     */
    public function importDatabase(Request $request)
    {
        $request->validate([
            'backup_file' => 'required|file|mimes:json',
        ]);

        $file = $request->file('backup_file');
        $jsonData = file_get_contents($file->getRealPath());
        $data = json_decode($jsonData, true);

        if (!$data || !is_array($data)) {
            return redirect()->route('setting.index')->with('error', 'Format file backup JSON tidak valid.');
        }

        // Verify required tables exist in backup
        $requiredTables = ['users', 'settings', 'watchlists', 'trades'];
        foreach ($requiredTables as $table) {
            if (!isset($data[$table]) || !is_array($data[$table])) {
                return redirect()->route('setting.index')->with('error', 'Data backup tidak lengkap. Tabel ' . $table . ' tidak ditemukan.');
            }
        }

        try {
            DB::transaction(function () use ($data) {
                // Disable foreign keys temporarily
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');

                // 1. Restore Settings
                DB::table('settings')->truncate();
                foreach ($data['settings'] as $item) {
                    DB::table('settings')->insert([
                        'id' => $item['id'] ?? null,
                        'key' => $item['key'],
                        'value' => $item['value'] ?? '',
                        'created_at' => $item['created_at'] ?? now(),
                        'updated_at' => $item['updated_at'] ?? now(),
                    ]);
                }

                // 2. Restore Watchlists
                DB::table('watchlists')->truncate();
                foreach ($data['watchlists'] as $item) {
                    DB::table('watchlists')->insert([
                        'id' => $item['id'] ?? null,
                        'symbol' => $item['symbol'],
                        'notes' => $item['notes'] ?? null,
                        'created_at' => $item['created_at'] ?? now(),
                        'updated_at' => $item['updated_at'] ?? now(),
                    ]);
                }

                // 3. Restore Trades
                DB::table('trades')->truncate();
                foreach ($data['trades'] as $item) {
                    DB::table('trades')->insert([
                        'id' => $item['id'] ?? null,
                        'symbol' => $item['symbol'],
                        'type' => $item['type'],
                        'price' => $item['price'],
                        'amount' => $item['amount'],
                        'total' => $item['total'],
                        'trade_time' => $item['trade_time'],
                        'stoch_rsi_k' => $item['stoch_rsi_k'] ?? null,
                        'stoch_rsi_d' => $item['stoch_rsi_d'] ?? null,
                        'divergence' => $item['divergence'] ?? 'None',
                        'notes' => $item['notes'] ?? null,
                        'created_at' => $item['created_at'] ?? now(),
                        'updated_at' => $item['updated_at'] ?? now(),
                    ]);
                }

                // 4. Restore Users (using updateOrInsert to secure active session)
                foreach ($data['users'] as $item) {
                    DB::table('users')->updateOrInsert(
                        ['email' => $item['email']],
                        [
                            'name' => $item['name'],
                            'password' => $item['password'],
                            'role' => $item['role'] ?? 'investor',
                            'created_at' => $item['created_at'] ?? now(),
                            'updated_at' => $item['updated_at'] ?? now(),
                        ]
                    );
                }

                // Enable foreign keys back
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            });

            return redirect()->route('setting.index')->with('success', 'Database berhasil di-import. Semua data perdagangan, watchlist, dan konfigurasi telah diselaraskan.');
        } catch (\Exception $e) {
            Log::error('Import database failed: ' . $e->getMessage());
            return redirect()->route('setting.index')->with('error', 'Gagal melakukan import database: ' . $e->getMessage());
        }
    }

    /**
     * Get pending database queries (local only).
     */
    public function getPendingQueries()
    {
        if (config('app.env') !== 'local') {
            return response()->json(['error' => 'Forbidden on production.'], 403)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
        }

        $logPath = storage_path('app/pending_queries.json');
        $queries = [];
        if (file_exists($logPath)) {
            $queries = json_decode(file_get_contents($logPath), true) ?? [];
        }

        return response()->json($queries)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
    }

    /**
     * Clear pending database queries (local only).
     */
    public function clearPendingQueries()
    {
        if (config('app.env') !== 'local') {
            return response()->json(['error' => 'Forbidden on production.'], 403)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
        }

        $logPath = storage_path('app/pending_queries.json');
        if (file_exists($logPath)) {
            file_put_contents($logPath, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);
        }

        return response()->json(['success' => true])
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
    }

    /**
     * Apply queries received from the browser (production only).
     */
    public function applyPendingQueries(Request $request)
    {
        $request->validate([
            'queries' => 'required|array',
            'queries.*.sql' => 'required|string',
        ]);

        $queries = $request->input('queries');

        try {
            DB::transaction(function () use ($queries) {
                // Disable foreign keys temporarily
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');

                foreach ($queries as $q) {
                    $sql = $q['sql'];
                    // Execute raw statement
                    DB::statement($sql);
                }

                // Enable foreign keys back
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            });

            return response()->json([
                'success' => true, 
                'message' => count($queries) . ' perubahan database berhasil disinkronkan.'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to apply queries: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Gagal menerapkan query: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Telegram credentials and alert threshold.
     */
    public function updateTelegram(Request $request)
    {
        $request->validate([
            'telegram_bot_token' => 'nullable|string|max:255',
            'telegram_alert_threshold' => 'required|numeric|min:0.01|max:100',
            'telegram_alert_enabled' => 'required|in:0,1',
        ]);

        Setting::updateOrCreate(
            ['key' => 'telegram_bot_token'],
            ['value' => trim($request->telegram_bot_token ?? '')]
        );

        Setting::updateOrCreate(
            ['key' => 'telegram_alert_threshold'],
            ['value' => trim($request->telegram_alert_threshold)]
        );

        Setting::updateOrCreate(
            ['key' => 'telegram_alert_enabled'],
            ['value' => trim($request->telegram_alert_enabled)]
        );

        return redirect()->route('setting.index')->with('success', 'Konfigurasi Telegram berhasil diperbarui.');
    }

    /**
     * Store a new Telegram recipient.
     */
    public function storeTelegramRecipient(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'chat_id' => 'required|string|max:100|unique:telegram_recipients,chat_id',
        ]);

        TelegramRecipient::create([
            'name' => trim($request->name),
            'chat_id' => trim($request->chat_id),
            'is_active' => true,
        ]);

        return redirect()->route('setting.index')->with('success', 'Penerima notifikasi Telegram berhasil didaftarkan.');
    }

    /**
     * Toggle active state of a Telegram recipient.
     */
    public function toggleTelegramRecipient($id)
    {
        $recipient = TelegramRecipient::findOrFail($id);
        $recipient->is_active = !$recipient->is_active;
        $recipient->save();

        return redirect()->route('setting.index')->with('success', 'Status penerima Telegram berhasil diperbarui.');
    }

    /**
     * Delete a Telegram recipient.
     */
    public function destroyTelegramRecipient($id)
    {
        $recipient = TelegramRecipient::findOrFail($id);
        $recipient->delete();

        return redirect()->route('setting.index')->with('success', 'Penerima Telegram berhasil dihapus.');
    }

    /**
     * Test sending a welcome message to a specific Telegram Chat ID.
     */
    public function testTelegramRecipient($id)
    {
        $recipient = TelegramRecipient::findOrFail($id);
        $botToken = Setting::where('key', 'telegram_bot_token')->value('value');

        if (empty($botToken)) {
            return redirect()->route('setting.index')->with('error', 'Gagal mengirim test: Bot Token Telegram belum dikonfigurasi.');
        }

        $message = "🔔 *Uji Coba Notifikasi Jurnal Trading*\n\n"
                 . "Halo *{$recipient->name}*,\n"
                 . "Koneksi bot Telegram Anda ke sistem *Jurnal Trading* berhasil terhubung 100%! "
                 . "Anda akan menerima notifikasi otomatis jika terdapat pergerakan harga aset portfolio Anda di atas ambang batas yang ditentukan.";

        try {
            $response = Http::timeout(6)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $recipient->chat_id,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                return redirect()->route('setting.index')->with('success', "Test notifikasi berhasil dikirim ke {$recipient->name}.");
            } else {
                $errorData = $response->json();
                $errorMsg = $errorData['description'] ?? $response->body();
                return redirect()->route('setting.index')->with('error', "Telegram API Error: " . $errorMsg);
            }
        } catch (\Exception $e) {
            return redirect()->route('setting.index')->with('error', "Gagal menghubungi Telegram API: " . $e->getMessage());
        }
    }
}
