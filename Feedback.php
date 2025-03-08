<?php

namespace App\Models;

use App\Enum\ActiveStatusEnum;
use App\Models\Scopes\ActiveScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Wildside\Userstamps\Userstamps;

class Feedback extends Model
{
    use HasFactory, Userstamps, SoftDeletes, LogsActivity;

    const CREATED_BY = 'created_by';
    const UPDATED_BY = 'updated_by';
    const DELETED_BY = 'deleted_by';

    protected $casts = [
        'is_active' => ActiveStatusEnum::class,
    ];

    protected $fillable = [
        'is_active','feedback_topic', 'upload_file_type', 'prompt_id', 'board_id', 'medium_id', 'standard_id', 'dropdown_id','subject_id'
    ];

    
    protected $table ="feedbacks";  // Specify table name if not using default naming convention

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new ActiveScope);
    }


    /**
     * Save activity log
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable);
    }

    /**
     * Scope a query to only include inactive users.
     */
    public function scopeInActive(Builder $query): void
    {
        $query->where('is_active', ActiveStatusEnum::Inactive);
    }

    /**
     * Scope a query to exclude active scope.
     */
    public function scopeWithoutStatus(Builder $query): void
    {
        $query->withoutGlobalScope(ActiveScope::class);
    }

    public function prompt()
     {
        return $this->belongsTo(Prompt::class);
    }

    public function answers()
    {
        return $this->hasMany(FeedbackAnswer::class, 'feedback_id', 'id');
    }

}
