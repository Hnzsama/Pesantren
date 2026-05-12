<?php

namespace App\Traits;

use App\Models\Student;

use Illuminate\Support\Facades\Schema;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasStudentSnapshot
{
    protected static function bootHasStudentSnapshot()
    {
        forward_static_call([static::class, 'creating'], function ($model) {
            if ($model->student_id && empty($model->student_name)) {
                $student = Student::find($model->student_id, ['*']);
                if ($student) {
                    $model->student_name = $student->full_name;
                    $model->student_nis = $student->nis;
                    
                    // Also handle student_class if the table has it
                    if (Schema::hasColumn($model->getTable(), 'student_class')) {
                        $model->student_class = $student->level?->name;
                    }
                }
            }
        });
    }
}
