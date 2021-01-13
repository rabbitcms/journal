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

    public function created(Model $model, ?Attributes\Journal $attribute): void
    {
        (new Journal([
            'type' => __FUNCTION__,
            'current' => $this->filter($model->getAttributes(), $attribute, ['created_at', 'updated_at']),
        ]))
            ->entity()->associate($model)
            ->save();
    }

    public function updated(Model $model, ?Attributes\Journal $attribute): void
    {
        $except = [$model::CREATED_AT, $model::UPDATED_AT, 'deleted_at'];
        $dirty = $model->getDirty();

        if (count($dirty) > 0) {
            $attributes = $model->getAttributes();
            $previous = $this->filter(array_intersect_key($model->getRawOriginal(), $dirty), $attribute, $except);
            $current = $this->filter(array_intersect_key($attributes, $dirty), $attribute, $except);

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

    public function deleted(Model $model, ?Attributes\Journal $attribute): void
    {
        (new Journal([
            'type' => $model->exists ? __FUNCTION__ : 'forceDeleted',
            'previous' => $this->filter($model->getRawOriginal(), $attribute,
                [$model::CREATED_AT, $model::UPDATED_AT, 'deleted_at']),
        ]))
            ->entity()->associate($model)
            ->save();
    }

    public function forceDeleted(Model $model, ?Attributes\Journal $attribute): void
    {
        $this->deleted($model, $attribute);
    }

    public function restored(Model $model, ?Attributes\Journal $attribute): void
    {
        (new Journal([
            'type' => __FUNCTION__,
            'current' => $this->filter($model->getAttributes(), $attribute,
                [$model::CREATED_AT, $model::UPDATED_AT, 'deleted_at']),
        ]))
            ->entity()->associate($model)
            ->save();
    }

    protected function filter(array $fields, ?Attributes\Journal $attribute, array $except = [])
    {
        if ($attribute) {
            if (is_array($attribute->only)) {
                $fields = array_intersect_key($fields, array_combine($attribute->only, $attribute->only));
            }
            if ($attribute->except) {
                $fields = array_diff_key($fields, array_combine($attribute->except, $attribute->except));
            }
        }

        return $except ? array_diff_key($fields, array_combine($except, $except)) : $fields;
    }

    public function subscribe(Dispatcher $events)
    {
        $module = self::module();
        if ($module->config('enabled', true)) {
            $strict = $module->config('strict', false);
            foreach (['created', 'updated', 'deleted', 'restored', 'forceDeleted'] as $method) {
                $events->listen('eloquent.'.$method.': *',
                    function (string $event, array $models) use ($method, $strict) {
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
