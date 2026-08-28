<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Events\NewNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Notification;
use App\Models\TeacherSubjectGrade;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\FileDownload;

class FileController extends Controller
{
    /**
     * رفع ملف جديد
     */
    public function uploadFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_subject_grade_id' => 'required|exists:teacher_subject_grade,id',
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt|max:51200', // 50MB
        ], [
            'file.required' => 'الملف مطلوب',
            'file.mimes' => 'صيغة الملف غير مدعومة. الصيغ المدعومة: pdf, doc, docx, xls, xlsx, ppt, pptx, zip, rar, txt',
            'file.max' => 'حجم الملف لا يتجاوز 50 ميجابايت',
            'title.required' => 'عنوان الملف مطلوب',
            'teacher_subject_grade_id.required' => 'يجب اختيار المادة والصف',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // التأكد من أن المدرس يملك هذه المادة
        $teacherSubjectGrade = TeacherSubjectGrade::where('id', $request->teacher_subject_grade_id)
            ->where('teacher_id', auth()->id())
            ->where('is_active', true)
            ->first();

        if (!$teacherSubjectGrade) {
            return response()->json([
                'success' => false,
                'message' => 'هذه المادة غير متاحة لك'
            ], 403);
        }

        // =============================================
        // معالجة الملف
        // =============================================
        $file = $request->file('file');

        // توليد اسم فريد للملف
        $fileName = $this->generateUniqueFileName($file);
        $path = $file->storeAs('files', $fileName, 'public');

        // استخراج حجم الملف
        $fileSize = $file->getSize(); // بالبايت
        $fileSizeKB = round($fileSize / 1024, 2); // بالكيلوبايت
        $fileSizeMB = round($fileSize / (1024 * 1024), 2); // بالميجابايت

        // استخراج نوع الملف (الامتداد)
        $fileType = $file->getClientOriginalExtension();
        $fileTypeName = $this->getFileTypeName($fileType);

        // =============================================
        // جلب الإعدادات العامة للمدرس
        // =============================================
        $settings = auth()->user()->teacherSettings;
        if (!$settings) {
            $settings = auth()->user()->createDefaultSettings();
        }
        $defaultFileSettings = $settings->getDefaultFileSettings();

        // =============================================
        // حفظ بيانات الملف في قاعدة البيانات
        // =============================================
        $fileModel = File::create([
            'teacher_subject_grade_id' => $teacherSubjectGrade->id,
            'teacher_id' => auth()->id(),
            'subject_id' => $teacherSubjectGrade->subject_id,
            'title' => $request->title,
            'description' => $request->description,
            'file_path' => 'files/' . $fileName,
            'file_type' => $fileType,
            'file_type_name' => $fileTypeName,
            'file_size' => $fileSizeKB,
            'file_size_mb' => $fileSizeMB,
            'downloads_count' => 0,
            'is_active' => $defaultFileSettings['is_active'],
            'is_downloadable' => $defaultFileSettings['is_downloadable'],
        ]);

        // إرسال إشعار للأدمنة
        $this->notifyAdminsForFile($fileModel);

        return response()->json([
            'success' => true,
            'message' => 'تم رفع الملف بنجاح',
            'data' => $this->formatFileData($fileModel),
        ], 201);
    }

    /**
     * جلب كل ملفات المدرس
     */
    public function getFiles(Request $request)
    {
        $teacherId = auth()->id();

        $query = File::where('teacher_id', $teacherId)
            ->with(['subject', 'teacherSubjectGrade.grade']);

        // فلترة حسب المادة
        if ($request->has('subject_id') && $request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        // فلترة حسب الصف
        if ($request->has('grade_id') && $request->grade_id) {
            $query->whereHas('teacherSubjectGrade', function ($q) use ($request) {
                $q->where('grade_id', $request->grade_id);
            });
        }

        // فلترة حسب الحالة
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }

        // فلترة حسب التحميل
        if ($request->has('is_downloadable') && $request->is_downloadable !== '') {
            $query->where('is_downloadable', $request->is_downloadable);
        }

        // بحث بالعنوان
        if ($request->has('search') && $request->search) {
            $query->where('title', 'LIKE', '%' . $request->search . '%');
        }

        $files = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        $formattedFiles = $files->through(function ($file) {
            return $this->formatFileData($file);
        });

        return response()->json([
            'success' => true,
            'data' => $formattedFiles,
            'pagination' => [
                'current_page' => $files->currentPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
                'last_page' => $files->lastPage(),
                'next_page_url' => $files->nextPageUrl(),
                'prev_page_url' => $files->previousPageUrl(),
            ]
        ]);
    }

    /**
     * عرض ملف معين
     */
    public function showFile($id)
    {
        $file = File::where('teacher_id', auth()->id())
            ->with(['subject', 'teacherSubjectGrade.grade'])
            ->find($id);

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'الملف غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatFileData($file),
        ]);
    }

    /**
     * تعديل اسم ووصف الملف
     */
    public function updateFile(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $file = File::where('teacher_id', auth()->id())->find($id);

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'الملف غير موجود'
            ], 404);
        }

        $file->update([
            'title' => $request->title,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف بنجاح',
            'data' => $this->formatFileData($file),
        ]);
    }
    public function deleteFile($id)
    {
        $file = File::where('teacher_id', auth()->id())->find($id);

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'الملف غير موجود'
            ], 404);
        }

        // حذف الملف من التخزين
        if ($file->file_path) {
            Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الملف بنجاح'
        ]);
    }

    /**
     * تبديل حالة التفعيل (تفعيل/تعطيل)
     */
    public function toggleActive($id)
    {
        $file = File::where('teacher_id', auth()->id())->find($id);

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'الملف غير موجود'
            ], 404);
        }

        $file->is_active = !$file->is_active;
        $file->save();

        return response()->json([
            'success' => true,
            'message' => $file->is_active ? 'تم تفعيل الملف' : 'تم تعطيل الملف',
            'data' => $this->formatFileData($file),
        ]);
    }

    /**
     * تبديل حالة التحميل (فتح/غلق التحميل)
     */
    public function toggleDownloadable($id)
    {
        $file = File::where('teacher_id', auth()->id())->find($id);

        if (!$file) {
            return response()->json([
                'success' => false,
                'message' => 'الملف غير موجود'
            ], 404);
        }

        $file->is_downloadable = !$file->is_downloadable;
        $file->save();

        return response()->json([
            'success' => true,
            'message' => $file->is_downloadable ? 'تم فتح التحميل' : 'تم غلق التحميل',
            'data' => $this->formatFileData($file),
        ]);
    }

    // =============================================
    // دوال مساعدة
    // =============================================

    /**
     * تنسيق بيانات الملف للـ Response
     */
    private function formatFileData($file)
    {
        return [
            'id' => $file->id,
            'title' => $file->title,
            'file_path' => $file->file_path ? asset('storage/' . $file->file_path) : null,
            'file_type' => $file->file_type,
            'file_type_name' => $file->file_type_name,
            'file_size' => $file->file_size . ' KB',
            'file_size_mb' => $file->file_size_mb . ' MB',
            'downloads_count' => $file->downloads_count,
            'is_active' => $file->is_active,
            'is_downloadable' => $file->is_downloadable,
            'subject' => [
                'id' => $file->subject->id ?? null,
                'name' => $file->subject->name ?? null,
            ],
            'grade' => [
                'id' => $file->teacherSubjectGrade->grade->id ?? null,
                'name' => $file->teacherSubjectGrade->grade->name ?? null,
            ],
            'created_at' => $file->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $file->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * استخراج اسم نوع الملف
     */
    private function getFileTypeName($extension)
    {
        $types = [
            'pdf' => 'PDF',
            'doc' => 'Word',
            'docx' => 'Word',
            'xls' => 'Excel',
            'xlsx' => 'Excel',
            'ppt' => 'PowerPoint',
            'pptx' => 'PowerPoint',
            'zip' => 'ZIP',
            'rar' => 'RAR',
            'txt' => 'Text',
            'jpg' => 'Image',
            'jpeg' => 'Image',
            'png' => 'Image',
            'gif' => 'Image',
        ];

        return $types[strtolower($extension)] ?? ucfirst($extension);
    }

    /**
     * توليد اسم فريد للملف
     */
    private function generateUniqueFileName($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y_m_d_H_i_s');
        $random = Str::random(8);
        $cleanName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        return "{$cleanName}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * إرسال إشعار للأدمنة عند رفع ملف جديد
     */
    private function notifyAdminsForFile($file): void
    {
        $admins = User::where('role', 'admin')
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            $notification = Notification::create([
                'user_id' => $admin->id,
                'triggered_by_id' => auth()->id(),
                'type' => 'file_uploaded',
                'message' => "📄 قام المدرس {$file->teacher->name} برفع ملف جديد: {$file->title}",
                'data' => [
                    'file_id' => $file->id,
                    'file_title' => $file->title,
                    'teacher_id' => $file->teacher_id,
                    'teacher_name' => $file->teacher->name,
                    'subject_name' => $file->subject->name,
                    'subject_id' => $file->subject_id,
                    'file_type' => $file->file_type_name,
                    'file_size' => $file->file_size . ' KB',
                    'created_at' => $file->created_at,
                ],
                'is_read' => false,
            ]);

            try {
                broadcast(new NewNotificationEvent($notification));
            } catch (\Exception $e) {
                // لو الـ broadcasting مش شغال
            }
        }
    }



  

public function download($id)
{
    $file = File::findOrFail($id);
    
    // ✅ تسجيل التحميل
    FileDownload::create([
        'file_id' => $file->id,
        'student_id' => auth()->id(),
        'downloaded_at' => now(),
        'ip_address' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
    
    // ✅ زيادة عدد التحميلات
    $file->increment('downloads_count');
    
    // ✅ تحميل الملف
    return response()->download(storage_path('app/public/' . $file->file_path));
}
}