<?php

declare(strict_types=1);

namespace RabbitCMS\Journal\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use RabbitCMS\Contracts\Journal\NoJournal;

/**
 * Class Journal.
 *
 * @property-read int $id
 * @property-read int $owner_id
 * @property-read Eloquent $entity
 * @property-read Carbon $created_at
 * @property-read array $previous
 * @property-read array $current
 */
class Journal extends Eloquent implements NoJournal
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'journal';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'previous' => 'array',
        'current' => 'array',
        'created_at' => 'datetime',
    ];
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'previous',
        'current',
    ];

    /**
     * {@inheritdoc}
     */
    public static function boot()
    {
        parent::boot();
        static::creating(function (Journal $model) {
            $model->setAttribute('created_at', Carbon::now());
            $user = Auth::user();
            if ($user instanceof Eloquent) {
                $model->owner()->associate($user);
            }
        });
    }

    /**
     * Get entity relation.
     *
     * @return MorphTo
     */
    public function entity(): MorphTo
    {
        return $this->morphTo('entity');
    }

    /**
     * Get owner relation.
     *
     * @return MorphTo
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }

    /**
     * Encode the given value as JSON.
     *
     * @param  mixed  $value
     *
     * @return string
     */
    protected function asJson($value)
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
