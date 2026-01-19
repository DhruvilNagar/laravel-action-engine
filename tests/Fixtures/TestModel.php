<?php

namespace DhruvilNagar\ActionEngine\Tests\Fixtures;

use DhruvilNagar\ActionEngine\Traits\HasBulkActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestModel extends Model
{
    use HasBulkActions, SoftDeletes;

    protected $table = 'test_models';

    protected $fillable = [
        'name',
        'email',
        'status',
        'archived_at',
        'archive_reason',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];
}
