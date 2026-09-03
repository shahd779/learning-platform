<?php

namespace App\Exports;

use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Collection; // ✅ أضفنا

class TeacherSubjectCodesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $teacherId;
    protected $request;

    public function __construct($teacherId, $request = null)
    {
        $this->teacherId = $teacherId;
        $this->request = $request;
    }

    /**
     * ✅ يجب أن ترجع Collection
     */
    public function collection(): Collection
    {
        $query = TeacherSubjectGrade::where('teacher_id', $this->teacherId)
            ->with(['subject', 'grade']);

        if ($this->request && $this->request->has('search') && $this->request->search) {
            $search = $this->request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('subject', function($q2) use ($search) {
                    $q2->where('name', 'LIKE', '%' . $search . '%');
                })
                ->orWhereHas('grade', function($q2) use ($search) {
                    $q2->where('name', 'LIKE', '%' . $search . '%');
                })
                ->orWhere('access_code', 'LIKE', '%' . $search . '%');
            });
        }

        return $query->get(); // ✅ ترجع Collection
    }

    public function map($item): array
    {
        $studentsCount = StudentSubscription::where('teacher_subject_grade_id', $item->id)
            ->where('status', 'active')
            ->distinct('student_id')
            ->count();

        return [
            $item->id,
            $item->subject->name,
            $item->grade->name,
            $item->access_code,
            $studentsCount,
            $item->is_active ? 'نشط' : 'غير نشط',
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'المادة',
            'الصف',
            'كود المادة',
            'عدد الطلاب',
            'الحالة',
        ];
    }
}