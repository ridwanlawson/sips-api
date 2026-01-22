<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class Karyawan extends Model
{
	use HasApiTokens, HasFactory;

	protected $table = 'employee';
	protected $fillable = [
		'fccode',
		'fcname',
		'sectionname',
		'gangcode',
		'fcba',
		'noancak',
		'photo',
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
}
