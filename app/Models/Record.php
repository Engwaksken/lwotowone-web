<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Record extends Model {
 protected $guarded=['id'];
 private array $recordColumns=[];
 // Laravel's default column cache is keyed by model class. This model spans
 // multiple tables, so guardable columns must be cached per connection/table.
 protected function isGuardableColumn($key){
  if($this->hasSetMutator($key)||$this->hasAttributeSetMutator($key)||$this->isClassCastable($key))return true;
  $connection=$this->getConnection();$cacheKey=$connection->getName().':'.$connection->getDatabaseName().':'.$this->getTable();
  $columns=$this->recordColumns[$cacheKey]??=$connection->getSchemaBuilder()->getColumnListing($this->getTable());
  return $columns===[]||in_array($key,$columns,true);
 }
 public static function for(string $table): static { $model=static::class===self::class && in_array($table,['courses','programs'],true) ? new InstructorRecord : new static; $model->setTable($table); return $model; }
}
