<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;
    protected $table = 'tasks';
    protected $primaryKey = 'task_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'status_code',
        'due_date',
        'reminder_offset_minutes',
        'role_id',
    ];

    protected $dates = ['deleted_at', 'due_date', 'created_at'];

    protected $casts = [
        'task_id' => 'string',
        'user_id' => 'string',
        'role_id' => 'string',
        'status' => 'boolean',
        'due_date' => 'datetime',
        'reminder_offset_minutes' => 'integer'
    ];

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'task_id', 'task_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'task_id', 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
