<?php

namespace Core\Dev\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Core\Data\Database\Schema;

class RunSchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "run:schema
                           {--d|database : Create database(s) and user(s)}
                           {--b|backup : Backup database(s) into storage}
                           {--r|restore : Restore database(s) from backup}
                           {--f|force : Force dropping/creating database(s)}
                           {--m|migrate : Generate/run database migrations}
                           {--s|storage= : Path to the backup storage file}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Handle database schemas, backup and restoration";


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
        $backup = $this->option('backup');
        $database = $this->option('database');
        $restore = $this->option('restore');
        $storage = $this->option('storage');
        $migrate = $this->option('migrate');

        $this->line('======================================================');

        $dbDefault = config('database.default');
        $dbConnect = DB::connection($dbDefault);
        $dbConfigs = $this->dbConfigs($dbDefault);

        if(!$backup && !$database && !$restore && !$storage  && !$migrate){
            $this->error(__('>>> Missing arguments! use --help for usage!'));
        }

        if ($restore) {
            $this->doRestore($dbConnect, $storage);
        }

        if ($backup) {
            $this->doBackup($dbConnect, $dbConfigs, $storage);
        }

        if ($database) {
            $this->doSchema($dbConnect, $dbConfigs);
        }

        if ($migrate) {
            $this->doMigrations($dbConnect, $dbConfigs);
        }

        $this->line('======================================================');
    }

    public function dbConfigs($dbDefault)
    {
        $configs = config('database.connections');

        foreach ($configs as $config => $settings) {
            if (Str::contains($config, 'legacy')
                || !($settings['database'] ?? null)
                || !($settings['username'] ?? null)
                || !($settings['password'] ?? null)
            ) {
                unset($configs[$config]);
            }
        }
        return $configs;
    }

    public function doRestore($dbConnect, $storage = null)
    {
        $this->line('   Restoring Database from File   ');
        $this->line("------------------------------------------------------");

        $backupName = basename($storage);
        if ($this->restore($dbConnect, $storage)) {
            $this->info('Success in restoring data from '.$backupName);
        } else {
            $this->error('An error occurred while restoring '.$backupName);
        }
    }

    private function restore($dbConnect, $storage = null)
    {
        if ($storage) {
            $backup = realpath(getcwd() . '/' . $storage);
        } else {
            $backupDir = config('database.backup');
            $allBackups = glob($backupDir.'/*/*.sql');
            $backup = realpath(Arr::last($allBackups));
        }
        if (!$backup) {
            $this->error('There are no backups to restore from!');
            return false;
        }

        return $this->restoreDB($dbConnect, $backup);
    }

    public function restoreDB($dbConnect, $backup)
    {
        try {
            $this->line('Running database restore ...');

            $backupSQL = file_exists($backup)? file_get_contents($backup): 'mysqldump:';
            if (Str::contains($backupSQL, ['mysqldump:', 'error:']) || !trim($backupSQL)) {
                $this->error(sprintf(
                    __(">>> The backup file '%s' has errors or is empty"),
                    basename($backup)
                ));
                return false;
            }

            static $host, $port, $database, $username, $password, $backup;
            extract($dbConnect->getConfig(), EXTR_OVERWRITE);

            $this->mysqldump($host, $port, $database, $username, $password, $backup, "<");

            return true;
        } catch (\Exception $ex) {
            $this->error($ex->getMessage());
            return false;
        }
    }

    public function doBackup($db, $configs, $storage = null)
    {
        $this->line('   Database Backup to File   ');
        $this->line("------------------------------------------------------");

        $backupFile = $this->backup($db, $configs, $storage);

        if ($backupFile) {
            $this->info("Backup created successfully in $backupFile");
        } else {
            $this->error("Error generating database backup in $storage");
        }
    }

    public function backup($db, $configs, $storage = null)
    {
        $backup_dir = is_null($storage)
            ? $dddd = config('database.backup')
            : getcwd() . '/' . $storage;

        $backup_dir = rtrim($backup_dir, '/\\')."/db-".date('Ymd');
        $backup = "$backup_dir/bak-".date('His').".sql";

        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0644, true);
        }

        return $this->backupDB($db, $backup, $configs);
    }

    public function mysqldump($host, $port, $database, $username, $password, $backup, $direct)
    {
        if (!isset($host) || !isset($port) || !isset($username) || !isset($password)) {
            $this->error('DB configurations are not set or missing details');
            return;
        }

        $mysqldump = "mysqldump -h $host -u $username -P $port";
        if (!empty($password)) {
            $mysqldump .= " -p'$password'";
        }

        $flags =" --compact --no-create-info --column-statistics=0 --replace";
        $mysqldump .= "$flags $database $direct $backup";

        $this->warn('>>>');
        $command = str_ireplace("-p'$password'", "-p'[HIDDEN]'", $mysqldump);
        $this->info("$command");
        $this->warn('<<<');

        return shell_exec(trim($mysqldump));
    }

    public function backupDB($dbConnect, $backup, $configs, $returnSQL = false)
    {
        // TODO: handle individual configs
        $this->line('Running database backup ...');

        static $host, $port, $database, $username, $password;
        extract($dbConnect->getConfig(), EXTR_OVERWRITE);

        $this->mysqldump($host, $port, $database, $username, $password, $backup, ">");

        $backupSQL = file_exists($backup)? file_get_contents($backup): 'mysqldump:';
        if (Str::contains($backupSQL, ['mysqldump:', 'error:'])) {
            return false;
        }

        return $returnSQL? $backupSQL: $backup;
    }

    public function doSchema($db, $configs)
    {
        $this->line('   Generating Database(s) and User(s)   ');
        $this->line("------------------------------------------------------");

        $schemaGeneration = $this->genSchema($db, $configs);

        if ($schemaGeneration) {
            $this->info("Success in creating database(s) and users");
        } else {
            $this->error("Error generating database(s) or user(s)");
        }
    }

    public function genSchema($dbConnect, $dbConfigs)
    {
        if (file_exists($modules = base_path('modules'))) {
            foreach (glob($modules."/*") as $module_path) {
                $module_name = strtolower(basename($module_path));
                $module_conf = config("{$module_name}.database.connections");
                $dbConfigs += array_filter($module_conf ?: []);
            }
        }

        $dbs = [];
        foreach ($dbConfigs as $conn => $config) {
            $db = $config['database'];

            if (!in_array($db, $dbs)) {
                $this->genDBschema($dbConnect, $conn, $config);
                $dbs[] = $db;
                $this->info("Done generating db user `$db`");
                $this->line("------------------------------------------------------");
            }
        }

        $users = [];
        foreach ($dbConfigs as $conn => $config) {
            $user = $conn.'_'.($u = $config['username']);

            if (!in_array($user, $users)) {
                $this->getDBusers($dbConnect, $conn, $config);
                $users[] = $user;
                $this->info("Done creating user `$u`");
                $this->line("------------------------------------------------------");
            }
        }

        return true;
    }

    public function genDBschema($CONNECTION, $dbConn, $dbConfigs)
    {
        try {
            static $database, $charset, $collation;
            extract($dbConfigs, EXTR_OVERWRITE);

            if ($this->option('force')) {
                $this->doBackup($CONNECTION, $dbConfigs);
                $CONNECTION->unprepared("DROP DATABASE IF EXISTS $database;");
            }
            $this->warn("... adding database: db='$database'; charset='$charset'; collation='$collation'");
            $CONNECTION->unprepared("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET $charset COLLATE $collation;");

            return true;
        } catch (\Exception $ex) {
            $this->error($ex->getMessage());
            return false;
        }
    }

    public function getDBusers($CONNECTION, $dbConn, $dbConfigs)
    {
        static $database, $username, $password;
        extract($dbConfigs, EXTR_OVERWRITE);

        $host = '%';

        if ($database && $username && $password) {
            $privileges = "SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,DROP,INDEX,EXECUTE,REFERENCES";
//            $privileges = "ALL PRIVILEGES";
            if (Str::startsWith($dbConn, 'dbr_')) {
                $privileges = "SELECT";
            } elseif (Str::startsWith($dbConn, 'dbw_')) {
                $privileges = "SELECT,INSERT,UPDATE,DELETE";
            } else {
                $database = "*";
            }

            $this->warn("... run: CREATE USER IF NOT EXISTS '$username'@'%' IDENTIFIED BY '$password';");
            $CONNECTION->unprepared("CREATE USER IF NOT EXISTS '$username'@'%' IDENTIFIED BY '$password';");

            if ($username != 'root') {
                $this->warn("... GRANT $privileges ON $database.* TO '$username'@'$host'; ...");
                $CONNECTION->unprepared("GRANT $privileges ON $database.* TO '$username'@'$host';");
            }
            $CONNECTION->unprepared('FLUSH PRIVILEGES;');
        }
    }

    public function doMigrations($dbConnect, $dbConfigs)
    {
        $this->line('   Generating Database(s) and User(s)   ');
        $this->line("------------------------------------------------------");

        $runMigrations = $this->runMigrations($dbConnect, $dbConfigs);

        if ($runMigrations) {
            $this->info("Success in migrating database table(s)");
        } else {
            $this->error("Error migrating database table(s))");
        }
    }

    public function runMigrations($dbConnect, $dbConfigs)
    {
        try {
            $isForced = $this->option('force');

            if ($isForced) {
                $this->doBackup($dbConnect, $dbConfigs);
            }

            $conn = $dbConnect->getConfig('name');
            $this->migrateDB($conn, $isForced);

            foreach ($dbConfigs as $conn => $config) {
                $this->migrateDB($conn, $isForced);
            }

            return true;
        } catch (\Exception $ex) {
            $this->error($ex->getMessage());
            return false;
        }
    }

    public function migrateDB($dbConn, $isForced = false)
    {
        try {
            $this->line('---------------------------------------');
            $migrate = $isForced? 'migrate:fresh': 'migrate';

            if (!Str::startsWith($dbConn, 'db_')) {
                return true;
            }

            $this->warn("Running `$migrate` on the `$dbConn` database connection ...");
            $this->call($migrate, [
                '--database' => $dbConn,
                '--ansi' => $this->option('ansi') ?: true
            ]);

            $this->line('---------------------------------------');
            return true;
        } catch (\Exception $ex) {
            $this->error($ex->getMessage());
            return false;
        }
    }
}
