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

class BlockedUsersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        $query = User::query();

        $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->whereIn('role', ['admin', 'teacher'])
                   ->where('is_active', false);
            })
            ->orWhere(function ($q2) {
                $q2->where('role', 'student')
                   ->where(function ($q3) {
                       $q3->where('is_active', false)
                          ->orWhereHas('studentSubscriptions', function ($q4) {
                              $q4->where('is_banned', true);
                          });
                   });
            });
        });

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
        
        // ✅ نوع الحظر
        $banType = '';
        $banDetails = [];
        
        if ($user->role === 'student') {
            $bannedSubscriptions = StudentSubscription::where('student_id', $user->id)
                ->where('is_banned', true)
                ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade'])
                ->get();
            
            $hasBanned = $bannedSubscriptions->count() > 0;
            
            if (!$user->is_active) {
                $banType = 'حساب موقوف كلياً';
            } elseif ($hasBanned) {
                $banType = 'مواد محظورة';
                $banDetails = $bannedSubscriptions->map(function($sub) {
                    return $sub->teacherSubjectGrade->subject->name . ' (' . $sub->teacherSubjectGrade->grade->name . ')';
                })->toArray();
            } else {
                $banType = 'بدون حظر';
            }
        } elseif (in_array($user->role, ['admin', 'teacher']) && !$user->is_active) {
            $banType = 'حساب موقوف';
        }

        // ✅ الحالة (زي صفحة المستخدمين)
        $status = '';
        if ($user->role === 'student') {
            $subscription = StudentSubscription::where('student_id', $user->id)
                ->where('status', 'active')
                ->where('is_banned', false)
                ->first();
            
            if ($subscription) {
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

        // ✅ المواد المحظورة (نص)
        $bannedSubjects = !empty($banDetails) ? implode(' - ', $banDetails) : '-';

        return [
            $user->id,
            $user->name,
            $user->phone,
            $roles[$user->role] ?? $user->role,
            $status, // ✅ حالة الاشتراك (للطالب) / حالة الحساب (للمدرس والأدمن)
            $banType, // ✅ نوع الحظر
            $bannedSubjects, // ✅ المواد المحظورة
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
            'نوع الحظر',
            'المواد المحظورة',
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