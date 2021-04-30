<?php

namespace Core\Dev\Commands;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
//
// TODO: Break this command into smaller commands callable from here!
//
class RunInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:install
                            {--f|force : Re-write initial resources during install}
                            {--r|reset : Delete initial resources and re-install}
                            {--d|db : Create all configured databases}
                            {--D|doc : Generate api documentation for the api}
                            {--N|newdb : Create new local database}
                            {--m|migrate : Run migrations after database creation}
                            {--s|seed : Run seeds after migrations}
                            {--e|export : Export data from database into backup}
                            {--i|import : Import data into database from backup}
                            {--u|upgrade : Upgrade composer packages (not included in --all)}
                            {--a|app : Run installation of the application}
                            {--A|all : Run this command with ALL the options!}
                            {--S|serve : Run a local server after completion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the application/resources on this server';

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
        $options = $this->options();

        $this->line('======================================================');

        $this->call('optimize');
        $this->call('cache:clear');
        $this->line('removing cached files');

        shell_exec('rm -rf bootstrap/cache/logs/*');
        shell_exec('rm -rf storage/logs/*');

        if ($options['app'] || $options['all']) {
            $this->install_app($options);
        }

        if ($options['db'] || $options['all']) {
            $this->call('run:schema', [
                '--database' => true,
                '--force' => $this->option('force'),
                '--ansi' => $this->option('ansi') ?: true,
            ]);
        }

        if ($options['migrate'] || $options['all']) {
            $this->call('run:schema', [
                '--migrate' => true,
                '--force' => $this->option('force'),
                '--ansi' => $this->option('ansi') ?: true,
            ]);
        }

        if ($options['seed'] || $options['all']) {
            $this->call('run:seeders', [
                '--all' => true,
                '--ansi' => $this->option('ansi') ?: true,
            ]);
        }

        if ($options['import'] || $options['all']) {
            $this->call('run:schema', [
                '--restore' => true,
                '--ansi' => $this->option('ansi') ?: true,
            ]);
        }

        if ($options['doc'] || $options['all']) {
            //$this->call("apidoc:generate");
            //$this->call("scribe:generate");
        }

        if ($options['serve']) {
            $this->init_server($options);
        }

        $this->line('======================================================');
//        exit();
        return false;
    }

    public function update_autoloads()
    {
        $this->line("Updating autoload files first!' ...  ");
        shell_exec('composer dump-autoload');
        $this->line('');
    }

    public function install_app($options)
    {
        $this->line('-----------------------------------------------------');
        $this->line("  Intalling/Updating 3rd-party packages' ...  ");
        $this->line('-----------------------------------------------------');

        $force = '';
        if ($options['force']) {
            $force = ' -f';
            shell_exec("git clean . -fdX");
            if (file_exists($env =  base_path('.env'))) {
                unlink($env);
            }
        }
        $action = isset($options['upgrade'])? 'update': 'install';
        shell_exec("composer $action -n");

        if ($options['reset'] || $options['all']) {
            $this->line('-------------------------------------');
            $this->line("   Adding initial resources...  ");
            $this->line('-------------------------------------');

            shell_exec('composer run-script post-root-package-install');
            shell_exec('composer run-script post-create-project-cmd');
            if(file_exists(base_path('vendor/laravel/passport'))){
                $this->call('passport:install');
            }
            $this->line(' ');
        }
    }

    public function init_server($options = null)
    {
        $this->line('---------------------------------------');
        $this->line('  Initializing local server');
        $this->line('---------------------------------------');

        $port = $port = mt_rand(1111, 9999);
        $host = '127.0.0.1';

        $this->line("Setting up port ".$port);
        shell_exec("start \"\" http://$host:$port");
        $this->call("serve", ['--host'=>$host, '--port'=>$port]);
        $this->line('');
    }
}
