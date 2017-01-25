<?php
declare(strict_types=1);
namespace RabbitCMS\Journal\Listeners;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Events\Dispatcher;
use RabbitCMS\Journal\Entities\Journal;
use RabbitCMS\Journal\NoJournal;

/**
 * Class DBEventsListener.
 */
final class DBEventsListener
{
    /**
     * @param Eloquent $model
     */
    public function created(Eloquent $model)
    {
        if ($model instanceof NoJournal) {
            return;
        }

        $attributes = $model->getAttributes();
        unset($attributes['created_at'], $attributes['updated_at']);
        $journal = new Journal(['type' => 'created', 'current' => $attributes]);
        $journal->entity()->associate($model);
        $journal->save();
    }

    /**
     * @param Eloquent $model
     */
    public function updated(Eloquent $model)
    {
        if ($model instanceof NoJournal) {
            return;
        }

        $next = $model->getDirty();
        unset($next['updated_at']);
        if (count($next) > 0) {
            $previous = [];
            foreach (array_keys($next) as $key) {
                $previous[$key] = $model->getOriginal($key);
            }

            $journal = new Journal(['type' => 'updated', 'current' => $next, 'previous' => $previous]);
            $journal->entity()->associate($model);
            $journal->save();
        }
    }

    /**
     * @param Eloquent $model
     */
    public function deleted(Eloquent $model)
    {
        if ($model instanceof NoJournal) {
            return;
        }

        $journal = new Journal(['type' => 'deleted', 'previous' => $model->getOriginal()]);
        $journal->entity()->associate($model);
        $journal->save();
    }

    /**
     * @param Eloquent $model
     */
    public function restored(Eloquent $model)
    {
        if ($model instanceof NoJournal) {
            return;
        }

        $journal = new Journal(['type' => 'restored', 'current' => $model->getOriginal()]);
        $journal->entity()->associate($model);
        $journal->save();
    }

    /**
     * @param Dispatcher $events
     */
    public function subscribe(Dispatcher $events)
    {
        foreach (['created', 'updated', 'deleted', 'restored'] as $method) {
            $events->listen('eloquent.'.$method.': *', self::class.'@'.$method);
        }
    }
}