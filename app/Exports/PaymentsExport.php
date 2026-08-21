<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return Payment::with(['student', 'teacherSubjectGrade.subject', 'teacherSubjectGrade.teacher', 'teacherSubjectGrade.grade'])
            ->orderBy('created_at', 'desc');
    }

    public function map($payment): array
    {
        return [
            $payment->transaction_id,
            $payment->student->name ?? '-',
            $payment->student->phone ?? '-',
            $payment->from_phone,
            $payment->teacherSubjectGrade->subject->name . ' - ' . $payment->teacherSubjectGrade->grade->name ?? '-',
            $payment->teacherSubjectGrade->teacher->name ?? '-',
            $payment->teacherSubjectGrade->access_code ?? '-',
            $this->getStatusLabel($payment->status),
            $payment->rejection_reason ?? '-',
            $payment->created_at->format('Y-m-d H:i:s'),
            $payment->reviewer->name ?? '-',
            $payment->reviewed_at ? $payment->reviewed_at->format('Y-m-d H:i:s') : '-',
        ];
    }

    public function headings(): array
    {
        return [
            'رقم العملية',
            'اسم الطالب',
            'رقم الهاتف',
            'رقم المحول منه',
            'المادة - الصف',
            'المدرس',
            'كود المادة',
            'الحالة',
            'سبب الرفض',
            'تاريخ الطلب',
            'مراجع بواسطة',
            'تاريخ المراجعة',
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

    private function getStatusLabel($status)
    {
        $labels = [
            'pending' => 'بانتظار المراجعة',
            'approved' => 'تمت الموافقة',
            'rejected' => 'تم الرفض',
        ];

        return $labels[$status] ?? $status;
    }
}