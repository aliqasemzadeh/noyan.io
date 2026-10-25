<?php

namespace App\Models\Concerns;

use App\Enums\Accounting\DetailType;
use App\Models\Accounting\GlDetail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @mixin Model
 */
trait HasFloatingDetail
{
    public static function bootHasFloatingDetail(): void
    {
        static::created(function (Model $model): void {
            if (! method_exists($model, 'syncFloatingDetail')) {
                return;
            }

            /** @var Model&object{syncFloatingDetail: callable(): GlDetail} $model */
            $model->syncFloatingDetail();
        });

        static::updated(function (Model $model): void {
            if (! method_exists($model, 'syncFloatingDetail')) {
                return;
            }

            /** @var Model&object{syncFloatingDetail: callable(): GlDetail} $model */
            $model->syncFloatingDetail();
        });

        static::deleted(function (Model $model): void {
            $forceDeleting = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();

            if ($forceDeleting) {
                GlDetail::withTrashed()
                    ->where('business_id', $model->getAttribute('business_id'))
                    ->where('detailable_type', $model->getMorphClass())
                    ->where('detailable_id', $model->getKey())
                    ->forceDelete();

                return;
            }

            $model->glDetail()?->delete();
        });

        static::restored(function (Model $model): void {
            $detail = GlDetail::withTrashed()
                ->where('business_id', $model->getAttribute('business_id'))
                ->where('detailable_type', $model->getMorphClass())
                ->where('detailable_id', $model->getKey())
                ->first();

            if ($detail !== null) {
                $detail->restore();
            }

            if (method_exists($model, 'syncFloatingDetail')) {
                /** @var Model&object{syncFloatingDetail: callable(): GlDetail} $model */
                $model->syncFloatingDetail();
            }
        });
    }

    public function glDetail(): MorphOne
    {
        return $this->morphOne(GlDetail::class, 'detailable');
    }

    abstract protected function floatingDetailType(): DetailType;

    protected function floatingDetailCode(): ?string
    {
        $code = $this->getAttribute('code')
            ?? $this->getAttribute('account_number')
            ?? null;

        return filled($code) ? (string) $code : null;
    }

    protected function floatingDetailName(): string
    {
        return (string) ($this->getAttribute('name') ?? class_basename($this).' #'.$this->getKey());
    }

    protected function floatingDetailIsActive(): bool
    {
        if (! array_key_exists('is_active', $this->getAttributes()) && ! $this->offsetExists('is_active')) {
            return true;
        }

        return (bool) $this->getAttribute('is_active');
    }

    public function syncFloatingDetail(): GlDetail
    {
        $businessId = (int) $this->getAttribute('business_id');

        $detail = GlDetail::withTrashed()->firstOrNew([
            'business_id' => $businessId,
            'detailable_type' => $this->getMorphClass(),
            'detailable_id' => $this->getKey(),
        ]);

        $detail->fill([
            'type' => $this->floatingDetailType(),
            'code' => $this->floatingDetailCode(),
            'name' => $this->floatingDetailName(),
            'is_active' => $this->floatingDetailIsActive(),
        ]);

        if ($detail->trashed()) {
            $detail->restore();
        } else {
            $detail->save();
        }

        return $detail->fresh() ?? $detail;
    }
}
