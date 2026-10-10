<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Record extends Model {
 protected $guarded=['id'];
 public static function for(string $table): static { $model=static::class===self::class && in_array($table,['courses','programs'],true) ? new InstructorRecord : new static; $model->setTable($table); return $model; }
}
