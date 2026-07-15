<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ScanAltcoins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:altcoins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Node.js quantitative altcoin scanner';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting altcoin scan...');
        
        $scriptPath = base_path('bin/altcoin_scanner.js');
        
        if (!file_exists($scriptPath)) {
            $this->error("Scanner script not found at {$scriptPath}");
            return 1;
        }

        // Bypass SSL rejects for KuCoin fetch locally
        $process = new Process(['node', $scriptPath], null, [
            'NODE_TLS_REJECT_UNAUTHORIZED' => '0'
        ]);
        
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Scanner process failed!');
            $this->error($process->getErrorOutput());
            return 1;
        }

        $this->info($process->getOutput());
        $this->info('Altcoin scan completed successfully!');
        return 0;
    }
}
