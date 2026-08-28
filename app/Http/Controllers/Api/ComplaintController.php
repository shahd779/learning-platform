<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Notification;
use App\Events\NewNotificationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ComplaintController extends Controller
{
    /**
     * إرسال شكوى جديدة (للمستخدم: طالب أو مدرس)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,pdf,doc,docx', // 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // =============================================
        // معالجة المرفق (اختياري)
        // =============================================
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('complaints', 'public');
        }

        // =============================================
        // إنشاء الشكوى
        // =============================================
        $complaint = Complaint::create([
            'code' => 'CM-' . now()->format('Y') . '-' . str_pad(Complaint::count() + 1, 4, '0', STR_PAD_LEFT),
            'user_id' => auth()->id(),
            'type' => 'general', // افتراضي: شكوى عامة
            'subject' => $request->subject,
            'description' => $request->description,
            'attachment' => $attachmentPath,
            'status' => 'pending',
        ]);

        // =============================================
        // إرسال إشعار للأدمنة
        // =============================================
        $this->notifyAdmins($complaint);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الشكوى بنجاح، سيتم مراجعتها في أقرب وقت',
            'data' => [
                'id' => $complaint->id,
                'code' => $complaint->code,
                'subject' => $complaint->subject,
                'description' => $complaint->description,
                'attachment' => $complaint->attachment ? asset('storage/' . $complaint->attachment) : null,
                'status' => $complaint->status,
                'status_label' => 'مغلقة',
                'created_at' => $complaint->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * جلب شكاوي المستخدم الحالي
     */
    public function myComplaints(Request $request)
    {
        $userId = auth()->id();

        $query = Complaint::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        $perPage = $request->per_page ?? 15;
        $complaints = $query->paginate($perPage);

        $formattedComplaints = $complaints->through(function ($complaint) {
            return [
                'id' => $complaint->id,
                'code' => $complaint->code,
                'type' => $complaint->type,
                'subject' => $complaint->subject,
                'description' => $complaint->description,
                'attachment' => $complaint->attachment ? asset('storage/' . $complaint->attachment) : null,
                'status' => $complaint->status,
                'status_label' => $this->getStatusLabel($complaint->status),
                'resolved_at' => $complaint->resolved_at,
                'created_at' => $complaint->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $complaint->updated_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
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
     * عرض شكوى معينة للمستخدم (تأكد من أنها ملكه)
     */
public function show($id)
{
    $complaint = Complaint::with([
        'replies' => function ($q) {
            $q->with('user:id,name,role')
              ->orderBy('created_at', 'asc'); 
        }
    ])
    ->where('user_id', auth()->id())
    ->find($id);

    if (!$complaint) {
        return response()->json([
            'success' => false,
            'message' => 'الشكوى غير موجودة',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $complaint->id,
            'code' => $complaint->code,
            'type' => $complaint->type,
            'type_label' => $this->getTypeLabel($complaint->type),
            'subject' => $complaint->subject,
            'description' => $complaint->description,
            'attachment' => $complaint->attachment ? asset('storage/' . $complaint->attachment) : null,
            'status' => $complaint->status,
            'status_label' => $this->getStatusLabel($complaint->status),
            'resolved_at' => $complaint->resolved_at,
            'replies' => $complaint->replies->map(function ($reply) {
                return [
                    'id' => $reply->id,
                    'message' => $reply->message,
                    'sender_type' => $reply->sender_type,
                    'sender_type_label' => $reply->sender_type === 'admin' ? 'أدمن' : 'أنت',
                    'user' => [
                        'id' => $reply->user->id ?? null,
                        'name' => $reply->user->name ?? null,
                        'role' => $reply->user->role ?? null,
                    ],
                    'attachment' => $reply->attachment ? asset('storage/' . $reply->attachment) : null,
                    'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            'replies_count' => $complaint->replies->count(),
            'created_at' => $complaint->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $complaint->updated_at->format('Y-m-d H:i:s'),
        ]
    ]);
}
    /**
 * رد المستخدم على شكوى (لتوضيح أو إضافة معلومات)
 */
public function userReply(Request $request, $id)
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

    // =============================================
    // التأكد من أن الشكوى ملك للمستخدم الحالي
    // =============================================
    $complaint = Complaint::where('user_id', auth()->id())->find($id);

    if (!$complaint) {
        return response()->json([
            'success' => false,
            'message' => 'الشكوى غير موجودة',
        ], 404);
    }

    // =============================================
    // منع الرد إذا كانت الشكوى محلولة أو مغلقة
    // =============================================
    if (in_array($complaint->status, ['resolved', 'closed'])) {
        return response()->json([
            'success' => false,
            'message' => 'لا يمكن الرد على هذه الشكوى لأنها ' . ($complaint->status === 'resolved' ? 'محلولة' : 'مغلقة'),
        ], 422);
    }

    // =============================================
    // معالجة المرفق
    // =============================================
    $attachmentPath = null;
    if ($request->hasFile('attachment')) {
        $attachmentPath = $request->file('attachment')->store('complaint_replies', 'public');
    }

    // =============================================
    // إنشاء الرد
    // =============================================
    $reply = \App\Models\ComplaintReply::create([
        'complaint_id' => $complaint->id,
        'user_id' => auth()->id(),
        'sender_type' => 'user',
        'message' => $request->message,
        'attachment' => $attachmentPath,
    ]);

    // =============================================
    // تحديث حالة الشكوى إلى "قيد المعالجة" (لو مش كده)
    // =============================================
    if ($complaint->status === 'pending') {
        $complaint->update(['status' => 'in_progress']);
    }

    // =============================================
    // إرسال إشعار للأدمنة
    // =============================================
    $this->notifyAdminsReply($complaint, $request->message);

    return response()->json([
        'success' => true,
        'message' => 'تم إرسال ردك بنجاح',
        'data' => [
            'id' => $reply->id,
            'message' => $reply->message,
            'attachment' => $reply->attachment ? asset('storage/' . $reply->attachment) : null,
            'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
        ]
    ]);
}

/**
 * إرسال إشعار للأدمنة عند رد المستخدم على شكوى
 */
private function notifyAdminsReply($complaint, $message)
{
    $admins = \App\Models\User::where('role', 'admin')
        ->where('is_active', true)
        ->get();

    $user = auth()->user();

    foreach ($admins as $admin) {
        $notification = Notification::create([
            'user_id' => $admin->id,
            'triggered_by_id' => auth()->id(),
            'type' => 'complaint_reply',
            'message' => " رد جديد من {$user->name} على شكوى {$complaint->code}: " . substr($message, 0, 50) . '...',
            'data' => [
                'complaint_id' => $complaint->id,
                'complaint_code' => $complaint->code,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'reply_message' => $message,
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

    // =============================================
    // دوال مساعدة
    // =============================================

    private function notifyAdmins($complaint)
    {
        $admins = \App\Models\User::where('role', 'admin')
            ->where('is_active', true)
            ->get();

        $user = auth()->user();

        foreach ($admins as $admin) {
            $notification = Notification::create([
                'user_id' => $admin->id,
                'triggered_by_id' => auth()->id(),
                'type' => 'new_complaint',
                'message' => " شكوى جديدة من {$user->name}: {$complaint->subject}",
                'data' => [
                    'complaint_id' => $complaint->id,
                    'complaint_code' => $complaint->code,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'subject' => $complaint->subject,
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

    private function getTypeLabel($type)
    {
        $labels = [
            'general' => 'شكوى عامة',
            'code' => 'مشاكل الكود',
            'payment' => 'مشاكل الدفع',
        ];

        return $labels[$type] ?? $type;
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
}