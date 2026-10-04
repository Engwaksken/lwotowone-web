<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Record extends Model {
 protected $guarded=['id'];
 public static function for(string $table): static { $model=new static; $model->setTable($table); return $model; }
}
