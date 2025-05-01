<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskBackup extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'backup_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'backup_id',
        'user_id',
        'file_path',
        'created_at'
    ];

    protected $dates = ['created_at', 'deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function restoreHistory()
    {
        return $this->hasMany(RestoreHistory::class, 'backup_id', 'backup_id');
    }
} 