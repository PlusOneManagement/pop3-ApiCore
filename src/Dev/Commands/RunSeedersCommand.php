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

        if($fakeSeeds && !$allSeeders){
            $this->doSeed('db:seed', $app_db, '/seeds/Mock*');
            $this->doSeed('module:seed', $modules, '/*/*/Seeders/Mock*', true);
        }
        if($realSeeds && !$allSeeders){
            $this->doSeed('db:seed', $app_db, '/seeds/Real*');
            $this->doSeed('module:seed', $modules, '/*/*/Seeders/Real*', true);
        }
        if($allSeeders){
            $this->call('db:seed');
            $this->call('module:seed');
        }

        endCommand:
        $this->line('======================================================');
    }

    public function doSeed($command, $dir, $pattern, $isModule = false)
    {
        if(!$dir){
            $this->error('Looks like there  are directories with seeds!');
            return false;
        }

        $seeders = $this->getSeedsFrom($dir, $pattern, $isModule);

        foreach ($seeders as $class => $seeder) {

            $commandArgs = [];
            if($isModule){
                list($module, $class) = explode(":", $class);
                $commandArgs['module'] = $module;
            }
            $commandArgs['--class'] = $class;

            $this->warn("Seeding $class");
            $this->call($command, $commandArgs);
        }
        return true;
    }

    public function getSeedsFrom($dir, $pattern, $isModule)
    {
        $seeders = [];
        foreach (glob($dir . $pattern) as $seeder) {

            $class = $this->getSeedClass($dir, $seeder, $isModule);
            $seeders[$class] = $seeder;
        }
        return $seeders;
    }

    public function getSeedClass($directory, $seeder, $isModule)
    {
        // TODO: Update this to include namespace starting with laravel 8.*
        $seedClass = Str::before(basename($seeder), '.php');

        if($isModule){
            $moduleName = Str::of($seeder)->after($directory)->before('Database');
            $seedClass = trim((string)$moduleName, '/\\') . ":$seedClass";
        }
        return trim($seedClass, "/\\");
    }
}
