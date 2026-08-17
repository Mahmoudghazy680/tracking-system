<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int         $id
 * @property int         $user_id
 * @property int|null    $time_interval_id
 * @property string      $email_client
 * @property string      $direction
 * @property string|null $from_address
 * @property array|null  $to_addresses
 * @property string|null $subject
 * @property string|null $body_excerpt
 * @property bool        $has_attachment
 * @property \Carbon\Carbon|null $email_datetime
 */
class MonitoredEmail extends Model
{
    use SoftDeletes;

    protected $table = 'monitored_emails';

    protected $fillable = [
        'user_id',
        'time_interval_id',
        'email_client',
        'direction',
        'from_address',
        'to_addresses',
        'subject',
        'body_excerpt',
        'has_attachment',
        'email_datetime',
    ];

    protected $casts = [
        'to_addresses'   => 'array',
        'has_attachment' => 'boolean',
        'email_datetime' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timeInterval(): BelongsTo
    {
        return $this->belongsTo(TimeInterval::class);
    }
}
