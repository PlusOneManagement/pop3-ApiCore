<?php

namespace Core\Dev\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class RunSeedersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "run:seeders
                            {--m|mock : Seed all fake seeders only}
                            {--r|real : Seed all real seeders only}
                            {--a|all : Seed reak and fake seeders}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Run seeders with real or mock data";

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

        $this->line('======================================================');

        $fakeSeeds = $this->option('mock') ?? false;
        $realSeeds = $this->option('real') ?? false;
        $allSeeders = $this->option('all') ?? false;

        $app_db = realpath(base_path('database') . '/');
        $modules = realpath(config('modules.paths.modules') . '/');

        $seeders = $this->getSeederFiles($modules, $app_db);

        $classes = $this->getSeederClasses($modules, $app_db, $seeders);

        foreach($classes as $class){
            $this->warn("Seeding $class");

            $seedCMD = Str::contains($class, '\\')? 'db:seed': 'module:seed';

            $this->call($seedCMD, ['--class' => $class]);
        }


        $this->line('======================================================');
    }

    public function getSeedsFrom($dir, $pattern)
    {
        $seeders = [];
        foreach (glob($dir . $pattern) as $seeder){
            $seeders[] = $seeder;
        }
        return $seeders;
    }

    public function getSeederFiles($modules, $app_db)
    {
        $fakeSeeds = $this->option('mock') ?? false;
        $realSeeds = $this->option('real') ?? false;
        $allSeeders = $this->option('all') ?? false;

        $seeders = [];

        if($fakeSeeds){
            $seeders += $this->getSeedsFrom($app_db, '/seeds/Mock*');
            $seeders += $this->getSeedsFrom($modules, '/*/*/Seeders/Mock*');
        }

        if($realSeeds){
            $seeders += $this->getSeedsFrom($app_db, '/seeds/Real*');
            $seeders += $this->getSeedsFrom($modules, '/*/*/Seeders/Real*');
        }

        if($allSeeders){
            $seeders += $this->getSeedsFrom($app_db, '/seeds/DatabaseSeeder.php');
            $seeders += $this->getSeedsFrom($modules, '/*/*/Seeders/*DatabaseSeeder.php');
        }

        return $seeders;
    }

    public function getSeederClasses($modules, $app_db, $seeders)
    {
        $moduleSpace = config('modules.namespace');

        $seedClasses = [];

        foreach ($seeders as $seeder){
            if(Str::startsWith($seeder, $modules)){
                $seedClass = $moduleSpace. (Str::after($seeder, $modules));
                $seedClass = str_ireplace(['/', '.php'], ['\\', ''], $seedClass);
                $seedClasses[] = class_exists($seedClass)? $seedClass: null;
                //
            } elseif(Str::startsWith($seeder, realpath($app_db.'/seeds'))){
                $seedClass = Str::before( basename($seeder), '.php' );
                $seedClasses[] = class_exists($seedClass)? $seedClass: null;
            }
        }

        return array_unique(array_filter($seedClasses));
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
