<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'الباقة الأساسية',
                'price' => 20.00,
                'duration_days' => 30,
                'features' => [
                    'عدد غير محدود للطلاب',
                    'تشمل الامتحانات والملفات المرفوعة من المدرس المختار',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'الباقة الشاملة',
                'price' => 200.00,
                'duration_days' => 30,
                'features' => [
                    'عدد غير محدود للطلاب',
                    'تشمل الامتحانات والملفات المرفوعة من المدرس المختار',
                    'تشمل الفيديوهات',
                    'إمكانية طرح أسئلة في التعليمات',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($packages as $package) {
            Package::create($package);
        }

        
    }
}