<?php

declare(strict_types=1);

namespace RabbitCMS\Journal\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use RabbitCMS\Journal\Entities\Journal;
use RabbitCMS\Contracts\Journal\NoJournal;
use RabbitCMS\Journal\Attributes;

final class DBEventsListener
{
    public function created(Model $model): void
    {
        $attributes = $model->getAttributes();
        unset($attributes['created_at'], $attributes['updated_at']);
        $journal = new Journal([
            'type' => __FUNCTION__,
            'current' => $attributes,
        ]);
        $journal->entity()->associate($model);
        $journal->save();
    }

    public function updated(Model $model): void
    {
        $next = array_diff(array_keys($model->getDirty()), ['updated_at', 'created_at', 'deleted_at']);

        $attribute = class_exists(\ReflectionAttribute::class) ?
            (new \ReflectionClass($model))->getAttributes(Attributes\Journal::class)[0] ?? null : null;
        if ($attribute) {
            /* @var Attributes\Journal $attr */
            $attr = $attribute->newInstance();
            if ($attr->only !== null) {
                $next = array_intersect($next, $attr->only);
            }
            $next = array_diff($next, $attr->except);
        }
        if (count($next) > 0) {
            $previous = [];
            $current = [];
            $attributes = $model->getAttributes();
            foreach ($next as $key) {
                $previous[$key] = $model->getRawOriginal($key);
                $current[$key] = $attributes[$key] ?? null;
            }

            if ($model instanceof Pivot) {
                $previous = [
                        $model->getForeignKey() => $attributes[$model->getForeignKey()] ?? null,
                        $model->getRelatedKey() => $attributes[$model->getRelatedKey()] ?? null,
                    ] + $previous;
            }

            $journal = new Journal([
                'type' => __FUNCTION__,
                'current' => $current,
                'previous' => $previous,
            ]);
            $journal->entity()->associate($model);
            $journal->save();
        }
    }

    public function deleted(Model $model): void
    {
        $journal = new Journal([
            'type' => $model->exists ? __FUNCTION__ : 'forceDeleted',
            'previous' => $model->getRawOriginal(),
        ]);
        $journal->entity()->associate($model);
        $journal->save();
    }

    public function forceDeleted(Model $model): void
    {
        $journal = new Journal([
            'type' => __FUNCTION__,
            'previous' => $model->getRawOriginal(),
        ]);
        $journal->entity()->associate($model);
        $journal->save();
    }

    public function restored(Model $model): void
    {
        $journal = new Journal([
            'type' => __FUNCTION__,
            'current' => $model->getRawOriginal(),
        ]);
        $journal->entity()->associate($model);
        $journal->save();
    }

    protected function canStore(Model $model): bool
    {
        if ($model instanceof NoJournal) {
            return false;
        }

        if (class_exists(\ReflectionAttribute::class) && count((new \ReflectionClass($model))->getAttributes(Attributes\NoJournal::class))) {
            return false;
        }

        return true;
    }

    public function subscribe(Dispatcher $events)
    {
        foreach (['created', 'updated', 'deleted', 'restored', 'forceDeleted'] as $method) {
            $events->listen('eloquent.'.$method.': *', function (string $event, array $models) use ($method) {
                count($models) && $this->canStore($models[0]) && array_map([$this, $method], $models);
            });
        }
    }
}
