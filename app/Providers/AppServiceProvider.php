<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'local') {
            \Illuminate\Support\Facades\DB::listen(function ($query) {
                $sql = $query->sql;
                $bindings = $query->bindings;

                // Only record insert, update, and delete queries
                if (preg_match('/^\s*(insert|update|delete)/i', $sql)) {
                    // Check if it targets one of our core tables
                    if (preg_match('/(trades|watchlists|settings|users)/i', $sql)) {
                        // Prevent logging queries related to session/cache/jobs/migrations
                        if (str_contains($sql, 'sessions') || str_contains($sql, 'cache') || str_contains($sql, 'jobs') || str_contains($sql, 'migrations')) {
                            return;
                        }

                        // Reconstruct the raw SQL statement with bindings
                        foreach ($bindings as $binding) {
                            if ($binding instanceof \DateTime) {
                                $val = "'" . $binding->format('Y-m-d H:i:s') . "'";
                            } elseif (is_numeric($binding)) {
                                $val = $binding;
                            } elseif (is_null($binding)) {
                                $val = 'NULL';
                            } else {
                                $val = "'" . addslashes($binding) . "'";
                            }
                            // Replace the first ? parameter placeholder
                            $sql = preg_replace('/\?/', $val, $sql, 1);
                        }

                        // Append to the local pending queries file
                        $logPath = storage_path('app/pending_queries.json');
                        $queries = [];
                        if (file_exists($logPath)) {
                            $queries = json_decode(file_get_contents($logPath), true) ?? [];
                        }

                        $queries[] = [
                            'id' => uniqid(),
                            'sql' => $sql,
                            'timestamp' => time(),
                        ];

                        file_put_contents($logPath, json_encode($queries, JSON_PRETTY_PRINT), LOCK_EX);
                    }
                }
            });
        }
    }
}
