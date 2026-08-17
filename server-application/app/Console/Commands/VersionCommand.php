<?php

namespace App\Console\Commands;

use App\Helpers\Version;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'Tracker:version')]
class VersionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Tracker:version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Tracker version';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info((string)new Version());
    }
}
