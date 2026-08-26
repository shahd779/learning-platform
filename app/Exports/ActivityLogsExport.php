<?php

namespace App\Exports;

use App\Models\ActivityLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ActivityLogsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $request;

    public function __construct($request = null)
    {
        $this->request = $request;
    }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        $query = ActivityLog::with(['user']);

        if ($this->request) {
            if ($this->request->has('type') && $this->request->type && $this->request->type !== 'all') {
                $query->where('type', $this->request->type);
            }

            if ($this->request->has('user_role') && $this->request->user_role && $this->request->user_role !== 'all') {
                $query->where('user_role', $this->request->user_role);
            }

            if ($this->request->has('date_from') && $this->request->date_from) {
                $query->whereDate('created_at', '>=', $this->request->date_from);
            }

            if ($this->request->has('date_to') && $this->request->date_to) {
                $query->whereDate('created_at', '<=', $this->request->date_to);
            }

            if ($this->request->has('search') && $this->request->search) {
                $search = $this->request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('activity', 'LIKE', '%' . $search . '%')
                      ->orWhere('description', 'LIKE', '%' . $search . '%')
                      ->orWhere('user_name', 'LIKE', '%' . $search . '%');
                });
            }
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function map($log): array
    {
        return [
            $log->id,
            $log->user_name ?? '-',
            $this->getRoleLabel($log->user_role),
            $log->activity ?? '-',
            $log->description ?? '-',
            $log->created_at ? $log->created_at->format('Y/m/d') : '-',
            $log->created_at ? $log->created_at->format('h:i A') : '-',
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'المستخدم',
            'الدور',
            'النشاط',
            'الوصف',
            'التاريخ',
            'الوقت',
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

    private function getRoleLabel($role)
    {
        $roles = [
            'admin' => 'مدير',
            'teacher' => 'مدرس',
            'student' => 'طالب',
        ];

        return $roles[$role] ?? $role;
    }
}