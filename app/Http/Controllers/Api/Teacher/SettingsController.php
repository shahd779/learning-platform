<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherSetting;
use App\Traits\VideoHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    use VideoHelperTrait;

    /**
     * جلب إعدادات المدرس
     */
    public function getSettings()
    {
        $user = auth()->user();

        $settings = TeacherSetting::firstOrCreate(
            ['teacher_id' => $user->id],
            [
                'videos_availability' => 'always',
                'videos_availability_days' => null,
                'videos_max_watch_count' => null,
                'files_downloadable_by_default' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                // ============= إعدادات الفيديوهات =============
                'videos' => [
                    'availability' => $settings->videos_availability,
                    'availability_days' => $settings->videos_availability_days,
                    'max_watch_count' => $settings->videos_max_watch_count,
                    'max_watch_count_label' => $settings->videos_max_watch_count 
                        ? $settings->videos_max_watch_count . ' مرات' 
                        : 'غير محدود',
                ],
                // ============= إعدادات الملفات =============
                'files' => [
                    'downloadable_by_default' => (bool) $settings->files_downloadable_by_default,
                ],
                
            ]
        ]);
    }

    /**
     * تحديث إعدادات المدرس
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // إعدادات الفيديوهات
            'videos_availability' => 'nullable|in:always,limited',
            'videos_availability_days' => 'nullable|integer|min:1|max:365',
            'videos_max_watch_count' => 'nullable|in:unlimited,1,2,3,5',
            
            // إعدادات الملفات
            'files_downloadable_by_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        $settings = TeacherSetting::firstOrCreate(
            ['teacher_id' => $user->id],
            [
                'videos_active_by_default' => true,
                'videos_availability' => 'always',
                'videos_availability_days' => null,
                'videos_max_watch_count' => null,
                'files_active_by_default' => true,
                'files_downloadable_by_default' => true,
            ]
        );

        $data = [];

        // =============================================
        // إعدادات الفيديوهات
        // =============================================
        if ($request->has('videos_active_by_default')) {
            $data['videos_active_by_default'] = $request->videos_active_by_default;
        }

        if ($request->has('videos_availability')) {
            $data['videos_availability'] = $request->videos_availability;
        }

        if ($request->has('videos_availability_days')) {
            $data['videos_availability_days'] = $request->videos_availability_days;
        }

        if ($request->has('videos_max_watch_count')) {
            $data['videos_max_watch_count'] = $request->videos_max_watch_count === 'unlimited' 
                ? null 
                : (int) $request->videos_max_watch_count;
        }

        // =============================================
        // إعدادات الملفات
        // =============================================
        if ($request->has('files_active_by_default')) {
            $data['files_active_by_default'] = $request->files_active_by_default;
        }

        if ($request->has('files_downloadable_by_default')) {
            $data['files_downloadable_by_default'] = $request->files_downloadable_by_default;
        }

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => ' لم تقم بتحديد أي إعدادات للتعديل.',
            ], 422);
        }

        $settings->update($data);

        return response()->json([
            'success' => true,
            'message' => ' تم تحديث الإعدادات بنجاح',
            'data' => [
                'videos' => [
                    'active_by_default' => (bool) $settings->videos_active_by_default,
                    'availability' => $settings->videos_availability,
                    'availability_days' => $settings->videos_availability_days,
                    'max_watch_count' => $settings->videos_max_watch_count,
                    'max_watch_count_label' => $settings->videos_max_watch_count 
                        ? $settings->videos_max_watch_count . ' مرات' 
                        : 'غير محدود',
                ],
                'files' => [
                    'active_by_default' => (bool) $settings->files_active_by_default,
                    'downloadable_by_default' => (bool) $settings->files_downloadable_by_default,
                ],
            ]
        ]);
    }

    /**
     * خيارات الإعدادات المتاحة (للدروب داون)
     */
    private function getOptions()
    {
        return [
            'videos' => [
                'availability' => [
                    ['value' => 'always', 'label' => 'اتاحة دائمة'],
                    ['value' => 'limited', 'label' => 'لمدة محددة'],
                ],
                'max_watch_count' => [
                    ['value' => 'unlimited', 'label' => 'غير محدود'],
                    ['value' => 1, 'label' => 'مرة واحدة'],
                    ['value' => 2, 'label' => 'مرتين'],
                    ['value' => 3, 'label' => '3 مرات'],
                    ['value' => 5, 'label' => '5 مرات'],
                ],
                'availability_days' => [
                    ['value' => 1, 'label' => 'يوم'],
                    ['value' => 2, 'label' => 'يومين'],
                    ['value' => 7, 'label' => 'اسبوع'],
                    ['value' => 30, 'label' => 'شهر'],
                    
                ],
            ],
        ];
    }

}