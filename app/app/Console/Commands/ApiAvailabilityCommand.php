<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ApiAvailabilityCommand extends Command
{
    protected $signature = 'api:availability {action : stop, start, or status}';

    protected $description = 'Stop, start, or check availability of all API routes.';

    public function handle(): int
    {
        $marker = storage_path('framework/api-disabled');
        $action = $this->argument('action');

        if ($action === 'status') {
            $disabled = is_file($marker);
            $this->info($disabled ? 'API is stopped.' : 'API is running.');

            return self::SUCCESS;
        }

        if (! in_array($action, ['stop', 'start'], true)) {
            $this->error('Action must be stop, start, or status.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($marker));

        if ($action === 'stop') {
            if (is_file($marker)) {
                $this->info('API is already stopped.');

                return self::SUCCESS;
            }

            if (file_put_contents($marker, now()->toIso8601String(), LOCK_EX) === false) {
                $this->error('Could not stop the API: unable to write the state file.');

                return self::FAILURE;
            }

            $this->info('API stopped. All API routes now return HTTP 503.');

            return self::SUCCESS;
        }

        if (is_file($marker) && ! unlink($marker)) {
            $this->error('Could not start the API: unable to remove the state file.');

            return self::FAILURE;
        }

        $this->info('API started.');

        return self::SUCCESS;
    }
}
