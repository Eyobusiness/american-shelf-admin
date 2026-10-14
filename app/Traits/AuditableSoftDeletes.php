<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

trait AuditableSoftDeletes
{
    use SoftDeletes;

    /**
     * Boot the auditable soft deletes trait for a model.
     * Automatically registers deleting and restoring event listeners to manage 'deleted_by'.
     */
    public static function bootAuditableSoftDeletes(): void
    {
        static::deleting(function ($model) {
            if (!method_exists($model, 'isForceDeleting') || !$model->isForceDeleting()) {
                if ($model->isFillable('deleted_by') || in_array('deleted_by', $model->getDates(), true) || property_exists($model, 'deleted_by') || isset($model->attributes['deleted_by']) || array_key_exists('deleted_by', $model->attributes)) {
                    $userId = Auth::id() ?? Auth::guard('api')->id();
                    if ($userId) {
                        $model->deleted_by = $userId;
                        // Ne déclenche pas d'événements supplémentaires pendant la suppression
                        $model->saveQuietly();
                    }
                }
            }
        });

        static::restoring(function ($model) {
            if ($model->isFillable('deleted_by') || isset($model->attributes['deleted_by']) || array_key_exists('deleted_by', $model->attributes)) {
                $model->deleted_by = null;
            }
        });
    }

    /**
     * Mark model as deleted with specific user id.
     *
     * @param int|string|null $userId
     * @return bool|null
     */
    public function deleteWithAuditor(int|string|null $userId = null): ?bool
    {
        $this->deleted_by = $userId ?? Auth::id() ?? Auth::guard('api')->id();
        $this->saveQuietly();

        return $this->delete();
    }
}
