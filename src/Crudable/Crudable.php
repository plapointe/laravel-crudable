<?php

namespace Flobbos\Crudable;

use Flobbos\Crudable\Exceptions\MissingRelationDataException;

trait Crudable {
    
    protected   $relation = [];
    protected   $withHasMany,$withBelongsToMany,$model;
    
    /**
     * Retrieve the Eloquent model
     * @return Eloquent model
     */
    public function raw(){
        return $this->model;
    }
    
    /**
     * Get a single item or collection
     * @param int $id
     * @return Model/Collection
     */
    public function get($id = null){
        if(!is_null($id)){
            return $this->find($id);
        }
        return $this->model->get();
    }
    
    /**
     * Adds a chainable where statement
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return self
     */
    public function where(...$params){
        $this->model = $this->model->where(...$params);
        return $this;
    }
    /**
     * Get paginated collection
     * @param int $perPage
     * @return Collection
     */
    public function paginate($perPage){
        return $this->model->paginate($perPage);
    }
    
    /**
     * Alias of model find
     * @param int $id
     * @return Model
     */
    public function find($id){
        return $this->model->find($id);
    }
    
    /**
     * Retrieve single trashed item or all
     * @param int $id
     * @return Model/Collection
     */
    public function getTrash($id = null){
        if(!is_null($id)){
            return $this->getTrashedItem($id);
        }
        return $this->model->onlyTrashed()->get();
    }
    
    /**
     * Return single trashed item
     * @param int $id
     * @return Model
     */
    public function getTrashedItem($id){
        return $this->model->withTrashed()->find($id);
    }
    
    /**
     * Set relationship for retrieving model and relations
     * @param array $relation
     * @return self
     */
    public function setRelation(array $relation){
        $this->model = $this->model->with($relation);
        return $this;
    }
    
    /**
     * Order the collection you pull
     * @param string $field
     * @param string $order default asc
     */
    public function orderBy($field, $order = 'asc'){
        $this->model = $this->model->orderBy(...func_get_args());
        return $this;
    }
    
    /**
     * Create new database entry including related models
     * @param array $data
     * @return Model
     */
    public function create(array $data){
        $model = $this->model->create($data);
        //check for hasMany
        if($this->validateRelationData($this->withHasMany)){
            $model->{$this->withHasMany['relation']}()->saveMany($this->withHasMany['data']);
        }
        //check for belongsToMany
        if($this->validateRelationData($this->withBelongsToMany)){
            $model->{$this->withBelongsToMany['relation']}()->sync($this->withBelongsToMany['data']);
        }
        return $model;
    }
    
    /**
     * Update Model
     * @param array $data
     * @return bool
     */
    public function update($id, array $data, $return_model = false){
        $model = $this->find($id);
        if($return_model){
            $model->update($data);
            return $model;
        }
        return $model->update($data);
    }
    
    /**
     * Delete model either soft or hard delete
     * @param int $id
     * @param bool $hardDelete
     * @return bool
     */
    public function delete($id, $hardDelete = false){
        $model = $this->model->find($id);
        if($hardDelete){
            return $model->forceDelete($id);
        }
        return $model->delete($id);
    }
    
    /**
     * Set related models that need to be created
     * for a hasMany relationship
     * @param array $data
     * @param string $relatedModel
     * @return self
     */
    public function withHasMany(array $data, $relatedModel, $relation_name){
        $this->withHasMany['relation'] = $relation_name;
        foreach($data as $k=>$v){
            $this->withHasMany['data'][] = new $relatedModel($v);
        }
        return $this;
    }
    
    /**
     * Set related models for belongsToMany relationship
     * @param array $data
     * @return self
     */
    public function withBelongsToMany(array $data, $relation){
        $this->withBelongsToMany = [
                    'data' => $data,
                    'relation' => $relation
                ];
        return $this;
    }
    
    /**
     * Handle a file upload
     * @param \Illuminate\Http\Request $request
     * @param type $fieldname
     * @param type $folder
     * @param type $storage_disk
     * @return string filename
     */
    public function handleUpload(\Illuminate\Http\Request $request, $fieldname = 'photo', $folder = 'images', $storage_disk = 'public', $randomize = true){
        if(!$request->file($fieldname)->isValid()){
            throw new \Exception(trans('crud.invalid_file_upload'));
        }
        //Get filename
        $basename = basename($request->file($fieldname)->getClientOriginalName(),'.'.$request->file($fieldname)->getClientOriginalExtension());
        if($randomize){
            $filename = uniqid().'_'.str_slug($basename).'.'.$request->file($fieldname)->getClientOriginalExtension();
        }
        else{
            $filename = str_slug($basename).'.'.$request->file($fieldname)->getClientOriginalExtension();
        }
        //Move file to location
        $request->file($fieldname)->storeAs($folder,$filename,$storage_disk);
        return $filename;
    }
    
    private function validateRelationData($related_data){
        //Check if data attribute was set
        if(!is_null($this->withHasMany)){
            if(!isset($this->withHasMany['relation']) || !isset($this->withHasMany['data']))
                throw new MissingRelationDataException('HasMany Relation');
        }
        if(!is_null($this->withBelongsToMany)){
            if(!isset($this->withBelongsToMany['relation']) || !isset($this->withBelongsToMany['data']))
                throw new MissingRelationDataException('HasMany Relation');
        }
        return true;
    }
    
}