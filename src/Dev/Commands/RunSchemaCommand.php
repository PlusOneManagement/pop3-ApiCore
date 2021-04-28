<?php

namespace Core\Dev\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
                           {--c|create : Create database(s) if not exists}
                           {--b|backup : Backup database(s) into storage}
                           {--r|restore : Restore database(s) from backup}
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
        $create = $this->option('create');
        $restore = $this->option('restore');
        $storage = $this->option('storage');

        $this->line('======================================================');

        $dbDefault = config('database.default');
        $dbConnect = DB::connection($dbDefault);
        $dbConfigs = $this->dbConfigs($dbDefault);

        if($restore){
            $this->doRestore($dbConnect, $storage);
        }

        if($backup){
            $this->doBackup($dbConnect, $dbConfigs, $storage);
        }

        if($create){
            $this->doCreate($dbConnect);
        }

        $this->line('======================================================');
    }

    public function dbConfigs($dbDefault)
    {
        $configs = config('database.connections');

        foreach ($configs as $config => $settings){
            if(Str::contains($config, 'legacy')
                || !($settings['database'] ?? null)
                || !($settings['username'] ?? null)
                || !($settings['password'] ?? null)
            ){
                unset($configs[$config]);
            }
        }
        return $configs;
    }

    public function doRestore($dbConnect, $storage = null)
    {
        $this->line('   Restoring Database from File   ');
        $this->line("------------------------------------------------------");

        if($this->restore($dbConnect, $storage)){
            $this->info('Success in restoring file from '.basename($storage));
        } else {
            $this->error('An error occurred while restoring '.basename($storage));
        }
    }

    private function restore($dbConnect, $storage = null)
    {
        if($storage){
            $storage = realpath(getcwd() . '/' . $storage);
        } else {
            $backupDir = storage_path('data/backups');
            $allBackups = glob($backupDir.'/*/*.sql');
            $storage = realpath(Arr::last($allBackups));
        }
        if(!$storage){
            $this->error('There are no backups to restore from!');
            exit();
        }

        $backup = file_get_contents($storage);

        return $this->restoreDB($dbConnect, $backup);
    }

    public function restoreDB($dbConnect, $backup)
    {
        $statements = preg_split('#REPLACE\s+INTO\s*#msi', $backup);
        dd($statements);

        $result = [];
        $dbConnect->statement('SET FOREIGN_KEY_CHECKS=0;');
        foreach ($statements as $statement){
            $statement = trim($statement);
            if(empty($statement)){
                continue;
            }
            if(!Str::contains($statement, 'REPLACE INTO')){
                $statement = "REPLACE INTO $statement";
            }
            $result[] = $dbConnect->unprepared(trim($statement)) ? null: 'bad';
        }
        $dbConnect->statement('SET FOREIGN_KEY_CHECKS=1;');
        return !count(array_filter($result));
    }

    public function doBackup($db, $configs, $storage = null)
    {
        dd($configs, $storage);
    }

    public function backupDB($db, $configs)
    {

        dd($db, $configs);
        extract($configs);
        if(isset($password) && $password) {
            $password = "-p'$password'";
        }

        if(!file_exists($backup_path = storage_path("data/backups/bak-".date('Ymd')))){
            mkdir($backup_path, 0644, true);
        }

        $backup = "$backup_path/db-".date('His').".sql";

        $database = $db['database'];

        if(isset($host) && isset($username) && isset($port) && isset($username)){
            $flags =" --compact --no-create-info --column-statistics=0";
            $mysqldump = "mysqldump -h $host -u $username -P $port $password $flags $database > $backup 2>&1";
            shell_exec( trim($mysqldump) );
        }

        if(!($backup = realpath($backup))){
            $this->line('Backup creation was NOT successful!');
            $continue = $this->ask('We can still drop the database. Continue? (Y/N)', 'N');
            if(Str::of($continue)->lower()->startswith('n')) die();
            goto finishBackup;
        }
        $clean_backup_path = Str::after($backup, base_path());
        $this->line("");
        $this->info("Backup created in $clean_backup_path!");
        $continue = $this->ask("Confirm that the backup is valid! Continue? (Y/N)", 'Y');

        $rawSQL = trim(file_get_contents($backup));
        if(empty($rawSQL)) {
            $emptyBak = $this->ask('Backup file created but it is empty, continue? (Y/N)');
            if(Str::startswith(strtolower($emptyBak),'n')) die();
            goto finishBackup;
        }
        $InsertInto = "REPLACE INTO `$database`.`";
        $SQL = str_replace('INSERT INTO `', $InsertInto, $rawSQL);
        file_put_contents($backup, $SQL);
        if(Str::startswith(strtolower($continue),'n')) die();

        finishBackup:
        $this->line("... backup complete!'");
    }

    public function backup($storage)
    {
        $rootdb = config('database.connections.root');
        if(!$rootdb){
            $continue = $this->ask('Root database configuration doesn not exist! Continue? Y or N', 'Y');
            if(Str::startswith(strtolower($continue),'n')) die();
        }
        extract($rootdb);
        if(isset($password) && $password) {
            $password = "-p'$password'";
        }

        if(!file_exists($backup_path = storage_path("data/backups/bak-".date('Ymd')))){
            mkdir($backup_path, 0644, true);
        }

        $backup = "$backup_path/db-".date('His').".sql";

        $database = $db['database'];

        if(isset($host) && isset($username) && isset($port) && isset($username)){
            $flags =" --compact --no-create-info";
            $mysqldump = "mysqldump -h $host -u $username -P $port $password $flags $database > $backup 2>&1";
            shell_exec( trim($mysqldump) );
        }

        if(!($backup = realpath($backup))){
            $this->line('Backup creation was NOT successful!');
            $continue = $this->ask('We will still drop the database. Continue? (Y/N)', 'Y');
            if(Str::of($continue)->lower()->startswith('n')) die();
            goto finishBackup;
        }
        $clean_backup_path = Str::after($backup, base_path());
        $this->line("");
        $this->line("Backup created in $clean_backup_path!");
        $continue = $this->ask("Confirm that the backup is valid! Continue? (Y/N)", 'Y');

        $rawSQL = trim(file_get_contents($backup));
        if(empty($rawSQL)) {
            $emptyBak = $this->ask('Backup file created but it is empty, continue? (Y/N)');
            if(Str::startswith(strtolower($emptyBak),'n')) die();
            goto finishBackup;
        }
        $InsertInto = "REPLACE INTO `$database`.`";
        $SQL = str_replace('INSERT INTO `', $InsertInto, $rawSQL);
        file_put_contents($backup, $SQL);
        if(Str::startswith(strtolower($continue),'n')) die();

        finishBackup:
        $this->line("... backup complete!'");
    }

    public function install_db($options)
    {
        $this->line('-------------------------------------------');
        $this->line('  Initialize the database  ');
        $this->line('--------------------------------------------');


        if ($options['newdb']) {
            $database = $this->ask('What is your database name?') ?? 'popcxdb00';
            $username = $this->ask('What is your database username?') ?? 'popcxusr00';
            $dbport = $this->ask('What is your database port?') ?? '3306';
            $password = $this->secret('What is your database password?') ?? 'P0pcxU$r00';
            $this->local_db($database, $username, $dbport, $password);
        } else {
            $CONNECTION = $this->connect_db('root');

            if ($CONNECTION) {
                $this->create_databases($CONNECTION);
                $this->disconnect_db($CONNECTION);
            } else {
                $this->line('Database connection failed: '.mysqli_connect_error());
            }
        }
        $this->line('Done!');
        $this->line('');
    }

    public function local_db($database, $username, $dbport, $password)
    {
        $env_data = file_get_contents($env_file = base_path('.env'));
        if ($database) {
            $env_data = preg_replace("#(DB_DATABASE\=)(.+?)\s+#si", "$1$database\n", $env_data);
        }
        if ($username) {
            $env_data = preg_replace("#(DB_USERNAME\=)(.+?)\s+#si", "$1$username\n", $env_data);
        }
        if ($password) {
            $env_data = preg_replace("#(DB_PASSWORD\=)(.+?)\s+#si", "$1$password\n", $env_data);
        }
        if ($dbport) {
            $env_data = preg_replace("#(DB_PORT\=)(.+?)\s+#si", "$1$dbport\n", $env_data);
        }
        file_put_contents($env_file, $env_data);
    }

    public function create_databases($CONNECTION)
    {
        $DS = DIRECTORY_SEPARATOR;

        $DATABASES = array_filter(config('database.connections'));

        if (file_exists($modules = base_path('modules'))) {
            foreach (glob($modules.$DS."*") as $module_path) {
                $module_name = strtolower(basename($module_path));
                $module_conf = config("{$module_name}.database.connections");
                $DATABASES += array_filter($module_conf ?: []);
            }
        }

        $this->line("Creating database(s)...");
        $CreatedDB = [];
        foreach ($DATABASES as $n => $db) {

            if( !($cdb = $db['database'] ?? null) ){
                continue;
            }
            if (is_null($CreatedDB) || !in_array($cdb, array_unique($CreatedDB))) {
                if(Str::contains($n, 'legacy') || Str::contains($cdb, 'legacy')){
                    $this->line("... skipping $cdb");
                    continue;
                }
                $this->init_db($CONNECTION, $db);
            }
            $CreatedDB[] = $cdb;
        }
        $this->line('Database creation completed!');
        $this->line('');
        $this->line('----------------------------------------------');
        $this->line('Creating database users...');
        foreach ($DATABASES as $n => $db) {
            $cdb = $db['database'] ?? null;
            if(!$cdb){
                continue;
            }
            if (is_null($CreatedDB) || !in_array($cdb, array_unique($CreatedDB))) {
                if(Str::contains($n, 'legacy') || Str::contains($cdb, 'legacy')){
                    continue;
                }
            }
            $CreatedDB[] = $cdb;
            $this->add_user($CONNECTION, $db);
        }
        $this->line('Database user access completed!');
        $this->line('');
    }



    public function init_db($CONNECTION, $db)
    {
        $database = $db['database'] ?? null;
        $charset = $db['charset'] ?? 'utf8mb4';
        $collation = $db['collation'] ?? 'utf8mb4_general_ci';
        $driver = $db['driver'] ?? "mysql";

        if ($database && $driver) {
            $this->line("... backing up database '$database' if exists");
            $this->backup_db($db);

            $this->line("... adding database: db='$database'; charset='$charset'; collation='$collation'");
            $CONNECTION->unprepared("DROP DATABASE IF EXISTS $database;");
            $CONNECTION->unprepared("CREATE DATABASE $database CHARACTER SET $charset COLLATE $collation;");
        }
    }

    public function add_user($CONNECTION, $db)
    {
        $host = '%';
        $database = $db['database'] ?? null;
        $username = $db['username'] ?? null;
        $password = $db['password'] ?? "";
        $driver = isset($db['driver']) && $db['driver'] == "mysql";

        if ($database && $username && $password && $driver) {
            $privileges = "SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,DROP,INDEX,EXECUTE,REFERENCES";
//            $privileges = "ALL PRIVILEGES";
            if(Str::startsWith($username, 'dbr_')){
                $privileges = "SELECT";
            } elseif(Str::startsWith($username, 'dbw_')){
                $privileges = "SELECT,INSERT,UPDATE,DELETE";
            } else{
                $database = "*";
            }
            //$this->line("... adding user: username='$username', password='$password'; grant='$privileges' to $database;");
            $this->line("... run: CREATE USER IF NOT EXISTS '$username'@'%' IDENTIFIED BY '$password';");
            $CONNECTION->unprepared("CREATE USER IF NOT EXISTS '$username'@'%' IDENTIFIED BY '$password';");

            if($username != 'root'){
                $this->line("... GRANT $privileges ON $database.* TO '$username'@'$host'; ...");
                $CONNECTION->unprepared("GRANT $privileges ON $database.* TO '$username'@'$host';");
            }
            $CONNECTION->unprepared('FLUSH PRIVILEGES;');
        }
    }

    public function backup_db($db)
    {
        $rootdb = config('database.connections.root');
        if(!$rootdb){
            $continue = $this->ask('Root database configuration doesn not exist! Continue? Y or N', 'Y');
            if(Str::startswith(strtolower($continue),'n')) die();
        }
        extract($rootdb);
        if(isset($password) && $password) {
            $password = "-p'$password'";
        }

        if(!file_exists($backup_path = storage_path("data/backups/bak-".date('Ymd')))){
            mkdir($backup_path, 0644, true);
        }

        $backup = "$backup_path/db-".date('His').".sql";

        $database = $db['database'];

        if(isset($host) && isset($username) && isset($port) && isset($username)){
            $flags =" --compact --no-create-info";
            $mysqldump = "mysqldump -h $host -u $username -P $port $password $flags $database > $backup 2>&1";
            shell_exec( trim($mysqldump) );
        }

        if(!($backup = realpath($backup))){
            $this->line('Backup creation was NOT successful!');
            $continue = $this->ask('We will still drop the database. Continue? (Y/N)', 'Y');
            if(Str::of($continue)->lower()->startswith('n')) die();
            goto finishBackup;
        }
        $clean_backup_path = Str::after($backup, base_path());
        $this->line("");
        $this->line("Backup created in $clean_backup_path!");
        $continue = $this->ask("Confirm that the backup is valid! Continue? (Y/N)", 'Y');

        $rawSQL = trim(file_get_contents($backup));
        if(empty($rawSQL)) {
            $emptyBak = $this->ask('Backup file created but it is empty, continue? (Y/N)');
            if(Str::startswith(strtolower($emptyBak),'n')) die();
            goto finishBackup;
        }
        $InsertInto = "REPLACE INTO `$database`.`";
        $SQL = str_replace('INSERT INTO `', $InsertInto, $rawSQL);
        file_put_contents($backup, $SQL);
        if(Str::startswith(strtolower($continue),'n')) die();

        finishBackup:
        $this->line("... backup complete!'");
    }
}
