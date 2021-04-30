<?php

namespace Core\Dev\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Nwidart\Modules\Facades\Module;

class MakeClassCommand extends Command
{
    /**
     * The name  of the console command.
     *
     * @var string
     */
    protected $name = "make:class";
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:class
        {name : name of the class to be created}
        {module? : name of the modules to be created in}
        {--p|path= : Location where the class will be added to: defaults to Services}
        {--t|type= : The type of class will determine what folder, e.g. Observers, Services, etc.}
        {--namespace= : The namespace to create the Class in in}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new generic class in the specified folders';

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
        $DS = DIRECTORY_SEPARATOR;

        $class = $this->argument('name');
        $type = ucwords($this->option('type') ?: 'services');
        if($module = $this->argument('module')){
            $namespace = config('modules.namespace');
            $path = module_path($Module = Str::studly($module),  "$type");
            $namespace = ($this->option('namespace') ?: "$namespace\\$Module\\$type") ."\\";
        } else{
            $path  = base_path($this->option('path') ?: "app/$type");
            $namespace = ($this->option('namespace') ?: "App\\$type") ."\\";
        }

        $classPath = $path .$DS. $class;
        $className = ucwords(trim(dirname($class), '.'));

        $namespace .= preg_replace("#(\\\/)+#msi","\\", $className);

        $stub = base_path('stubs/custom/Class.stub');
        if(!realpath($stub)) {
            $stub = base_path('core/lib/stubs/Class.stub');
        }


        $classDir  = pathinfo($classPath, PATHINFO_DIRNAME);
        $className = pathinfo($classPath, PATHINFO_BASENAME);


        $classDir = str_replace(['/','\\'], $DS, $classDir);
        if(!file_exists($classDir)){
            if (!mkdir($classDir, 0775, true) && !is_dir($classDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $classDir));
            }
        }

        $classPath .= '.php';
        if(file_exists($classPath)){
            $this->line("... Class, '$class' already exists!");
            return;
        }

        $STUB_DATA = file_get_contents($stub);

        $CLASS_DATA = str_replace([
            '$CLASS_NAME$', '$NAMESPACE$'
        ], [
            $className, trim($namespace,'/\\')
        ], $STUB_DATA);

        file_put_contents($classPath, $CLASS_DATA);

        $cleanPath = trim(Str::after($path, base_path()), '/\\');

        $this->info("New class '$class' created in '$cleanPath' successfully");
    }
}
