<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskHistory extends Model
{
    use HasFactory, SoftDeletes;

    public $timestamps = false;
    protected $keyType = 'uuid';
    public $incrementing = false;
    protected $primaryKey = 'history_id';

    protected $fillable = [
        'history_id',
        'task_id',
        'title',
        'description',
        'status',
        'due_date',
        'reminder_offset_minutes',
    ];

    protected $casts = [
        'status' => 'boolean',
        'due_date' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }
}
