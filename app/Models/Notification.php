<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'notification_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'task_id',
        'user_id',
        'scheduled_at',
        'sent_at',
        'status',
        'created_at',
        'deleted_at'
    ];

    protected $dates = [
        'scheduled_at',
        'sent_at',
        'created_at',
        'deleted_at'
    ];

    protected $casts = [
        'status' => 'boolean',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime'
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', true)
                    ->whereNull('sent_at')
                    ->where('scheduled_at', '<=', now());
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', true)
                    ->whereNull('sent_at')
                    ->where('scheduled_at', '>', now());
    }

    public function scopeSent($query)
    {
        return $query->whereNotNull('sent_at');
    }

    public function markAsSent()
    {
        $this->update([
            'sent_at' => now(),
            'status' => false
        ]);
    }
} 