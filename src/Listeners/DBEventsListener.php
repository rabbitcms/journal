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
use RabbitCMS\Modules\Concerns\BelongsToModule;

final class DBEventsListener
{
    use BelongsToModule;

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

    public function updated(Model $model, ?Attributes\Journal $attribute): void
    {
        $next = array_diff(array_keys($model->getDirty()), ['updated_at', 'created_at', 'deleted_at']);

        if ($attribute) {
            if ($attribute->only !== null) {
                $next = array_intersect($next, $attribute->only);
            }
            $next = array_diff($next, $attribute->except);
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

    public function subscribe(Dispatcher $events)
    {
        $module = self::module();
        if ($module->config('enabled', true)) {
            $strict = $module->config('strict', false);
            foreach (['created', 'updated', 'deleted', 'restored', 'forceDeleted'] as $method) {
                $events->listen('eloquent.'.$method.': *', function (string $event, array $models) use ($method, $strict) {
                    if (! count($models) || $models[0] instanceof NoJournal) {
                        return;
                    }

                    $attr = null;
                    if (class_exists(\ReflectionAttribute::class)) {
                        $class = new \ReflectionClass($models[0]);

                        if (count($class->getAttributes(Attributes\NoJournal::class))) {
                            return;
                        }

                        $attribute = $class->getAttributes(Attributes\Journal::class)[0] ?? null;

                        if ($attribute) {
                            $attr = $attribute->newInstance();
                        } elseif ($strict) {
                            return;
                        }
                    }

                    array_map(fn($model) => $this->{$method}($model, $attr), $models);

                });
            }
        }
    }
}
