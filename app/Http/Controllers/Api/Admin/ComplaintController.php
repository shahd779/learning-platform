<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\User;
use App\Models\Notification;
use App\Events\NewNotificationEvent;
use App\Models\StudentSubscription;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    /**
     * عرض كل الشكاوى مع إحصائيات وفلترة
     */
    public function index(Request $request)
    {
        $query = Complaint::with([
            'user:id,name,phone,role',
            'resolvedBy:id,name',
            'replies' // ✅ جلب الردود مع الشكوى
        ]);
        

        // فلترة حسب الحالة
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // فلترة حسب التصنيف
        if ($request->has('type') && $request->type && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // فلترة حسب التاريخ
        if ($request->has('date') && $request->date) {
            $query->whereDate('created_at', Carbon::parse($request->date)->toDateString());
        }

        // البحث
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('phone', 'LIKE', '%' . $search . '%');
            })->orWhere('code', 'LIKE', '%' . $search . '%');
        }

        $query->orderBy('created_at', 'desc');
        $perPage = $request->per_page ?? 15;
        $complaints = $query->paginate($perPage);
        

        $formattedComplaints = $complaints->through(function ($complaint) {
        $grade = null;
        if ($complaint->user && $complaint->user->role === 'student') {
            $subscription = StudentSubscription::where('student_id', $complaint->user_id)
                ->where('status', 'active')
                ->with('teacherSubjectGrade.grade')
                ->first();
            
            $grade = $subscription->teacherSubjectGrade->grade->name ?? null;
        }
            return [
                'id' => $complaint->id,
                'code' => $complaint->code,
                'user' => [
                    'id' => $complaint->user->id ?? null,
                    'name' => $complaint->user->name ?? null,
                    'phone' => $complaint->user->phone ?? null,
                    'role' => $complaint->user->role ?? null,
                    'grade' => $grade
                ],
                'type' => $complaint->type,
                'type_label' => $this->getTypeLabel($complaint->type),
                'subject' => $complaint->subject,
                'status' => $complaint->status,
                'status_label' => $this->getStatusLabel($complaint->status),
                'created_at' => $complaint->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $stats = $this->getStats();
        $filters = $this->getFilterOptions();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'data' => $formattedComplaints,
            'pagination' => [
                'current_page' => $complaints->currentPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
                'last_page' => $complaints->lastPage(),
                'next_page_url' => $complaints->nextPageUrl(),
                'prev_page_url' => $complaints->previousPageUrl(),
            ]
        ]);
    }

    /**
     * عرض شكوى معينة مع كل الردود
     */
    public function show($id)
    {
        $complaint = Complaint::with([
            'user:id,name,phone,role',
            'resolvedBy:id,name',
            'replies.user:id,name,phone,role'
        ])->find($id);

        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'الشكوى غير موجودة'
            ], 404);
        }
          if ($complaint->status === 'pending') {
        $complaint->update(['status' => 'in_progress']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $complaint->id,
                'code' => $complaint->code,
                'user' => [
                    'id' => $complaint->user->id ?? null,
                    'name' => $complaint->user->name ?? null,
                    'phone' => $complaint->user->phone ?? null,
                    'role' => $complaint->user->role ?? null,
                ],
                'type' => $complaint->type,
                'type_label' => $this->getTypeLabel($complaint->type),
                'subject' => $complaint->subject,
                'description' => $complaint->description,
                'attachment' => $complaint->attachment ? asset('storage/' . $complaint->attachment) : null,
                'status' => $complaint->status,
                'status_label' => $this->getStatusLabel($complaint->status),
                'replies' => $complaint->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'user' => [
                            'id' => $reply->user->id ?? null,
                            'name' => $reply->user->name ?? null,
                            'role' => $reply->user->role ?? null,
                        ],
                        'sender_type' => $reply->sender_type,
                        'message' => $reply->message,
                        'attachment' => $reply->attachment ? asset('storage/' . $reply->attachment) : null,
                        'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'admin_response' => $complaint->admin_response,
                'resolved_by' => $complaint->resolvedBy ? [
                    'id' => $complaint->resolvedBy->id,
                    'name' => $complaint->resolvedBy->name,
                ] : null,
                'resolved_at' => $complaint->resolved_at ? $complaint->resolved_at->format('Y-m-d H:i:s') : null,
                'created_at' => $complaint->created_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * إضافة رد على الشكوى (من الأدمن)
     */
    public function addReply(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:1000',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $complaint = Complaint::find($id);

        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'الشكوى غير موجودة'
            ], 404);
        }

        // ✅ تحويل الحالة إلى in_progress
        if ($complaint->status === 'pending') {
            $complaint->update(['status' => 'in_progress']);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('complaint_replies', 'public');
        }

        // إنشاء الرد
        $reply = ComplaintReply::create([
            'complaint_id' => $complaint->id,
            'user_id' => auth()->id(),
            'sender_type' => 'admin',
            'message' => $request->message,
            'attachment' => $attachmentPath,
        ]);

        // إرسال إشعار للمستخدم
        $this->notifyUser($complaint, $request->message);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الرد بنجاح',
            'data' => $reply
        ]);
    }

    

    /**
     * تغيير حالة الشكوى
     */
    public function changeStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,in_progress,resolved',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $complaint = Complaint::find($id);

        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'الشكوى غير موجودة'
            ], 404);
        }

        $data = ['status' => $request->status];

        if ($request->status === 'resolved') {
            $data['resolved_by'] = auth()->id();
            $data['resolved_at'] = now();
        }

        $complaint->update($data);

        $this->notifyUser($complaint, null, 'status');

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير حالة الشكوى بنجاح',
            'data' => $complaint
        ]);
    }

    /**
     * تغيير تصنيف الشكوى
     */
    public function changeType(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:general,code,payment',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $complaint = Complaint::find($id);

        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'الشكوى غير موجودة'
            ], 404);
        }

        $complaint->update(['type' => $request->type]);

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير تصنيف الشكوى بنجاح',
            'data' => $complaint
        ]);
    }

    /**
     * حذف شكوى
     */
    public function destroy($id)
    {
        $complaint = Complaint::find($id);

        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'الشكوى غير موجودة'
            ], 404);
        }

        $complaint->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الشكوى بنجاح'
        ]);
    }

    /**
     * جلب خيارات الفلترة
     */
    public function filterOptions()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getFilterOptions()
        ]);
    }

    // =============================================
    // دوال مساعدة
    // =============================================

    private function getStats()
    {
        return [
            'total' => Complaint::count(),
            'pending' => Complaint::where('status', 'pending')->count(),
            'in_progress' => Complaint::where('status', 'in_progress')->count(),
            'resolved' => Complaint::where('status', 'resolved')->count(),
        ];
    }

    private function getFilterOptions()
    {
        return [
            'statuses' => [
                ['value' => 'all', 'label' => 'كل الحالات'],
                ['value' => 'pending', 'label' => 'مغلقة'],
                ['value' => 'in_progress', 'label' => 'قيد المراجعة'],
                ['value' => 'resolved', 'label' => 'تم الحل'],
            ],
            'types' => [
                ['value' => 'all', 'label' => 'كل التصنيفات'],
                ['value' => 'general', 'label' => 'شكاوى عامة'],
                ['value' => 'code', 'label' => 'مشاكل الكود'],
                ['value' => 'payment', 'label' => 'مشاكل الدفع'],
            ],
        ];
    }

    private function getStatusLabel($status)
    {
        $labels = [
            'pending' => 'مغلقة',
            'in_progress' => 'قيد المراجعة',
            'resolved' => 'تم الحل',
        ];

        return $labels[$status] ?? $status;
    }

    private function getTypeLabel($type)
    {
        $labels = [
            'general' => 'شكاوى عامة',
            'code' => 'مشاكل الكود',
            'payment' => 'مشاكل الدفع',
        ];

        return $labels[$type] ?? $type;
    }

    private function notifyUser($complaint, $message = null, $type = 'reply')
    {
        $statusLabels = [
            'pending' => 'قيد المراجعة',
            'in_progress' => 'قيد المعالجة',
            'resolved' => 'تم الحل',
        ];

        if ($type === 'reply') {
            $notifMessage = "📩 هناك رد جديد على شكواك رقم {$complaint->code}: " . substr($message, 0, 50) . '...';
        } else {
            $notifMessage = "📋 تم تحديث حالة شكواك رقم {$complaint->code} إلى: {$statusLabels[$complaint->status]}";
        }

        $notification = Notification::create([
            'user_id' => $complaint->user_id,
            'triggered_by_id' => auth()->id(),
            'type' => 'complaint_' . $type,
            'message' => $notifMessage,
            'data' => [
                'complaint_id' => $complaint->id,
                'complaint_code' => $complaint->code,
                'status' => $complaint->status,
                'status_label' => $statusLabels[$complaint->status] ?? $complaint->status,
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