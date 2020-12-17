<?php


namespace Core\Http\Repositories;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use function Webmozart\Assert\Tests\StaticAnalysis\methodExists;

trait AssociatesRelations
{
    protected $result;

    public function associateRelation($association, $message = null)
    {
        try {
            $this->result['data'] = $association;
            $this->result['message'] = $message ?: "Success";
            //
        } catch(\Exception $ex){
            $this->result['code'] = $ex->getCode();
            $this->result['message'] = $ex->getMessage();
            $this->result['trace'] = $ex->getTrace();
        }
        return $this->result;
    }

    public function sync()
    {
        return $this;
    }

    public function action($action, $Model, $data, $message)
    {
       return $this->associateRelation( $Model->$action($data), $message );
    }

    public function attach(BelongsToMany $Model, $data)
    {
        $message = "Successfully attached new records";
        return $this->action('attach', $Model, $data, $message);
    }

    public function newSync(BelongsToMany $Model, $data)
    {
        $message = "Successfully synced records";
        return $this->action('syncWithoutDetaching', $Model, $data, $message);
    }

    public function updateSync(BelongsToMany $Model, $data)
    {
        $message = "Successfully updated synced new records";
        return $this->action('sync', $Model, $data, $message);

    }
    
    public function updateWithExisting(BelongsToMany $Model,int $id, $data)
    {
        return $this->associateRelation($Model->updateExistingPivot($id, $data),
        "Successfully updated records"
    );
    }
	
    public function detach(BelongsToMany $Model, $data)
    {
        $message = "Successfully removed associated records";
        return $this->action('detach', $Model, $data, $message);
    }

    public function associate(BelongsTo $Model, $data)
    {
        $message = "Successfully associated new records";
        return $this->action('associate', $Model, $data, $message);
    }

    public function createMany(HasMany $Model, $data = [])
    {
        $message = "Successfully created new records";
        return $this->action('createMany', $Model, $data, $message);
    }
}
