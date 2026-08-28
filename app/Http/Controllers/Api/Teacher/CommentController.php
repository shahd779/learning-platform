<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Events\NewNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Video;
use App\Models\TeacherSubjectGrade;
use App\Traits\VideoHelperTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    use VideoHelperTrait;

public function getAllVideos(Request $request)
{
    $teacherId = auth()->id();

    $query = Video::where('teacher_id', $teacherId)
        ->where('status', 'approved');


    if ($request->has('subject_id') && $request->subject_id) {
        $query->where('subject_id', $request->subject_id);
    }

    if ($request->has('grade_id') && $request->grade_id) {
        $query->whereHas('teacherSubjectGrade', function ($q) use ($request) {
            $q->where('grade_id', $request->grade_id);
        });
    }

    $query->orderBy('created_at', 'asc');

    $videos = $query->select('id', 'title')->get();

    return response()->json([
        'success' => true,
        'data' => $videos,
    ]);
}
    public function index(Request $request)
{
    $teacherId = auth()->id();

    // =============================================
    // 1. جلب الفيديوهات الأساسية (كل الفيديوهات)
    // =============================================
    $videoQuery = Video::where('teacher_id', $teacherId)
        ->where('status', 'approved');

    // =============================================
    // 2. تطبيق فلاتر المادة والصف على الفيديوهات
    // =============================================
    if ($request->has('subject_id') && $request->subject_id) {
        $videoQuery->where('subject_id', $request->subject_id);
    }

    if ($request->has('grade_id') && $request->grade_id) {
        $videoQuery->whereHas('teacherSubjectGrade', function ($q) use ($request) {
            $q->where('grade_id', $request->grade_id);
        });
    }

    // جلب IDs الفيديوهات بعد الفلترة
    $videoIds = $videoQuery->pluck('id');

    if ($videoIds->isEmpty()) {
        return response()->json([
            'success' => true,
            'data' => [],
            'stats' => $this->getStats($teacherId, $request),
            'filters' => $this->getFilters($teacherId, $request),
            'pagination' => [],
        ]);
    }

    // =============================================
    // 3. جلب التعليقات بناءً على الفيديوهات المفلترة
    // =============================================
    $query = Comment::with([
        'user:id,name,phone',
        'video:id,title,thumbnail,duration,subject_id,teacher_subject_grade_id',
        'video.subject:id,name',
        'video.teacherSubjectGrade.grade:id,name',
        'replies.user:id,name,role'
    ])
    ->whereIn('video_id', $videoIds)
    ->where('user_id', '!=', $teacherId); // ✅ استبعد تعليقات المدرس نفسه

    // =============================================
    // 4. فلتر الفيديو (من دروب داون الفيديوهات)
    // =============================================
    if ($request->has('video_id') && $request->video_id) {
        $query->where('video_id', $request->video_id);
    }

    // =============================================
    // 5. فلتر حالة التعليق
    // =============================================
    if ($request->has('status_filter') && $request->status_filter) {
        switch ($request->status_filter) {
            case 'new':
                $query->where('created_at', '>=', now()->subDay());
                break;
            case 'unreplied':
                $query->whereDoesntHave('replies');
                break;
            case 'replied':
                $query->whereHas('replies');
                break;
        }
    }

    // =============================================
    // 6. البحث
    // =============================================
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('content', 'LIKE', '%' . $search . '%')
              ->orWhereHas('user', function ($q2) use ($search) {
                  $q2->where('name', 'LIKE', '%' . $search . '%');
              });
        });
    }

    // =============================================
    // 7. الترتيب (الأحدث / الأقدم)
    // =============================================
    $sort = $request->sort ?? 'desc';
    $query->orderBy('created_at', $sort);

    $perPage = $request->per_page ?? 15;
    $comments = $query->paginate($perPage);

    // =============================================
    // 8. تنسيق البيانات
    // =============================================
    $formattedComments = $comments->through(function ($comment) use ($teacherId) {
        $hasTeacherReply = $comment->replies->contains(function ($reply) use ($teacherId) {
            return $reply->user_id == $teacherId;
        });

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'user' => [
                'id' => $comment->user->id ?? null,
                'name' => $comment->user->name ?? null,
                'phone' => $comment->user->phone ?? null,
            ],
            'video' => [
                'id' => $comment->video->id ?? null,
                'title' => $comment->video->title ?? null,
                'thumbnail' => $comment->video->thumbnail ? asset('storage/' . $comment->video->thumbnail) : null,
                'duration' => $comment->video->duration ?? null,
                'duration_formatted' => isset($comment->video->duration) ? $this->formatDuration($comment->video->duration) : null,
            ],
            'subject' => [
                'id' => $comment->video->subject->id ?? null,
                'name' => $comment->video->subject->name ?? null,
            ],
            'grade' => [
                'id' => $comment->video->teacherSubjectGrade->grade->id ?? null,
                'name' => $comment->video->teacherSubjectGrade->grade->name ?? null,
            ],
            'has_replies' => $comment->replies->count() > 0,
            'replies_count' => $comment->replies->count(),
            'has_teacher_reply' => $hasTeacherReply,
            'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
        ];
    });

    return response()->json([
        'success' => true,
        'stats' => $this->getStats($teacherId, $request),
        'data' => $formattedComments,
        'filters' => $this->getFilters($teacherId, $request),
        'pagination' => [
            'current_page' => $comments->currentPage(),
            'per_page' => $comments->perPage(),
            'total' => $comments->total(),
            'last_page' => $comments->lastPage(),
            'next_page_url' => $comments->nextPageUrl(),
            'prev_page_url' => $comments->previousPageUrl(),
        ]
    ]);
}

    /**
     * الإحصائيات (بناءً على الفلاتر)
     */
    private function getStats($teacherId, $request)
    {
        // جلب الفيديوهات المفلترة
        $videoQuery = Video::where('teacher_id', $teacherId)
            ->where('status', 'approved');

        if ($request->has('subject_id') && $request->subject_id) {
            $videoQuery->where('subject_id', $request->subject_id);
        }

        if ($request->has('grade_id') && $request->grade_id) {
            $videoQuery->whereHas('teacherSubjectGrade', function ($q) use ($request) {
                $q->where('grade_id', $request->grade_id);
            });
        }

        $videoIds = $videoQuery->pluck('id');

        if ($videoIds->isEmpty()) {
            return [
                'total' => 0,
                'new' => 0,
                'unreplied' => 0,
                'replied' => 0,
            ];
        }

        $total = Comment::whereIn('video_id', $videoIds)->count();
        $new = Comment::whereIn('video_id', $videoIds)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $unreplied = Comment::whereIn('video_id', $videoIds)
            ->whereDoesntHave('replies')
            ->count();
        $replied = Comment::whereIn('video_id', $videoIds)
            ->whereHas('replies')
            ->count();

        return [
            'total' => $total,
            'new' => $new,
            'unreplied' => $unreplied,
            'replied' => $replied,
        ];
    }

    /**
     * خيارات الفلاتر (مع دروب داون الفيديوهات المتغيرة)
     */
    private function getFilters($teacherId, $request)
    {
        // =============================================
        // 1. الصفوف (كل الصفوف المتاحة للمدرس)
        // =============================================
        $grades = TeacherSubjectGrade::where('teacher_id', $teacherId)
            ->with('grade')
            ->get()
            ->unique('grade_id')
            ->map(fn($item) => [
                'id' => $item->grade_id,
                'name' => $item->grade->name,
            ])
            ->values();

        // =============================================
        // 2. المواد (كل المواد المتاحة للمدرس)
        // =============================================
        $subjects = TeacherSubjectGrade::where('teacher_id', $teacherId)
            ->with('subject')
            ->get()
            ->unique('subject_id')
            ->map(fn($item) => [
                'id' => $item->subject_id,
                'name' => $item->subject->name,
            ])
            ->values();

        // =============================================
        // 3. الفيديوهات (بناءً على المادة والصف المختارين)
        // =============================================
        $videoQuery = Video::where('teacher_id', $teacherId)
            ->where('status', 'approved');

        if ($request->has('subject_id') && $request->subject_id) {
            $videoQuery->where('subject_id', $request->subject_id);
        }

        if ($request->has('grade_id') && $request->grade_id) {
            $videoQuery->whereHas('teacherSubjectGrade', function ($q) use ($request) {
                $q->where('grade_id', $request->grade_id);
            });
        }

        $videos = $videoQuery->select('id', 'title')
            ->orderBy('title')
            ->get();

        return [
            'grades' => $grades,
            'subjects' => $subjects,
            'videos' => $videos,
            'statuses' => [
                ['value' => 'new', 'label' => 'جديد (آخر 24 ساعة)'],
                ['value' => 'unreplied', 'label' => 'متردش عليها'],
                ['value' => 'replied', 'label' => 'تم الرد عليها'],
            ],
            'sort_options' => [
                ['value' => 'desc', 'label' => 'الأحدث'],
                ['value' => 'asc', 'label' => 'الأقدم'],
            ],
        ];
    }


    /**
     * جلب تعليقات فيديو معين
     */
   public function getVideoComments($videoId)
{
    $teacherId = auth()->id();

    $video = Video::where('teacher_id', $teacherId)
        ->where('status', 'approved')
        ->find($videoId);

    if (!$video) {
        return response()->json([
            'success' => false,
            'message' => 'الفيديو غير موجود',
        ], 404);
    }

    // =============================================
    // جلب التعليقات الرئيسية (parent_id = null) مع الردود
    // =============================================
    $comments = Comment::with([
        'user:id,name,phone',
        'replies.user:id,name,phone' // ✅ جلب الردود مع بيانات اللي رد
    ])
    ->where('video_id', $videoId)
    ->whereNull('parent_id') // ✅ بس التعليقات الرئيسية
    ->orderBy('created_at', 'desc')
    ->get();

    // =============================================
    // تنسيق البيانات مع الردود
    // =============================================
    $formattedComments = $comments->map(function ($comment) {
        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'user' => [
                'id' => $comment->user->id ?? null,
                'name' => $comment->user->name ?? null,
                'phone' => $comment->user->phone ?? null,
            ],
            'replies_count' => $comment->replies->count(),
            'replies' => $comment->replies->map(function ($reply) {
                return [
                    'id' => $reply->id,
                    'content' => $reply->content,
                    'user' => [
                        'id' => $reply->user->id ?? null,
                        'name' => $reply->user->name ?? null,
                        'phone' => $reply->user->phone ?? null,
                    ],
                    'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
        ];
    });

    return response()->json([
        'success' => true,
        'data' => [
            'video' => [
                'id' => $video->id,
                'title' => $video->title,
            ],
            'comments' => $formattedComments,
            'total' => $comments->count(),
        ],
    ]);
}
/**
 * جلب تعليقات الطلاب مع ردود المدرس فقط
 */
public function getCommentsWithTeacherReplies(Request $request)
{
    $teacherId = auth()->id();

    // =============================================
    // 1. جلب الفيديوهات الخاصة بالمدرس
    // =============================================
    $videoQuery = Video::where('teacher_id', $teacherId)
        ->where('status', 'approved');

    // =============================================
    // 2. تطبيق فلاتر المادة والصف
    // =============================================
    if ($request->has('subject_id') && $request->subject_id) {
        $videoQuery->where('subject_id', $request->subject_id);
    }

    if ($request->has('grade_id') && $request->grade_id) {
        $videoQuery->whereHas('teacherSubjectGrade', function ($q) use ($request) {
            $q->where('grade_id', $request->grade_id);
        });
    }

    $videoIds = $videoQuery->pluck('id');

    if ($videoIds->isEmpty()) {
        return response()->json([
            'success' => true,
            'data' => [],
            'stats' => [
                'total' => 0,
                'new' => 0,
                'unreplied' => 0,
                'replied' => 0,
            ],
            'filters' => $this->getFilters($teacherId, $request),
            'pagination' => [],
        ]);
    }

    // =============================================
    // 3. جلب التعليقات مع ردود المدرس فقط
    // =============================================
    $query = Comment::with([
        'user:id,name,phone',
        'video:id,title,thumbnail,duration,subject_id,teacher_subject_grade_id',
        'video.subject:id,name',
        'video.teacherSubjectGrade.grade:id,name',
        'replies' => function ($q) use ($teacherId) {
            // ✅ جلب ردود المدرس فقط
            $q->where('user_id', $teacherId);
        },
        'replies.user:id,name,role'
    ])
    ->whereIn('video_id', $videoIds)
    ->where('user_id', '!=', $teacherId) // ✅ استبعد تعليقات المدرس
    ->whereHas('replies', function ($q) use ($teacherId) {
        // ✅ بس التعليقات اللي عليها رد من المدرس
        $q->where('user_id', $teacherId);
    });

    // =============================================
    // 4. فلتر الفيديو
    // =============================================
    if ($request->has('video_id') && $request->video_id) {
        $query->where('video_id', $request->video_id);
    }

    // =============================================
    // 6. البحث
    // =============================================
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('content', 'LIKE', '%' . $search . '%')
              ->orWhereHas('user', function ($q2) use ($search) {
                  $q2->where('name', 'LIKE', '%' . $search . '%');
              });
        });
    }

    // =============================================
    // 7. الترتيب
    // =============================================
    $sort = $request->sort ?? 'desc';
    $query->orderBy('created_at', $sort);

    $perPage = $request->per_page ?? 15;
    $comments = $query->paginate($perPage);

    // =============================================
    // 8. تنسيق البيانات
    // =============================================
    $formattedComments = $comments->through(function ($comment) use ($teacherId) {
        // جلب ردود المدرس فقط
        $teacherReplies = $comment->replies->where('user_id', $teacherId);

        return [
            'id' => $comment->id,
            'content' => $comment->content,
            'user' => [
                'id' => $comment->user->id ?? null,
                'name' => $comment->user->name ?? null,
                'phone' => $comment->user->phone ?? null,
            ],
            'video' => [
                'id' => $comment->video->id ?? null,
                'title' => $comment->video->title ?? null,
                'thumbnail' => $comment->video->thumbnail ? asset('storage/' . $comment->video->thumbnail) : null,
                'duration' => $comment->video->duration ?? null,
                'duration_formatted' => isset($comment->video->duration) ? $this->formatDuration($comment->video->duration) : null,
            ],
            'subject' => [
                'id' => $comment->video->subject->id ?? null,
                'name' => $comment->video->subject->name ?? null,
            ],
            'grade' => [
                'id' => $comment->video->teacherSubjectGrade->grade->id ?? null,
                'name' => $comment->video->teacherSubjectGrade->grade->name ?? null,
            ],
            'teacher_reply' => $teacherReplies->first() ? [
                'id' => $teacherReplies->first()->id,
                'content' => $teacherReplies->first()->content,
                'user' => [
                    'id' => $teacherReplies->first()->user->id ?? null,
                    'name' => $teacherReplies->first()->user->name ?? null,
                ],
                'created_at' => $teacherReplies->first()->created_at->format('Y-m-d H:i:s'),
            ] : null,
            'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
        ];
    });


    return response()->json([
        'success' => true,
        'data' => $formattedComments,
        'pagination' => [
            'current_page' => $comments->currentPage(),
            'per_page' => $comments->perPage(),
            'total' => $comments->total(),
            'last_page' => $comments->lastPage(),
            'next_page_url' => $comments->nextPageUrl(),
            'prev_page_url' => $comments->previousPageUrl(),
        ]
    ]);
}
 public function replyComment(Request $request, $commentId)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $teacherId = auth()->id();

        // جلب التعليق الأصلي والتأكد من أنه على فيديو خاص بالمدرس
        $parentComment = Comment::whereHas('video', function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })->find($commentId);

        if (!$parentComment) {
            return response()->json([
                'success' => false,
                'message' => 'التعليق غير موجود أو لا يمكنك الرد عليه',
            ], 404);
        }

        // إنشاء الرد
        $reply = Comment::create([
            'content' => $request->content,
            'user_id' => $teacherId,
            'video_id' => $parentComment->video_id,
            'parent_id' => $commentId,
        ]);

        // إرسال إشعارات
        $this->notifyCommentReplied($parentComment, $reply);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الرد بنجاح',
            'data' => [
                'id' => $reply->id,
                'content' => $reply->content,
                'user' => [
                    'id' => auth()->user()->id,
                    'name' => auth()->user()->name,
                ],
                'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * تعديل رد
     */
    public function updateReply(Request $request, $replyId)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $teacherId = auth()->id();

        // التأكد من أن الرد مكتوب بواسطة المدرس نفسه
        $reply = Comment::where('id', $replyId)
            ->where('user_id', $teacherId)
            ->whereHas('video', function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->first();

        if (!$reply) {
            return response()->json([
                'success' => false,
                'message' => 'الرد غير موجود أو لا يمكنك تعديله',
            ], 404);
        }

        $reply->update([
            'content' => $request->content,
        ]);

        // إرسال إشعار بتعديل الرد
        $this->notifyReplyUpdated($reply);

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل الرد بنجاح',
            'data' => [
                'id' => $reply->id,
                'content' => $reply->content,
                'updated_at' => $reply->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }
    /**
 * جلب تعليق معين مع كل الردود عليه
 */
public function getCommentWithReplies($commentId)
{
    $teacherId = auth()->id();

    // =============================================
    // جلب التعليق مع التأكد من أنه على فيديو خاص بالمدرس
    // =============================================
    $comment = Comment::with([
        'user:id,name,phone',
        'video:id,title,thumbnail,duration,subject_id,teacher_subject_grade_id',
        'video.subject:id,name',
        'video.teacherSubjectGrade.grade:id,name',
        'replies' => function ($q) {
            $q->with('user:id,name,phone,role')
              ->orderBy('created_at', 'asc'); // ✅ الردود من الأقدم للأحدث
        }
    ])
    ->whereHas('video', function ($q) use ($teacherId) {
        $q->where('teacher_id', $teacherId)
          ->where('status', 'approved');
    })
    ->find($commentId);

    if (!$comment) {
        return response()->json([
            'success' => false,
            'message' => 'التعليق غير موجود أو لا يمكنك الوصول إليه',
        ], 404);
    }

    // =============================================
    // تنسيق البيانات
    // =============================================
    $formattedComment = [
        'id' => $comment->id,
        'content' => $comment->content,
        'user' => [
            'id' => $comment->user->id ?? null,
            'name' => $comment->user->name ?? null,
            'phone' => $comment->user->phone ?? null,
        ],
        'video' => [
            'id' => $comment->video->id ?? null,
            'title' => $comment->video->title ?? null,
            'thumbnail' => $comment->video->thumbnail ? asset('storage/' . $comment->video->thumbnail) : null,
            'duration' => $comment->video->duration ?? null,
            'duration_formatted' => isset($comment->video->duration) ? $this->formatDuration($comment->video->duration) : null,
        ],
        'subject' => [
            'id' => $comment->video->subject->id ?? null,
            'name' => $comment->video->subject->name ?? null,
        ],
        'grade' => [
            'id' => $comment->video->teacherSubjectGrade->grade->id ?? null,
            'name' => $comment->video->teacherSubjectGrade->grade->name ?? null,
        ],
        'replies_count' => $comment->replies->count(),
        'replies' => $comment->replies->map(function ($reply) {
            return [
                'id' => $reply->id,
                'content' => $reply->content,
                'user' => [
                    'id' => $reply->user->id ?? null,
                    'name' => $reply->user->name ?? null,
                    'phone' => $reply->user->phone ?? null,
                    'role' => $reply->user->role ?? null,
                    'is_teacher' => $reply->user->role === 'teacher' || $reply->user->role === 'admin',
                ],
                'created_at' => $reply->created_at->format('Y-m-d H:i:s'),
            ];
        }),
        'created_at' => $comment->created_at->format('Y-m-d H:i:s'),
    ];

    return response()->json([
        'success' => true,
        'data' => $formattedComment,
    ]);
}


    /**
     * حذف رد
     */
    public function deleteReply($replyId)
    {
        $teacherId = auth()->id();

        // التأكد من أن الرد مكتوب بواسطة المدرس نفسه
        $reply = Comment::where('id', $replyId)
            ->where('user_id', $teacherId)
            ->whereHas('video', function ($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->first();

        if (!$reply) {
            return response()->json([
                'success' => false,
                'message' => 'الرد غير موجود أو لا يمكنك حذفه',
            ], 404);
        }

        // جلب صاحب التعليق الأصلي قبل الحذف
        $parentComment = Comment::find($reply->parent_id);

        $reply->delete();

        // إرسال إشعار بحذف الرد
        // if ($parentComment) {
        //     $this->notifyReplyDeleted($parentComment);
        // }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الرد بنجاح',
        ]);
    }

    /**
     * حذف تعليق مع إشعار لصاحب التعليق
     */
    public function deleteComment($id)
    {
        $teacherId = auth()->id();

        $comment = Comment::whereHas('video', function ($q) use ($teacherId) {
            $q->where('teacher_id', $teacherId);
        })->find($id);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'message' => 'التعليق غير موجود',
            ], 404);
        }

        // حفظ صاحب التعليق قبل الحذف
        $commentOwnerId = $comment->user_id;
        $commentContent = $comment->content;

        // حذف التعليق وجميع الردود عليه
        $comment->delete();

        // إرسال إشعار لصاحب التعليق
        $this->notifyCommentDeleted($commentOwnerId, $commentContent);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف التعليق بنجاح',
        ]);
    }

    // =============================================
    // دوال الإشعارات
    // =============================================

    /**
     * إرسال إشعار عند الرد على تعليق
     */
    private function notifyCommentReplied($parentComment, $reply)
    {
        // 1. إشعار لصاحب التعليق الأصلي
        if ($parentComment->user_id != auth()->id()) {
            $this->sendNotification(
                $parentComment->user_id,
                'comment_replied',
                " قام المدرس بالرد على تعليقك: \"{$parentComment->content}\"",
                [
                    'comment_id' => $parentComment->id,
                    'reply_id' => $reply->id,
                    'reply_content' => $reply->content,
                ]
            );
        }

        // 2. إشعار لكل من رد على التعليق الأصلي (ما عدا صاحب التعليق والمدرس نفسه)
        $repliers = Comment::where('parent_id', $parentComment->id)
            ->where('user_id', '!=', $parentComment->user_id)
            ->where('user_id', '!=', auth()->id())
            ->distinct('user_id')
            ->pluck('user_id');

        foreach ($repliers as $userId) {
            $this->sendNotification(
                $userId,
                'comment_replied',
                " هناك رد جديد من المدرس على تعليق: \"{$parentComment->content}\"",
                [
                    'comment_id' => $parentComment->id,
                    'reply_id' => $reply->id,
                    'reply_content' => $reply->content,
                ]
            );
        }
    }

    /**
     * إرسال إشعار عند تعديل رد
     */
    private function notifyReplyUpdated($reply)
    {
        // جلب التعليق الأصلي
        $parentComment = Comment::find($reply->parent_id);
        if (!$parentComment) return;

        // إشعار لصاحب التعليق
        if ($parentComment->user_id != auth()->id()) {
            $this->sendNotification(
                $parentComment->user_id,
                'reply_updated',
                " تم تعديل رد المدرس على تعليقك",
                [
                    'comment_id' => $parentComment->id,
                    'reply_id' => $reply->id,
                    'new_content' => $reply->content,
                ]
            );
        }

        // إشعار لكل من رد على التعليق
        $repliers = Comment::where('parent_id', $parentComment->id)
            ->where('user_id', '!=', $parentComment->user_id)
            ->where('user_id', '!=', auth()->id())
            ->distinct('user_id')
            ->pluck('user_id');

        foreach ($repliers as $userId) {
            $this->sendNotification(
                $userId,
                'reply_updated',
                " تم تعديل رد المدرس على تعليق",
                [
                    'comment_id' => $parentComment->id,
                    'reply_id' => $reply->id,
                ]
            );
        }
    }

    /**
     * إرسال إشعار عند حذف رد
     */
    // private function notifyReplyDeleted($parentComment)
    // {
    //     // إشعار لصاحب التعليق
    //     if ($parentComment->user_id != auth()->id()) {
    //         $this->sendNotification(
    //             $parentComment->user_id,
    //             'reply_deleted',
    //             " تم حذف رد المدرس على تعليقك",
    //             [
    //                 'comment_id' => $parentComment->id,
    //             ]
    //         );
    //     }

    //     // إشعار لكل من رد على التعليق
    //     $repliers = Comment::where('parent_id', $parentComment->id)
    //         ->where('user_id', '!=', $parentComment->user_id)
    //         ->where('user_id', '!=', auth()->id())
    //         ->distinct('user_id')
    //         ->pluck('user_id');

    //     foreach ($repliers as $userId) {
    //         $this->sendNotification(
    //             $userId,
    //             'reply_deleted',
    //             " تم حذف رد المدرس على تعليق",
    //             [
    //                 'comment_id' => $parentComment->id,
    //             ]
    //         );
    //     }
    // }

    /**
     * إرسال إشعار عند حذف تعليق
     */
    private function notifyCommentDeleted($userId, $commentContent)
    {
        if ($userId != auth()->id()) {
            $this->sendNotification(
                $userId,
                'comment_deleted',
                " تم حذف تعليقك: \"{$commentContent}\" بواسطة المدرس",
                [
                    'deleted_content' => $commentContent,
                ]
            );
        }
    }

    /**
     * دالة مساعدة لإرسال الإشعارات
     */
    private function sendNotification($userId, $type, $message, $data = [])
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'triggered_by_id' => auth()->id(),
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'is_read' => false,
        ]);

        try {
            broadcast(new NewNotificationEvent($notification));
        } catch (\Exception $e) {
            // لو الـ broadcasting مش شغال
        }
    }


    /**
     * حذف جميع تعليقات فيديو معين
     */
    public function deleteVideoComments($videoId)
    {
        $teacherId = auth()->id();

        $video = Video::where('teacher_id', $teacherId)
            ->where('status', 'approved')
            ->find($videoId);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => 'الفيديو غير موجود',
            ], 404);
        }

        $count = Comment::where('video_id', $videoId)->delete();

        return response()->json([
            'success' => true,
            'message' => "تم حذف {$count} تعليق",
        ]);
    }

}