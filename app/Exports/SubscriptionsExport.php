<?php

namespace App\Exports;

use App\Models\StudentSubscription;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SubscriptionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $startDate;
    protected $endDate;

    // public function __construct($startDate, $endDate)
    // {
    //     $this->startDate = $startDate;
    //     $this->endDate = $endDate;
    // }

    public function query(): Builder
    {
        return StudentSubscription::with([
            'student:id,name,phone',
            'package:id,name,price',
            'teacherSubjectGrade.subject:id,name',
            'teacherSubjectGrade.teacher:id,name',
            'teacherSubjectGrade.grade:id,name'
        ])
        ->whereBetween('created_at', [$this->startDate, $this->endDate])
        ->orderBy('created_at', 'desc');
    }

    public function map($subscription): array
    {
        return [
            $subscription->student->name ?? '-',
            $subscription->student->phone ?? '-',
            $subscription->package->name ?? '-',
            $subscription->package->price ?? 0,
            $subscription->teacherSubjectGrade->subject->name ?? '-',
            $subscription->teacherSubjectGrade->teacher->name ?? '-',
            $subscription->teacherSubjectGrade->grade->name ?? '-',
            $subscription->teacherSubjectGrade->access_code ?? '-',
            $this->getStatusLabel($subscription),
            $subscription->subscribed_at ? $subscription->subscribed_at->format('Y-m-d H:i:s') : '-',
            $subscription->expires_at ? $subscription->expires_at->format('Y-m-d H:i:s') : '-',
        ];
    }

    public function headings(): array
    {
        return [
            'اسم الطالب',
            'رقم الهاتف',
            'الباقة',
            'السعر',
            'المادة',
            'المدرس',
            'الصف',
            'كود المادة',
            'الحالة',
            'تاريخ الاشتراك',
            'تاريخ الانتهاء',
        ];
    }

    public function styles(Worksheet $sheet): array|null
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

    private function getStatusLabel($subscription): string
    {
        if ($subscription->status === 'expired') {
            return 'منتهي';
        }

        if ($subscription->expires_at && $subscription->expires_at <= now()->addDays(7)) {
            return 'ينتهي قريباً';
        }

        return 'نشط';
    }
}