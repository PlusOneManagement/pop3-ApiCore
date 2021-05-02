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

        $fakeSeeds = $this->option('mock');
        $realSeeds = $this->option('real');
        $allSeeders = $this->option('all') ;

        if (!$fakeSeeds && !$realSeeds && !$allSeeders) {
            $this->error(__('>>> Missing options in the command! Add --help for usage!'));
            goto endCommand;
        }

        $app_db = realpath(base_path('database') . '/');
        $modules = realpath(config('modules.paths.modules') . '/');

        $seeders = $this->getSeederFiles($modules, $app_db);

        $classes = $this->getSeederClasses($modules, $app_db, $seeders);

        foreach ($classes as $class) {
            $this->warn("Seeding $class");

            $seedCMD = Str::contains($class, '\\')? 'db:seed': 'module:seed';

            $this->call($seedCMD, ['--class' => $class]);
        }

        endCommand:
        $this->line('======================================================');
    }

    public function getSeedsFrom($dir, $pattern)
    {
        $seeders = [];
        foreach (glob($dir . $pattern) as $seeder) {
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

        if ($fakeSeeds) {
            $seeders += $this->getSeedsFrom($app_db, '/seeds/Mock*');
            $seeders += $this->getSeedsFrom($modules, '/*/*/Seeders/Mock*');
        }

        if ($realSeeds) {
            $seeders += $this->getSeedsFrom($app_db, '/seeds/Real*');
            $seeders += $this->getSeedsFrom($modules, '/*/*/Seeders/Real*');
        }

        if ($allSeeders) {
            $seeders += $this->getSeedsFrom($app_db, '/seeds/DatabaseSeeder.php');
            $seeders += $this->getSeedsFrom($modules, '/*/*/Seeders/*DatabaseSeeder.php');
        }

        return $seeders;
    }

    public function getSeederClasses($modules, $app_db, $seeders)
    {
        $moduleSpace = config('modules.namespace');

        $seedClasses = [];

        foreach ($seeders as $seeder) {
            if (Str::startsWith($seeder, $modules)) {
                $seedClass = $moduleSpace. (Str::after($seeder, $modules));
                $seedClass = str_ireplace(['/', '.php'], ['\\', ''], $seedClass);
                $seedClasses[] = class_exists($seedClass)? $seedClass: null;
            //
            } elseif (Str::startsWith($seeder, realpath($app_db.'/seeds'))) {
                $seedClass = Str::before(basename($seeder), '.php');
                $seedClasses[] = class_exists($seedClass)? $seedClass: null;
            }
        }

        return array_unique(array_filter($seedClasses));
    }
}
