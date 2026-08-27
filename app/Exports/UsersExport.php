<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;

class UsersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        $query = User::query();

        $showBlocked = $this->request && $this->request->has('show_blocked') && $this->request->show_blocked === 'true';
        
        if (!$showBlocked) {
            $query->where('is_active', true);
        }

        if ($this->request && $this->request->has('role') && $this->request->role && $this->request->role !== 'all') {
            $query->where('role', $this->request->role);
        }

        if ($this->request && $this->request->has('search') && $this->request->search) {
            $search = $this->request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('phone', 'LIKE', '%' . $search . '%');
            });
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function map($user): array
    {
        $roles = ['admin' => 'مدير', 'teacher' => 'مدرس', 'student' => 'طالب'];
        
        // ✅ الحالة حسب الدور
        $status = '';
        if ($user->role === 'student') {
            // ✅ التحقق من وجود اشتراكات قبل محاولة قراءة البيانات
            $subscription = StudentSubscription::where('student_id', $user->id)
                ->where('status', 'active')
                ->where('is_banned', false)
                ->first();
            
            if ($subscription && $subscription->teacherSubjectGrade) {
                $status = 'اشتراك نشط';
            } else {
                $hasExpired = StudentSubscription::where('student_id', $user->id)
                    ->where('status', 'expired')
                    ->exists();
                $isBanned = StudentSubscription::where('student_id', $user->id)
                    ->where('is_banned', true)
                    ->exists();
                
                if ($isBanned) {
                    $status = 'محظور';
                } elseif ($hasExpired) {
                    $status = 'اشتراك منتهي';
                } else {
                    $status = 'بدون اشتراك';
                }
            }
        } elseif ($user->role === 'teacher') {
            $status = $user->is_active ? 'نشط' : 'موقوف';
        } elseif ($user->role === 'admin') {
            $status = $user->is_active ? 'نشط' : 'موقوف';
        }

        // ✅ المواد أو الاشتراكات (مع التحقق من null)
        $subjectsInfo = '';
        if ($user->role === 'teacher') {
            $subjects = TeacherSubjectGrade::where('teacher_id', $user->id)
                ->with(['subject', 'grade'])
                ->get();
            $subjectsInfo = $subjects->filter(function($item) {
                return $item->subject !== null && $item->grade !== null;
            })->map(function($item) {
                return ($item->subject->name ?? 'محذوف') . ' (' . ($item->grade->name ?? 'محذوف') . ')';
            })->implode(' - ');
        } elseif ($user->role === 'student') {
            $subscriptions = StudentSubscription::where('student_id', $user->id)
                ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade'])
                ->get();
            $subjectsInfo = $subscriptions->filter(function($item) {
                return $item->teacherSubjectGrade !== null 
                    && $item->teacherSubjectGrade->subject !== null
                    && $item->teacherSubjectGrade->grade !== null;
            })->map(function($item) {
                return ($item->teacherSubjectGrade->subject->name ?? 'محذوف') . ' (' . ($item->teacherSubjectGrade->grade->name ?? 'محذوف') . ')';
            })->implode(' - ');
        } else {
            $subjectsInfo = '-';
        }

        return [
            $user->id,
            $user->name,
            $user->phone,
            $roles[$user->role] ?? $user->role,
            $status,
            $subjectsInfo ?: '-',
            $user->created_at->format('Y/m/d'),
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'الاسم',
            'رقم الهاتف',
            'الدور',
            'الحالة',
            'المواد / الاشتراكات',
            'تاريخ التسجيل',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E9ECEF'],
                ],
            ],
        ];
    }
}