<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestoreHistory extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'restore_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'restore_id',
        'user_id',
        'backup_id',
        'restored_at'
    ];

    protected $dates = ['restored_at', 'deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function backup()
    {
        return $this->belongsTo(TaskBackup::class, 'backup_id', 'backup_id');
    }
} 