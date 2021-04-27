<?php

namespace Core\Dev\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class RunSeedersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:seeders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Run seeders with real of mock data
                            {--m|mock : Re-write initial resources during install}
                            {--r|real : Delete initial resources and re-install}";

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $options = $this->options();
        $args = $this->arguments();

        $this->line('======================================================');

        $this->line('======================================================');
    }

    public function migrate_db($options)
    {
        $this->line('-------------------------------------------------');
        $this->line('  Run database migrations ');
        $this->line('-------------------------------------------------');

        $migrations = base_path($dbmig = 'database/migrations');
        $this->runMigrations($dbmig);

        // $this->line("Run in $dbmig");
        // $this->call("migrate:refresh", [
        //     '--path' => $dbmig
        // ]);
        // $this->line("");
        foreach (glob($migrations.'/*') as $migration) {
            if ($migration == "." || $migration == '..') {
                continue;
            }
            if (is_file($migration)) {
                continue;
            }


            $name = basename($migration);
            $this->runMigrations($dbmig, $name);
            // $conn =  config('database.connections');

            // $props = ["--path" => "$dbmig/$name"];
            // if (in_array("$name", $conn)) {
            //     $props += ["--database" => "$name"];
            // }
            // $this->call("migrate:refresh", $props);
            // $this->line("");
        }
        if (file_exists(base_path('modules'))) {
            $this->call("module:migrate", ['--force' => true]);
        }

        if ($options['import'] || $options['all']) {
            $this->line("------------------------------------------------");
            $this->line('Restoring database from backup!');
            $this->line('------------------------------------------------');

            $dbBackupDir = storage_path('data/backups');
            if(!($dbBackupDir = realpath($dbBackupDir))){
                $this->line('There are no backups to restore from!');
            } else {
                $allBackups = glob($dbBackupDir.'/*/*.sql');
                $this->line('We found '.(count($allBackups)).' backups!');
                $this->restoreDatabase(Arr::last($allBackups));
            }
        }

        if ($options['seed'] || $options['all']) {
            $this->line("------------------------------------------------");
            $this->line('Running database seeds');
            $this->line('------------------------------------------------');
            $this->call('db:seed', $cmdArgs = ['--force' => true]);
            if (file_exists(base_path('modules'))) {
                $this->call('module:seed', $cmdArgs);
            }
        }

        $this->line('');
    }


    public function runMigrations($migrations, $name = null, $module = false)
    {
        $this->connect_db($conn = ($name ? "$name": null));

        $migrations = "{$migrations}/{$name}";
        $connections =  config('database.connections');

        $props = $mPath = ["--path" => $migrations, '--force' => true];
        if ($name && in_array("$name", $connections)) {
            $props += $mConn = ["--database" => "$name"];
        }

        $this->line("Run in $migrations");
        if ($module) {
            $this->call("module:migrate-reset $name", $props);
        } else {
//            $this->call('migrate:install', $mConn  ?? []);
            $this->call("migrate", $props);
        }
        $this->line("");
        $this->disconnect_db($conn);
    }

    public function restoreDatabase($sqlFile)
    {
        try {
            $CONNECTION = $this->connect_db();

            $this->line("... restoring database data from latest backup: ".basename($sqlFile));
            $rawSQL =  file_get_contents($sqlFile);
//        $rawSQL = trim(preg_replace(
//            "#(--.*)|(((\/\*)+?[\w\W]+?(\*\/\;)+))#msi",
//            " ",
//            $rawSQL
//        ));
            if(empty($rawSQL)){
                $this->line('WARNING: There are no instructions in the backup file!');
            } else {

                $raw = $CONNECTION->unprepared($rawSQL);
                $this->line(($raw? 'SUCCESS': 'FAILURE') . ' restoring database from backup!');
            }
            $this->disconnect_db($CONNECTION);
        } catch(\Exception $exc) {
            $this->line($exc->getMessage());
        }
    }
}
