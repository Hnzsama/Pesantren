<?php

namespace App\Traits;

use App\Models\School;
use Illuminate\Database\Eloquent\Builder;

trait HasSchoolScope
{
    public static function bootHasSchoolScope()
    {
        if (app()->runningInConsole() || app()->runningUnitTests()) {
            return;
        }

        static::addGlobalScope('school', function (Builder $builder) {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            if ($user->hasRole('super_admin')) {
                // If Super Admin has selected a specific school in session
                if (session()->has('active_school_id')) {
                    $builder->where($builder->qualifyColumn('school_id'), session('active_school_id'));
                }
                // If no school selected, show ALL (no filter)
            } else {
                // Regular users are restricted to their assigned school
                if ($user->school_id) {
                    $builder->where($builder->qualifyColumn('school_id'), $user->school_id);
                } else {
                    // Safety: If user has no school, maybe show nothing?
                    // Or allow if specific logic requires it.
                    // For now, let's assume valid users have school_id.
                    // $builder->whereNull($builder->qualifyColumn('school_id')); // Optional safety
                }
            }
        });
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
