<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleCalendarChannel extends Model
{
    protected $fillable = ['comercio_id', 'channel_id', 'resource_id', 'expires_at'];
}
