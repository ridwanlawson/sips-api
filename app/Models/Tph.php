<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Tph extends Model
{
	use HasFactory;

	protected $table = 'tph';
	protected $primaryKey = 'id';     // penting untuk Oracle
	public $incrementing = true;
	protected $keyType = 'int';

	protected $fillable = [
		'notph',
		'fieldcode',
		'ancakno',
		'fcba',
		'afdeling',
		'typetph',
		'status',
		'location',
		'ha',
		'created_by',
		'updated_by',
	];

	public function getCreatedAtAttribute($value)
	{
		return Carbon::parse($value)->timezone('Asia/Makassar')->toDateTimeString();
	}

	public function getUpdatedAtAttribute($value)
	{
		return Carbon::parse($value)->timezone('Asia/Makassar')->toDateTimeString();
	}

	/**
	 * Relasi ke model Ancak
	 * Satu TPH dapat memiliki banyak ancak
	 */
	public function ancaks()
	{
		return $this->hasMany(Ancak::class);
	}
}
