<?php

namespace App\Livewire\Partials;

use App\Models\School;
use Livewire\Component;

class SchoolSwitcher extends Component
{
    public $schools;

    public $activeSchoolId;

    public function mount()
    {
        $this->schools = School::all();
        $this->activeSchoolId = session('active_school_id', null);
    }

    public function setSchool($schoolId)
    {
        if ($schoolId === 'all') {
            session()->forget('active_school_id');
        } else {
            session(['active_school_id' => $schoolId]);
        }

        $this->redirect(request()->url());
    }

    public function render()
    {
        return view('livewire.partials.school-switcher');
    }
}
